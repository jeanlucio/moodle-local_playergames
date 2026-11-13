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
 * Tests for the PlayerFill gameplay manager.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\games;

use PHPUnit\Framework\Attributes\CoversClass;
use stdClass;

/**
 * Unit tests for {@see fill_manager}.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(fill_manager::class)]
final class fill_manager_test extends \advanced_testcase {
    /**
     * Creates a concept-type cartridge.
     *
     * @return int Cartridge id.
     */
    private function make_cartridge(): int {
        global $DB;
        $now = time();
        return (int) $DB->insert_record('local_playergames_cartridges', (object) [
            'name' => 'Concepts', 'version' => '1.0', 'language' => 'en', 'type' => 'concept',
            'timecreated' => $now, 'timemodified' => $now, 'uploadedby' => 0, 'active' => 1,
        ]);
    }

    /**
     * Adds one concept to a cartridge.
     *
     * @param int $cartridgeid Owning cartridge.
     * @param string $term Term text.
     * @param string $definition Definition text.
     * @return int Concept id.
     */
    private function add_concept(int $cartridgeid, string $term, string $definition = 'A definition.'): int {
        global $DB;
        return (int) $DB->insert_record('local_playergames_concepts', (object) [
            'cartridgeid' => $cartridgeid, 'term' => $term, 'definition' => $definition,
            'difficulty' => 1,
        ]);
    }

    /**
     * Builds a concept stdClass the way get_daily_concepts() would return it, without
     * touching the database — enough for build_puzzle(), which only reads its fields.
     *
     * @param int $id Concept id.
     * @param string $term Term text.
     * @param string $definition Definition text.
     * @return stdClass
     */
    private function concept(int $id, string $term, string $definition = 'A definition.'): stdClass {
        return (object) ['id' => $id, 'term' => $term, 'definition' => $definition];
    }

    public function test_num_words_default_configured_and_clamped(): void {
        $this->resetAfterTest();
        unset_config('fill_num_words', 'local_playergames');
        $this->assertSame(fill_manager::DEFAULT_NUM_WORDS, fill_manager::num_words());

        set_config('fill_num_words', '6', 'local_playergames');
        $this->assertSame(6, fill_manager::num_words());

        set_config('fill_num_words', '2', 'local_playergames');
        $this->assertSame(fill_manager::MIN_NUM_WORDS, fill_manager::num_words());

        set_config('fill_num_words', '20', 'local_playergames');
        $this->assertSame(fill_manager::MAX_NUM_WORDS, fill_manager::num_words());
    }

    public function test_max_attempts_default_and_configured(): void {
        $this->resetAfterTest();
        unset_config('fill_max_attempts', 'local_playergames');
        $this->assertSame(fill_manager::DEFAULT_MAX_ATTEMPTS, fill_manager::max_attempts());

        set_config('fill_max_attempts', '3', 'local_playergames');
        $this->assertSame(3, fill_manager::max_attempts());

        set_config('fill_max_attempts', '0', 'local_playergames');
        $this->assertSame(fill_manager::DEFAULT_MAX_ATTEMPTS, fill_manager::max_attempts());
    }

    public function test_build_puzzle_assigns_shared_slot_to_repeated_letters(): void {
        $concepts = [
            $this->concept(1, 'gato'),
            $this->concept(2, 'toca'),
        ];

        $puzzle = fill_manager::build_puzzle($concepts);
        $words = $puzzle['words'];

        $this->assertSame('gato', $words[0]['word']);
        $this->assertSame('toca', $words[1]['word']);

        // Terms "gato" and "toca" share t, o, a — every shared letter must resolve to
        // the same slot number regardless of its position in either word.
        $slotsbyletter0 = array_combine(str_split($words[0]['word']), $words[0]['slots']);
        $slotsbyletter1 = array_combine(str_split($words[1]['word']), $words[1]['slots']);
        foreach (['t', 'o', 'a'] as $letter) {
            $this->assertSame($slotsbyletter0[$letter], $slotsbyletter1[$letter]);
        }
        // Letters "g" (gato only) and "c" (toca only) never appear in the other word,
        // so they must never collide with a shared letter's slot number.
        $this->assertNotContains($slotsbyletter0['g'], $words[1]['slots']);
        $this->assertNotContains($slotsbyletter1['c'], $words[0]['slots']);
    }

    public function test_build_puzzle_keeps_disjoint_words_on_separate_slots(): void {
        $concepts = [
            $this->concept(1, 'sol'),
            $this->concept(2, 'lua'),
        ];

        $puzzle = fill_manager::build_puzzle($concepts);
        $words = $puzzle['words'];

        // Terms "sol" and "lua" share the letter "l" — every other letter is exclusive
        // to one word, so the slot map must have exactly 5 distinct numbers (s,o,l,u,a).
        $this->assertSame(5, $puzzle['slotcount']);
        $shared = array_intersect($words[0]['slots'], $words[1]['slots']);
        $this->assertCount(1, array_unique($shared));
    }

    public function test_build_tiles_reveals_only_letters_in_revealed_slots(): void {
        $tiles = fill_manager::build_tiles('cat', [1, 2, 3], [2]);

        $this->assertFalse($tiles[0]['revealed']);
        $this->assertSame('1', $tiles[0]['slotnum']);
        $this->assertSame('', $tiles[0]['letter']);

        $this->assertTrue($tiles[1]['revealed']);
        $this->assertSame('A', $tiles[1]['letter']);
        $this->assertSame('', $tiles[1]['slotnum']);
    }

    public function test_apply_cascade_resolves_word_fully_covered_by_revealed_slots(): void {
        // Mirrors the real caller (play_fill.php): the directly-guessed word (index 0)
        // is already marked resolved, and its slots already merged into revealedslots,
        // before apply_cascade() is asked to resolve any side effect on the others.
        $words = [
            ['conceptid' => 1, 'word' => 'cat', 'slots' => [1, 2, 3], 'resolved' => true, 'exhausted' => false],
            ['conceptid' => 2, 'word' => 'at', 'slots' => [2, 3], 'resolved' => false, 'exhausted' => false],
        ];

        // Solving word 1 reveals slots 1, 2 and 3 — every slot word 2 needs — so word
        // 2 must be auto-resolved even though it was never guessed directly.
        $updated = fill_manager::apply_cascade($words, [1, 2, 3]);

        $this->assertTrue($updated[1]['resolved']);
    }

    public function test_apply_cascade_leaves_partially_revealed_words_pending(): void {
        $words = [
            ['conceptid' => 1, 'word' => 'cat', 'slots' => [1, 2, 3], 'resolved' => false, 'exhausted' => false],
        ];

        $updated = fill_manager::apply_cascade($words, [1, 2]);

        $this->assertFalse($updated[0]['resolved']);
    }

    public function test_get_daily_concepts_returns_only_todays_fill_assignments_in_order(): void {
        global $DB;
        $this->resetAfterTest();

        $cartridgeid = $this->make_cartridge();
        $conceptid1 = $this->add_concept($cartridgeid, 'gato');
        $conceptid2 = $this->add_concept($cartridgeid, 'toca');
        $otherconceptid = $this->add_concept($cartridgeid, 'sol');
        $gamedate = mktime(0, 0, 0, 1, 1, 2026);

        $DB->insert_record('local_playergames_daily_assignments', (object) [
            'gamedate' => $gamedate, 'gametype' => 'fill', 'conceptid' => $conceptid1,
        ]);
        $DB->insert_record('local_playergames_daily_assignments', (object) [
            'gamedate' => $gamedate, 'gametype' => 'fill', 'conceptid' => $conceptid2,
        ]);
        // A same-day 'guess' assignment must never leak into fill's own result.
        $DB->insert_record('local_playergames_daily_assignments', (object) [
            'gamedate' => $gamedate, 'gametype' => 'guess', 'conceptid' => $otherconceptid,
        ]);

        $concepts = fill_manager::get_daily_concepts($gamedate);

        $this->assertCount(2, $concepts);
        $this->assertSame('gato', $concepts[0]->term);
        $this->assertSame('toca', $concepts[1]->term);
    }

    public function test_build_words_payload_reveals_answer_only_when_resolved_or_revealing(): void {
        $concepts = [$this->concept(1, 'cat'), $this->concept(2, 'at')];
        $puzzle = fill_manager::build_puzzle($concepts);
        $words = [];
        foreach ($puzzle['words'] as $word) {
            $words[] = $word + ['resolved' => false, 'attemptsused' => 0, 'exhausted' => false];
        }
        $words[0]['resolved'] = true;
        $state = ['words' => $words, 'revealedslots' => $words[0]['slots']];

        $payload = fill_manager::build_words_payload($state, false);

        $this->assertSame(1, $payload[0]['conceptid']);
        $this->assertTrue($payload[0]['resolved']);
        $this->assertSame('cat', $payload[0]['revealword']);
        // Word 2 is still pending and revealanswers is false, so its spelling must
        // stay hidden — this is what stops a loss reveal leaking early.
        $this->assertSame('', $payload[1]['revealword']);
        $this->assertFalse($payload[1]['resolved']);

        // Once revealing (the round just ended in a loss), even a pending word's
        // spelling must be included so the player can see the answer.
        $payloadrevealed = fill_manager::build_words_payload($state, true);
        $this->assertSame('at', $payloadrevealed[1]['revealword']);
    }

    public function test_get_daily_concepts_returns_empty_when_unassigned(): void {
        $this->resetAfterTest();
        $this->assertSame([], fill_manager::get_daily_concepts(time()));
    }

    /**
     * Opens an active season with PlayerFill on and today's two words assigned.
     *
     * @return array [season record, concept id of "gato", concept id of "rede"]
     */
    private function open_round(): array {
        global $DB;
        $this->redirectEvents();

        $now = time();
        $seasonid = (int) $DB->insert_record('local_playergames_seasons', (object) [
            'name' => 'Season', 'startdate' => $now - DAYSECS, 'enddate' => $now + (DAYSECS * 30),
            'status' => 'active', 'config_snapshot' => json_encode(['xp_per_game_fill' => 10, 'xp_games_fill' => 1]),
            'timecreated' => $now, 'timemodified' => $now,
        ]);
        $DB->insert_record('local_playergames_season_games', (object) [
            'seasonid' => $seasonid, 'gametype' => 'fill', 'enabled' => 1, 'source' => 'cartridge', 'auxid' => 0,
        ]);
        $cartridgeid = $this->make_cartridge();
        $ids = [$this->add_concept($cartridgeid, 'gato'), $this->add_concept($cartridgeid, 'rede')];
        foreach ($ids as $conceptid) {
            $DB->insert_record('local_playergames_daily_assignments', (object) [
                'gamedate' => mktime(0, 0, 0), 'gametype' => 'fill', 'conceptid' => $conceptid,
            ]);
        }
        set_config('fill_max_attempts', 2, 'local_playergames');

        return [$DB->get_record('local_playergames_seasons', ['id' => $seasonid], '*', MUST_EXIST), $ids[0], $ids[1]];
    }

    /**
     * Regression test: the round used to live in the session, so a new login gave the lost round again,
     * with every word the loss had just revealed.
     */
    public function test_a_lost_round_stays_lost_after_a_new_session(): void {
        global $DB, $SESSION;
        $this->resetAfterTest();
        [$season, $gato, $rede] = $this->open_round();
        $user = $this->getDataGenerator()->create_user();
        $today = mktime(0, 0, 0);
        $context = \context_system::instance();

        $first = fill_manager::submit_word_guess((int) $user->id, $gato, 'zzzz', $today, $season, $context);
        $this->assertFalse($first['finished']);
        $second = fill_manager::submit_word_guess((int) $user->id, $gato, 'yyyy', $today, $season, $context);
        $this->assertTrue($second['finished']);
        $this->assertFalse($second['won']);
        $this->assertSame('gato', $second['words'][0]['revealword']);

        $SESSION = new \stdClass();
        $replay = fill_manager::submit_word_guess((int) $user->id, $gato, 'gato', $today, $season, $context);

        $this->assertSame(['success' => false, 'reason' => 'finished'], $replay);
        $this->assertFalse($DB->record_exists('local_playergames_daily_scores', ['userid' => $user->id]));
    }

    public function test_the_attempts_used_on_a_word_survive_a_new_session(): void {
        global $SESSION;
        $this->resetAfterTest();
        [$season, $gato] = $this->open_round();
        $user = $this->getDataGenerator()->create_user();
        $today = mktime(0, 0, 0);
        $context = \context_system::instance();

        $first = fill_manager::submit_word_guess((int) $user->id, $gato, 'zzzz', $today, $season, $context);
        $SESSION = new \stdClass();
        $second = fill_manager::submit_word_guess((int) $user->id, $gato, 'yyyy', $today, $season, $context);

        $this->assertSame(1, $first['words'][0]['attemptsused']);
        $this->assertSame(2, $second['words'][0]['attemptsused']);
        $this->assertTrue($second['words'][0]['exhausted']);
    }

    public function test_resolving_every_word_pays_the_xp_once(): void {
        global $DB;
        $this->resetAfterTest();
        [$season, $gato, $rede] = $this->open_round();
        $user = $this->getDataGenerator()->create_user();
        $today = mktime(0, 0, 0);
        $context = \context_system::instance();

        $one = fill_manager::submit_word_guess((int) $user->id, $gato, 'gato', $today, $season, $context);
        $two = fill_manager::submit_word_guess((int) $user->id, $rede, 'rede', $today, $season, $context);
        $again = fill_manager::submit_word_guess((int) $user->id, $rede, 'rede', $today, $season, $context);

        $this->assertTrue($one['resolved']);
        $this->assertFalse($one['finished']);
        $this->assertTrue($two['won']);
        $this->assertSame(10, $two['xpawarded']);
        $this->assertSame('finished', $again['reason']);
        $this->assertSame(1, (int) $DB->get_field('local_playergames_daily_scores', 'attempts', [
            'userid' => $user->id, 'gamedate' => $today, 'gametype' => 'fill',
        ]));
    }

    public function test_a_word_of_another_day_is_refused(): void {
        $this->resetAfterTest();
        [$season] = $this->open_round();
        $user = $this->getDataGenerator()->create_user();

        $answer = fill_manager::submit_word_guess(
            (int) $user->id,
            999999,
            'gato',
            mktime(0, 0, 0),
            $season,
            \context_system::instance()
        );

        $this->assertSame(['success' => false, 'reason' => 'invalid'], $answer);
    }

    public function test_submit_is_unavailable_without_words_for_the_day(): void {
        $this->resetAfterTest();
        [$season, $gato] = $this->open_round();
        $user = $this->getDataGenerator()->create_user();

        $answer = fill_manager::submit_word_guess(
            (int) $user->id,
            $gato,
            'gato',
            mktime(0, 0, 0) - DAYSECS,
            $season,
            \context_system::instance()
        );

        $this->assertSame(['success' => false, 'reason' => 'unavailable'], $answer);
    }
}
