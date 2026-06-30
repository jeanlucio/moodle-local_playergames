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
 * External function to equip or unequip an avatar.
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
use local_playergames\hub\avatar_manager;

/**
 * Equip or unequip an avatar for the current user.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class set_avatar extends external_api {
    /**
     * Parameters for execute().
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'emoji' => new external_value(PARAM_RAW_TRIMMED, 'Avatar emoji to equip, or empty to unequip'),
        ]);
    }

    /**
     * Equips the given avatar (or unequips when empty) for the current user.
     *
     * @param string $emoji Avatar emoji, or '' to unequip.
     * @return array
     */
    public static function execute(string $emoji): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), ['emoji' => $emoji]);
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/playergames:viewhub', $context);

        avatar_manager::equip((int) $USER->id, $params['emoji']);

        return ['equipped' => avatar_manager::get_equipped((int) $USER->id)];
    }

    /**
     * Return structure for execute().
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'equipped' => new external_value(PARAM_RAW, 'The currently equipped avatar emoji, or empty'),
        ]);
    }
}
