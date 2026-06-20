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
 * Tests for the close_expired_seasons scheduled task.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\task;

use local_playergames\hub\season_manager;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Unit tests for {@see close_expired_seasons}.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(close_expired_seasons::class)]
final class close_expired_seasons_test extends \advanced_testcase {
    /**
     * Inserts an active season whose end date is in the past.
     *
     * @return int Season id.
     */
    private function make_expired_active_season(): int {
        global $DB;
        $now = time();
        return (int) $DB->insert_record('local_playergames_seasons', (object) [
            'name' => 'Old', 'startdate' => $now - (DAYSECS * 60), 'enddate' => $now - DAYSECS,
            'status' => 'active', 'config_snapshot' => json_encode([]),
            'timecreated' => $now - (DAYSECS * 60), 'timemodified' => $now,
        ]);
    }

    /**
     * Runs the task, swallowing its mtrace output.
     *
     * @return void
     */
    private function run_task(): void {
        ob_start();
        (new close_expired_seasons())->execute();
        ob_get_clean();
    }

    public function test_closes_expired_season(): void {
        global $DB;
        $this->resetAfterTest();
        $this->redirectEvents();
        $seasonid = $this->make_expired_active_season();

        $this->run_task();

        $this->assertSame('closed', $DB->get_field('local_playergames_seasons', 'status', [
            'id' => $seasonid,
        ]));
        // Auto-renew is off by default, so no replacement season is created.
        $this->assertNull(season_manager::get_active_or_upcoming());
    }

    public function test_autorenew_creates_and_activates_next_season(): void {
        global $DB;
        $this->resetAfterTest();
        $this->redirectEvents();
        set_config('autorenew_seasons', 1, 'local_playergames');
        $oldid = $this->make_expired_active_season();

        $this->run_task();

        $this->assertSame('closed', $DB->get_field('local_playergames_seasons', 'status', [
            'id' => $oldid,
        ]));
        // A fresh active season is created and activated.
        $active = season_manager::get_active();
        $this->assertNotNull($active);
        $this->assertNotEquals($oldid, (int) $active->id);
    }
}
