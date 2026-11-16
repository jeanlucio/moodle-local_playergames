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
 * Tests for the ecosystem plugin registry.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\ecosystem;

/**
 * Unit tests for {@see plugin_registry}.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_playergames\ecosystem\plugin_registry
 */
final class plugin_registry_test extends \advanced_testcase {
    public function test_catalog_structure(): void {
        $catalog = plugin_registry::get_catalog();

        $this->assertNotEmpty($catalog);
        // The hub is always the first entry.
        $this->assertSame('local_playergames', $catalog[0]['component']);

        $components = array_column($catalog, 'component');
        $validtypes = [
            plugin_registry::REL_REQUIRES,
            plugin_registry::REL_AI,
            plugin_registry::REL_ASSOC,
            plugin_registry::REL_PLANNED,
        ];

        foreach ($catalog as $entry) {
            $this->assertArrayHasKey('component', $entry);
            $this->assertArrayHasKey('displayname', $entry);
            $this->assertArrayHasKey('abbr', $entry);
            $this->assertArrayHasKey('color', $entry);
            $this->assertArrayHasKey('icon', $entry);
            $this->assertArrayHasKey('group', $entry);
            $this->assertContains($entry['group'], plugin_registry::GROUPS);
            $this->assertArrayHasKey('relations', $entry);
            $this->assertIsArray($entry['relations']);

            foreach ($entry['relations'] as $relation) {
                $this->assertArrayHasKey('target', $relation);
                $this->assertArrayHasKey('type', $relation);
                $this->assertContains($relation['target'], $components);
                $this->assertContains($relation['type'], $validtypes);
            }
        }
    }

    public function test_components_are_unique(): void {
        $components = array_column(plugin_registry::get_catalog(), 'component');
        $this->assertSame($components, array_values(array_unique($components)));
    }

    /**
     * Regression test: the names of the plugins were English literals shown in every language.
     */
    public function test_every_plugin_has_a_name_in_each_language_file(): void {
        global $CFG;
        $this->resetAfterTest();

        foreach (['en', 'pt_br'] as $lang) {
            $string = [];
            include($CFG->dirroot . '/local/playergames/lang/' . $lang . '/local_playergames.php');
            foreach (array_column(plugin_registry::get_catalog(), 'component') as $component) {
                $this->assertArrayHasKey('plugin_name_' . $component, $string, "{$lang}: no name for {$component}.");
                $this->assertNotSame('', $string['plugin_name_' . $component]);
            }
        }
    }

    public function test_the_display_name_is_the_string_of_the_plugin(): void {
        $this->resetAfterTest();

        $names = array_column(plugin_registry::get_catalog(), 'displayname', 'component');

        $this->assertSame(get_string('plugin_name_local_latepenalty', 'local_playergames'), $names['local_latepenalty']);
        $this->assertSame(get_string('plugin_name_local_playergames', 'local_playergames'), $names['local_playergames']);
    }
}
