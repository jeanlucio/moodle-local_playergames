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
 * Learning XP pool: mirrors block_playerhud course XP into a windowed total.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\hub;

use cache;
use stdClass;

/**
 * Stores and aggregates the "learning XP" pool (course XP via block_playerhud).
 *
 * Separate from xp_manager's season XP pool — never summed together. Data is
 * stored in monthly buckets (local_playergames_student_xp_monthly), the source
 * of truth, and a denormalized windowed total
 * (local_playergames_student_xp_cache) kept for O(1) ranking/display reads.
 * The window length is configurable (learningxp_window_months, 0 = unlimited).
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class learning_xp_manager {
    /**
     * @var string Cache key prefix for the learning ranking lists (season-agnostic).
     * Suffixed with '_students' or '_staff' by the hub renderable, mirroring the
     * season ranking's per-group cache keys.
     */
    public const RANKING_CACHE_KEY = 'learning';

    /**
     * Returns the configured window length in months (0 = unlimited).
     *
     * @return int
     */
    public static function window_months(): int {
        $configured = get_config('local_playergames', 'learningxp_window_months');
        return ($configured !== false && $configured !== '') ? max(0, (int) $configured) : 12;
    }

    /**
     * Returns the 'YYYYMM' period string for a timestamp.
     *
     * @param int $timestamp Unix timestamp.
     * @return string
     */
    public static function period_for(int $timestamp): string {
        return date('Ym', $timestamp);
    }

    /**
     * Returns the oldest period still inside the window, or '' when unlimited.
     *
     * @param int $timestamp Reference timestamp (defaults to now via caller).
     * @return string 'YYYYMM', or '' when the window is unlimited (0).
     */
    public static function oldest_period_in_window(int $timestamp): string {
        $months = self::window_months();
        if ($months <= 0) {
            return '';
        }
        return date('Ym', strtotime("-{$months} months", $timestamp));
    }

    /**
     * Records a signed XP delta for the current month and applies it to the
     * windowed cache immediately (cheap incremental update).
     *
     * Called by the observer on every block_playerhud xp_changed event. The
     * nightly recompute_learning_xp task is what corrects the window's aging
     * (a bucket that ages out is only dropped there, not here).
     *
     * @param int $userid User id.
     * @param int $delta Signed XP delta (positive gain, negative loss).
     * @return void
     */
    public static function record_change(int $userid, int $delta): void {
        global $DB;
        if ($delta === 0) {
            return;
        }

        $now    = time();
        $period = self::period_for($now);

        $bucket = $DB->get_record(
            'local_playergames_student_xp_monthly',
            ['userid' => $userid, 'period' => $period]
        );
        if ($bucket) {
            $bucket->xp           = (int) $bucket->xp + $delta;
            $bucket->timemodified = $now;
            $DB->update_record('local_playergames_student_xp_monthly', $bucket);
        } else {
            $DB->insert_record('local_playergames_student_xp_monthly', (object) [
                'userid'       => $userid,
                'period'       => $period,
                'xp'           => $delta,
                'timecreated'  => $now,
                'timemodified' => $now,
            ]);
        }

        $cacherow = self::get_or_create_cache($userid);
        $cacherow->windowedxp   = max(0, (int) $cacherow->windowedxp + $delta);
        $cacherow->timemodified = $now;
        $DB->update_record('local_playergames_student_xp_cache', $cacherow);

        self::invalidate_ranking_cache();
    }

    /**
     * Returns (creating if absent) the cache row for a user.
     *
     * @param int $userid User id.
     * @return stdClass
     */
    public static function get_or_create_cache(int $userid): stdClass {
        global $DB;
        $cache = $DB->get_record('local_playergames_student_xp_cache', ['userid' => $userid]);
        if ($cache) {
            return $cache;
        }
        $now   = time();
        $cache = (object) [
            'userid'        => $userid,
            'windowedxp'    => 0,
            'showinranking' => 0,
            'timecreated'   => $now,
            'timemodified'  => $now,
        ];
        $cache->id = $DB->insert_record('local_playergames_student_xp_cache', $cache);
        return $cache;
    }

    /**
     * Returns the windowed learning XP for a user (read-only, no row creation).
     *
     * @param int $userid User id.
     * @return int
     */
    public static function get_windowedxp(int $userid): int {
        global $DB;
        $value = $DB->get_field('local_playergames_student_xp_cache', 'windowedxp', ['userid' => $userid]);
        return $value !== false ? (int) $value : 0;
    }

    /**
     * Sets a user's learning-XP ranking opt-in (season-agnostic, separate from
     * player_profile.showinranking).
     *
     * @param int $userid User id.
     * @param bool $show Whether the user should appear in the learning ranking.
     * @return void
     */
    public static function set_ranking_visibility(int $userid, bool $show): void {
        global $DB;
        $cacherow = self::get_or_create_cache($userid);
        $cacherow->showinranking = $show ? 1 : 0;
        $cacherow->timemodified  = time();
        $DB->update_record('local_playergames_student_xp_cache', $cacherow);

        self::invalidate_ranking_cache();
    }

    /**
     * Returns a user's 1-based learning-ranking position within a participant
     * group, counting only opted-in users (showinranking = 1, windowedxp > 0
     * on student_xp_cache) ahead of them.
     *
     * Mirrors the ORDER BY of the learning ranking (windowedxp DESC,
     * timemodified ASC, userid ASC) and the role split of
     * xp_manager::get_season_position(). The user never counts themselves.
     * Used to show the position of someone who opted in but falls outside the
     * loaded top-50 list. A single indexed COUNT — no full-table sort.
     *
     * @param int   $userid User whose position is wanted.
     * @param int   $mywindowedxp The user's windowed learning XP.
     * @param int   $mytimemodified The user's cache row timemodified (tie-break).
     * @param int[] $staffids Staff user IDs used to split the ranking by group.
     * @param bool  $wantstaff True to rank within staff; false within students.
     * @return int 1-based position, or 0 when the requested group has no ranking.
     */
    public static function get_position(
        int $userid,
        int $mywindowedxp,
        int $mytimemodified,
        array $staffids,
        bool $wantstaff
    ): int {
        global $DB;

        $params = [
            'xpgt'     => $mywindowedxp,
            'xpeq1'    => $mywindowedxp,
            'tmlt'     => $mytimemodified,
            'xpeq2'    => $mywindowedxp,
            'tmeq'     => $mytimemodified,
            'myuserid' => $userid,
        ];
        $filter = '';

        if (!empty($staffids)) {
            [$insql, $inparams] = $DB->get_in_or_equal($staffids, SQL_PARAMS_NAMED, 'uid', $wantstaff);
            $params = array_merge($params, $inparams);
            $filter = "AND c.userid {$insql}";
        } else if ($wantstaff) {
            return 0;
        }

        $sql = "SELECT COUNT(1)
                  FROM {local_playergames_student_xp_cache} c
                 WHERE c.showinranking = 1
                   AND c.windowedxp > 0
                   {$filter}
                   AND (c.windowedxp > :xpgt
                        OR (c.windowedxp = :xpeq1 AND c.timemodified < :tmlt)
                        OR (c.windowedxp = :xpeq2 AND c.timemodified = :tmeq AND c.userid < :myuserid))";

        return (int) $DB->count_records_sql($sql, $params) + 1;
    }

    /**
     * Deletes both cached learning ranking lists (students and staff).
     *
     * Called after every learning XP change or visibility toggle, so the
     * ranking reflects the latest state within the 60-second TTL window.
     *
     * @return void
     */
    private static function invalidate_ranking_cache(): void {
        $cache = cache::make('local_playergames', 'ranking');
        $cache->delete(self::RANKING_CACHE_KEY . '_students');
        $cache->delete(self::RANKING_CACHE_KEY . '_staff');
    }

    /**
     * Recomputes the windowed cache for every user from the monthly buckets,
     * pruning buckets that have aged out of the window.
     *
     * Heavy GROUP BY/SUM work — intended to run only from the nightly
     * recompute_learning_xp scheduled task, never on a web request.
     *
     * @return void
     */
    public static function recompute_all(): void {
        global $DB;

        $cutoff = self::oldest_period_in_window(time());
        if ($cutoff !== '') {
            $DB->delete_records_select(
                'local_playergames_student_xp_monthly',
                'period < :cutoff',
                ['cutoff' => $cutoff]
            );
        }

        $sums = $DB->get_records_sql(
            'SELECT userid, SUM(xp) AS total
               FROM {local_playergames_student_xp_monthly}
           GROUP BY userid'
        );

        $existing = $DB->get_records('local_playergames_student_xp_cache', null, '', 'userid, id, windowedxp, timemodified');

        $now = time();
        foreach ($existing as $userid => $cache) {
            $newvalue = isset($sums[$userid]) ? max(0, (int) $sums[$userid]->total) : 0;
            if ($newvalue !== (int) $cache->windowedxp) {
                $cache->windowedxp   = $newvalue;
                $cache->timemodified = $now;
                $DB->update_record('local_playergames_student_xp_cache', $cache);
            }
        }

        foreach ($sums as $userid => $sum) {
            if (isset($existing[$userid])) {
                continue;
            }
            $DB->insert_record('local_playergames_student_xp_cache', (object) [
                'userid'        => $userid,
                'windowedxp'    => max(0, (int) $sum->total),
                'showinranking' => 0,
                'timecreated'   => $now,
                'timemodified'  => $now,
            ]);
        }
    }
}
