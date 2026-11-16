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
 * Final results of closed seasons.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\hub;

use local_playergames\local\access;
use local_playergames\task\build_season_summary;

/**
 * Keeps one row per user and closed season with the result the user ended with.
 *
 * The detailed data of an old season (profile, mission progress, scores) is purged after a few seasons. The
 * summary is what stays: final XP, level, missions done and the position in the ranking, so the user keeps a
 * history and the hall of fame has something to read once the season's own rows are gone.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class season_summary {
    /** @var int Rows written by one insert. */
    private const INSERT_CHUNK = 500;

    /**
     * Queues the task that saves the results of a season that has just closed.
     *
     * The position needs the same participation rule the ranking applies to every user, which is too slow
     * for the request that closes the season.
     *
     * @param int $seasonid The closed season.
     * @return void
     */
    public static function queue_build(int $seasonid): void {
        $task = new build_season_summary();
        $task->set_custom_data(['seasonid' => $seasonid]);
        \core\task\manager::queue_adhoc_task($task, true);
    }

    /**
     * Saves the final result of every player of a season that has none yet.
     *
     * Safe to run again: a user who already has a row is left alone, so a result a user has changed since
     * (the hall of fame switch) is never overwritten. Only players with XP get a row, because a profile that
     * never earned anything says nothing about the season.
     *
     * The position is the one the hub showed: the same order (XP, then the earlier update, then the id), the
     * same people (visible in the ranking, not suspended, still taking part) and the same split between
     * students and staff, each group counted on its own.
     *
     * @param int $seasonid The season.
     * @return int Number of rows written.
     */
    public static function build(int $seasonid): int {
        global $DB;

        $profiles = $DB->get_records_sql(
            "SELECT p.userid, p.xp, p.level, p.showinranking, u.suspended
               FROM {local_playergames_player_profile} p
               JOIN {user} u ON u.id = p.userid AND u.deleted = 0
              WHERE p.seasonid = :seasonid
                AND p.xp > 0
           ORDER BY p.xp DESC, p.timemodified ASC, p.userid ASC",
            ['seasonid' => $seasonid]
        );
        if (empty($profiles)) {
            return 0;
        }

        $existing = array_flip($DB->get_fieldset_select(
            'local_playergames_season_summary',
            'userid',
            'seasonid = :seasonid',
            ['seasonid' => $seasonid]
        ));
        $missions = $DB->get_records_sql_menu(
            "SELECT userid, COUNT(1)
               FROM {local_playergames_mission_progress}
              WHERE seasonid = :seasonid AND completed = 1
           GROUP BY userid",
            ['seasonid' => $seasonid]
        );
        $staff = array_flip(access::get_staff_ids());

        $now      = time();
        $position = ['staff' => 0, 'students' => 0];
        $rows     = [];
        $groups   = [];
        foreach ($profiles as $profile) {
            $userid  = (int) $profile->userid;
            $group   = isset($staff[$userid]) ? 'staff' : 'students';
            $ranked  = (int) $profile->showinranking === 1
                && (int) $profile->suspended === 0
                && access::is_participant($userid, $group === 'staff');
            $finalrank = null;
            if ($ranked) {
                $position[$group]++;
                $finalrank = $position[$group];
            }
            if (isset($existing[$userid])) {
                continue;
            }
            $groups[count($rows)] = $finalrank === null ? null : $group;
            $rows[] = (object) [
                'userid'       => $userid,
                'seasonid'     => $seasonid,
                'xp'           => (int) $profile->xp,
                'level'        => (int) $profile->level,
                'missionsdone' => (int) ($missions[$userid] ?? 0),
                'finalrank'    => $finalrank,
                'rankedof'     => null,
                'showinhall'   => $ranked ? 1 : 0,
                'timecreated'  => $now,
            ];
        }

        // The size of each group is only known once everyone was counted.
        foreach ($rows as $index => $row) {
            if ($groups[$index] !== null) {
                $row->rankedof = $position[$groups[$index]];
            }
        }

        $transaction = $DB->start_delegated_transaction();
        foreach (array_chunk($rows, self::INSERT_CHUNK) as $chunk) {
            $DB->insert_records('local_playergames_season_summary', $chunk);
        }
        $transaction->allow_commit();

        return count($rows);
    }

    /**
     * Number of players of a season.
     *
     * Reads the profiles while the season still has them and the summary once the purge removed them.
     *
     * @param int $seasonid The season.
     * @return int
     */
    public static function count_players(int $seasonid): int {
        global $DB;

        $profiles = $DB->count_records('local_playergames_player_profile', ['seasonid' => $seasonid]);
        if ($profiles > 0) {
            return $profiles;
        }
        return $DB->count_records('local_playergames_season_summary', ['seasonid' => $seasonid]);
    }

    /**
     * Returns the results of a user, most recent season first.
     *
     * @param int $userid The user.
     * @return \stdClass[] Summary rows with the season name (seasonname) and dates, re-indexed from 0.
     */
    public static function get_for_user(int $userid): array {
        global $DB;

        return array_values($DB->get_records_sql(
            "SELECT s.id, s.seasonid, s.xp, s.level, s.missionsdone, s.finalrank, s.rankedof, s.showinhall,
                    n.name AS seasonname, n.startdate, n.enddate
               FROM {local_playergames_season_summary} s
               JOIN {local_playergames_seasons} n ON n.id = s.seasonid
              WHERE s.userid = :userid
           ORDER BY n.enddate DESC, s.id DESC",
            ['userid' => $userid]
        ));
    }
}
