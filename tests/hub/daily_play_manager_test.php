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
 * Tests for the daily-play manager.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\hub;

/**
 * Unit tests for {@see daily_play_manager}.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_playergames\hub\daily_play_manager
 * @covers \local_playergames\hub\mission_manager
 * @covers \local_playergames\observer
 */
final class daily_play_manager_test extends \advanced_testcase {
    /**
     * Creates an active season with the given config snapshot.
     *
     * @param array $snapshot Config snapshot values.
     * @return \stdClass Season record.
     */
    private function make_season(array $snapshot): \stdClass {
        global $DB;
        $now = time();
        $id  = (int) $DB->insert_record('local_playergames_seasons', (object) [
            'name' => 'Season',
            'startdate' => $now - DAYSECS,
            'enddate' => $now + (DAYSECS * 30),
            'status' => 'active',
            'config_snapshot' => json_encode($snapshot),
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        return $DB->get_record('local_playergames_seasons', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Adds a cartridge of a family.
     *
     * @param string $type 'quiz' or 'concept'.
     * @param int $active Whether it is active.
     * @return void
     */
    private function add_cartridge(string $type, int $active = 1): void {
        global $DB;
        $now = time();
        $DB->insert_record('local_playergames_cartridges', (object) [
            'name' => 'C', 'version' => '1.0', 'language' => 'en', 'type' => $type,
            'timecreated' => $now, 'timemodified' => $now, 'uploadedby' => 0, 'active' => $active,
        ]);
    }

    /**
     * Sets how the active season feeds a game.
     *
     * @param \stdClass $season The season.
     * @param string $gametype Game type.
     * @param string $source Source constant value.
     * @param int $auxid Question bank category id.
     * @return void
     */
    private function set_source(\stdClass $season, string $gametype, string $source, int $auxid = 0): void {
        global $DB;
        $DB->insert_record('local_playergames_season_games', (object) [
            'seasonid' => $season->id, 'gametype' => $gametype, 'enabled' => 1,
            'source' => $source, 'cartridgeids' => null, 'auxid' => $auxid,
        ]);
    }

    /**
     * A shared question bank category, on every Moodle version.
     *
     * @return int Category id.
     */
    private function shared_category(): int {
        global $DB;
        $generator = $this->getDataGenerator();
        if ($DB->get_manager()->table_exists('qbank')) {
            $course = $generator->create_course();
            $bank = $generator->create_module('qbank', ['course' => $course->id]);
            $context = \context_module::instance($bank->cmid);
        } else {
            $context = \context_system::instance();
        }
        $category = $generator->get_plugin_generator('core_question')
            ->create_question_category(['contextid' => $context->id]);
        return (int) $category->id;
    }

    /**
     * Regression test: one global "is there an active cartridge" decided every game, so a site that
     * reads only the question bank saw Quiz and Battle locked in the hub while the pages worked.
     */
    public function test_a_bank_only_game_is_open_without_any_cartridge(): void {
        $this->resetAfterTest();
        $user     = $this->getDataGenerator()->create_user();
        $snapshot = [];
        $season   = $this->make_season($snapshot);
        $category = $this->shared_category();
        $this->set_source($season, 'quiz', 'questionbank', $category);
        $this->set_source($season, 'battle', 'questionbank', $category);

        $games = array_column(daily_play_manager::get_games_today((int) $user->id, $snapshot), null, 'gametype');

        $this->assertTrue($games['quiz']['pending']);
        $this->assertTrue($games['battle']['pending']);
        $this->assertTrue($games['guess']['locked']);
        $this->assertTrue($games['fill']['locked']);
    }

    public function test_a_bank_source_without_a_shared_category_stays_locked(): void {
        $this->resetAfterTest();
        $user     = $this->getDataGenerator()->create_user();
        $season   = $this->make_season([]);
        $this->set_source($season, 'quiz', 'questionbank', 0);

        $games = array_column(daily_play_manager::get_games_today((int) $user->id, []), null, 'gametype');

        $this->assertTrue($games['quiz']['locked']);
    }

    /**
     * Each family of cartridges opens its own games: a quiz cartridge does not feed Guess and Fill.
     */
    public function test_cartridges_open_only_the_games_that_read_their_family(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->add_cartridge('quiz');

        $games = array_column(daily_play_manager::get_games_today((int) $user->id, []), null, 'gametype');
        $this->assertTrue($games['quiz']['pending']);
        $this->assertTrue($games['battle']['pending']);
        $this->assertTrue($games['guess']['locked']);
        $this->assertTrue($games['fill']['locked']);

        $this->add_cartridge('concept');
        $games = array_column(daily_play_manager::get_games_today((int) $user->id, []), null, 'gametype');
        $this->assertTrue($games['guess']['pending']);
        $this->assertTrue($games['fill']['pending']);
    }

    public function test_an_inactive_cartridge_opens_nothing(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->add_cartridge('quiz', 0);
        $this->add_cartridge('concept', 0);

        $games = array_column(daily_play_manager::get_games_today((int) $user->id, []), null, 'gametype');

        foreach (['quiz', 'guess', 'fill', 'battle'] as $type) {
            $this->assertTrue($games[$type]['locked'], $type);
        }
    }

    public function test_multiple_plays_split_xp_evenly(): void {
        global $DB;
        $this->resetAfterTest();
        $this->redirectEvents();

        $user    = $this->getDataGenerator()->create_user();
        $context = \context_system::instance();
        $today   = mktime(0, 0, 0);
        $season  = $this->make_season([
            'xp_per_game_quiz' => 8,
            'xp_games_quiz'    => 3,
            'xp_cap_quiz'      => 24,
        ]);

        $first  = daily_play_manager::register_play((int) $user->id, 'quiz', $today, $season, $context);
        $second = daily_play_manager::register_play((int) $user->id, 'quiz', $today, $season, $context);
        $third  = daily_play_manager::register_play((int) $user->id, 'quiz', $today, $season, $context);

        $this->assertSame(8, $first['xpawarded']);
        $this->assertSame(2, $first['remaining']);
        $this->assertSame(8, $third['xpawarded']);
        $this->assertSame(0, $third['remaining']);

        // A single accumulating row holds the whole day's XP and play count.
        $row = $DB->get_record('local_playergames_daily_scores', [
            'userid' => $user->id, 'gamedate' => $today, 'gametype' => 'quiz',
        ]);
        $this->assertSame(24, (int) $row->xpawarded);
        $this->assertSame(3, (int) $row->attempts);
    }

    /**
     * The extra payload reaches the event, but never replaces the keys the manager owns.
     */
    public function test_extra_payload_is_merged_without_overriding_core_keys(): void {
        $this->resetAfterTest();
        $sink = $this->redirectEvents();

        $user   = $this->getDataGenerator()->create_user();
        $today  = mktime(0, 0, 0);
        $season = $this->make_season(['xp_per_game_battle' => 10, 'xp_games_battle' => 1]);

        daily_play_manager::register_play(
            (int) $user->id,
            'battle',
            $today,
            $season,
            \context_system::instance(),
            ['victory' => 1, 'gametype' => 'quiz']
        );

        $events = array_values(array_filter(
            $sink->get_events(),
            static fn($event) => $event instanceof \local_playergames\event\game_completed
        ));
        $this->assertCount(1, $events);
        $this->assertSame(1, $events[0]->other['victory']);
        $this->assertSame('battle', $events[0]->other['gametype']);
    }

    /**
     * Regression test: the battle mission completes only through the real play path.
     *
     * The observer completes battle_win from other['victory'], so register_play() has to
     * carry it. Without the extra payload the event arrived without it and the mission
     * could never complete.
     */
    public function test_victory_completes_the_battle_mission_only_when_flagged(): void {
        global $DB;
        $this->resetAfterTest();

        $user   = $this->getDataGenerator()->create_user();
        $today  = mktime(0, 0, 0);
        $season = $this->make_season(['xp_per_game_battle' => 10, 'xp_games_battle' => 2]);
        $context = \context_system::instance();

        daily_play_manager::register_play((int) $user->id, 'battle', $today, $season, $context);
        $mission = $DB->get_record('local_playergames_missions', ['type' => 'battle_win']);
        $this->assertFalse($mission && $DB->record_exists('local_playergames_mission_progress', [
            'userid' => $user->id, 'missionid' => $mission->id, 'completed' => 1,
        ]));

        daily_play_manager::register_play((int) $user->id, 'battle', $today, $season, $context, ['victory' => 1]);
        $mission = $DB->get_record('local_playergames_missions', ['type' => 'battle_win'], '*', MUST_EXIST);
        $this->assertTrue($DB->record_exists('local_playergames_mission_progress', [
            'userid' => $user->id, 'missionid' => $mission->id, 'seasonid' => $season->id, 'completed' => 1,
        ]));
    }

    /**
     * Every game that can be played today links to its page, and one already played does not.
     */
    public function test_pending_games_link_to_their_pages_and_played_ones_do_not(): void {
        global $DB;
        $this->resetAfterTest();
        $this->redirectEvents();
        $this->add_cartridge('quiz');
        $this->add_cartridge('concept');
        $user     = $this->getDataGenerator()->create_user();
        $snapshot = ['xp_per_game_battle' => 10, 'xp_games_battle' => 1];
        $season   = $this->make_season($snapshot);

        $games = array_column(daily_play_manager::get_games_today((int) $user->id, $snapshot), null, 'gametype');
        foreach (['quiz', 'guess', 'fill', 'battle'] as $type) {
            $this->assertTrue($games[$type]['pending'], $type);
            $this->assertStringContainsString("/local/playergames/play_{$type}.php", $games[$type]['url'], $type);
        }

        daily_play_manager::register_play(
            (int) $user->id,
            'battle',
            mktime(0, 0, 0),
            $season,
            \context_system::instance()
        );
        $games = array_column(daily_play_manager::get_games_today((int) $user->id, $snapshot), null, 'gametype');
        $this->assertTrue($games['battle']['done']);
        $this->assertSame('', $games['battle']['url']);
    }

    public function test_play_beyond_daily_quota_is_rejected(): void {
        $this->resetAfterTest();
        $this->redirectEvents();

        $user    = $this->getDataGenerator()->create_user();
        $context = \context_system::instance();
        $today   = mktime(0, 0, 0);
        $season  = $this->make_season([
            'xp_per_game_quiz' => 10,
            'xp_games_quiz'    => 1,
            'xp_cap_quiz'      => 10,
        ]);

        $first  = daily_play_manager::register_play((int) $user->id, 'quiz', $today, $season, $context);
        $second = daily_play_manager::register_play((int) $user->id, 'quiz', $today, $season, $context);

        $this->assertTrue($first['success']);
        $this->assertSame(10, $first['xpawarded']);
        $this->assertFalse($second['success']);
        $this->assertSame(0, $second['xpawarded']);
    }

    public function test_last_play_is_trimmed_to_exact_cap(): void {
        $this->resetAfterTest();
        $this->redirectEvents();

        $user    = $this->getDataGenerator()->create_user();
        $context = \context_system::instance();
        $today   = mktime(0, 0, 0);
        // Per game rounded up (9) so the cap (25) trims the final play to 7.
        $season  = $this->make_season([
            'xp_per_game_quiz' => 9,
            'xp_games_quiz'    => 3,
            'xp_cap_quiz'      => 25,
        ]);

        $awarded = 0;
        for ($i = 0; $i < 3; $i++) {
            $awarded += daily_play_manager::register_play((int) $user->id, 'quiz', $today, $season, $context)['xpawarded'];
        }

        $this->assertSame(25, $awarded);
    }

    /**
     * A game the admin switched off for the season pays nothing and is not offered in the hub.
     */
    public function test_a_game_switched_off_for_the_season_earns_nothing_and_is_hidden(): void {
        global $DB;
        $this->resetAfterTest();
        $this->redirectEvents();

        $user     = $this->getDataGenerator()->create_user();
        $snapshot = ['xp_per_game_quiz' => 10, 'xp_games_quiz' => 1];
        $season   = $this->make_season($snapshot);
        $DB->insert_record('local_playergames_season_games', (object) [
            'seasonid' => $season->id, 'gametype' => 'quiz', 'enabled' => 0,
            'source' => 'cartridge', 'cartridgeids' => null, 'auxid' => 0,
        ]);

        $result = daily_play_manager::register_play(
            (int) $user->id,
            'quiz',
            mktime(0, 0, 0),
            $season,
            \context_system::instance()
        );

        $this->assertFalse($result['success']);
        $this->assertFalse($DB->record_exists('local_playergames_daily_scores', ['userid' => $user->id]));
        $types = array_column(daily_play_manager::get_games_today((int) $user->id, $snapshot), 'gametype');
        $this->assertNotContains('quiz', $types);
        $this->assertContains('guess', $types);
    }

    /**
     * A user the participant rules exclude earns nothing, whichever page the play came from.
     */
    public function test_a_user_outside_the_participants_earns_nothing(): void {
        global $DB;
        $this->resetAfterTest();
        $this->redirectEvents();

        $user   = $this->getDataGenerator()->create_user();
        $season = $this->make_season(['xp_per_game_quiz' => 10, 'xp_games_quiz' => 1]);
        set_user_preference('local_playergames_gamification', 0, $user);

        $result = daily_play_manager::register_play(
            (int) $user->id,
            'quiz',
            mktime(0, 0, 0),
            $season,
            \context_system::instance()
        );

        $this->assertFalse($result['success']);
        $this->assertSame(0, $DB->count_records('local_playergames_daily_scores', ['userid' => $user->id]));
        $this->assertSame(0, $DB->count_records('local_playergames_player_profile', ['userid' => $user->id]));
    }

    /**
     * Regression test: the setting offered two games a day for Guess and Fill, but their single round
     * could only be played once, so the hub kept showing the game as pending for the rest of the day.
     */
    public function test_single_round_games_allow_one_play_a_day_whatever_the_snapshot_says(): void {
        $snapshot = ['xp_games_guess' => 3, 'xp_games_fill' => 2, 'xp_games_quiz' => 3, 'xp_games_battle' => 2];

        $this->assertSame(1, daily_play_manager::games_per_day($snapshot, 'guess'));
        $this->assertSame(1, daily_play_manager::games_per_day($snapshot, 'fill'));
        $this->assertSame(3, daily_play_manager::games_per_day($snapshot, 'quiz'));
        $this->assertSame(2, daily_play_manager::games_per_day($snapshot, 'battle'));
    }

    public function test_a_single_round_game_is_done_after_its_one_play(): void {
        $this->resetAfterTest();
        $this->redirectEvents();

        $user    = $this->getDataGenerator()->create_user();
        $today   = mktime(0, 0, 0);
        $season  = $this->make_season(['xp_per_game_guess' => 10, 'xp_games_guess' => 2, 'xp_cap_guess' => 20]);
        $context = \context_system::instance();

        $first  = daily_play_manager::register_play((int) $user->id, 'guess', $today, $season, $context);
        $second = daily_play_manager::register_play((int) $user->id, 'guess', $today, $season, $context);

        $this->assertSame(10, $first['xpawarded']);
        $this->assertSame(0, $first['remaining']);
        $this->assertFalse($second['success']);
        $this->assertSame(0, daily_play_manager::remaining_plays(
            (int) $user->id,
            'guess',
            $today,
            ['xp_games_guess' => 2]
        ));
    }

    public function test_the_season_snapshot_stores_one_game_a_day_for_the_single_round_games(): void {
        $this->resetAfterTest();
        set_config('xp_games_guess', 3, 'local_playergames');
        set_config('xp_games_fill', 3, 'local_playergames');
        set_config('xp_games_quiz', 3, 'local_playergames');
        set_config('xp_per_game_guess', 10, 'local_playergames');

        $snapshot = season_manager::build_snapshot();

        $this->assertSame(1, $snapshot['xp_games_guess']);
        $this->assertSame(1, $snapshot['xp_games_fill']);
        $this->assertSame(10, $snapshot['xp_cap_guess']);
        $this->assertSame(3, $snapshot['xp_games_quiz']);
    }
}
