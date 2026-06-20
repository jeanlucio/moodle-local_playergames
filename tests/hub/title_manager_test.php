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
 * Tests for the level-to-title manager.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\hub;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Unit tests for {@see title_manager}.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(title_manager::class)]
final class title_manager_test extends \advanced_testcase {
    public function test_get_string_key_clamps_to_valid_range(): void {
        $this->assertSame('level_title_1', title_manager::get_string_key(0));
        $this->assertSame('level_title_1', title_manager::get_string_key(1));
        $this->assertSame('level_title_5', title_manager::get_string_key(5));
        $this->assertSame('level_title_20', title_manager::get_string_key(20));
        // Above the maximum the key is clamped to MAX_LEVEL.
        $this->assertSame('level_title_20', title_manager::get_string_key(99));
    }

    public function test_get_title_returns_translated_string(): void {
        $title = title_manager::get_title(5);
        $this->assertIsString($title);
        $this->assertNotSame('', $title);
        // Must resolve to the actual lang string, not a "[[missing]]" placeholder.
        $this->assertSame(get_string('level_title_5', 'local_playergames'), $title);
    }
}
