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

use local_playergames\local\day;
use local_playergames\local\unique_row;
use local_playergames\local\user_lock;
use stdClass;

/**
 * The missions: the five the plugin starts with, and the ones the administrator adds of the same kinds.
 *
 * Each kind is followed by code that listens to one event, so a mission can only be of one of the kinds below; any
 * number of missions can share a kind, with their own target and rewards.
 *
 *   daily          — that many games played today; starts again every day
 *   streak         — a streak of that many days of activity, counted from the start of the season
 *   cumulative     — that much XP in the season
 *   battle_win     — that many PlayerBattle victories in the season
 *
 * A mission that somebody completed in the active season keeps its target and rewards, and stays on, until the
 * season ends: changing them halfway would be unfair to whoever had to meet the old ones.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mission_manager {
    /** @var array[] Each kind: its icon and the range its target may take. */
    public const TYPES = [
        'daily'          => ['icon' => 'fa-play', 'min' => 1, 'max' => 20],
        'streak'         => ['icon' => 'fa-fire', 'min' => 2, 'max' => 365],
        'cumulative'     => ['icon' => 'fa-star', 'min' => 1, 'max' => 1000000],
        'battle_win'     => ['icon' => 'fa-trophy', 'min' => 1, 'max' => 365],
    ];

    /** @var int Most XP a mission can give. */
    public const MAX_XP = 10000;

    /**
     * The missions a new site starts with.
     *
     * Their names are language strings, so each viewer reads them in their own language.
     *
     * @var array<int, array<string, mixed>>
     */
    private const MISSIONS = [
        ['type' => 'daily', 'targetvalue' => 1, 'xpreward' => 5, 'freezereward' => 0, 'namestring' => 'mission_daily_name'],
        ['type' => 'streak', 'targetvalue' => 7, 'xpreward' => 50, 'freezereward' => 1, 'namestring' => 'mission_streak_name'],
        ['type' => 'streak', 'targetvalue' => 14, 'xpreward' => 100, 'freezereward' => 1,
            'namestring' => 'mission_streak14_name'],
        ['type' => 'streak', 'targetvalue' => 30, 'xpreward' => 200, 'freezereward' => 2,
            'namestring' => 'mission_streak30_name'],
        ['type' => 'cumulative', 'targetvalue' => 100, 'xpreward' => 20, 'freezereward' => 0,
            'namestring' => 'mission_cumulative_name'],
        ['type' => 'battle_win', 'targetvalue' => 1, 'xpreward' => 30, 'freezereward' => 0,
            'namestring' => 'mission_battle_win_name'],
    ];

    /**
     * Ensures the missions a new site starts with exist.
     *
     * Safe to call multiple times; uses namestring as idempotency key.
     *
     * @return void
     */
    public static function sync(): void {
        global $DB;
        foreach (self::MISSIONS as $def) {
            if (!$DB->record_exists('local_playergames_missions', ['namestring' => $def['namestring']])) {
                $def['icon'] = self::TYPES[$def['type']]['icon'];
                $def['enabled'] = 1;
                $DB->insert_record('local_playergames_missions', (object) $def);
            }
        }
    }

    /**
     * Loads every mission, on or off, in the order they were added (calls sync() if none exist).
     *
     * @return stdClass[] Keyed by id.
     */
    public static function get_all(): array {
        global $DB;
        $missions = $DB->get_records('local_playergames_missions', null, 'id ASC');
        if (empty($missions)) {
            self::sync();
            $missions = $DB->get_records('local_playergames_missions', null, 'id ASC');
        }
        return $missions;
    }

    /**
     * The missions that are on, in the order they were added.
     *
     * @return stdClass[] Keyed by id.
     */
    public static function get_enabled(): array {
        return array_filter(self::get_all(), static fn(stdClass $mission): bool => (bool) $mission->enabled);
    }

    /**
     * The name of a mission: the one the administrator gave, or the one it came with.
     *
     * @param stdClass $mission The mission.
     * @return string Plain text.
     */
    public static function display_name(stdClass $mission): string {
        if (trim((string) ($mission->name ?? '')) !== '') {
            return \local_playergames\local\text::plain($mission->name);
        }
        if ((string) ($mission->namestring ?? '') !== '') {
            return get_string($mission->namestring, 'local_playergames');
        }
        return get_string('mission_type_' . $mission->type, 'local_playergames');
    }

    /**
     * What a mission asks for, with its target.
     *
     * @param stdClass $mission The mission.
     * @return string
     */
    public static function description(stdClass $mission): string {
        $target = (int) $mission->targetvalue;
        $one = $target === 1 && in_array($mission->type, ['daily', 'battle_win'], true);
        return get_string('mission_desc_' . $mission->type . ($one ? '_one' : ''), 'local_playergames', $target);
    }

    /**
     * The missions somebody completed in a season, which may not change until it ends.
     *
     * @param int $seasonid The season.
     * @return array Mission ids, as keys.
     */
    public static function locked_ids(int $seasonid): array {
        global $DB;
        $ids = $DB->get_fieldset_select(
            'local_playergames_mission_progress',
            'DISTINCT missionid',
            'seasonid = :seasonid AND timefirstcompleted IS NOT NULL',
            ['seasonid' => $seasonid]
        );
        return array_fill_keys(array_map('intval', $ids), true);
    }

    /**
     * Whether a mission may not change now, because somebody completed it in the active season.
     *
     * @param int $missionid The mission.
     * @return bool
     */
    public static function is_locked(int $missionid): bool {
        $season = season_manager::get_active();
        return $season !== null && isset(self::locked_ids((int) $season->id)[$missionid]);
    }

    /**
     * Whether a mission was ever started by somebody, in which case it is switched off rather than deleted.
     *
     * The seasons count the missions their players completed, and the hub shows their progress.
     *
     * @param int $missionid The mission.
     * @return bool
     */
    public static function has_history(int $missionid): bool {
        global $DB;
        return $DB->record_exists('local_playergames_mission_progress', ['missionid' => $missionid]);
    }

    /**
     * Adds a mission, or changes one.
     *
     * A locked mission keeps its target, rewards and state: only its name changes.
     *
     * @param stdClass $data id (0 to add), type, name, targetvalue, xpreward, freezereward and enabled.
     * @return int The mission id.
     * @throws \moodle_exception When the kind is unknown or a value is out of its range.
     */
    public static function save(stdClass $data): int {
        global $DB;
        $id = (int) ($data->id ?? 0);
        $current = $id > 0 ? $DB->get_record('local_playergames_missions', ['id' => $id], '*', MUST_EXIST) : null;
        $type = $current ? $current->type : (string) ($data->type ?? '');
        if (!isset(self::TYPES[$type])) {
            throw new \moodle_exception('error_mission_type', 'local_playergames');
        }
        $name = trim(\core_text::substr(clean_param((string) ($data->name ?? ''), PARAM_TEXT), 0, 255));
        if ($current !== null && self::is_locked($id)) {
            $DB->set_field('local_playergames_missions', 'name', $name !== '' ? $name : null, ['id' => $id]);
            return $id;
        }

        foreach (self::errors($type, (int) $data->targetvalue, (int) $data->xpreward, (int) $data->freezereward) as $error) {
            throw new \moodle_exception($error, 'local_playergames');
        }
        $record = (object) [
            'type' => $type,
            'name' => $name !== '' ? $name : null,
            'targetvalue' => (int) $data->targetvalue,
            'xpreward' => (int) $data->xpreward,
            'freezereward' => (int) $data->freezereward,
            'enabled' => empty($data->enabled) ? 0 : 1,
            'icon' => self::TYPES[$type]['icon'],
        ];
        if ($current === null) {
            $record->namestring = '';
            return (int) $DB->insert_record('local_playergames_missions', $record);
        }
        $record->id = $id;
        $DB->update_record('local_playergames_missions', $record);
        return $id;
    }

    /**
     * The problems with the values of a mission, as language string keys.
     *
     * @param string $type The kind.
     * @param int $target The target.
     * @param int $xp The XP reward.
     * @param int $freezes The freeze reward.
     * @return string[] Keyed by the form field each is about.
     */
    public static function errors(string $type, int $target, int $xp, int $freezes): array {
        $errors = [];
        if ($target < self::TYPES[$type]['min'] || $target > self::TYPES[$type]['max']) {
            $errors['targetvalue'] = 'error_mission_target';
        }
        if ($xp < 0 || $xp > self::MAX_XP) {
            $errors['xpreward'] = 'error_mission_xp';
        }
        if ($freezes < 0 || $freezes > streak_manager::max_freezes()) {
            $errors['freezereward'] = 'error_mission_freezes';
        }
        if ($xp === 0 && $freezes === 0) {
            $errors['xpreward'] = 'error_mission_noreward';
        }
        return $errors;
    }

    /**
     * Switches a mission on or off, unless somebody completed it in the active season.
     *
     * @param int $missionid The mission.
     * @param bool $enabled Whether it is on.
     * @return bool Whether it changed.
     */
    public static function set_enabled(int $missionid, bool $enabled): bool {
        global $DB;
        if (self::is_locked($missionid)) {
            return false;
        }
        $DB->set_field('local_playergames_missions', 'enabled', (int) $enabled, ['id' => $missionid]);
        return true;
    }

    /**
     * Deletes a mission nobody ever started; one with a history can only be switched off.
     *
     * @param int $missionid The mission.
     * @return bool Whether it was deleted.
     */
    public static function delete(int $missionid): bool {
        global $DB;
        if (self::has_history($missionid)) {
            return false;
        }
        $DB->delete_records('local_playergames_missions', ['id' => $missionid]);
        return true;
    }

    /**
     * Updates mission progress for a user after an in-game event.
     *
     * Triggers:
     *   'game_played'   — advances daily mission
     *   'streak_updated'— checks streak mission (context: ['streak' => int])
     *   'xp_earned'     — checks cumulative mission (context: ['total_xp' => int])
     *   'battle_won'    — completes battle_win mission
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
        $types = [
            'game_played' => 'daily',
            'streak_updated' => 'streak',
            'xp_earned' => 'cumulative',
            'battle_won' => 'battle_win',
        ];
        $type = $types[$trigger] ?? null;
        foreach (self::get_enabled() as $mission) {
            if ($mission->type === $type) {
                self::advance($userid, $seasonid, $mission, $context);
            }
        }
    }

    /**
     * Moves one mission forward for the event that concerns it.
     *
     * @param int $userid The player.
     * @param int $seasonid The season.
     * @param stdClass $mission The mission.
     * @param array $context Data of the event: the streak or the XP total.
     * @return void
     */
    private static function advance(int $userid, int $seasonid, stdClass $mission, array $context): void {
        $progress = self::get_or_create_progress($userid, (int) $mission->id, $seasonid);

        if ($mission->type === 'daily') {
            if ($progress->completed && (int) $progress->timecompleted < day::today()) {
                // A mission completed on another day is over, whether or not the reset of midnight has run yet:
                // the cron runs late on many sites, and today's play must not find yesterday's mission done.
                $progress->completed     = 0;
                $progress->currentvalue  = 0;
                $progress->timecompleted = null;
            }
            if (!$progress->completed) {
                $progress->currentvalue++;
                self::advance_or_complete($userid, $seasonid, $mission, $progress);
            }
            return;
        }
        if ($progress->completed) {
            return;
        }

        if ($mission->type === 'streak' && isset($context['streak'])) {
            // The streak goes on from one season to the next, but each season gives its missions again: a player
            // who arrives with a long streak starts them from the first day of the season, like everyone else.
            $progress->currentvalue = min((int) $context['streak'], self::season_day($seasonid));
        } else if ($mission->type === 'cumulative' && isset($context['total_xp'])) {
            $progress->currentvalue = (int) $context['total_xp'];
        } else if ($mission->type === 'battle_win') {
            $progress->currentvalue++;
        } else {
            return;
        }
        self::advance_or_complete($userid, $seasonid, $mission, $progress);
    }

    /**
     * Which day of a season today is, the first being 1.
     *
     * @param int $seasonid The season.
     * @return int
     */
    private static function season_day(int $seasonid): int {
        global $DB;
        $start = $DB->get_field('local_playergames_seasons', 'startdate', ['id' => $seasonid]);
        if ($start === false) {
            return PHP_INT_MAX;
        }
        return max(1, (int) round((day::today() - day::midnight((int) $start)) / DAYSECS) + 1);
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
        $params['today'] = day::today();
        // The rows are found by the time they were completed, so that time is cleared last: once it is null the
        // condition no longer matches them.
        $where = "missionid {$insql} AND seasonid = :seasonid AND timecompleted < :today";
        $DB->set_field_select('local_playergames_mission_progress', 'currentvalue', 0, $where, $params);
        $DB->set_field_select('local_playergames_mission_progress', 'completed', 0, $where, $params);
        $DB->set_field_select('local_playergames_mission_progress', 'timecompleted', null, $where, $params);
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
            ['currentvalue' => 0, 'completed' => 0, 'timecompleted' => null, 'timefirstcompleted' => null]
        );
    }

    /**
     * Saves the progress of a mission, or completes it once it reaches the target.
     *
     * The progress is stored while it is below the target, so the hub shows how far the player is and not 0 until
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
        $progress->timefirstcompleted = $progress->timefirstcompleted ?? $progress->timecompleted;
        $DB->update_record('local_playergames_mission_progress', $progress);
        xp_manager::award_uncapped($userid, (int) $mission->xpreward, $seasonid);

        if (!CLI_SCRIPT) {
            \core\notification::success(get_string(
                'mission_completed',
                'local_playergames',
                (object) [
                    'name' => self::display_name($mission),
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
