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
    /** @var string Cache key for the learning ranking list (single, season-agnostic list). */
    public const RANKING_CACHE_KEY = 'learning_students';

    /** @var int Users handled per slice by recompute_all(). */
    public const RECOMPUTE_BATCH = 1000;

    /**
     * Whether the learning XP pool should be visible under the current site
     * configuration.
     *
     * Deliberately NOT gated on the viewer's own isstaff flag (a global "teacher
     * in any course" signal) — a person can be staff in one course and still a
     * genuine PlayerHUD-earning student in another, and should keep seeing their
     * own learning XP. Only the admin toggle and allowed_participants (a
     * site-level mode, unrelated to any one person's role) gate it. Shared by
     * the Hub and block_playergames so this rule only lives in one place.
     *
     * @param string $allowed Value of the allowed_participants setting.
     * @return bool
     */
    public static function is_visible_to(string $allowed): bool {
        return (bool) get_config('local_playergames', 'showlearningxp')
            && in_array($allowed, ['students', 'both'], true);
    }

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
     * @param int $courseid Course the delta originated from, if known (0 if not).
     * @return void
     */
    public static function record_change(int $userid, int $delta, int $courseid = 0): void {
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

        activity_log::record($userid, activity_log::TYPE_LEARNING_XP, $delta, 'block_playerhud', $courseid);

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
     * Returns a user's 1-based learning-ranking position, counting only
     * opted-in users (showinranking = 1 on student_xp_cache) ahead of them.
     *
     * Mirrors the ORDER BY of the learning ranking (windowedxp DESC,
     * timemodified ASC, userid ASC). The user never counts themselves. Used to
     * show the position of someone who opted in but falls outside the loaded
     * top-50 list. A single indexed COUNT — no full-table sort.
     *
     * @param int $userid User whose position is wanted.
     * @param int $mywindowedxp The user's windowed learning XP.
     * @param int $mytimemodified The user's cache row timemodified (tie-break).
     * @return int 1-based position.
     */
    public static function get_position(int $userid, int $mywindowedxp, int $mytimemodified): int {
        global $DB;

        $sql = "SELECT COUNT(1)
                  FROM {local_playergames_student_xp_cache} c
                  JOIN {user} u ON u.id = c.userid
                 WHERE c.showinranking = 1
                   AND u.deleted = 0
                   AND u.suspended = 0
                   AND (c.windowedxp > :xpgt
                        OR (c.windowedxp = :xpeq1 AND c.timemodified < :tmlt)
                        OR (c.windowedxp = :xpeq2 AND c.timemodified = :tmeq AND c.userid < :myuserid))";
        $params = [
            'xpgt'     => $mywindowedxp,
            'xpeq1'    => $mywindowedxp,
            'tmlt'     => $mytimemodified,
            'xpeq2'    => $mywindowedxp,
            'tmeq'     => $mytimemodified,
            'myuserid' => $userid,
        ];

        return (int) $DB->count_records_sql($sql, $params) + 1;
    }

    /**
     * Deletes the cached learning ranking list.
     *
     * Called after every learning XP change or visibility toggle, so the
     * ranking reflects the latest state within the 60-second TTL window.
     *
     * @return void
     */
    private static function invalidate_ranking_cache(): void {
        cache::make('local_playergames', 'ranking')->delete(self::RANKING_CACHE_KEY);
    }

    /**
     * Recomputes the windowed cache for every user from the monthly buckets,
     * pruning buckets that have aged out of the window.
     *
     * Heavy GROUP BY/SUM work — intended to run only from the nightly
     * recompute_learning_xp scheduled task, never on a web request.
     *
     * @param int $batch Users handled per slice.
     * @return void
     */
    public static function recompute_all(int $batch = self::RECOMPUTE_BATCH): void {
        global $DB;

        $cutoff = self::oldest_period_in_window(time());
        if ($cutoff !== '') {
            $DB->delete_records_select(
                'local_playergames_student_xp_monthly',
                'period < :cutoff',
                ['cutoff' => $cutoff]
            );
        }

        $now  = time();
        $last = 0;
        do {
            // One slice of users at a time, so neither the sums nor the cache rows of the whole site are
            // held in memory together.
            $sums = $DB->get_records_sql(
                'SELECT userid, SUM(xp) AS total
                   FROM {local_playergames_student_xp_monthly}
                  WHERE userid > :last
               GROUP BY userid
               ORDER BY userid',
                ['last' => $last],
                0,
                $batch
            );
            if ($sums === []) {
                break;
            }
            $last = (int) array_key_last($sums);
            self::apply_sums($sums, $now);
        } while (count($sums) === $batch);

        self::zero_users_without_buckets($now);
    }

    /**
     * Writes the windowed XP of a slice of users into the cache, creating the rows that are missing.
     *
     * @param \stdClass[] $sums Rows with userid and total, keyed by userid.
     * @param int $now Time stamp of the recompute.
     * @return void
     */
    private static function apply_sums(array $sums, int $now): void {
        global $DB;

        $existing = $DB->get_records_list(
            'local_playergames_student_xp_cache',
            'userid',
            array_keys($sums),
            '',
            'userid, id, windowedxp, timemodified'
        );

        $new = [];
        foreach ($sums as $userid => $sum) {
            $value = max(0, (int) $sum->total);
            if (!isset($existing[$userid])) {
                $new[] = (object) [
                    'userid'        => $userid,
                    'windowedxp'    => $value,
                    'showinranking' => 0,
                    'timecreated'   => $now,
                    'timemodified'  => $now,
                ];
                continue;
            }
            $cache = $existing[$userid];
            if ($value !== (int) $cache->windowedxp) {
                $cache->windowedxp   = $value;
                $cache->timemodified = $now;
                $DB->update_record('local_playergames_student_xp_cache', $cache);
            }
        }
        if ($new !== []) {
            $DB->insert_records('local_playergames_student_xp_cache', $new);
        }
    }

    /**
     * Sets to zero the windowed XP of users whose buckets all aged out and were pruned.
     *
     * @param int $now Time stamp of the recompute.
     * @return void
     */
    private static function zero_users_without_buckets(int $now): void {
        global $DB;

        $select = 'windowedxp <> 0 AND NOT EXISTS (
                       SELECT 1 FROM {local_playergames_student_xp_monthly} m
                        WHERE m.userid = {local_playergames_student_xp_cache}.userid)';
        $transaction = $DB->start_delegated_transaction();
        // The time stamp first: it uses the same condition, which the second statement would clear.
        $DB->set_field_select('local_playergames_student_xp_cache', 'timemodified', $now, $select);
        $DB->set_field_select('local_playergames_student_xp_cache', 'windowedxp', 0, $select);
        $transaction->allow_commit();
    }
}
