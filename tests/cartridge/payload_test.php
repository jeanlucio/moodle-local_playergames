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
 * Unit tests for the payload field readers.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\cartridge;

/**
 * Unit tests for the payload field readers.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_playergames\cartridge\payload
 */
final class payload_test extends \advanced_testcase {
    public function test_text_cleans_scalars_and_treats_the_rest_as_missing(): void {
        $this->assertSame('Hello', payload::text('  <b>Hello</b> '));
        $this->assertSame('5', payload::text(5));
        $this->assertSame('', payload::text(['x']));
        $this->assertSame('', payload::text(['a' => 'b']));
        $this->assertSame('', payload::text(null));
    }

    public function test_values_keeps_arrays_and_wraps_a_single_scalar(): void {
        $this->assertSame(['a', 'b'], payload::values(['a', 'b']));
        $this->assertSame(['one'], payload::values('one'));
        $this->assertSame([], payload::values(''));
        $this->assertSame([], payload::values(null));
    }

    public function test_number_clamps_and_falls_back(): void {
        $this->assertSame(5, payload::number(9, 1, 5, 3));
        $this->assertSame(1, payload::number('-4', 1, 5, 3));
        $this->assertSame(2, payload::number('2', 1, 5, 3));
        $this->assertSame(3, payload::number(['x'], 1, 5, 3));
        $this->assertSame(3, payload::number(null, 1, 5, 3));
        $this->assertSame(3, payload::number('abc', 1, 5, 3));
    }

    public function test_texts_keeps_only_the_texts_that_are_not_empty(): void {
        $this->assertSame(['a', 'c', '4'], payload::texts(['a', '', ['b'], 'c', '  ', null, 4]));
        $this->assertSame(['one'], payload::texts('one'));
        $this->assertSame([], payload::texts(''));
        $this->assertSame([], payload::texts(null));
    }
}
