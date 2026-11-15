<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Lifecycle tests: what is left consistent after something changes under the games.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames;

use local_playergames\cartridge\controller;
use local_playergames\games\guess_manager;
use local_playergames\games\guess_state;
use local_playergames\games\season_game_config;
use local_playergames\hub\level_manager;
use local_playergames\hub\season_manager;
use local_playergames\hub\xp_manager;

/**
 * Drives the events that change data other parts of the plugin keep a reference to (a cartridge, a
 * concept, a season's games, the level ladder) and checks that nothing is left pointing at the old state.
 *
 * The references are not database keys, so the database cannot catch a dangling one: each scenario ends
 * by asking {@see self::assert_consistent()}, which lists what must always hold.
 *
 * @package    local_playergames
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class lifecycle_test extends \advanced_testcase {
    /**
     * Creates a concept cartridge with the given terms.
     *
     * @param string $name The cartridge name.
     * @param string[] $terms The terms of its concepts.
     * @return int[] The ids: the cartridge first, then its concepts keyed by term.
     */
    private function cartridge_with(string $name, array $terms): array {
        global $DB;
        $now = time();
        $cartridgeid = (int) $DB->insert_record('local_playergames_cartridges', (object) [
            'name' => $name, 'version' => '1.0', 'language' => 'en', 'type' => 'concept',
            'timecreated' => $now, 'timemodified' => $now, 'uploadedby' => 0, 'active' => 1,
        ]);
        $ids = ['cartridge' => $cartridgeid];
        foreach ($terms as $term) {
            $ids[$term] = (int) $DB->insert_record('local_playergames_concepts', (object) [
                'cartridgeid' => $cartridgeid, 'term' => $term, 'definition' => 'A word', 'difficulty' => 3,
            ]);
        }
        return $ids;
    }

    /**
     * Assigns a concept as today's word of a game.
     *
     * @param string $gametype The game.
     * @param int $conceptid The concept.
     * @return void
     */
    private function assign_today(string $gametype, int $conceptid): void {
        global $DB;
        $DB->insert_record('local_playergames_daily_assignments', (object) [
            'gamedate' => mktime(0, 0, 0), 'gametype' => $gametype, 'conceptid' => $conceptid,
        ]);
    }

    /**
     * Opens a season that covers today.
     *
     * @return \stdClass The season.
     */
    private function open_season(): \stdClass {
        $season = season_manager::create('Season 1', time() - DAYSECS, time() + (30 * DAYSECS));
        season_manager::activate((int) $season->id);
        return $season;
    }

    /**
     * What must hold after any change: no reference points at something that is gone or switched off.
     *
     * @return void
     */
    private function assert_consistent(): void {
        global $DB;

        $today = mktime(0, 0, 0);
        $assigned = $DB->get_records_sql(
            "SELECT a.id, a.gametype, a.conceptid, c.id AS foundconcept, k.active
               FROM {local_playergames_daily_assignments} a
          LEFT JOIN {local_playergames_concepts} c ON c.id = a.conceptid
          LEFT JOIN {local_playergames_cartridges} k ON k.id = c.cartridgeid
              WHERE a.gamedate >= :today",
            ['today' => $today]
        );
        foreach ($assigned as $row) {
            $this->assertNotNull($row->foundconcept, "An assignment of {$row->gametype} names a concept that is gone.");
            $this->assertSame(1, (int) $row->active, "An assignment of {$row->gametype} names a cartridge that is off.");
        }

        $kept = $DB->get_fieldset_select('local_playergames_season_games', 'cartridgeids', 'cartridgeids IS NOT NULL');
        foreach ($kept as $json) {
            foreach (json_decode($json, true) as $cartridgeid) {
                $this->assertTrue(
                    $DB->record_exists('local_playergames_cartridges', ['id' => $cartridgeid]),
                    "A season game keeps cartridge {$cartridgeid}, which is gone."
                );
            }
        }

        foreach (['concepts', 'concept_questions'] as $table) {
            $orphans = $DB->count_records_sql(
                "SELECT COUNT(1)
                   FROM {local_playergames_{$table}} t
              LEFT JOIN {local_playergames_categories} g ON g.id = t.categoryid
                  WHERE t.categoryid IS NOT NULL AND (g.id IS NULL OR g.cartridgeid <> t.cartridgeid)"
            );
            $this->assertSame(0, $orphans, "{$table} point at a category that is gone or belongs to another cartridge.");
        }

        $profiles = $DB->get_records('local_playergames_player_profile');
        foreach ($profiles as $profile) {
            $this->assertSame(
                xp_manager::get_level((int) $profile->xp),
                (int) $profile->level,
                "The stored level of profile {$profile->id} does not match its XP on the current ladder."
            );
        }
    }

    /**
     * Editing the ladder moves the levels already stored for the players.
     *
     * The ranking prints the level stored in the profile, which is only rewritten when the player earns XP.
     */
    public function test_editing_the_ladder_updates_the_levels_in_the_ranking(): void {
        global $DB;
        $this->resetAfterTest();
        $season = $this->open_season();
        $now = time();
        $users = [];
        foreach ([0, 1500, 2500, 7000] as $xp) {
            $users[$xp] = $this->getDataGenerator()->create_user();
            $profile = xp_manager::get_or_create_profile((int) $users[$xp]->id, (int) $season->id);
            $DB->update_record('local_playergames_player_profile', (object) [
                'id' => $profile->id, 'xp' => $xp, 'level' => xp_manager::get_level($xp), 'timemodified' => $now,
            ]);
        }
        $this->assert_consistent();

        level_manager::save_ladder([
            ['minxp' => 0, 'title' => 'A'],
            ['minxp' => 1000, 'title' => 'B'],
            ['minxp' => 2000, 'title' => 'C'],
        ]);

        $this->assert_consistent();
        $this->assertSame(2, (int) $DB->get_field('local_playergames_player_profile', 'level', ['userid' => $users[1500]->id]));
        $this->assertSame(3, (int) $DB->get_field('local_playergames_player_profile', 'level', ['userid' => $users[7000]->id]));
    }

    /**
     * Narrowing the cartridges of a game draws today's word again when it came from one that was left out.
     */
    public function test_narrowing_the_cartridges_of_a_game_redraws_the_word_of_the_day(): void {
        global $DB;
        $this->resetAfterTest();
        $season = $this->open_season();
        $left = $this->cartridge_with('Left out', ['apple']);
        $kept = $this->cartridge_with('Kept', ['grape']);
        $this->assign_today('guess', $left['apple']);

        $DB->set_field(
            'local_playergames_season_games',
            'cartridgeids',
            json_encode([$kept['cartridge']]),
            ['seasonid' => $season->id, 'gametype' => 'guess']
        );
        season_game_config::release_todays_words_outside((int) $season->id);

        $this->assert_consistent();
    }

    /**
     * Posts the edit of a concept as an administrator.
     *
     * @param array $words The cartridge and concept ids.
     * @param string $term The term the form carries.
     * @param string $definition The definition the form carries.
     * @return void
     */
    private function edit_concept(array $words, string $term, string $definition): void {
        $this->setAdminUser();
        $_POST = [
            'action' => 'edit_concept', 'cartridgeid' => $words['cartridge'], 'concept_id' => $words['apple'],
            'term' => $term, 'definition' => $definition,
        ];
        try {
            (new controller(\context_system::instance(), 'library', 0, 0))->handle_post();
        } catch (\moodle_exception $e) {
            $this->assertSame('redirecterrordetected', $e->errorcode);
        } finally {
            $_POST = [];
        }
    }

    /**
     * Keeps a round of PlayerGuess for a player, guessed against the word as it is.
     *
     * @param \stdClass $player The player.
     * @param int $conceptid Today's concept.
     * @param string $term Its term.
     * @return void
     */
    private function start_round(\stdClass $player, int $conceptid, string $term): void {
        guess_state::save((int) $player->id, [
            'gamedate' => mktime(0, 0, 0), 'conceptid' => $conceptid, 'term' => $term,
            'rows' => [['guess' => 'plane', 'feedback' => ['absent', 'absent', 'present', 'absent', 'correct']]],
            'finished' => false, 'won' => false,
        ]);
    }

    /**
     * Changing the term of today's word starts the rounds over, so they do not mix two words, even when the
     * same concept is drawn again.
     */
    public function test_editing_the_term_of_todays_word_starts_the_round_over(): void {
        $this->resetAfterTest();
        set_config('wordle_minlen', 4, 'local_playergames');
        set_config('wordle_maxlen', 12, 'local_playergames');
        $this->open_season();
        $words = $this->cartridge_with('Words', ['apple', 'grapefruit']);
        $this->assign_today('guess', $words['apple']);
        $player = $this->getDataGenerator()->create_user();
        $this->start_round($player, $words['apple'], 'apple');

        $this->edit_concept($words, 'pineapple', 'A word');

        $assigned = guess_manager::get_daily_concept(mktime(0, 0, 0));
        $state = guess_state::load((int) $player->id, mktime(0, 0, 0), (int) $assigned->id, (string) $assigned->term);
        $this->assertSame([], $state['rows'], 'The rows guessed against the old word are still in the round.');
        $this->assert_consistent();
    }

    /**
     * Editing something else about today's word leaves the rounds in progress alone.
     */
    public function test_editing_the_definition_of_todays_word_keeps_the_round(): void {
        $this->resetAfterTest();
        $this->open_season();
        $words = $this->cartridge_with('Words', ['apple', 'grape']);
        $this->assign_today('guess', $words['apple']);
        $player = $this->getDataGenerator()->create_user();
        $this->start_round($player, $words['apple'], 'apple');

        $this->edit_concept($words, 'apple', 'A better clue');

        $state = guess_state::load((int) $player->id, mktime(0, 0, 0), $words['apple'], 'apple');
        $this->assertCount(1, $state['rows']);
        $this->assert_consistent();
    }

    /**
     * Whether the hub offers a game of the day as playable.
     *
     * @param string $gametype The game.
     * @return bool
     */
    private function hub_offers(string $gametype): bool {
        $season = season_manager::get_active();
        foreach (hub\daily_play_manager::get_games_today(0, season_manager::get_config_snapshot($season)) as $game) {
            if ($game['gametype'] === $gametype) {
                return $game['pending'];
            }
        }
        return false;
    }

    /**
     * The hub does not offer PlayerGuess or PlayerFill on a day they have no word to play.
     *
     * Regression test: the check was "some concept cartridge is active", so a season that keeps only a
     * cartridge that is off, or a pool with nothing eligible, still showed a game that answers "unavailable".
     */
    public function test_the_hub_offers_the_word_games_only_when_they_have_words_today(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('wordle_minlen', 4, 'local_playergames');
        set_config('wordle_maxlen', 12, 'local_playergames');
        $season = $this->open_season();
        $off = $this->cartridge_with('Off', ['apple']);
        $on = $this->cartridge_with('On', ['grape', 'lemon', 'mango', 'peach', 'melon', 'berry', 'olive']);
        $DB->set_field('local_playergames_cartridges', 'active', 0, ['id' => $off['cartridge']]);
        $DB->set_field(
            'local_playergames_season_games',
            'cartridgeids',
            json_encode([$off['cartridge']]),
            ['seasonid' => $season->id, 'gametype' => 'guess']
        );

        \local_playergames\task\assign_daily_games::draw_now();

        $this->assertFalse($this->hub_offers('guess'), 'PlayerGuess is offered with no word.');
        $this->assertTrue($this->hub_offers('fill'));

        $DB->set_field('local_playergames_season_games', 'cartridgeids', null, ['seasonid' => $season->id, 'gametype' => 'guess']);
        \local_playergames\task\assign_daily_games::draw_now();

        $this->assertTrue($this->hub_offers('guess'));
        $this->assert_consistent();
    }

    /**
     * Closes the active season and opens another, the way the nightly task does.
     *
     * @param \stdClass $closing The season to close.
     * @return \stdClass The season that follows.
     */
    private function roll_over(\stdClass $closing): \stdClass {
        season_manager::close((int) $closing->id);
        $next = season_manager::create('Season 2', time() - 1, time() + (30 * DAYSECS));
        season_manager::activate((int) $next->id);
        return $next;
    }

    /**
     * A quiz play started in a season does not pay, or count, in the one that replaced it.
     *
     * The play keeps the season it started in; an answer sent after the season rolled over is refused instead of
     * crediting XP to a season the player never played.
     */
    public function test_a_quiz_play_does_not_cross_into_the_next_season(): void {
        global $DB;
        $this->resetAfterTest();
        $this->redirectEvents();
        set_config('quiz_question_seconds', 0, 'local_playergames');
        $season = $this->open_season();
        $player = $this->getDataGenerator()->create_user();
        $this->setUser($player);
        $questions = [];
        for ($i = 1; $i <= 3; $i++) {
            $question = new games\quiz_question();
            $question->source = 'cartridge';
            $question->sourceid = 100 + $i;
            $question->questiontext = "Question {$i}?";
            for ($j = 0; $j < 5; $j++) {
                $answer = new games\quiz_answer();
                $answer->text = "Q{$i} option {$j}";
                $answer->correct = $j === 0;
                $question->answers[] = $answer;
            }
            $questions[] = $question;
        }
        $context = \context_system::instance();
        games\quiz_play::start(mktime(0, 0, 0), (int) $season->id, $questions, $context);

        $next = $this->roll_over($season);
        $verdict = games\quiz_play::answer((int) $player->id, 0, 0, $next, $context);

        $this->assertFalse($verdict['success']);
        $this->assertSame('noplay', $verdict['reason']);
        $this->assertSame(0, $DB->count_records('local_playergames_daily_scores', ['userid' => $player->id]));
        $this->assertSame(0, $DB->count_records('local_playergames_player_profile', ['seasonid' => $next->id]));
    }

    /**
     * What belongs to the player and not to a season survives the rollover; what belongs to a season starts over.
     */
    public function test_a_rollover_resets_the_season_and_keeps_the_player(): void {
        global $DB;
        $this->resetAfterTest();
        $this->redirectEvents();
        $season = $this->open_season();
        $player = $this->getDataGenerator()->create_user();
        $now = time();
        $profile = xp_manager::get_or_create_profile((int) $player->id, (int) $season->id);
        $DB->update_record('local_playergames_player_profile', (object) [
            'id' => $profile->id, 'xp' => 2500, 'level' => 2, 'showinranking' => 1, 'timemodified' => $now,
        ]);
        hub\avatar_manager::record_level((int) $player->id, 3);
        hub\streak_manager::record_activity((int) $player->id);

        $next = $this->roll_over($season);

        $fresh = xp_manager::get_or_create_profile((int) $player->id, (int) $next->id);
        $this->assertSame(0, (int) $fresh->xp);
        $this->assertSame(0, (int) $fresh->showinranking, 'The new season starts hidden, as every profile does.');
        $this->assertSame(3, (int) hub\avatar_manager::get_state((int) $player->id)->bestlevel);
        $this->assertSame(1, (int) hub\streak_manager::get_or_create((int) $player->id)->currentstreak);
        $this->assertSame(2500, (int) $DB->get_field('local_playergames_player_profile', 'xp', ['id' => $profile->id]));
        $this->assert_consistent();
    }

    /**
     * With no season open, nothing that records game data writes: not the check-in on every page, not an answer.
     */
    public function test_nothing_is_recorded_while_no_season_is_open(): void {
        global $DB;
        $this->resetAfterTest();
        $this->redirectEvents();
        $season = $this->open_season();
        $player = $this->getDataGenerator()->create_user();
        $this->setUser($player);
        season_manager::close((int) $season->id);

        hub\checkin_manager::record((int) $player->id);
        $day = mktime(0, 0, 0);

        $this->assertNull(season_manager::get_active());
        $this->assertSame(0, $DB->count_records('local_playergames_daily_scores'));
        $this->assertSame(0, $DB->count_records('local_playergames_activity_log'));
        $this->assertSame(['success' => false, 'reason' => 'unavailable'], games\guess_manager::submit_guess(
            (int) $player->id,
            'planet',
            $day,
            $season,
            \context_system::instance()
        ));
    }

    /**
     * Fills one row of a table of the plugin that is keyed by a user, with whatever its columns need.
     *
     * @param string $table Table name without the prefix.
     * @param int $userid The user.
     * @return void
     */
    private function insert_row_for(string $table, int $userid): void {
        global $DB;

        $row = [];
        foreach ($DB->get_columns($table) as $name => $column) {
            if ($name === 'id') {
                continue;
            }
            if ($name === 'userid') {
                $row[$name] = $userid;
            } else if (in_array($column->meta_type, ['I', 'N', 'F'], true)) {
                $row[$name] = 1;
            } else {
                $row[$name] = 'x';
            }
        }
        $DB->insert_record($table, (object) $row, false);
    }

    /**
     * Deleting a user leaves nothing of theirs in any table of the plugin, including tables added later.
     *
     * The tables are found by their userid column, not from a list the privacy provider also keeps, so a new
     * table nobody declared there is caught here instead of keeping the rows of deleted accounts.
     */
    public function test_deleting_a_user_leaves_no_row_in_any_table_with_a_userid(): void {
        global $DB;
        // The events are left to run: it is the plugin's observer of the deletion that is being tested.
        $this->resetAfterTest();
        $gone = $this->getDataGenerator()->create_user();
        $stays = $this->getDataGenerator()->create_user();

        $tables = [];
        foreach ($DB->get_tables(false) as $table) {
            if (str_starts_with($table, 'local_playergames_') && isset($DB->get_columns($table)['userid'])) {
                $tables[] = $table;
                $this->insert_row_for($table, (int) $gone->id);
                $this->insert_row_for($table, (int) $stays->id);
            }
        }
        $this->assertGreaterThan(10, count($tables), 'The scan found too few tables to be trusted.');

        delete_user($gone);

        foreach ($tables as $table) {
            $this->assertSame(0, $DB->count_records($table, ['userid' => $gone->id]), "{$table} keeps rows of a deleted user.");
            $this->assertSame(1, $DB->count_records($table, ['userid' => $stays->id]), "{$table} lost rows of another user.");
        }
    }
}
