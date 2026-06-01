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
 * Renderable output class for the achievements page.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\output;

use moodle_url;
use renderable;
use renderer_base;
use templatable;
use local_playergames\hub\achievement_manager;

/**
 * Prepares data for the achievements Mustache template.
 *
 * Shows all six hardcoded V1 achievements. Earned ones are highlighted;
 * not-yet-earned ones are shown in a locked style.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class achievements implements renderable, templatable {
    /** @var int Current user ID. */
    private int $userid;

    /**
     * Constructs the achievements renderable.
     *
     * @param int $userid Current user ID.
     */
    public function __construct(int $userid) {
        $this->userid = $userid;
    }

    /**
     * Exports all data needed by the achievements Mustache template.
     *
     * @param renderer_base $output Moodle renderer (unused; kept for interface).
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        global $DB;

        achievement_manager::sync();

        $allachieves = $DB->get_records('local_playergames_achievements');
        $earnedrows  = $DB->get_records(
            'local_playergames_user_achievements',
            ['userid' => $this->userid],
            '',
            'achievementid, timecreated'
        );
        $earnedmap = [];
        foreach ($earnedrows as $row) {
            $earnedmap[(int) $row->achievementid] = (int) $row->timecreated;
        }

        $items = [];
        foreach ($allachieves as $ach) {
            $earned   = isset($earnedmap[(int) $ach->id]);
            $earnedts = $earned ? userdate($earnedmap[(int) $ach->id]) : '';
            $items[]  = [
                'icon'      => $ach->icon,
                'name'      => get_string($ach->namestring, 'local_playergames'),
                'desc'      => get_string($ach->descstring, 'local_playergames'),
                'earned'    => $earned,
                'locked'    => !$earned,
                'earnedts'  => $earnedts,
            ];
        }

        usort($items, fn($a, $b) => ($b['earned'] <=> $a['earned']));

        return [
            'str_pagetitle' => get_string('achievements_pagetitle', 'local_playergames'),
            'str_huburl'    => (new moodle_url('/local/playergames/hub.php'))->out(false),
            'items'         => $items,
            'totalearned'   => count($earnedmap),
            'total'         => count($allachieves),
        ];
    }
}
