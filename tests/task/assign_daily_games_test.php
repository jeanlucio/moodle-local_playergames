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
 * Tests for the assign_daily_games scheduled task.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\task;

use local_playergames\games\fill_manager;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Unit tests for {@see assign_daily_games}.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(assign_daily_games::class)]
final class assign_daily_games_test extends \advanced_testcase {
    /**
     * Creates an active concept cartridge with wordle-eligible terms — enough of them
     * (fill_manager::DEFAULT_NUM_WORDS) that PlayerFill's own multi-concept assignment
     * has a large enough pool to draw from too.
     *
     * @return int Cartridge id.
     */
    private function seed_concepts(): int {
        global $DB;
        $now = time();
        $cid = (int) $DB->insert_record('local_playergames_cartridges', (object) [
            'name' => 'C', 'version' => '1.0', 'language' => 'en', 'type' => 'concept',
            'timecreated' => $now, 'timemodified' => $now, 'uploadedby' => 0, 'active' => 1,
        ]);
        $terms = [
            'apple' => 'A fruit', 'table' => 'Furniture', 'planet' => 'A world',
            'river' => 'A body of water', 'stone' => 'A piece of rock',
        ];
        foreach ($terms as $term => $def) {
            $DB->insert_record('local_playergames_concepts', (object) [
                'cartridgeid' => $cid, 'term' => $term, 'definition' => $def,
                'difficulty' => 3, 'categoryid' => null, 'language' => null,
            ]);
        }
        return $cid;
    }

    /**
     * Runs the task, swallowing its mtrace output.
     *
     * @return void
     */
    private function run_task(): void {
        ob_start();
        (new assign_daily_games())->execute();
        ob_get_clean();
    }

    public function test_get_name_returns_a_string(): void {
        $this->assertNotEmpty((new assign_daily_games())->get_name());
    }

    public function test_active_cartridge_with_no_concepts_skips_single_concept_games(): void {
        global $DB;
        $this->resetAfterTest();
        // An active cartridge that has not been populated with any concept yet is a
        // real scenario (a teacher just created it) — quiz and guess must both be
        // skipped gracefully instead of erroring, and fill must stay unassigned too.
        $now = time();
        $DB->insert_record('local_playergames_cartridges', (object) [
            'name' => 'Empty', 'version' => '1.0', 'language' => 'en', 'type' => 'concept',
            'timecreated' => $now, 'timemodified' => $now, 'uploadedby' => 0, 'active' => 1,
        ]);

        $this->run_task();

        $this->assertSame(0, $DB->count_records('local_playergames_daily_assignments'));
    }

    public function test_assigns_one_concept_per_single_concept_game(): void {
        global $DB;
        $this->resetAfterTest();
        $this->seed_concepts();

        $this->run_task();

        $today = mktime(0, 0, 0);
        foreach (['quiz', 'guess'] as $gametype) {
            $this->assertSame(1, $DB->count_records('local_playergames_daily_assignments', [
                'gamedate' => $today,
                'gametype' => $gametype,
            ]), "expected exactly one assignment for {$gametype}");
        }
    }

    public function test_assigns_num_words_concepts_to_fill(): void {
        global $DB;
        $this->resetAfterTest();
        $this->seed_concepts();

        $this->run_task();

        $today = mktime(0, 0, 0);
        $fillcount = $DB->count_records('local_playergames_daily_assignments', [
            'gamedate' => $today,
            'gametype' => 'fill',
        ]);
        $this->assertSame(fill_manager::num_words(), $fillcount);

        // Every assigned concept must be distinct — the puzzle would be degenerate
        // (a word "sharing letters with itself") if the same concept were drawn twice.
        $conceptids = $DB->get_fieldset_select(
            'local_playergames_daily_assignments',
            'conceptid',
            'gamedate = :gd AND gametype = :gt',
            ['gd' => $today, 'gt' => 'fill']
        );
        $this->assertCount(count(array_unique($conceptids)), $conceptids);
    }

