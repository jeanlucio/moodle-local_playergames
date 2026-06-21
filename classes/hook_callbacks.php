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
 * Hook listener callbacks.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames;

use core\hook\navigation\primary_extend;
use local_playergames\local\preferences;

/**
 * Hook listener callbacks for local_playergames.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * Add the PlayerGames link to the primary navigation.
     *
     * The destination depends on the user's role: staff who can view the
     * ecosystem dashboard land on the plugin map (with cards for the hub,
     * achievements, engagement meter and API keys), while students go straight
     * to the Player Hub. The dashboard map is a staff-oriented technical view,
     * so students never see it.
     *
     * @param primary_extend $hook The primary navigation hook.
     */
    public static function extend_primary_navigation(primary_extend $hook): void {
        global $USER;

        if (!isloggedin() || isguestuser()) {
            return;
        }

        $context = \context_system::instance();
        if (!has_capability('local/playergames:viewhub', $context)) {
            return;
        }

        // Respect the per-user gamification opt-out.
        if (!preferences::is_gamification_enabled($USER->id)) {
            return;
        }

        if (has_capability('local/playergames:viewdashboard', $context)) {
            $url = new \moodle_url('/local/playergames/dashboard.php');
        } else {
            $url = new \moodle_url('/local/playergames/hub.php');
        }

        $view = $hook->get_primaryview();

        $view->add(
            get_string('pluginname', 'local_playergames'),
            $url,
            \navigation_node::TYPE_ROOTNODE,
            null,
            'local_playergames_hub'
        );
    }
}
