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
use core\hook\output\before_standard_top_of_body_html_generation;
use local_playergames\hub\checkin_manager;
use local_playergames\local\access;
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
     * Everyone lands on the Player Hub, the secondary header on each page then
     * exposes the other sections by role. The only exception is staff who have
     * opted out of gamification: with no hub to show, they land on the ecosystem
     * dashboard, where their tools live.
     *
     * @param primary_extend $hook The primary navigation hook.
     */
    public static function extend_primary_navigation(primary_extend $hook): void {
        global $USER;

        if (!isloggedin() || isguestuser()) {
            return;
        }

        $context = \context_system::instance();
        $hasgamification = has_capability('local/playergames:viewhub', $context)
            && preferences::is_gamification_enabled((int) $USER->id);
        $isstaff = access::is_staff();

        // Show the entry only when the user has at least one PlayerGames page.
        if (!$hasgamification && !$isstaff) {
            return;
        }

        // Players land on the hub. Staff who opted out of gamification land on
        // the ecosystem dashboard instead, where their tools live.
        $url = $hasgamification
            ? new \moodle_url('/local/playergames/hub.php')
            : new \moodle_url('/local/playergames/dashboard.php');

        $view = $hook->get_primaryview();

        $view->add(
            get_string('pluginname', 'local_playergames'),
            $url,
            \navigation_node::TYPE_ROOTNODE,
            null,
            'local_playergames_hub'
        );
    }

    /**
     * Records the daily check-in on the first page the user opens each day.
     *
     * Runs on every standard web page (not AJAX/CLI/web services). A per-day
     * session flag short-circuits all later page loads so the real work happens
     * at most once per session per day; the daily_scores row is the cross-session
     * guard.
     *
     * @param before_standard_top_of_body_html_generation $hook The output hook.
     */
    public static function daily_checkin(before_standard_top_of_body_html_generation $hook): void {
        global $USER, $SESSION;

        if (!isloggedin() || isguestuser()) {
            return;
        }

        $flag = 'playergames_checkin_' . date('Ymd');
        if (!empty($SESSION->$flag)) {
            return;
        }
        $SESSION->$flag = true;

        if (checkin_manager::is_participant((int) $USER->id)) {
            checkin_manager::record((int) $USER->id);
        }
    }
}
