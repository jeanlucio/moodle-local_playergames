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

use local_playergames\hub\season_manager;

/**
 * Closes active seasons whose enddate < now and optionally creates the next one.
 *
 * If autorenew_seasons is enabled and no active or upcoming season exists after
 * closing, season_manager::create_next() is called to schedule a new season.
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
     * Closes expired seasons and auto-renews if configured.
     *
     * @return void
     */
    public function execute(): void {
        global $DB;
        $now     = time();
        $expired = $DB->get_records_select(
            'local_playergames_seasons',
            "status = 'active' AND enddate < :now",
            ['now' => $now]
        );

        foreach ($expired as $season) {
            season_manager::close((int) $season->id);
            mtrace("Closed season {$season->id}: {$season->name}");
        }

        if (empty($expired)) {
            return;
        }

        $autorenew = (bool) get_config('local_playergames', 'autorenew_seasons');
        if (!$autorenew) {
            return;
        }

        $next = season_manager::get_active_or_upcoming();
        if ($next) {
            return;
        }

        $lastclosed = end($expired);
        $newseason  = season_manager::create_next($lastclosed);
        mtrace("Auto-created next season {$newseason->id}: {$newseason->name}");

        season_manager::activate((int) $newseason->id);
        mtrace("Activated season {$newseason->id}");
    }
}
