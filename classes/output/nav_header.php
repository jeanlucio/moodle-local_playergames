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
 * Shared secondary-navigation header for the PlayerGames pages.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\output;

use context_system;
use moodle_url;
use renderable;
use renderer_base;
use templatable;
use local_playergames\local\access;
use local_playergames\local\preferences;

/**
 * Builds the role-filtered button bar rendered on every PlayerGames page.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class nav_header implements renderable, templatable {
    /** @var string Key of the active page. */
    private string $active;

    /**
     * Constructor.
     *
     * @param string $active Key of the current page (hub, achievements, history, engmeter, ecosystem).
     */
    public function __construct(string $active) {
        $this->active = $active;
    }

    /**
     * Exports the navigation items for the Mustache template.
     *
     * @param renderer_base $output Moodle renderer (unused; kept for interface).
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        global $USER;

        $context = context_system::instance();
        $gamification = has_capability('local/playergames:viewhub', $context)
            && preferences::is_gamification_enabled((int) $USER->id);
        $teaches = !empty(access::teacher_courseids((int) $USER->id));
        $staff = access::is_staff();

        $items = [];
        if ($gamification) {
            $items[] = $this->item('hub', 'fa-trophy', 'dashboard_card_hub', '/local/playergames/hub.php');
            $items[] = $this->item(
                'achievements',
                'fa-award',
                'dashboard_card_achievements',
                '/local/playergames/achievements.php'
            );
            $items[] = $this->item(
                'history',
                'fa-clock-rotate-left',
                'dashboard_card_history',
                '/local/playergames/history.php'
            );
        }
        if ($teaches) {
            $items[] = $this->item(
                'engmeter',
                'fa-chart-line',
                'dashboard_card_engmeter',
                '/local/playergames/engagement_meter.php'
            );
        }
        if ($staff) {
            $items[] = $this->item('ecosystem', 'fa-diagram-project', 'nav_ecosystem', '/local/playergames/dashboard.php');
        }

        return [
            'str_nav'  => get_string('nav_aria', 'local_playergames'),
            'items'    => $items,
            'hasitems' => !empty($items),
        ];
    }

    /**
     * Builds a single navigation item.
     *
     * @param string $key Item key, matched against the active page.
     * @param string $icon Font Awesome icon class.
     * @param string $stringkey Language string key for the label.
     * @param string $url Target URL.
     * @return array
     */
    private function item(string $key, string $icon, string $stringkey, string $url): array {
        return [
            'key'    => $key,
            'icon'   => $icon,
            'label'  => get_string($stringkey, 'local_playergames'),
            'url'    => (new moodle_url($url))->out(false),
            'active' => $key === $this->active,
        ];
    }
}
