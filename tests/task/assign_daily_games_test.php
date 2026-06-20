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
     * Creates an active concept cartridge with a few wordle-eligible terms.
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
        foreach (['apple' => 'A fruit', 'table' => 'Furniture', 'planet' => 'A world'] as $term => $def) {
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

    public function test_assigns_one_concept_per_concept_game(): void {
        global $DB;
        $this->resetAfterTest();
        $this->seed_concepts();

        $this->run_task();

        $today = mktime(0, 0, 0);
        foreach (['quiz', 'guess', 'fill'] as $gametype) {
            $this->assertTrue($DB->record_exists('local_playergames_daily_assignments', [
                'gamedate' => $today,
                'gametype' => $gametype,
            ]), "missing assignment for {$gametype}");
        }
        $this->assertSame(3, $DB->count_records('local_playergames_daily_assignments'));
    }

    public function test_is_idempotent(): void {
        global $DB;
        $this->resetAfterTest();
        $this->seed_concepts();

        $this->run_task();
        $this->run_task();

        // A second run must not create duplicate assignments.
        $this->assertSame(3, $DB->count_records('local_playergames_daily_assignments'));
    }

    public function test_no_active_cartridges_assigns_nothing(): void {
        global $DB;
        $this->resetAfterTest();

        $this->run_task();

        $this->assertSame(0, $DB->count_records('local_playergames_daily_assignments'));
    }
}
