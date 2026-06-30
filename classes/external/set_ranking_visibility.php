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
 * External function to set the current user's ranking visibility.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_playergames\hub\season_manager;
use local_playergames\hub\xp_manager;

/**
 * Set whether the current user appears in the ranking.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class set_ranking_visibility extends external_api {
    /**
     * Parameters for execute().
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'show' => new external_value(PARAM_INT, '1 to appear in the ranking, 0 to hide'),
        ]);
    }

    /**
     * Sets the current user's ranking visibility for the active season.
     *
     * @param int $show 1 to appear, 0 to hide.
     * @return array
     */
    public static function execute(int $show): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), ['show' => $show]);
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/playergames:viewhub', $context);

        $visible = (bool) $params['show'];
        $season  = season_manager::get_active();
        if ($season) {
            xp_manager::set_ranking_visibility((int) $USER->id, (int) $season->id, $visible);
        }

        return ['showinranking' => ($season && $visible) ? 1 : 0];
    }

    /**
     * Return structure for execute().
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'showinranking' => new external_value(PARAM_INT, 'The applied visibility (1 or 0)'),
        ]);
    }
}