    public function test_fill_is_skipped_when_pool_smaller_than_num_words(): void {
        global $DB;
        $this->resetAfterTest();
        // Only 2 eligible concepts, fewer than fill_manager::DEFAULT_NUM_WORDS (5) —
        // quiz and guess only ever need one concept each, so they must still be
        // assigned even though fill's own, larger pool requirement is not met.
        $now = time();
        $cid = (int) $DB->insert_record('local_playergames_cartridges', (object) [
            'name' => 'C', 'version' => '1.0', 'language' => 'en', 'type' => 'concept',
            'timecreated' => $now, 'timemodified' => $now, 'uploadedby' => 0, 'active' => 1,
        ]);
        foreach (['apple' => 'A fruit', 'table' => 'Furniture'] as $term => $def) {
            $DB->insert_record('local_playergames_concepts', (object) [
                'cartridgeid' => $cid, 'term' => $term, 'definition' => $def,
                'difficulty' => 3, 'categoryid' => null, 'language' => null,
            ]);
        }

        $this->run_task();

        $today = mktime(0, 0, 0);
        $this->assertSame(0, $DB->count_records('local_playergames_daily_assignments', [
            'gamedate' => $today, 'gametype' => 'fill',
        ]));
        $this->assertSame(1, $DB->count_records('local_playergames_daily_assignments', [
            'gamedate' => $today, 'gametype' => 'quiz',
        ]));
    }

    public function test_is_idempotent(): void {
        global $DB;
        $this->resetAfterTest();
        $this->seed_concepts();

        $this->run_task();
        $firstcount = $DB->count_records('local_playergames_daily_assignments');

        $this->run_task();

        // A second run must not create duplicate assignments.
        $this->assertSame($firstcount, $DB->count_records('local_playergames_daily_assignments'));
    }

    public function test_no_active_cartridges_assigns_nothing(): void {
        global $DB;
        $this->resetAfterTest();

        $this->run_task();

        $this->assertSame(0, $DB->count_records('local_playergames_daily_assignments'));
    }

    public function test_guess_never_assigns_a_non_alphabetic_term(): void {
        global $DB;
        $this->resetAfterTest();

        $now = time();
        $cid = (int) $DB->insert_record('local_playergames_cartridges', (object) [
            'name' => 'C', 'version' => '1.0', 'language' => 'en', 'type' => 'concept',
            'timecreated' => $now, 'timemodified' => $now, 'uploadedby' => 0, 'active' => 1,
        ]);
        // The term "AI-5" sits inside the default 4-8 length window yet is not a
        // valid letters-only guess target; "table" is the only eligible term.
        foreach (['AI-5' => 'Institutional Act No. 5', 'table' => 'Furniture'] as $term => $def) {
            $DB->insert_record('local_playergames_concepts', (object) [
                'cartridgeid' => $cid, 'term' => $term, 'definition' => $def,
                'difficulty' => 3, 'categoryid' => null, 'language' => null,
            ]);
        }

        $this->run_task();

        $today = mktime(0, 0, 0);
        $conceptid = $DB->get_field('local_playergames_daily_assignments', 'conceptid', [
            'gamedate' => $today, 'gametype' => 'guess',
        ]);
        $this->assertNotFalse($conceptid);
        $term = $DB->get_field('local_playergames_concepts', 'term', ['id' => $conceptid]);
        $this->assertSame('table', $term);
    }

    /**
     * Opens an active season whose PlayerGuess and PlayerFill draw only from the given cartridges.
     *
     * @param int[]|null $kept Cartridge ids kept for both games, or null for "all of them".
     * @return void
     */
    private function open_season_keeping(?array $kept): void {
        global $DB;
        $now = time();
        $seasonid = (int) $DB->insert_record('local_playergames_seasons', (object) [
            'name' => 'Season', 'startdate' => $now - DAYSECS, 'enddate' => $now + (DAYSECS * 30),
            'status' => 'active', 'config_snapshot' => json_encode([]),
            'timecreated' => $now, 'timemodified' => $now,
        ]);
        foreach (['guess', 'fill'] as $gametype) {
            $DB->insert_record('local_playergames_season_games', (object) [
                'seasonid' => $seasonid, 'gametype' => $gametype, 'enabled' => 1, 'source' => 'cartridge',
                'cartridgeids' => $kept === null ? null : json_encode($kept), 'auxid' => 0,
            ]);
        }
    }

