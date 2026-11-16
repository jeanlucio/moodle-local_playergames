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
 * The hall of fame: the first places of every closed season.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\output;

use local_playergames\hub\season_summary;
use local_playergames\local\text;
use renderable;
use renderer_base;
use templatable;

/**
 * Lists, season by season, who finished among the first places of the students and of the staff.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hall_of_fame implements renderable, templatable {
    /** @var int User looking at the page, whose own row is marked. */
    private int $viewerid;

    /**
     * Constructs the renderable.
     *
     * @param int $viewerid User looking at the page.
     */
    public function __construct(int $viewerid) {
        $this->viewerid = $viewerid;
    }

    /**
     * Exports the hall for the Mustache template.
     *
     * @param renderer_base $output Moodle renderer.
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $seasons = [];
        foreach (season_summary::get_hall() as $season) {
            $students = $this->export_rows($season['students']);
            $staff    = $this->export_rows($season['staff']);
            if (empty($students) && empty($staff)) {
                continue;
            }
            $seasons[] = [
                'name'        => text::plain($season['name']),
                'students'    => $students,
                'hasstudents' => !empty($students),
                'staff'       => $staff,
                'hasstaff'    => !empty($staff),
                'bothgroups'  => !empty($students) && !empty($staff),
            ];
        }

        return [
            'str_intro'        => get_string('hall_intro', 'local_playergames'),
            'str_empty'        => get_string('hall_empty', 'local_playergames'),
            'str_students'     => get_string('hall_group_students', 'local_playergames'),
            'str_staff'        => get_string('hall_group_staff', 'local_playergames'),
            'str_rank_pos'     => get_string('hub_rank_pos', 'local_playergames'),
            'str_rank_player'  => get_string('hub_rank_player', 'local_playergames'),
            'str_rank_xp'      => get_string('hub_rank_xp', 'local_playergames'),
            'str_rank_level'   => get_string('hub_rank_level', 'local_playergames'),
            'str_you'          => get_string('hall_you', 'local_playergames'),
            'str_pagetitle'    => get_string('hall_pagetitle', 'local_playergames'),
            'seasons'          => $seasons,
            'hasseasons'       => !empty($seasons),
        ];
    }

    /**
     * Turns the rows of one group into template rows.
     *
     * @param \stdClass[] $rows Rows of season_summary::get_hall().
     * @return array
     */
    private function export_rows(array $rows): array {
        $exported = [];
        foreach ($rows as $row) {
            $exported[] = [
                'position' => $row->position,
                'name'     => $row->name,
                'xp'       => $row->xp,
                'level'    => $row->level,
                'isself'   => $row->userid === $this->viewerid,
            ];
        }
        return $exported;
    }
}
