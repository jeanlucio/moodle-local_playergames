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
 * XP and level manager for local_playergames.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\hub;

use cache;
use local_playergames\event\level_reached;
use stdClass;

/**
 * Manages XP award, daily cap enforcement, level calculation and ranking cache.
 *
 * Flow for game handlers (Phase 5):
 *   1. Validate game and re-fetch the concept server-side.
 *   2. Call xp_manager::award() to get the capped XP amount.
 *   3. Write daily_scores with xpawarded = return value.
 *   4. Fire game_completed event.
 *
 * award() checks the per-gametype daily cap via daily_scores so that
 * mid-day replays beyond the cap correctly return 0.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class xp_manager {
    /**
     * Game types that bypass the daily XP cap (e.g. mission rewards).
     *
     * @var string[]
     */
    const UNCAPPED_TYPES = ['mission'];

    /**
     * Awards XP to a user for a specific game type, respecting the daily cap.
     *
     * Reads the cap from the season's config_snapshot. If the daily cap
     * for this gametype has already been reached today, returns 0.
     * Updates player_profile, fires level_reached if level changed,
     * and invalidates the ranking cache.
     *
     * @param int $userid User to award XP to.
     * @param int $xp Requested XP amount (before cap enforcement).
     * @param string $gametype One of: quiz, guess, fill, battle, checkin, mission.
     * @param int $gamedate Unix timestamp of the game date (midnight of the day).
     * @param int $seasonid Season to award XP within.
     * @return int Actual XP awarded (0 if cap already reached).
     */
    public static function award(int $userid, int $xp, string $gametype, int $gamedate, int $seasonid): int {
        global $DB;
        if ($xp <= 0) {
            return 0;
        }
        $season   = $DB->get_record('local_playergames_seasons', ['id' => $seasonid], '*', MUST_EXIST);
        $snapshot = season_manager::get_config_snapshot($season);
        $actual   = $xp;

        if (!in_array($gametype, self::UNCAPPED_TYPES, true)) {
            $capkey = 'xp_cap_' . $gametype;
            $cap    = isset($snapshot[$capkey]) ? (int) $snapshot[$capkey] : 0;
            if ($cap > 0) {
                $alreadyearned = (int) $DB->get_field_sql(
                    "SELECT COALESCE(SUM(xpawarded), 0)
                       FROM {local_playergames_daily_scores}
                      WHERE userid = :uid AND gamedate = :gd AND gametype = :gt",
                    ['uid' => $userid, 'gd' => $gamedate, 'gt' => $gametype]
                );
                $actual = max(0, min($xp, $cap - $alreadyearned));
                if ($actual <= 0) {
                    return 0;
                }
            }
            if ($gametype === 'checkin') {
                $seasoncap     = isset($snapshot['xp_cap_checkin_season']) ? (int) $snapshot['xp_cap_checkin_season'] : 150;
                $seasonearned  = (int) $DB->get_field_sql(
                    "SELECT COALESCE(SUM(xpawarded), 0)
                       FROM {local_playergames_daily_scores}
                      WHERE userid = :uid AND gametype = 'checkin'
                        AND gamedate BETWEEN :start AND :end",
                    ['uid' => $userid, 'start' => $season->startdate, 'end' => $season->enddate]
                );
                $actual = max(0, min($actual, $seasoncap - $seasonearned));
                if ($actual <= 0) {
                    return 0;
                }
            }
        }

        $profile    = self::get_or_create_profile($userid, $seasonid);
        $oldlevel   = self::get_level($profile->xp);
        $profile->xp += $actual;
        $newlevel   = self::get_level($profile->xp);
        $profile->level         = $newlevel;
        $profile->timemodified  = time();
        $DB->update_record('local_playergames_player_profile', $profile);

        // Keep the user's lifetime best level so avatar unlocks are permanent.
        avatar_manager::record_level($userid, $newlevel);

        if ($newlevel > $oldlevel) {
            $event = level_reached::create([
                'objectid' => $profile->id,
                'context'  => \context_system::instance(),
                'userid'   => $userid,
                'other'    => ['seasonid' => $seasonid, 'level' => $newlevel],
            ]);
            $event->trigger();
        }

        activity_log::record($userid, activity_log::TYPE_SEASON_XP, $actual, $gametype);

        self::invalidate_ranking_cache($seasonid);
        return $actual;
    }

    /**
     * Awards XP without any daily cap enforcement (used for mission rewards).
     *
     * @param int $userid
     * @param int $xp
     * @param int $seasonid
     * @return int Actual XP awarded.
     */
    public static function award_uncapped(int $userid, int $xp, int $seasonid): int {
        if ($xp <= 0) {
            return 0;
        }
        $today    = mktime(0, 0, 0, (int) date('n'), (int) date('j'), (int) date('Y'));
        return self::award($userid, $xp, 'mission', $today, $seasonid);
    }

    /**
     * Returns the level corresponding to a given XP total.
     *
     * @param int $xp Total cumulative XP.
     * @return int Level (1–max level).
     */
    public static function get_level(int $xp): int {
        return level_manager::level_for_xp($xp);
    }

    /**
     * Returns the minimum XP required to reach a specific level.
     *
     * @param int $level Target level (1–max level).
     * @return int XP threshold.
     */
    public static function get_xp_for_level(int $level): int {
        return level_manager::minxp_for_level($level);
    }

    /**
     * Computes XP progress bar data for the current level.
     *
     * Shared by the Hub and block_playergames, so the progress-bar formula only
     * lives in one place.
     *
     * @param int $xp   Total season XP.
     * @param int $level Current level (1–max level).
     * @return array{xp_next: int, xp_in_level: int, level_range: int, progress_pct: int}
     */
    public static function build_level_data(int $xp, int $level): array {
        if ($level >= level_manager::max_level()) {
            return ['xp_next' => 0, 'xp_in_level' => 0, 'level_range' => 0, 'progress_pct' => 100];
        }
        $current    = self::get_xp_for_level($level);
        $next       = self::get_xp_for_level($level + 1);
        $range      = $next - $current;
        $inlevel    = $xp - $current;
        $pct        = $range > 0 ? min(100, (int) round(($inlevel / $range) * 100)) : 100;
        return [
            'xp_next'    => $next - $xp,
            'xp_in_level' => $inlevel,
            'level_range' => $range,
            'progress_pct' => $pct,
        ];
    }

    /**
     * Returns the player_profile record for a user/season, creating it if absent.
     *
     * @param int $userid
     * @param int $seasonid
     * @return stdClass
     */
    public static function get_or_create_profile(int $userid, int $seasonid): stdClass {
        global $DB;
        $profile = $DB->get_record(
            'local_playergames_player_profile',
            ['userid' => $userid, 'seasonid' => $seasonid]
        );
        if ($profile) {
            return $profile;
        }
        $now             = time();
        $profile         = new stdClass();
        $profile->userid         = $userid;
        $profile->seasonid       = $seasonid;
        $profile->xp             = 0;
        $profile->level          = 1;
        $profile->showinranking  = 0;
        $profile->timecreated    = $now;
        $profile->timemodified   = $now;
        $profile->id = $DB->insert_record('local_playergames_player_profile', $profile);
        return $profile;
    }

    /**
     * Returns a user's 1-based ranking position within a season, counting only
     * opted-in players (showinranking = 1) ahead of them.
     *
     * Mirrors the ORDER BY of the hub ranking (xp DESC, timemodified ASC,
     * userid ASC) so ties resolve identically: whoever reached the XP earlier
     * ranks ahead, with userid as the final deterministic fallback. The user
     * never counts themselves. A single indexed COUNT — no full-table sort.
     *
     * @param int   $userid User whose position is wanted.
     * @param int   $seasonid Active season ID.
     * @param int   $myxp The user's season XP.
     * @param int   $mytimemodified The user's profile timemodified (tie-break).
     * @param int[] $staffids Staff user IDs used to split the ranking by group.
     * @param bool  $wantstaff True to rank within staff; false within students.
     * @return int 1-based position, or 0 when the requested group has no ranking.
     */
    public static function get_season_position(
        int $userid,
        int $seasonid,
        int $myxp,
        int $mytimemodified,
        array $staffids,
        bool $wantstaff
    ): int {
        global $DB;

        $params = [
            'seasonid' => $seasonid,
            'xpgt'     => $myxp,
            'xpeq1'    => $myxp,
            'xpeq2'    => $myxp,
            'tmlt'     => $mytimemodified,
            'tmeq'     => $mytimemodified,
            'myuserid' => $userid,
        ];
        $filter = '';

        if (!empty($staffids)) {
            [$insql, $inparams] = $DB->get_in_or_equal($staffids, SQL_PARAMS_NAMED, 'uid', $wantstaff);
            $params = array_merge($params, $inparams);
            $filter = "AND p.userid {$insql}";
        } else if ($wantstaff) {
            return 0;
        }

        $sql = "SELECT COUNT(1)
                  FROM {local_playergames_player_profile} p
                 WHERE p.seasonid = :seasonid
                   AND p.showinranking = 1
                   {$filter}
                   AND (p.xp > :xpgt
                        OR (p.xp = :xpeq1 AND p.timemodified < :tmlt)
                        OR (p.xp = :xpeq2 AND p.timemodified = :tmeq AND p.userid < :myuserid))";

        return (int) $DB->count_records_sql($sql, $params) + 1;
    }

    /**
     * Sets the current user's ranking visibility for a season and refreshes
     * the affected ranking cache.
     *
     * @param int  $userid User id.
     * @param int  $seasonid Season id.
     * @param bool $show Whether the user should appear in the ranking.
     * @return void
     */
    public static function set_ranking_visibility(int $userid, int $seasonid, bool $show): void {
        global $DB;
        $profile = self::get_or_create_profile($userid, $seasonid);
        $profile->showinranking = $show ? 1 : 0;
        $profile->timemodified  = time();
        $DB->update_record('local_playergames_player_profile', $profile);
        self::invalidate_ranking_cache($seasonid);
    }

    /**
     * Deletes the cached ranking entry for a season.
     *
     * Called after every XP award so the ranking reflects the latest state
     * within the 60-second TTL window.
     *
     * @param int $seasonid
     * @return void
     */
    private static function invalidate_ranking_cache(int $seasonid): void {
        $cache = cache::make('local_playergames', 'ranking');
        $cache->delete('season_' . $seasonid . '_students');
        $cache->delete('season_' . $seasonid . '_staff');
    }
}
