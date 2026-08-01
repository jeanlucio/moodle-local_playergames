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
}
