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
 * Renderable for the Administration tab.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\output;

use local_playergames\local\admin_cards;
use renderable;
use renderer_base;
use templatable;

/**
 * The Administration tab: the cards of what the user can set up.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class administration implements renderable, templatable {
    /** @var int The user the tab is built for. */
    private int $userid;

    /**
     * Constructor.
     *
     * @param int $userid The user the tab is built for.
     */
    public function __construct(int $userid) {
        $this->userid = $userid;
    }

    /**
     * Exports the data for the administration template.
     *
     * @param renderer_base $output The renderer.
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        return [
            'str_title' => get_string('nav_admin', 'local_playergames'),
            'str_intro' => get_string('admin_intro', 'local_playergames'),
            'groups'    => admin_cards::for_user($this->userid),
        ];
    }
}
