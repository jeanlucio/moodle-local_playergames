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
 * The user's own results in past seasons.
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
 * Lists the saved final result of each closed season the user played.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class past_seasons implements renderable, templatable {
    /** @var int User whose results are listed. */
    private int $userid;

    /**
     * Constructs the renderable.
     *
     * @param int $userid User whose results are listed.
     */
    public function __construct(int $userid) {
        $this->userid = $userid;
    }

    /**
     * Exports the season results for the Mustache template.
     *
     * @param renderer_base $output Moodle renderer.
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $seasons = [];
        foreach (season_summary::get_for_user($this->userid) as $row) {
            $ranked = $row->finalrank !== null;
            $seasons[] = [
                'name'     => text::plain($row->seasonname),
                'level'    => (int) $row->level,
                'xp'       => (int) $row->xp,
                'missions' => (int) $row->missionsdone,
                'ranked'   => $ranked,
                'rank'     => $ranked
                    ? get_string('past_seasons_rank', 'local_playergames', (object) [
                        'rank'  => (int) $row->finalrank,
                        'total' => (int) $row->rankedof,
                    ])
                    : get_string('past_seasons_notranked', 'local_playergames'),
            ];
        }

        return [
            'str_heading'      => get_string('past_seasons_heading', 'local_playergames'),
            'str_col_season'   => get_string('past_seasons_col_season', 'local_playergames'),
            'str_col_xp'       => get_string('past_seasons_col_xp', 'local_playergames'),
            'str_col_level'    => get_string('past_seasons_col_level', 'local_playergames'),
            'str_col_missions' => get_string('past_seasons_col_missions', 'local_playergames'),
            'str_col_rank'     => get_string('past_seasons_col_rank', 'local_playergames'),
            'seasons'          => $seasons,
            'hasseasons'       => !empty($seasons),
        ];
    }
}
