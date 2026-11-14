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
 * Tests for the dashboard page data.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\output;

use local_playergames\ecosystem\plugin_registry;
use local_playergames\ecosystem\plugin_status;

/**
 * Unit tests for {@see dashboard}.
 *
 * @package    local_playergames
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_playergames\output\dashboard
 */
final class dashboard_test extends \advanced_testcase {
    /**
     * Exports the dashboard.
     *
     * @return array The template data.
     */
    private function export(): array {
        global $PAGE;

        return (new dashboard())->export_for_template($PAGE->get_renderer('core'));
    }

    /**
     * Every plugin of the catalog has a card, and each card sits in a group.
     */
    public function test_every_catalog_plugin_has_a_card_in_a_group(): void {
        $this->resetAfterTest();
        $catalog = plugin_registry::get_catalog();

        $data = $this->export();

        $this->assertCount(count($catalog), $data['cards']);
        $grouped = [];
        foreach ($data['groups'] as $group) {
            $this->assertContains($group['key'], plugin_registry::GROUPS);
            $this->assertNotEmpty($group['cards']);
            $this->assertNotSame('', $group['label']);
            foreach ($group['cards'] as $card) {
                $grouped[] = $card['component'];
            }
        }
        $this->assertEqualsCanonicalizing(array_column($catalog, 'component'), $grouped);
    }

    /**
     * A card tells whether its plugin is installed, with the version, and greys out the ones that are not.
     */
    public function test_a_card_follows_the_install_status_of_its_plugin(): void {
        $this->resetAfterTest();
        $status = plugin_status::get_installed();
        $bycomponent = array_column(plugin_registry::get_catalog(), null, 'component');

        foreach ($this->export()['cards'] as $card) {
            $installed = (bool) ($status[$card['component']]['installed'] ?? false);
            $this->assertSame($installed, $card['isinstalled'], $card['component']);
            $this->assertSame($installed ? 'bg-success' : 'bg-secondary', $card['statusbadge']);
            $this->assertSame($installed ? $bycomponent[$card['component']]['color'] : '#94a3b8', $card['color']);
            $this->assertSame($card['version'] !== '', $card['hasversion']);
            $this->assertNotSame('', $card['description']);
        }
        $this->assertContains('local_playergames', array_column(
            array_filter($this->export()['cards'], fn(array $card): bool => $card['isinstalled']),
            'component'
        ));
    }

    /**
     * The edges handed to the script name only plugins of the catalog, and every card lists its neighbours.
     */
    public function test_the_edges_connect_catalog_plugins_and_cards_list_their_connections(): void {
        $this->resetAfterTest();
        $components = array_column(plugin_registry::get_catalog(), 'component');

        $data = $this->export();
        $edges = json_decode($data['edgesjson'], true);

        $this->assertNotEmpty($edges);
        $connected = [];
        foreach ($edges as $edge) {
            $this->assertContains($edge['from'], $components);
            $this->assertContains($edge['to'], $components);
            $connected[$edge['from']] = true;
            $connected[$edge['to']] = true;
        }
        foreach ($data['cards'] as $card) {
            $this->assertSame(isset($connected[$card['component']]), $card['hasconnections'], $card['component']);
            foreach ($card['connectiongroups'] as $connection) {
                $this->assertNotSame('', $connection['names']);
                $this->assertStringStartsWith('pg-chip-', $connection['cssclass']);
            }
        }
    }

    /**
     * The legend names the four kinds of relation, in order, with the class the script paints them with.
     */
    public function test_the_legend_lists_the_four_relation_types(): void {
        $this->resetAfterTest();

        $legend = $this->export()['legend'];

        $this->assertSame(
            [
                'pg-edge-' . plugin_registry::REL_REQUIRES,
                'pg-edge-' . plugin_registry::REL_AI,
                'pg-edge-' . plugin_registry::REL_ASSOC,
                'pg-edge-' . plugin_registry::REL_PLANNED,
            ],
            array_column($legend, 'cssclass')
        );
    }

    /**
     * The page renders through its template without leaving a placeholder behind.
     */
    public function test_the_page_renders_every_plugin_name(): void {
        global $PAGE;
        $this->resetAfterTest();
        $data = $this->export();

        $html = $PAGE->get_renderer('core')->render_from_template('local_playergames/dashboard', $data);

        foreach ($data['cards'] as $card) {
            $this->assertStringContainsString(s($card['displayname']), $html);
        }
    }
}
