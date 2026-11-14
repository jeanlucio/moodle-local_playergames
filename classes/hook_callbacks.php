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
     * A user the participant rules let in lands on the Player Hub, and the secondary header on each
     * page then exposes the other sections by role. Staff the rules turn away from the hub (the default
     * "students only" setting, or an opt-out of gamification) land on the ecosystem dashboard, where
     * their tools live. Anyone else gets no entry, since neither page would open for them.
     *
     * @param primary_extend $hook The primary navigation hook.
     */
    public static function extend_primary_navigation(primary_extend $hook): void {
        global $USER;

        if (!isloggedin() || isguestuser()) {
            return;
        }

        // Whether the user is staff takes part in every rule below and costs queries on every page of the
        // site, so it comes from a copy kept for a few minutes. It only decides what to show: the pages the
        // link leads to check the real thing themselves.
        $isstaff = access::is_staff_for_display((int) $USER->id);
        $context = \context_system::instance();
        $hub = has_capability('local/playergames:viewhub', $context)
            && access::is_participant((int) $USER->id, $isstaff);

        if ($hub) {
            $url = new \moodle_url('/local/playergames/hub.php');
        } else if ($isstaff) {
            $url = new \moodle_url('/local/playergames/dashboard.php');
        } else {
            return;
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
