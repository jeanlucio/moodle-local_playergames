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

/**
 * Handles events that drive streak and presence tracking.
 *
 * Full implementation in Phase 4:
 * - game_completed → streak_manager::record_activity()
 * - user_loggedin  → registers login presence (no XP, streak only)
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * Called when a daily game is completed.
     *
     * @param game_completed $event
     * @return void
     */
    public static function game_completed(game_completed $event): void {
        // Stub: streak_manager::record_activity() called here in Phase 4.
    }

    /**
     * Called when a user logs in to Moodle.
     *
     * @param \core\event\user_loggedin $event
     * @return void
     */
    public static function user_loggedin(\core\event\user_loggedin $event): void {
        // Stub: login presence tracking for streak calculation added in Phase 4.
    }
}
