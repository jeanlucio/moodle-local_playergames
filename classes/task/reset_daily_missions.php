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
 * Scheduled task: reset daily missions and process streak breaks.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\task;

use local_playergames\hub\mission_manager;
use local_playergames\hub\season_manager;
use local_playergames\hub\streak_manager;

/**
 * Runs at midnight to reset daily missions and process streak breaks.
 *
 * Sequence:
 *   1. Process streak breaks for users who missed yesterday (freeze or reset).
 *   2. Reset daily mission progress for all users in the active season.
 *   3. Reset checkin_streak progress for users who missed a check-in yesterday.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reset_daily_missions extends \core\task\scheduled_task {
    /**
     * Returns the human-readable task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_reset_daily_missions', 'local_playergames');
    }

    /**
     * Resets daily missions and processes streak breaks.
     *
     * @return void
     */
    public function execute(): void {
        $today     = mktime(0, 0, 0, (int) date('n'), (int) date('j'), (int) date('Y'));
        $yesterday = $today - DAYSECS;

        $broken = streak_manager::process_breaks();
        mtrace("Streak breaks processed: {$broken}");

        $season = season_manager::get_active();
        if (!$season) {
            mtrace('No active season — skipping mission reset.');
            return;
        }

        mission_manager::reset_daily((int) $season->id);
        mtrace('Daily missions reset for season ' . $season->id);

        mission_manager::reset_missed_checkin_streaks((int) $season->id, $yesterday);
        mtrace('Checkin streak missions reset for missed users.');
    }
}
