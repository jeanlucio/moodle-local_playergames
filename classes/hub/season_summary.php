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
use local_playergames\local\preferences;
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
                'isstaff'      => $group === 'staff' ? 1 : 0,
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

    /**
     * Lets a user show or hide the name in the hall of fame for one season.
     *
     * Only a result that was ranked can be shown, so a user who was not in the ranking cannot be put in
     * the hall by switching it on.
     *
     * @param int $userid The user whose result changes.
     * @param int $seasonid The season.
     * @param bool $show True to show the name.
     * @return bool Whether the user has a ranked result in that season.
     */
    public static function set_showinhall(int $userid, int $seasonid, bool $show): bool {
        global $DB;

        $select = 'userid = :userid AND seasonid = :seasonid AND finalrank IS NOT NULL';
        $params = ['userid' => $userid, 'seasonid' => $seasonid];
        if (!$DB->record_exists_select('local_playergames_season_summary', $select, $params)) {
            return false;
        }
        $DB->set_field_select('local_playergames_season_summary', 'showinhall', $show ? 1 : 0, $select, $params);
        return true;
    }

    /**
     * Returns the people of the hall of fame, season by season, most recent season first.
     *
     * The first places of each group (students and staff) of the latest closed seasons, leaving out the
     * names their owners hid, the users who opted out of the gamification and the accounts deleted or
     * suspended since. A hidden name leaves a gap in the positions, as the place was really taken.
     *
     * @param int $places Positions listed for each group.
     * @param int $seasons Latest seasons listed.
     * @return array[] Each with id, name and the rows of the 'students' and 'staff' groups; a row has
     *     userid, name, xp, level and position.
     */
    public static function get_hall(int $places = 10, int $seasons = 12): array {
        global $DB;

        $closed = $DB->get_records_sql(
            "SELECT n.id, n.name
               FROM {local_playergames_seasons} n
              WHERE EXISTS (SELECT 1
                              FROM {local_playergames_season_summary} s
                             WHERE s.seasonid = n.id AND s.finalrank IS NOT NULL)
           ORDER BY n.enddate DESC, n.id DESC",
            [],
            0,
            $seasons
        );
        if (empty($closed)) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal(array_keys($closed), SQL_PARAMS_NAMED, 'season');
        [$optoutsql, $optoutparams] = preferences::sql_not_opted_out('s');
        $params = array_merge($params, $optoutparams, ['places' => $places]);

        $rows = $DB->get_records_sql(
            "SELECT s.id, s.seasonid, s.userid, s.xp, s.level, s.finalrank, s.isstaff,
                    u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename
               FROM {local_playergames_season_summary} s
               JOIN {user} u ON u.id = s.userid AND u.deleted = 0 AND u.suspended = 0
              WHERE s.seasonid {$insql}
                AND s.showinhall = 1
                AND s.finalrank <= :places
                AND {$optoutsql}
           ORDER BY s.finalrank ASC, s.id ASC",
            $params
        );

        $hall = [];
        foreach ($closed as $season) {
            $hall[(int) $season->id] = [
                'id'       => (int) $season->id,
                'name'     => $season->name,
                'students' => [],
                'staff'    => [],
            ];
        }
        foreach ($rows as $row) {
            $group = (int) $row->isstaff === 1 ? 'staff' : 'students';
            $hall[(int) $row->seasonid][$group][] = (object) [
                'userid'   => (int) $row->userid,
                'name'     => fullname($row),
                'xp'       => (int) $row->xp,
                'level'    => (int) $row->level,
                'position' => (int) $row->finalrank,
            ];
        }

        return array_values($hall);
    }
}
