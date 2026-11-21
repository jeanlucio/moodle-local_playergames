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
 * Mission manager for local_playergames.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\hub;

use local_playergames\local\unique_row;
use local_playergames\local\user_lock;
use stdClass;

/**
 * Manages the five hardcoded V1 missions.
 *
 * Missions are stored in local_playergames_missions and identified by
 * their namestring lang key. sync() ensures the rows exist; update() advances
 * mission progress when the relevant in-game event occurs.
 *
 * Mission types and their triggers:
 *   daily         — incremented by 'game_played'; reset each day by cron
 *   streak        — set when streak reaches targetvalue
 *   cumulative    — set when player season XP reaches targetvalue
 *   battle_win    — set on first battle victory
 *   checkin_streak — incremented by 'checkin'; reset by cron if no check-in yesterday
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mission_manager {
    /**
     * Hardcoded mission definitions (V1).
     *
     * namestring and descstring are lang string keys in local_playergames.
     *
     * @var array<int, array<string, mixed>>
     */
    private const MISSIONS = [
        [
            'type'         => 'daily',
            'targetvalue'  => 1,
            'xpreward'     => 5,
            'freezereward' => 0,
            'namestring'   => 'mission_daily_name',
            'descstring'   => 'mission_daily_desc',
            'icon'         => 'fa-play',
        ],
        [
            'type'         => 'streak',
            'targetvalue'  => 7,
            'xpreward'     => 50,
            'freezereward' => 1,
            'namestring'   => 'mission_streak_name',
            'descstring'   => 'mission_streak_desc',
            'icon'         => 'fa-fire',
        ],
        [
            'type'         => 'cumulative',
            'targetvalue'  => 100,
            'xpreward'     => 20,
            'freezereward' => 0,
            'namestring'   => 'mission_cumulative_name',
            'descstring'   => 'mission_cumulative_desc',
            'icon'         => 'fa-star',
        ],
        [
            'type'         => 'battle_win',
            'targetvalue'  => 1,
            'xpreward'     => 30,
            'freezereward' => 0,
            'namestring'   => 'mission_battle_win_name',
            'descstring'   => 'mission_battle_win_desc',
            'icon'         => 'fa-trophy',
        ],
        [
            'type'         => 'checkin_streak',
            'targetvalue'  => 7,
            'xpreward'     => 15,
            'freezereward' => 1,
            'namestring'   => 'mission_checkin_streak_name',
            'descstring'   => 'mission_checkin_streak_desc',
            'icon'         => 'fa-calendar-check',
        ],
    ];

    /**
     * Ensures all hardcoded missions exist in the DB.
     *
     * Safe to call multiple times; uses namestring as idempotency key.
     *
     * @return void
     */
    public static function sync(): void {
        global $DB;
        foreach (self::MISSIONS as $def) {
            if (!$DB->record_exists('local_playergames_missions', ['namestring' => $def['namestring']])) {
                $DB->insert_record('local_playergames_missions', (object) $def);
            }
        }
    }

    /**
     * Loads all missions from the DB (calls sync() if none exist).
     *
     * @return stdClass[] Keyed by mission type.
     */
    public static function get_all(): array {
        global $DB;
        $missions = $DB->get_records('local_playergames_missions');
        if (empty($missions)) {
            self::sync();
            $missions = $DB->get_records('local_playergames_missions');
        }
        $bytype = [];
        foreach ($missions as $m) {
            $bytype[$m->type] = $m;
        }
        return $bytype;
    }

    /**
     * Updates mission progress for a user after an in-game event.
     *
     * Triggers:
     *   'game_played'   — advances daily mission
     *   'streak_updated'— checks streak mission (context: ['streak' => int])
     *   'xp_earned'     — checks cumulative mission (context: ['total_xp' => int])
     *   'battle_won'    — completes battle_win mission
     *   'checkin'       — advances checkin_streak mission
     *
     * @param int    $userid
     * @param int    $seasonid
     * @param string $trigger  Event trigger key.
     * @param array  $context  Additional data for the trigger.
     * @return void
     * @throws \moodle_exception When another request of the user kept the lock for the whole timeout.
     */
    public static function update(int $userid, int $seasonid, string $trigger, array $context = []): void {
        // A mission is read, found not completed, then completed and paid. Two requests of the same user (two
        // games, or a game and the check-in) would both find it open and both pay it, so the whole step runs
        // under the lock of the user, which the game or the check-in that got here already holds.
        user_lock::run($userid, fn() => self::update_locked($userid, $seasonid, $trigger, $context));
    }

    /**
     * Does the work of update() while the request holds the lock of the user.
     *
     * @param int    $userid
     * @param int    $seasonid
     * @param string $trigger  Event trigger key.
     * @param array  $context  Additional data for the trigger.
     * @return void
     */
    private static function update_locked(int $userid, int $seasonid, string $trigger, array $context): void {
        global $DB;
        $missions = self::get_all();

        if ($trigger === 'game_played') {
            if (!isset($missions['daily'])) {
                return;
            }
            $mission  = $missions['daily'];
            $progress = self::get_or_create_progress($userid, (int) $mission->id, $seasonid);
            if ($progress->completed && (int) $progress->timecompleted < mktime(0, 0, 0)) {
                // A mission completed on another day is over, whether or not the reset of midnight has run yet:
                // the cron runs late on many sites, and today's play must not find yesterday's mission done.
                $progress->completed     = 0;
                $progress->currentvalue  = 0;
                $progress->timecompleted = null;
            }
            if ($progress->completed) {
                return;
            }
            $progress->currentvalue++;
            if ($progress->currentvalue >= (int) $mission->targetvalue) {
                self::complete_mission($userid, $seasonid, $mission, $progress);
            } else {
                $DB->update_record('local_playergames_mission_progress', $progress);
            }
        }

        if ($trigger === 'streak_updated' && isset($context['streak']) && isset($missions['streak'])) {
            $mission  = $missions['streak'];
            $progress = self::get_or_create_progress($userid, (int) $mission->id, $seasonid);
            if (!$progress->completed) {
                $progress->currentvalue = (int) $context['streak'];
                self::advance_or_complete($userid, $seasonid, $mission, $progress);
            }
        }

        if ($trigger === 'xp_earned' && isset($context['total_xp']) && isset($missions['cumulative'])) {
            $mission  = $missions['cumulative'];
            $progress = self::get_or_create_progress($userid, (int) $mission->id, $seasonid);
            if (!$progress->completed) {
                $progress->currentvalue = (int) $context['total_xp'];
                self::advance_or_complete($userid, $seasonid, $mission, $progress);
            }
        }

        if ($trigger === 'battle_won' && isset($missions['battle_win'])) {
            $mission  = $missions['battle_win'];
            $progress = self::get_or_create_progress($userid, (int) $mission->id, $seasonid);
            if (!$progress->completed) {
                $progress->currentvalue = 1;
                self::complete_mission($userid, $seasonid, $mission, $progress);
            }
        }

        if ($trigger === 'checkin' && isset($missions['checkin_streak'])) {
            $mission  = $missions['checkin_streak'];
            $progress = self::get_or_create_progress($userid, (int) $mission->id, $seasonid);
            if (!$progress->completed) {
                // Without a check-in yesterday the streak is broken, whether or not the reset of midnight has run.
                $yesterday = ['userid' => $userid, 'gametype' => 'checkin', 'gamedate' => streak_manager::midnight_yesterday()];
                if ($progress->currentvalue > 0 && !$DB->record_exists('local_playergames_daily_scores', $yesterday)) {
                    $progress->currentvalue = 0;
                }
                $progress->currentvalue++;
                if ($progress->currentvalue >= (int) $mission->targetvalue) {
                    self::complete_mission($userid, $seasonid, $mission, $progress);
                } else {
                    $DB->update_record('local_playergames_mission_progress', $progress);
                }
            }
        }
    }

    /**
     * Resets the daily missions of a season that were completed before today.
     *
     * Called at midnight by the reset_daily_missions task, which can run late: a mission already completed today
     * is today's and stays. A play that comes before the reset does the same reset for its own user.
     *
     * @param int $seasonid
     * @return void
     */
    public static function reset_daily(int $seasonid): void {
        global $DB;
        $missionids = $DB->get_fieldset_select(
            'local_playergames_missions',
            'id',
            "type = 'daily'"
        );
        if (empty($missionids)) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($missionids, SQL_PARAMS_NAMED, 'mid');
        $params['seasonid'] = $seasonid;
        $params['today'] = mktime(0, 0, 0);
        // The rows are found by the time they were completed, so that time is cleared last: once it is null the
        // condition no longer matches them.
        $where = "missionid {$insql} AND seasonid = :seasonid AND timecompleted < :today";
        $DB->set_field_select('local_playergames_mission_progress', 'currentvalue', 0, $where, $params);
        $DB->set_field_select('local_playergames_mission_progress', 'completed', 0, $where, $params);
        $DB->set_field_select('local_playergames_mission_progress', 'timecompleted', null, $where, $params);
    }

    /**
     * Resets checkin_streak progress for users who missed a check-in yesterday.
     *
     * Called at midnight by the reset_daily_missions task, which can run late: a user who already checked in
     * today had the streak restarted by that check-in, which the reset must not undo.
     *
     * @param int $seasonid
     * @param int $yesterday Unix timestamp of midnight yesterday.
     * @return void
     */
    public static function reset_missed_checkin_streaks(int $seasonid, int $yesterday): void {
        global $DB;
        $missionid = $DB->get_field('local_playergames_missions', 'id', ['type' => 'checkin_streak']);
        if (!$missionid) {
            return;
        }
        // Whoever has no check-in of yesterday, nor of today, loses the streak. Asked of the database as a
        // subquery: a list of the ids of everybody who did check in would grow with the site.
        $DB->set_field_select(
            'local_playergames_mission_progress',
            'currentvalue',
            0,
            "missionid = :mid AND seasonid = :sid AND currentvalue > 0
                AND NOT EXISTS (SELECT 1
                                  FROM {local_playergames_daily_scores} ds
                                 WHERE ds.userid = {local_playergames_mission_progress}.userid
                                   AND ds.gametype = 'checkin'
                                   AND ds.gamedate >= :yesterday)",
            ['mid' => $missionid, 'sid' => $seasonid, 'yesterday' => $yesterday]
        );
    }

    /**
     * Gets or creates a mission_progress row for a user/mission/season triple.
     *
     * @param int $userid
     * @param int $missionid
     * @param int $seasonid
     * @return stdClass
     */
    private static function get_or_create_progress(int $userid, int $missionid, int $seasonid): stdClass {
        return unique_row::get_or_create(
            'local_playergames_mission_progress',
            ['userid' => $userid, 'missionid' => $missionid, 'seasonid' => $seasonid],
            ['currentvalue' => 0, 'completed' => 0, 'timecompleted' => null]
        );
    }

    /**
     * Saves the progress of a mission measured by a total (a streak, the XP of the season), or completes it.
     *
     * The total is stored while it is below the target, so the hub shows how far the player is and not 0 until
     * the day it is reached.
     *
     * @param int $userid The player.
     * @param int $seasonid The season the progress belongs to.
     * @param stdClass $mission The mission, with its target.
     * @param stdClass $progress The player's progress row, with currentvalue set to the total now.
     * @return void
     */
    private static function advance_or_complete(int $userid, int $seasonid, stdClass $mission, stdClass $progress): void {
        global $DB;
        if ((int) $progress->currentvalue >= (int) $mission->targetvalue) {
            self::complete_mission($userid, $seasonid, $mission, $progress);
            return;
        }
        $DB->update_record('local_playergames_mission_progress', $progress);
    }

    /**
     * Marks a mission as completed and awards its XP reward.
     *
     * @param int $userid
     * @param int $seasonid
     * @param stdClass $mission
     * @param stdClass $progress
     * @return void
     */
    private static function complete_mission(
        int $userid,
        int $seasonid,
        stdClass $mission,
        stdClass $progress
    ): void {
        global $DB;
        $progress->completed     = 1;
        $progress->timecompleted = time();
        $DB->update_record('local_playergames_mission_progress', $progress);
        xp_manager::award_uncapped($userid, (int) $mission->xpreward, $seasonid);

        if (!CLI_SCRIPT) {
            \core\notification::success(get_string(
                'mission_completed',
                'local_playergames',
                (object) [
                    'name' => get_string($mission->namestring, 'local_playergames'),
                    'xp'   => (int) $mission->xpreward,
                ]
            ));
        }

        $freezereward = (int) ($mission->freezereward ?? 0);
        if ($freezereward > 0) {
            $granted = streak_manager::add_freezes($userid, $freezereward);
            if ($granted > 0 && !CLI_SCRIPT) {
                \core\notification::success(
                    get_string('mission_freeze_earned', 'local_playergames', $granted)
                );
            }
        }
    }
}
