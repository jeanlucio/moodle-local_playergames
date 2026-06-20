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

/**
 * Hook listener callbacks for local_playergames.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * Add Player Hub and Achievements links to the primary navigation.
     *
     * Both pages are end-user facing (students and teachers) but were only
     * reachable through the site administration tree, which those users cannot
     * access. This surfaces them in the top primary navigation instead.
     *
     * @param primary_extend $hook The primary navigation hook.
     */
    public static function extend_primary_navigation(primary_extend $hook): void {
        if (!isloggedin() || isguestuser()) {
            return;
        }

        $context = \context_system::instance();
        if (!has_capability('local/playergames:viewhub', $context)) {
            return;
        }

        $view = $hook->get_primaryview();

        $view->add(
            get_string('hub_pagetitle', 'local_playergames'),
            new \moodle_url('/local/playergames/hub.php'),
            \navigation_node::TYPE_ROOTNODE,
            null,
            'local_playergames_hub'
        );

        $view->add(
            get_string('achievements_pagetitle', 'local_playergames'),
            new \moodle_url('/local/playergames/achievements.php'),
            \navigation_node::TYPE_ROOTNODE,
            null,
            'local_playergames_achievements'
        );
    }
}
