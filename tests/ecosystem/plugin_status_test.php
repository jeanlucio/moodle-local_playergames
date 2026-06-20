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
 * Tests for the ecosystem plugin status checker.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\ecosystem;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Unit tests for {@see plugin_status}.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(plugin_status::class)]
final class plugin_status_test extends \advanced_testcase {
    public function test_get_installed_reports_hub_as_installed(): void {
        $status = plugin_status::get_installed();

        // Every catalog component has a status entry.
        foreach (plugin_registry::get_catalog() as $entry) {
            $this->assertArrayHasKey($entry['component'], $status);
            $this->assertArrayHasKey('installed', $status[$entry['component']]);
            $this->assertArrayHasKey('version', $status[$entry['component']]);
        }

        // The hub itself is, by definition, installed and exposes its release.
        $this->assertTrue($status['local_playergames']['installed']);
        $this->assertNotSame('', $status['local_playergames']['version']);
    }
}
