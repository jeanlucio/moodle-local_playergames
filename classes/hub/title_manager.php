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
 * Level-to-title mapping for local_playergames.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\hub;

/**
 * Maps player levels to display titles using lang strings.
 *
 * Titles are shown on user profiles and in forum posts (Phase 6).
 * Each level maps to a lang string key of the form level_title_{n}.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class title_manager {
    /** @var int Highest level with a defined title (mirrors xp_manager::MAX_LEVEL). */
    const MAX_LEVEL = 20;

    /**
     * Returns the lang string key for a given level.
     *
     * @param int $level Player level (1–MAX_LEVEL).
     * @return string Lang string key, e.g. 'level_title_5'.
     */
    public static function get_string_key(int $level): string {
        $clamped = max(1, min($level, self::MAX_LEVEL));
        return 'level_title_' . $clamped;
    }

    /**
     * Returns the translated title for a given level.
     *
     * @param int $level Player level (1–MAX_LEVEL).
     * @return string Translated title string.
     */
    public static function get_title(int $level): string {
        return get_string(self::get_string_key($level), 'local_playergames');
    }
}
