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
 * Achievement manager for local_playergames.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\hub;

use local_playergames\event\achievement_earned;
use stdClass;

/**
 * Manages the six hardcoded V1 achievements.
 *
 * Achievements are permanent (not per-season) and stored in
 * local_playergames_achievements. Once earned they never reset.
 * sync() ensures the definition rows exist; check() evaluates
 * whether a specific user has just unlocked any achievement.
 *
 * Condition types and their meanings:
 *   games_played    — total games completed across all seasons
 *   level_reached   — current season level >= conditionvalue
 *   season_champion — reached MAX_LEVEL in any season
 *   streak          — current streak >= conditionvalue
 *   all_games_day   — completed all 4 game types in one day
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class achievement_manager {
    /**
     * Hardcoded achievement definitions (V1).
     *
     * @var array<int, array<string, mixed>>
     */
    private const ACHIEVEMENTS = [
        [
            'conditiontype'  => 'games_played',
            'conditionvalue' => 1,
            'namestring'     => 'achievement_first_game_name',
            'descstring'     => 'achievement_first_game_desc',
            'icon'           => 'fa-gamepad',
        ],
        [
            'conditiontype'  => 'games_played',
            'conditionvalue' => 100,
            'namestring'     => 'achievement_100_games_name',
            'descstring'     => 'achievement_100_games_desc',
            'icon'           => 'fa-medal',
        ],
        [
            'conditiontype'  => 'level_reached',
            'conditionvalue' => 5,
            'namestring'     => 'achievement_level5_name',
            'descstring'     => 'achievement_level5_desc',
            'icon'           => 'fa-layer-group',
        ],
        [
            'conditiontype'  => 'season_champion',
            'conditionvalue' => 1,
            'namestring'     => 'achievement_champion_name',
            'descstring'     => 'achievement_champion_desc',
            'icon'           => 'fa-crown',
        ],
        [
            'conditiontype'  => 'streak',
            'conditionvalue' => 30,
            'namestring'     => 'achievement_streak30_name',
            'descstring'     => 'achievement_streak30_desc',
            'icon'           => 'fa-fire-flame-curved',
        ],
        [
            'conditiontype'  => 'all_games_day',
            'conditionvalue' => 1,
            'namestring'     => 'achievement_all_games_name',
            'descstring'     => 'achievement_all_games_desc',
            'icon'           => 'fa-bolt',
        ],
    ];

    /** @var string[] Game types that count toward the all_games_day achievement. */
    private const ALL_GAME_TYPES = ['quiz', 'guess', 'fill', 'battle'];

    /**
     * Ensures all hardcoded achievements exist in the DB.
     *
     * Safe to call multiple times; uses namestring as idempotency key.
     *
     * @return void
     */
    public static function sync(): void {
        global $DB;
        foreach (self::ACHIEVEMENTS as $def) {
            if (!$DB->record_exists('local_playergames_achievements', ['namestring' => $def['namestring']])) {
                $DB->insert_record('local_playergames_achievements', (object) $def);
            }
        }
    }

    /**
     * Checks and grants any newly earned achievements for a user.
     *
     * Should be called after significant events: game completed, XP awarded,
     * streak updated, or level reached. Already-earned achievements are skipped.
     *
     * @param int $userid
     * @param int $seasonid Current season.
     * @param string $trigger Event type: 'game_played', 'level_reached', 'streak_updated'.
     * @param array<string, mixed> $context Contextual values (level, streak, gamedate, etc.).
     * @return void
     */
    public static function check(int $userid, int $seasonid, string $trigger, array $context = []): void {
        global $DB;
        $achievements  = $DB->get_records('local_playergames_achievements');
        if (empty($achievements)) {
            self::sync();
            $achievements = $DB->get_records('local_playergames_achievements');
        }

        $earnedids = $DB->get_fieldset_select(
            'local_playergames_user_achievements',
            'achievementid',
            'userid = :uid',
            ['uid' => $userid]
        );
        $earned = array_flip($earnedids);

        foreach ($achievements as $achievement) {
            if (isset($earned[$achievement->id])) {
                continue;
            }
            if (self::evaluate($userid, $seasonid, $achievement, $trigger, $context)) {
                self::grant($userid, $achievement);
            }
        }
    }

    /**
     * Evaluates whether a single achievement condition is met.
     *
     * @param int $userid
     * @param int $seasonid
     * @param stdClass $achievement Achievement record.
     * @param string $trigger
     * @param array<string, mixed> $context
     * @return bool
     */
    private static function evaluate(
        int $userid,
        int $seasonid,
        stdClass $achievement,
        string $trigger,
        array $context
    ): bool {
        global $DB;
        $type  = $achievement->conditiontype;
        $value = (int) $achievement->conditionvalue;

        if ($type === 'games_played') {
            $count = (int) $DB->count_records('local_playergames_daily_scores', [
                'userid'    => $userid,
                'completed' => 1,
            ]);
            return $count >= $value;
        }

        if ($type === 'level_reached') {
            $level = isset($context['level']) ? (int) $context['level'] : 0;
            return $level >= $value;
        }

        if ($type === 'season_champion') {
            return isset($context['level']) && (int) $context['level'] >= xp_manager::MAX_LEVEL;
        }

        if ($type === 'streak') {
            $streak = isset($context['streak']) ? (int) $context['streak'] : 0;
            return $streak >= $value;
        }

        if ($type === 'all_games_day') {
            $gamedate = isset($context['gamedate']) ? (int) $context['gamedate'] : 0;
            if ($gamedate <= 0) {
                return false;
            }
            $played = $DB->get_fieldset_select(
                'local_playergames_daily_scores',
                'gametype',
                'userid = :uid AND gamedate = :gd AND completed = 1',
                ['uid' => $userid, 'gd' => $gamedate]
            );
            return count(array_intersect(self::ALL_GAME_TYPES, $played)) >= count(self::ALL_GAME_TYPES);
        }

        return false;
    }

    /**
     * Records an earned achievement and fires the achievement_earned event.
     *
     * @param int $userid
     * @param stdClass $achievement
     * @return void
     */
    private static function grant(int $userid, stdClass $achievement): void {
        global $DB;
        $record                = new stdClass();
        $record->userid        = $userid;
        $record->achievementid = (int) $achievement->id;
        $record->timecreated   = time();
        $rowid = $DB->insert_record('local_playergames_user_achievements', $record);

        $event = achievement_earned::create([
            'objectid' => $rowid,
            'context'  => \context_system::instance(),
            'userid'   => $userid,
            'other'    => ['achievementid' => (int) $achievement->id],
        ]);
        $event->trigger();
    }
}
