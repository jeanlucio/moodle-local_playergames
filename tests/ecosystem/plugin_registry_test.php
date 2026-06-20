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

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Unit tests for {@see plugin_registry}.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(plugin_registry::class)]
final class plugin_registry_test extends \advanced_testcase {
    public function test_catalog_structure(): void {
        $catalog = plugin_registry::get_catalog();

        $this->assertNotEmpty($catalog);
        // The hub is always the first entry.
        $this->assertSame('local_playergames', $catalog[0]['component']);

        foreach ($catalog as $entry) {
            $this->assertArrayHasKey('component', $entry);
            $this->assertArrayHasKey('displayname', $entry);
            $this->assertArrayHasKey('abbr', $entry);
            $this->assertArrayHasKey('color', $entry);
            $this->assertArrayHasKey('dependencies', $entry);
            $this->assertIsArray($entry['dependencies']);
        }
    }

    public function test_components_are_unique(): void {
        $components = array_column(plugin_registry::get_catalog(), 'component');
        $this->assertSame($components, array_values(array_unique($components)));
    }
}
