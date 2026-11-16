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

use local_playergames\hub\season_summary;

/**
 * Removes the detailed data of seasons closed more than N seasons ago, and old activity history.
 *
 * N is configured via the admin setting 'seasons_keep' (default 2).
 * The active season and the N most-recently-closed seasons are always preserved.
 * Only seasons with status = 'closed' are considered for purge. For each of them the game scores, the
 * player profiles and the mission progress go, after the final result of every player is saved in
 * local_playergames_season_summary, which is kept.
 *
 * The activity history and the freeze history are purged by age, by the 'activitylog_keep' setting
 * (months; 0 keeps everything).
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
     * Deletes the detailed data of seasons older than the retention window and the old history rows.
     *
     * @return void
     */
    public function execute(): void {
        global $DB;
        $keep = max(1, (int) get_config('local_playergames', 'seasons_keep') ?: 2);

        // The "already shown today" set is only relevant for the current day;
        // drop every earlier row so the table never accumulates.
        $today        = mktime(0, 0, 0, (int) date('n'), (int) date('j'), (int) date('Y'));
        $stalecount   = $DB->count_records_select(
            'local_playergames_served_questions',
            'gamedate < :today',
            ['today' => $today]
        );
        $DB->delete_records_select(
            'local_playergames_served_questions',
            'gamedate < :today',
            ['today' => $today]
        );
        mtrace("Purged {$stalecount} stale served_questions rows.");

        // A round only matters on its own day; the day's score is kept in daily_scores.
        $DB->delete_records_select('local_playergames_rounds', 'gamedate < :today', ['today' => $today]);

        // The words of the day are read for today only, and nothing else looks at an earlier day's.
        $DB->delete_records_select('local_playergames_daily_assignments', 'gamedate < :today', ['today' => $today]);

        $this->purge_history();

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

            $battles = $DB->count_records('local_playergames_battle_scores', ['seasonid' => $seasonid]);
            $DB->delete_records('local_playergames_battle_scores', ['seasonid' => $seasonid]);
            mtrace("Purged {$battles} battle_scores rows for season {$seasonid}: {$season->name}");

            // The result of each player is saved first, so the season is never purged without it.
            $saved = season_summary::build($seasonid);
            $DB->delete_records('local_playergames_mission_progress', ['seasonid' => $seasonid]);
            $DB->delete_records('local_playergames_player_profile', ['seasonid' => $seasonid]);
            mtrace("Saved {$saved} results and purged the profiles of season {$seasonid}: {$season->name}");
        }

        $preservednames = implode(', ', array_map(
            fn($id) => $closedseasons[$id]->name,
            $preserved
        ));
        mtrace("Preserved seasons: {$preservednames}");
    }

    /**
     * Deletes activity history and freeze history rows older than the 'activitylog_keep' months.
     *
     * @return void
     */
    private function purge_history(): void {
        global $DB;

        $months = max(0, (int) get_config('local_playergames', 'activitylog_keep'));
        if ($months === 0) {
            return;
        }
        $cutoff = strtotime("-{$months} months");
        foreach (['local_playergames_activity_log', 'local_playergames_freeze_log'] as $table) {
            $DB->delete_records_select($table, 'timecreated < :cutoff', ['cutoff' => $cutoff]);
        }
        mtrace("Purged activity history older than {$months} months.");
    }
}
