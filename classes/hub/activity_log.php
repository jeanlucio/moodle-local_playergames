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
 * Unified activity log: every season XP, learning XP, freeze and streak event.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\hub;

/**
 * Single insert point for local_playergames_activity_log, called from the
 * existing XP/streak/freeze funnel methods (xp_manager::award(),
 * learning_xp_manager::record_change(), streak_manager::log_freeze() and
 * process_breaks()) rather than at their scattered callers.
 *
 * One table, not the 3-table UNION ALL pattern block_playerhud uses for its
 * own history — PlayerGames events are already homogeneous signed-delta rows.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class activity_log {
    /** @var string A season XP gain (games, checkin or mission reward). */
    public const TYPE_SEASON_XP = 'season_xp';

    /** @var string A learning XP change mirrored from block_playerhud. */
    public const TYPE_LEARNING_XP = 'learning_xp';

    /** @var string A freeze consumable earned (currently only via missions). */
    public const TYPE_FREEZE_EARNED = 'freeze_earned';

    /** @var string A freeze consumable spent to protect a streak. */
    public const TYPE_FREEZE_USED = 'freeze_used';

    /** @var string A streak reset to zero (no freeze was available). */
    public const TYPE_STREAK_BROKEN = 'streak_broken';

    /**
     * Records one activity log row.
     *
     * @param int $userid User the event belongs to.
     * @param string $eventtype One of the TYPE_* constants.
     * @param int $xpdelta Signed XP delta; 0 for events with no XP (e.g. streak_broken).
     * @param string $source Gametype for season_xp; 'block_playerhud' for learning_xp;
     *     'mission'/'streak_break'/'cron' otherwise.
     * @param int $courseid Only meaningful for learning_xp rows; 0 otherwise.
     * @return void
     */
    public static function record(
        int $userid,
        string $eventtype,
        int $xpdelta,
        string $source,
        int $courseid = 0
    ): void {
        global $DB;

        $DB->insert_record('local_playergames_activity_log', (object) [
            'userid'      => $userid,
            'eventtype'   => $eventtype,
            'xpdelta'     => $xpdelta,
            'source'      => $source,
            'courseid'    => $courseid,
            'timecreated' => time(),
        ]);
    }
}
