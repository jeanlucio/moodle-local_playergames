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
 * Whether a user has accepted the AI usage policy of the site.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\local;

/**
 * Reads the answer of a user to the AI usage policy of the site, which the core keeps.
 *
 * The topic and the reference text of the AI tab go to the external provider of core_ai, so the policy is asked
 * for before the first use, as the AI placements of the core do: the page shows the dialogue of the core, and the
 * generator refuses to reach core_ai without the answer.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ai_policy {
    /**
     * Tells whether a user has accepted the AI usage policy of the site.
     *
     * A Moodle without that policy has nothing to accept.
     *
     * @param int $userid The user.
     * @return bool
     */
    public static function agreed(int $userid): bool {
        if (!class_exists(\core_ai\manager::class) || !method_exists(\core_ai\manager::class, 'get_user_policy_status')) {
            return true;
        }
        return (bool) \core_ai\manager::get_user_policy_status($userid);
    }
}
