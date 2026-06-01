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

use context_system;
use moodle_url;
use renderable;
use renderer_base;
use templatable;
use local_playergames\ecosystem\plugin_registry;
use local_playergames\ecosystem\plugin_status;

/**
 * Prepares all data needed by the dashboard Mustache template.
 *
 * Responsibilities:
 *   - Compute SVG node positions (radial layout, 6 peripheral plugins).
 *   - Compute SVG edge endpoints (clipped to circle edges, with arrowhead clearance).
 *   - Build capability-filtered admin navigation cards.
 *   - Build per-plugin modal content (status, version, action links).
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dashboard implements renderable, templatable {
    /** @var int SVG hub node centre X. */
    private const HUB_CX = 350;

    /** @var int SVG hub node centre Y. */
    private const HUB_CY = 240;

    /** @var int Radial distance from hub centre to peripheral node centres. */
    private const PERIPHERAL_RADIUS = 155;

    /** @var int Radius of every SVG node circle. */
    private const NODE_RADIUS = 44;

    /** @var int Extra gap added to node radius when calculating arrowhead clearance. */
    private const ARROW_GAP = 10;

    /** @var string Hex fill and stroke colour for not-installed plugin nodes. */
    private const COLOR_MISSING = '#94a3b8';

    /**
     * Exports data for the dashboard Mustache template.
     *
     * @param renderer_base $output Moodle renderer (unused; kept for interface).
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $context   = context_system::instance();
        $catalog   = plugin_registry::get_catalog();
        $statusmap = plugin_status::get_installed();

        [$svgnodes, $svgedges] = $this->build_svg($catalog, $statusmap, $context);
        $navcards = $this->build_nav_cards($context);

        return [
            'str_heading_nav'       => get_string('dashboard_heading_nav', 'local_playergames'),
            'str_heading_ecosystem' => get_string('dashboard_heading_ecosystem', 'local_playergames'),
            'navcards'              => $navcards,
            'hasnavcards'           => !empty($navcards),
            'svgviewbox'            => '0 0 700 500',
            'svgnodes'              => $svgnodes,
            'svgedges'              => $svgedges,
        ];
    }

    /**
     * Calculates SVG node and edge data for all plugins.
     *
     * @param array $catalog Plugin definitions from the plugin registry.
     * @param array $statusmap Installation status keyed by component name.
     * @param context_system $context System context for capability checks.
     * @return array Two-element array: [nodes[], edges[]] for the SVG template.
     */
    private function build_svg(array $catalog, array $statusmap, context_system $context): array {
        $hubcx     = self::HUB_CX;
        $hubcy     = self::HUB_CY;
        $r         = self::NODE_RADIUS;
        $arrowend  = $r + self::ARROW_GAP;
        $nodes     = [];
        $edges     = [];

        // Hub — first catalog entry, always installed.
        $hubdef  = $catalog[0];
        $nodes[] = $this->build_node(
            $hubdef,
            $hubcx,
            $hubcy,
            true,
            true,
            '',
            $this->build_hub_actions($context)
        );

        // Peripheral plugins arranged in a circle.
        $peripherals  = array_slice($catalog, 1);
        $count        = count($peripherals);
        $angleoffset  = -M_PI / 2; // Start at the top of the circle.

        foreach ($peripherals as $i => $def) {
            $angle     = $angleoffset + ($i * 2 * M_PI / $count);
            $nx        = (int) round($hubcx + self::PERIPHERAL_RADIUS * cos($angle));
            $ny        = (int) round($hubcy + self::PERIPHERAL_RADIUS * sin($angle));
            $status    = $statusmap[$def['component']] ?? ['installed' => false, 'version' => ''];
            $installed = $status['installed'];
            $version   = $status['version'];

            $nodes[] = $this->build_node($def, $nx, $ny, $installed, false, $version, []);

            // Edge from peripheral to hub, clipped to circle edges.
            $dx   = $hubcx - $nx;
            $dy   = $hubcy - $ny;
            $dist = sqrt($dx * $dx + $dy * $dy);

            if ($dist > 1) {
                $edges[] = [
                    'x1'        => (int) round($nx + ($dx / $dist) * $r),
                    'y1'        => (int) round($ny + ($dy / $dist) * $r),
                    'x2'        => (int) round($hubcx - ($dx / $dist) * $arrowend),
                    'y2'        => (int) round($hubcy - ($dy / $dist) * $arrowend),
                    'cssclass'  => $installed ? 'pg-edge-installed' : 'pg-edge-missing',
                    'strokedash' => $installed ? '' : '7 4',
                ];
            }
        }

        return [$nodes, $edges];
    }

    /**
     * Builds the data array for a single SVG node.
     *
     * @param array $def Plugin registry definition.
     * @param int $cx Node centre X in SVG coordinates.
     * @param int $cy Node centre Y in SVG coordinates.
     * @param bool $installed Whether the plugin is installed.
     * @param bool $ishub Whether this is the centre hub node.
     * @param string $version Release version string, or empty.
     * @param array $actions Capability-filtered action links.
     * @return array
     */
    private function build_node(
        array $def,
        int $cx,
        int $cy,
        bool $installed,
        bool $ishub,
        string $version,
        array $actions
    ): array {
        $component   = $def['component'];
        $fill        = $installed ? $def['color'] : self::COLOR_MISSING;
        $cssparts    = ['pg-ecosystem-node'];
        $cssparts[]  = $installed ? 'pg-node-installed' : 'pg-node-missing';
        if ($ishub) {
            $cssparts[] = 'pg-node-hub';
        }
        $statuslabel = get_string(
            $installed ? 'dashboard_plugin_installed' : 'dashboard_plugin_notinstalled',
            'local_playergames'
        );

        return [
            'component'   => $component,
            'displayname' => $def['displayname'],
            'abbr'        => $def['abbr'],
            'cx'          => $cx,
            'cy'          => $cy,
            'labelcy'     => $cy + self::NODE_RADIUS + 18,
            'r'           => self::NODE_RADIUS,
            'ishub'       => $ishub,
            'fill'        => $fill,
            'cssclass'    => implode(' ', $cssparts),
            'installed'   => $installed ? '1' : '0',
            'isinstalled' => $installed,
            'description' => get_string('plugin_desc_' . $component, 'local_playergames'),
            'version'     => $version,
            'hasversion'  => $version !== '',
            'statuslabel' => $statuslabel,
            'statusbadge' => $installed ? 'bg-success' : 'bg-secondary',
            'hasactions'  => !empty($actions),
            'actions'     => $actions,
        ];
    }

    /**
     * Returns capability-filtered action links shown in the hub node modal.
     *
     * @param context_system $context System context for capability checks.
     * @return array
     */
    private function build_hub_actions(context_system $context): array {
        $comingsoon = get_string('dashboard_card_comingsoon', 'local_playergames');
        $actions    = [];

        if (has_capability('local/playergames:managecartridges', $context)) {
            $actions[] = [
                'icon'       => 'fa-box-archive',
                'label'      => get_string('dashboard_card_cartridges', 'local_playergames'),
                'url'        => (new moodle_url('/local/playergames/cartridge.php'))->out(false),
                'disabled'   => false,
                'comingsoon' => false,
            ];
        }

        if (has_capability('local/playergames:viewhub', $context)) {
            $actions[] = [
                'icon'       => 'fa-trophy',
                'label'      => get_string('dashboard_card_hub', 'local_playergames'),
                'url'        => '#',
                'disabled'   => true,
                'comingsoon' => $comingsoon,
            ];
        }

        if (has_capability('local/playergames:viewengagementmeter', $context)) {
            $actions[] = [
                'icon'       => 'fa-chart-line',
                'label'      => get_string('dashboard_card_engmeter', 'local_playergames'),
                'url'        => (new moodle_url('/local/playergames/engagement_meter.php'))->out(false),
                'disabled'   => false,
                'comingsoon' => false,
            ];
        }

        $actions[] = [
            'icon'       => 'fa-key',
            'label'      => get_string('dashboard_card_mykeys', 'local_playergames'),
            'url'        => (new moodle_url('/local/playergames/mykeys.php'))->out(false),
            'disabled'   => false,
            'comingsoon' => false,
        ];

        if (has_capability('moodle/site:config', $context)) {
            $actions[] = [
                'icon'       => 'fa-gear',
                'label'      => get_string('dashboard_card_settings', 'local_playergames'),
                'url'        => (new moodle_url(
                    '/admin/settings.php',
                    ['section' => 'local_playergames_seasons']
                ))->out(false),
                'disabled'   => false,
                'comingsoon' => false,
            ];
        }

        return $actions;
    }

    /**
     * Builds the quick-access nav cards shown above the SVG.
     *
     * @param context_system $context System context.
     * @return array
     */
    private function build_nav_cards(context_system $context): array {
        $comingsoon = get_string('dashboard_card_comingsoon', 'local_playergames');
        $cards      = [];

        if (has_capability('local/playergames:managecartridges', $context)) {
            $cards[] = [
                'icon'       => 'fa-box-archive',
                'label'      => get_string('dashboard_card_cartridges', 'local_playergames'),
                'url'        => (new moodle_url('/local/playergames/cartridge.php'))->out(false),
                'disabled'   => false,
                'comingsoon' => false,
            ];
        }

        if (has_capability('local/playergames:viewhub', $context)) {
            $cards[] = [
                'icon'       => 'fa-trophy',
                'label'      => get_string('dashboard_card_hub', 'local_playergames'),
                'url'        => '#',
                'disabled'   => true,
                'comingsoon' => $comingsoon,
            ];
        }

        if (has_capability('local/playergames:viewengagementmeter', $context)) {
            $cards[] = [
                'icon'       => 'fa-chart-line',
                'label'      => get_string('dashboard_card_engmeter', 'local_playergames'),
                'url'        => (new moodle_url('/local/playergames/engagement_meter.php'))->out(false),
                'disabled'   => false,
                'comingsoon' => false,
            ];
        }

        $cards[] = [
            'icon'       => 'fa-key',
            'label'      => get_string('dashboard_card_mykeys', 'local_playergames'),
            'url'        => (new moodle_url('/local/playergames/mykeys.php'))->out(false),
            'disabled'   => false,
            'comingsoon' => false,
        ];

        if (has_capability('moodle/site:config', $context)) {
            $cards[] = [
                'icon'       => 'fa-gear',
                'label'      => get_string('dashboard_card_settings', 'local_playergames'),
                'url'        => (new moodle_url(
                    '/admin/settings.php',
                    ['section' => 'local_playergames_seasons']
                ))->out(false),
                'disabled'   => false,
                'comingsoon' => false,
            ];
        }

        return $cards;
    }
}