    /**
     * Adds a second active concept cartridge, with terms that no other cartridge has.
     *
     * @return int Cartridge id.
     */
    private function seed_other_concepts(): int {
        global $DB;
        $now = time();
        $cid = (int) $DB->insert_record('local_playergames_cartridges', (object) [
            'name' => 'Other', 'version' => '1.0', 'language' => 'en', 'type' => 'concept',
            'timecreated' => $now, 'timemodified' => $now, 'uploadedby' => 0, 'active' => 1,
        ]);
        foreach (['zebra', 'tiger', 'koala', 'panda', 'camel'] as $term) {
            $DB->insert_record('local_playergames_concepts', (object) [
                'cartridgeid' => $cid, 'term' => $term, 'definition' => 'An animal',
                'difficulty' => 3, 'categoryid' => null, 'language' => null,
            ]);
        }
        return $cid;
    }

    /**
     * Cartridge ids of the concepts assigned today to a game.
     *
     * @param string $gametype Game type.
     * @return int[]
     */
    private function assigned_cartridges(string $gametype): array {
        global $DB;
        return array_map('intval', $DB->get_fieldset_sql(
            "SELECT DISTINCT c.cartridgeid
               FROM {local_playergames_daily_assignments} a
               JOIN {local_playergames_concepts} c ON c.id = a.conceptid
              WHERE a.gamedate = :gd AND a.gametype = :gt",
            ['gd' => mktime(0, 0, 0), 'gt' => $gametype]
        ));
    }

    /**
     * Regression test: the cartridges ticked per game in the season settings were saved and never read,
     * so the words of an excluded cartridge reached the players anyway.
     */
    public function test_guess_and_fill_draw_only_from_the_cartridges_the_season_kept(): void {
        $this->resetAfterTest();
        $kept = $this->seed_concepts();
        $this->seed_other_concepts();
        $this->open_season_keeping([$kept]);

        $this->run_task();

        $this->assertSame([$kept], $this->assigned_cartridges('guess'));
        $this->assertSame([$kept], $this->assigned_cartridges('fill'));
    }

    public function test_no_cartridge_ticked_means_all_active_cartridges(): void {
        global $DB;
        $this->resetAfterTest();
        $this->seed_concepts();
        $this->seed_other_concepts();
        $this->open_season_keeping(null);

        $this->run_task();

        $this->assertSame(1, $DB->count_records('local_playergames_daily_assignments', ['gametype' => 'guess']));
        $this->assertSame(fill_manager::num_words(), $DB->count_records('local_playergames_daily_assignments', [
            'gametype' => 'fill',
        ]));
    }

    public function test_a_kept_cartridge_that_is_no_longer_active_assigns_nothing(): void {
        global $DB;
        $this->resetAfterTest();
        $kept = $this->seed_concepts();
        $this->seed_other_concepts();
        $this->open_season_keeping([$kept]);
        $DB->set_field('local_playergames_cartridges', 'active', 0, ['id' => $kept]);

        $this->run_task();

        $this->assertSame([], $this->assigned_cartridges('guess'));
        $this->assertSame([], $this->assigned_cartridges('fill'));
    }

    /**
     * Regression test: a range with the minimum above the maximum matched no term, so Guess and Fill got
     * no concept every day. A range that cannot work falls back to the defaults.
     */
    public function test_a_range_that_crosses_itself_falls_back_to_the_defaults(): void {
        global $DB;
        $this->resetAfterTest();
        $this->seed_concepts();
        set_config('wordle_minlen', 10, 'local_playergames');
        set_config('wordle_maxlen', 6, 'local_playergames');
        set_config('fill_minlen', 13, 'local_playergames');
        set_config('fill_maxlen', -1, 'local_playergames');

        $this->run_task();

        $today = mktime(0, 0, 0);
        $this->assertSame(1, $DB->count_records('local_playergames_daily_assignments', [
            'gamedate' => $today, 'gametype' => 'guess',
        ]));
        $this->assertSame(fill_manager::num_words(), $DB->count_records('local_playergames_daily_assignments', [
            'gamedate' => $today, 'gametype' => 'fill',
        ]));
    }
}
