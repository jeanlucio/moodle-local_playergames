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
 * Static catalog of all Player ecosystem plugins.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\ecosystem;

/**
 * Defines the catalog of plugins that form the Player ecosystem.
 *
 * The first entry is always the hub (local_playergames) and is placed at the
 * centre of the SVG. Remaining entries are arranged in a circle around it.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class plugin_registry {
    /**
     * Returns the ordered catalog of Player ecosystem plugins.
     *
     * Each entry contains:
     *   - component    Moodle frankenstyle component name.
     *   - displayname  Short human-readable name.
     *   - abbr         2–3 char abbreviation for the SVG node.
     *   - color        Hex fill colour used when the plugin is installed.
     *   - dependencies List of component names this plugin depends on.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function get_catalog(): array {
        return [
            [
                'component'    => 'local_playergames',
                'displayname'  => 'Player Games',
                'abbr'         => 'PG',
                'color'        => '#4f46e5',
                'dependencies' => [],
            ],
            [
                'component'    => 'block_playerhud',
                'displayname'  => 'Player HUD',
                'abbr'         => 'HUD',
                'color'        => '#0891b2',
                'dependencies' => ['local_playergames'],
            ],
            [
                'component'    => 'filter_playerhud',
                'displayname'  => 'HUD Filter',
                'abbr'         => 'FIL',
                'color'        => '#0e7490',
                'dependencies' => ['local_playergames'],
            ],
            [
                'component'    => 'mod_playergroup',
                'displayname'  => 'PlayerGroup',
                'abbr'         => 'GRP',
                'color'        => '#7c3aed',
                'dependencies' => ['local_playergames'],
            ],
            [
                'component'    => 'mod_playercross',
                'displayname'  => 'PlayerCross',
                'abbr'         => 'PCx',
                'color'        => '#ca8a04',
                'dependencies' => ['local_playergames'],
            ],
            [
                'component'    => 'mod_playerwords',
                'displayname'  => 'PlayerWords',
                'abbr'         => 'PW',
                'color'        => '#16a34a',
                'dependencies' => ['local_playergames'],
            ],
            [
                'component'    => 'mod_playerpuzzle',
                'displayname'  => 'PlayerPuzzle',
                'abbr'         => 'PPz',
                'color'        => '#dc2626',
                'dependencies' => ['local_playergames'],
            ],
        ];
    }
}
