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
 * Static catalog of all plugins authored for this ecosystem.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\ecosystem;

/**
 * Defines the catalog of plugins and the typed relations between them.
 *
 * The map groups plugins by family and connects them with four relation
 * types, declared once on the source side (the plugin whose code references
 * the other):
 *   - requires  Hard Moodle dependency ($plugin->dependencies).
 *   - ai        Optional AI dependency: pulls from the AI hub (local_aihub) when
 *               installed, otherwise falls back to Moodle core_ai.
 *   - assoc     Conditional functional integration ("when X is installed, do Y").
 *   - planned   A relation that is intended but not yet implemented in code.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class plugin_registry {
    /** @var string Hard Moodle dependency. */
    public const REL_REQUIRES = 'requires';

    /** @var string Optional AI dependency on the AI hub, local_aihub (fallback core_ai). */
    public const REL_AI = 'ai';

    /** @var string Conditional functional integration. */
    public const REL_ASSOC = 'assoc';

    /** @var string Intended relation not yet implemented in code. */
    public const REL_PLANNED = 'planned';

    /** @var string[] Ordered group keys used for layout columns. */
    public const GROUPS = ['interface', 'activity', 'core', 'integration', 'standalone'];

    /**
     * Returns the catalog of ecosystem plugins.
     *
     * Each entry is the one of catalog_entries() plus 'displayname', the short name in the language
     * of the user, read from the plugin_name_<component> strings.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function get_catalog(): array {
        return array_map(
            fn(array $entry): array => $entry + [
                'displayname' => get_string('plugin_name_' . $entry['component'], 'local_playergames'),
            ],
            self::catalog_entries()
        );
    }

    /**
     * Returns the catalog entries, without the display name.
     *
     * Each entry contains:
     *   - component    Moodle frankenstyle component name.
     *   - abbr         2–4 char abbreviation for the card badge.
     *   - color        Hex accent colour used when the plugin is installed.
     *   - group        One of self::GROUPS.
     *   - relations    List of ['target' => component, 'type' => REL_*].
     *
     * @return array<int, array<string, mixed>>
     */
    private static function catalog_entries(): array {
        return [
            // Core hub.
            [
                'component'   => 'local_playergames',
                'abbr'        => 'PG',
                'color'       => '#4f46e5',
                'icon'        => 'fa-gamepad',
                'group'       => 'core',
                'relations'   => [
                    ['target' => 'local_aihub', 'type' => self::REL_AI],
                    // Mirrors the course XP of the HUD into the learning XP pool.
                    ['target' => 'block_playerhud', 'type' => self::REL_ASSOC],
                    // The engagement meter recognises the courses that use them.
                    ['target' => 'mod_playergroup', 'type' => self::REL_ASSOC],
                    ['target' => 'mod_playerwords', 'type' => self::REL_ASSOC],
                    ['target' => 'mod_playerpuzzle', 'type' => self::REL_ASSOC],
                ],
            ],

            [
                'component'   => 'local_aihub',
                'abbr'        => 'AI',
                'color'       => '#0f766e',
                'icon'        => 'fa-robot',
                'group'       => 'core',
                'relations'   => [],
            ],
            [
                'component'   => 'block_playergames',
                'abbr'        => 'PGB',
                'color'       => '#6366f1',
                'icon'        => 'fa-trophy',
                'group'       => 'interface',
                'relations'   => [
                    ['target' => 'local_playergames', 'type' => self::REL_REQUIRES],
                ],
            ],

            // Player activities.
            [
                'component'   => 'mod_playergroup',
                'abbr'        => 'GRP',
                'color'       => '#7c3aed',
                'icon'        => 'fa-users',
                'group'       => 'activity',
                'relations'   => [],
            ],
            [
                'component'   => 'mod_playerwords',
                'abbr'        => 'PW',
                'color'       => '#16a34a',
                'icon'        => 'fa-font',
                'group'       => 'activity',
                'relations'   => [
                    ['target' => 'local_aihub', 'type' => self::REL_AI],
                    ['target' => 'block_playerhud', 'type' => self::REL_ASSOC],
                ],
            ],
            [
                'component'   => 'mod_playercross',
                'abbr'        => 'PCr',
                'color'       => '#0369a1',
                'icon'        => 'fa-table-cells',
                'group'       => 'activity',
                'relations'   => [
                    ['target' => 'local_aihub', 'type' => self::REL_AI],
                    ['target' => 'block_playerhud', 'type' => self::REL_ASSOC],
                ],
            ],
            [
                'component'   => 'mod_playerpuzzle',
                'abbr'        => 'PPz',
                'color'       => '#dc2626',
                'icon'        => 'fa-puzzle-piece',
                'group'       => 'activity',
                'relations'   => [
                    ['target' => 'local_aihub', 'type' => self::REL_AI],
                    ['target' => 'block_playerhud', 'type' => self::REL_ASSOC],
                ],
            ],

            // Interface / HUD.
            [
                'component'   => 'block_playerhud',
                'abbr'        => 'HUD',
                'color'       => '#0891b2',
                'icon'        => 'fa-gauge',
                'group'       => 'interface',
                'relations'   => [
                    ['target' => 'local_aihub', 'type' => self::REL_AI],
                    ['target' => 'mod_playergroup', 'type' => self::REL_ASSOC],
                    ['target' => 'local_latepenalty', 'type' => self::REL_ASSOC],
                ],
            ],
            [
                'component'   => 'filter_playerhud',
                'abbr'        => 'FIL',
                'color'       => '#0e7490',
                'icon'        => 'fa-filter',
                'group'       => 'interface',
                'relations'   => [
                    ['target' => 'block_playerhud', 'type' => self::REL_REQUIRES],
                    ['target' => 'mod_playergroup', 'type' => self::REL_ASSOC],
                ],
            ],
            [
                'component'   => 'availability_playerhud',
                'abbr'        => 'AVL',
                'color'       => '#0d9488',
                'icon'        => 'fa-lock',
                'group'       => 'interface',
                'relations'   => [
                    ['target' => 'block_playerhud', 'type' => self::REL_REQUIRES],
                ],
            ],

            // Plugins outside the Player family with at least one relation on the map, in either direction.
            [
                'component'   => 'report_unlocker',
                'abbr'        => 'UNL',
                'color'       => '#2563eb',
                'icon'        => 'fa-unlock',
                'group'       => 'integration',
                'relations'   => [
                    ['target' => 'local_aihub', 'type' => self::REL_AI],
                    ['target' => 'block_playerhud', 'type' => self::REL_ASSOC],
                    ['target' => 'availability_playerhud', 'type' => self::REL_ASSOC],
                ],
            ],
            [
                'component'   => 'local_studiolms',
                'abbr'        => 'STU',
                'color'       => '#9333ea',
                'icon'        => 'fa-wand-magic-sparkles',
                'group'       => 'integration',
                'relations'   => [
                    ['target' => 'local_aihub', 'type' => self::REL_AI],
                    ['target' => 'block_playerhud', 'type' => self::REL_ASSOC],
                    ['target' => 'tiny_studiolms', 'type' => self::REL_ASSOC],
                    ['target' => 'filter_studiolms', 'type' => self::REL_ASSOC],
                ],
            ],
            [
                'component'   => 'tiny_studiolms',
                'abbr'        => 'TIN',
                'color'       => '#a855f7',
                'icon'        => 'fa-pen-nib',
                'group'       => 'integration',
                'relations'   => [
                    ['target' => 'local_aihub', 'type' => self::REL_AI],
                    ['target' => 'filter_studiolms', 'type' => self::REL_ASSOC],
                ],
            ],
            [
                'component'   => 'filter_studiolms',
                'abbr'        => 'FSL',
                'color'       => '#c026d3',
                'icon'        => 'fa-puzzle-piece',
                'group'       => 'integration',
                'relations'   => [],
            ],
            [
                'component'   => 'local_latepenalty',
                'abbr'        => 'LATE',
                'color'       => '#b91c1c',
                'icon'        => 'fa-clock',
                'group'       => 'integration',
                'relations'   => [],
            ],
            [
                'component'   => 'local_virtuallab',
                'abbr'        => 'LAB',
                'color'       => '#0284c7',
                'icon'        => 'fa-flask',
                'group'       => 'integration',
                'relations'   => [
                    ['target' => 'block_teacher_checklist', 'type' => self::REL_ASSOC],
                ],
            ],
            [
                'component'   => 'block_teacher_checklist',
                'abbr'        => 'CHK',
                'color'       => '#059669',
                'icon'        => 'fa-list-check',
                'group'       => 'integration',
                'relations'   => [],
            ],

            // Plugins with no relation on the map.
            [
                'component'   => 'local_resourcestats',
                'abbr'        => 'RST',
                'color'       => '#475569',
                'icon'        => 'fa-chart-column',
                'group'       => 'standalone',
                'relations'   => [],
            ],
        ];
    }
}
