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
 * Scheduled task: close seasons whose end date has passed.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\task;

/**
 * Sets status to 'closed' for any season where enddate < now and status = 'active'.
 * Fires season_closed event for each closed season.
 *
 * Auto-renewal (Phase 4): after closing a season, if the admin setting
 * 'autorenew_seasons' is enabled and no active/upcoming season exists, this
 * task creates the next season automatically using 'season_duration_months'
 * (default 6). The new season inherits the XP caps from the closed season's
 * config_snapshot so settings stay consistent until the admin changes them.
 *
 * Full implementation in Phase 4.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class close_expired_seasons extends \core\task\scheduled_task {
    /**
     * Returns the human-readable task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_close_expired_seasons', 'local_playergames');
    }

    /**
     * Executes the task.
     *
     * @return void
     */
    public function execute(): void {
        // Stub: implemented in Phase 4.
        // Phase 4 sequence:
        // 1. Find seasons with enddate < time() and status = 'active'.
        // 2. For each: set status = 'closed', fire season_closed event.
        // 3. If autorenew_seasons is enabled and no active/upcoming season exists,
        // call season_manager::create_next() — inherits config_snapshot and uses
        // season_duration_months to calculate the new enddate.
    }
}
