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
 * Scheduled task: purge game scores from old closed seasons.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\task;

/**
 * Removes daily_scores rows from seasons closed more than N seasons ago.
 *
 * N is configured via the admin setting 'seasons_keep' (default 2).
 * The active season and the N most-recently-closed seasons are always preserved.
 * Only seasons with status = 'closed' are considered for purge.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class purge_old_scores extends \core\task\scheduled_task {
    /**
     * Returns the human-readable task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_purge_old_scores', 'local_playergames');
    }

    /**
     * Deletes daily_scores rows for seasons older than the retention window.
     *
     * @return void
     */
    public function execute(): void {
        global $DB;
        $keep = max(1, (int) get_config('local_playergames', 'seasons_keep') ?: 2);

        $closedseasons = $DB->get_records_select(
            'local_playergames_seasons',
            "status = 'closed'",
            [],
            'enddate DESC',
            'id, name, startdate, enddate'
        );

        if (count($closedseasons) <= $keep) {
            mtrace('Not enough closed seasons to purge — nothing to do.');
            return;
        }

        $preserved = array_slice(array_keys($closedseasons), 0, $keep);
        $purge     = array_slice(array_keys($closedseasons), $keep);

        foreach ($purge as $seasonid) {
            $season   = $closedseasons[$seasonid];
            $deleted  = $DB->count_records_select(
                'local_playergames_daily_scores',
                'gamedate BETWEEN :start AND :end',
                ['start' => $season->startdate, 'end' => $season->enddate]
            );
            $DB->delete_records_select(
                'local_playergames_daily_scores',
                'gamedate BETWEEN :start AND :end',
                ['start' => $season->startdate, 'end' => $season->enddate]
            );
            mtrace("Purged {$deleted} daily_scores rows for season {$seasonid}: {$season->name}");
        }

        $preservednames = implode(', ', array_map(
            fn($id) => $closedseasons[$id]->name,
            $preserved
        ));
        mtrace("Preserved seasons: {$preservednames}");
    }
}
