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
 *   - ai        Optional AI dependency: pulls from the PlayerGames AI hub when
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

    /** @var string Optional AI dependency on the PlayerGames hub (fallback core_ai). */
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
     * Each entry contains:
     *   - component    Moodle frankenstyle component name.
     *   - displayname  Short human-readable name.
     *   - abbr         2–4 char abbreviation for the card badge.
     *   - color        Hex accent colour used when the plugin is installed.
     *   - group        One of self::GROUPS.
     *   - relations    List of ['target' => component, 'type' => REL_*].
     *
     * @return array<int, array<string, mixed>>
     */
    public static function get_catalog(): array {
        return [
            // Core hub.
            [
                'component'   => 'local_playergames',
                'displayname' => 'PlayerGames',
                'abbr'        => 'PG',
                'color'       => '#4f46e5',
                'icon'        => 'fa-gamepad',
                'group'       => 'core',
                'relations'   => [],
            ],

            // Player activities.
            [
                'component'   => 'mod_playergroup',
                'displayname' => 'PlayerGroup',
                'abbr'        => 'GRP',
                'color'       => '#7c3aed',
                'icon'        => 'fa-users',
                'group'       => 'activity',
                'relations'   => [],
            ],
            [
                'component'   => 'mod_playerwords',
                'displayname' => 'PlayerWords',
                'abbr'        => 'PW',
                'color'       => '#16a34a',
                'icon'        => 'fa-font',
                'group'       => 'activity',
                'relations'   => [
                    ['target' => 'local_playergames', 'type' => self::REL_AI],
                    ['target' => 'block_playerhud', 'type' => self::REL_ASSOC],
                ],
            ],
            [
                'component'   => 'mod_playerpuzzle',
                'displayname' => 'PlayerPuzzle',
                'abbr'        => 'PPz',
                'color'       => '#dc2626',
                'icon'        => 'fa-puzzle-piece',
                'group'       => 'activity',
                'relations'   => [
                    ['target' => 'local_playergames', 'type' => self::REL_AI],
                ],
            ],
            [
                'component'   => 'mod_playerraid',
                'displayname' => 'PlayerRaid',
                'abbr'        => 'RAID',
                'color'       => '#ea580c',
                'icon'        => 'fa-dragon',
                'group'       => 'activity',
                'relations'   => [
                    ['target' => 'local_playergames', 'type' => self::REL_PLANNED],
                ],
            ],
            [
                'component'   => 'mod_playerland',
                'displayname' => 'PlayerLand',
                'abbr'        => 'LAND',
                'color'       => '#ca8a04',
                'icon'        => 'fa-mountain',
                'group'       => 'activity',
                'relations'   => [
                    ['target' => 'local_playergames', 'type' => self::REL_PLANNED],
                ],
            ],

            // Interface / HUD.
            [
                'component'   => 'block_playerhud',
                'displayname' => 'Player HUD',
                'abbr'        => 'HUD',
                'color'       => '#0891b2',
                'icon'        => 'fa-gauge',
                'group'       => 'interface',
                'relations'   => [
                    ['target' => 'local_playergames', 'type' => self::REL_AI],
                    ['target' => 'mod_playergroup', 'type' => self::REL_ASSOC],
                    ['target' => 'local_latepenalty', 'type' => self::REL_ASSOC],
                ],
            ],
            [
                'component'   => 'filter_playerhud',
                'displayname' => 'HUD Filter',
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
                'displayname' => 'HUD Availability',
                'abbr'        => 'AVL',
                'color'       => '#0d9488',
                'icon'        => 'fa-lock',
                'group'       => 'interface',
                'relations'   => [
                    ['target' => 'block_playerhud', 'type' => self::REL_REQUIRES],
                ],
            ],

            // Plugins that integrate with the ecosystem.
            [
                'component'   => 'report_unlocker',
                'displayname' => 'Report Unlocker',
                'abbr'        => 'UNL',
                'color'       => '#2563eb',
                'icon'        => 'fa-unlock',
                'group'       => 'integration',
                'relations'   => [
                    ['target' => 'local_playergames', 'type' => self::REL_AI],
                    ['target' => 'block_playerhud', 'type' => self::REL_ASSOC],
                    ['target' => 'availability_playerhud', 'type' => self::REL_ASSOC],
                ],
            ],
            [
                'component'   => 'local_studiolms',
                'displayname' => 'StudioLMS',
                'abbr'        => 'STU',
                'color'       => '#9333ea',
                'icon'        => 'fa-wand-magic-sparkles',
                'group'       => 'integration',
                'relations'   => [
                    ['target' => 'local_playergames', 'type' => self::REL_AI],
                    ['target' => 'block_playerhud', 'type' => self::REL_ASSOC],
                    ['target' => 'tiny_studiolms', 'type' => self::REL_ASSOC],
                ],
            ],
            [
                'component'   => 'tiny_studiolms',
                'displayname' => 'StudioLMS (Tiny)',
                'abbr'        => 'TIN',
                'color'       => '#a855f7',
                'icon'        => 'fa-pen-nib',
                'group'       => 'integration',
                'relations'   => [
                    ['target' => 'local_playergames', 'type' => self::REL_AI],
                ],
            ],
            [
                'component'   => 'local_latepenalty',
                'displayname' => 'Late Penalty',
                'abbr'        => 'LATE',
                'color'       => '#b91c1c',
                'icon'        => 'fa-clock',
                'group'       => 'integration',
                'relations'   => [
                    ['target' => 'mod_playergroup', 'type' => self::REL_ASSOC],
                ],
            ],
            [
                'component'   => 'local_virtuallab',
                'displayname' => 'Virtual Lab',
                'abbr'        => 'LAB',
                'color'       => '#0284c7',
                'icon'        => 'fa-flask',
                'group'       => 'integration',
                'relations'   => [
                    ['target' => 'block_teacher_checklist', 'type' => self::REL_ASSOC],
                ],
            ],

            // Standalone plugins (no ecosystem code links yet).
            [
                'component'   => 'block_teacher_checklist',
                'displayname' => 'Teacher Checklist',
                'abbr'        => 'CHK',
                'color'       => '#059669',
                'icon'        => 'fa-list-check',
                'group'       => 'standalone',
                'relations'   => [],
            ],
            [
                'component'   => 'local_resourcestats',
                'displayname' => 'Resource Stats',
                'abbr'        => 'RST',
                'color'       => '#475569',
                'icon'        => 'fa-chart-column',
                'group'       => 'standalone',
                'relations'   => [],
            ],
            [
                'component'   => 'local_pluginsview',
                'displayname' => 'Plugins View',
                'abbr'        => 'PV',
                'color'       => '#64748b',
                'icon'        => 'fa-plug',
                'group'       => 'standalone',
                'relations'   => [],
            ],
            [
                'component'   => 'mod_quickpoll',
                'displayname' => 'Quick Poll',
                'abbr'        => 'QP',
                'color'       => '#db2777',
                'icon'        => 'fa-square-poll-vertical',
                'group'       => 'standalone',
                'relations'   => [],
            ],
            [
                'component'   => 'mod_reflect',
                'displayname' => 'Reflect',
                'abbr'        => 'REF',
                'color'       => '#7c2d12',
                'icon'        => 'fa-book',
                'group'       => 'standalone',
                'relations'   => [],
            ],
            [
                'component'   => 'theme_lore',
                'displayname' => 'Lore Theme',
                'abbr'        => 'LORE',
                'color'       => '#334155',
                'icon'        => 'fa-palette',
                'group'       => 'standalone',
                'relations'   => [],
            ],
        ];
    }
}
