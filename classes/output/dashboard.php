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
 * Renderable output class for the Player ecosystem dashboard.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\output;

use renderable;
use renderer_base;
use templatable;
use local_playergames\ecosystem\plugin_registry;
use local_playergames\ecosystem\plugin_status;

/**
 * Prepares all data needed by the dashboard Mustache template.
 *
 * Responsibilities:
 *   - Build the capability-filtered quick-access action links.
 *   - Build grouped, clickable plugin cards with install status.
 *   - Build the typed relation edges (drawn as connectors by dashboard.js).
 *   - Build the relation-type legend.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dashboard implements renderable, templatable {
    /** @var string[] Relation types, in legend order. */
    private const EDGE_TYPES = [
        plugin_registry::REL_REQUIRES,
        plugin_registry::REL_AI,
        plugin_registry::REL_ASSOC,
        plugin_registry::REL_PLANNED,
    ];

    /**
     * Exports data for the dashboard Mustache template.
     *
     * @param renderer_base $output Moodle renderer (unused; kept for interface).
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        [$groups, $cards, $edges] = $this->build_ecosystem();

        return [
            'str_heading_ecosystem' => get_string('dashboard_heading_ecosystem', 'local_playergames'),
            'str_legend'            => get_string('dashboard_legend_heading', 'local_playergames'),
            'groups'                => $groups,
            'cards'                 => $cards,
            'legend'                => $this->build_legend(),
            'edgesjson'             => json_encode(
                $edges,
                JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
            ),
        ];
    }

    /**
     * Builds grouped cards, a flat card list and the directed edge list.
     *
     * @return array Three-element array: [groups[], cards[], edges[]].
     */
    private function build_ecosystem(): array {
        $catalog   = plugin_registry::get_catalog();
        $statusmap = plugin_status::get_installed();

        $bycomponent = [];
        foreach ($catalog as $def) {
            $bycomponent[$def['component']] = $def;
        }

        $edges     = $this->build_edges($catalog, $bycomponent);
        $adjacency = $this->build_adjacency($edges);

        $cards     = [];
        $bygroup   = [];
        foreach ($catalog as $def) {
            $component = $def['component'];
            $status    = $statusmap[$component] ?? ['installed' => false, 'version' => ''];
            $installed = (bool) $status['installed'];

            $connections = $this->build_connection_groups($adjacency[$component] ?? [], $bycomponent);

            $card = [
                'component'         => $component,
                'displayname'       => $def['displayname'],
                'abbr'              => $def['abbr'],
                'icon'              => $def['icon'],
                'color'             => $installed ? $def['color'] : '#94a3b8',
                'isinstalled'       => $installed,
                'statuslabel'       => get_string(
                    $installed ? 'dashboard_plugin_installed' : 'dashboard_plugin_notinstalled',
                    'local_playergames'
                ),
                'statusbadge'       => $installed ? 'bg-success' : 'bg-secondary',
                'version'           => $status['version'],
                'hasversion'        => $status['version'] !== '',
                'description'       => get_string('plugin_desc_' . $component, 'local_playergames'),
                'connectiongroups'  => $connections,
                'hasconnections'    => !empty($connections),
            ];

            $cards[] = $card;
            $bygroup[$def['group']][] = $card;
        }

        $groups = [];
        foreach (plugin_registry::GROUPS as $key) {
            if (empty($bygroup[$key])) {
                continue;
            }
            $groups[] = [
                'key'   => $key,
                'label' => get_string('dashboard_group_' . $key, 'local_playergames'),
                'cards' => $bygroup[$key],
            ];
        }

        return [$groups, $cards, $edges];
    }

    /**
     * Flattens the catalog relations into a directed edge list for the JS overlay.
     *
     * @param array $catalog Plugin catalog.
     * @param array $bycomponent Catalog indexed by component name.
     * @return array<int, array{from: string, to: string, type: string}>
     */
    private function build_edges(array $catalog, array $bycomponent): array {
        $edges = [];
        foreach ($catalog as $def) {
            foreach ($def['relations'] as $relation) {
                if (!isset($bycomponent[$relation['target']])) {
                    continue;
                }
                $edges[] = [
                    'from' => $def['component'],
                    'to'   => $relation['target'],
                    'type' => $relation['type'],
                ];
            }
        }
        return $edges;
    }

    /**
     * Builds an undirected adjacency map (component => [[neighbour, type], ...]).
     *
     * @param array $edges Directed edge list.
     * @return array<string, array<int, array{0: string, 1: string}>>
     */
    private function build_adjacency(array $edges): array {
        $adjacency = [];
        foreach ($edges as $edge) {
            $adjacency[$edge['from']][] = [$edge['to'], $edge['type']];
            $adjacency[$edge['to']][]   = [$edge['from'], $edge['type']];
        }
        return $adjacency;
    }

    /**
     * Groups a card's connections by relation type for the modal listing.
     *
     * @param array $neighbours List of [component, type] pairs.
     * @param array $bycomponent Catalog indexed by component name.
     * @return array
     */
    private function build_connection_groups(array $neighbours, array $bycomponent): array {
        $bytype = [];
        $seen   = [];
        foreach ($neighbours as [$component, $type]) {
            $key = $component . '|' . $type;
            if (isset($seen[$key]) || !isset($bycomponent[$component])) {
                continue;
            }
            $seen[$key] = true;
            $bytype[$type][] = $bycomponent[$component]['displayname'];
        }

        $groups = [];
        foreach (self::EDGE_TYPES as $type) {
            if (empty($bytype[$type])) {
                continue;
            }
            $groups[] = [
                'typelabel' => get_string('dashboard_edge_' . $type, 'local_playergames'),
                'cssclass'  => 'pg-chip-' . $type,
                'names'     => implode(', ', $bytype[$type]),
            ];
        }
        return $groups;
    }

    /**
     * Builds the relation-type legend.
     *
     * @return array
     */
    private function build_legend(): array {
        $legend = [];
        foreach (self::EDGE_TYPES as $type) {
            $legend[] = [
                'cssclass' => 'pg-edge-' . $type,
                'label'    => get_string('dashboard_edge_' . $type, 'local_playergames'),
            ];
        }
        return $legend;
    }
}
