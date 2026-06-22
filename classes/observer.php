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
 * Event observer for local_playergames.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames;

use local_playergames\event\game_completed;
use local_playergames\hub\achievement_manager;
use local_playergames\hub\mission_manager;
use local_playergames\hub\streak_manager;
use local_playergames\hub\xp_manager;

/**
 * Handles events that drive the Player Hub gamification loop.
 *
 * game_completed:
 *   - Records streak activity for the day.
 *   - Updates mission progress (game_played trigger).
 *   - Checks for new achievements.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * Called when a daily game is completed.
     *
     * Advances the streak, updates missions, and checks achievements.
     * XP has already been awarded by the game POST handler before firing this event.
     *
     * @param game_completed $event
     * @return void
     */
    public static function game_completed(game_completed $event): void {
        $userid   = (int) $event->userid;
        $seasonid = isset($event->other['seasonid']) ? (int) $event->other['seasonid'] : 0;
        $gametype = $event->other['gametype'] ?? '';
        $gamedate = isset($event->other['gamedate']) ? (int) $event->other['gamedate'] : 0;

        streak_manager::record_activity($userid);

        if ($seasonid <= 0) {
            return;
        }

        mission_manager::update($userid, $seasonid, 'game_played');

        $streak = streak_manager::get_or_create($userid);
        mission_manager::update($userid, $seasonid, 'streak_updated', [
            'streak' => (int) $streak->currentstreak,
        ]);

        if ($gametype === 'battle' && !empty($event->other['victory'])) {
            mission_manager::update($userid, $seasonid, 'battle_won');
        }

        $profile = xp_manager::get_or_create_profile($userid, $seasonid);
        mission_manager::update($userid, $seasonid, 'xp_earned', [
            'total_xp' => (int) $profile->xp,
        ]);

        achievement_manager::check($userid, $seasonid, 'game_played', [
            'gamedate' => $gamedate,
            'level'    => (int) $profile->level,
            'streak'   => (int) $streak->currentstreak,
        ]);
    }
}
