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
 * Tests for the concept generator response parser.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\cartridge;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Unit tests for the pure parsing logic in {@see ai_generator}.
 *
 * Only parse_concepts is exercised; no AI provider is contacted.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(ai_generator::class)]
final class ai_generator_test extends \advanced_testcase {
    /**
     * Invokes the protected parse_concepts method via reflection.
     *
     * @param string $response Raw AI response text.
     * @return array Parsed concepts.
     */
    private function parse(string $response): array {
        $generator = new ai_generator();
        $rm = new \ReflectionMethod($generator, 'parse_concepts');
        $rm->setAccessible(true);
        return $rm->invokeArgs($generator, [$response]);
    }

    public function test_parses_wrapped_object(): void {
        $json = json_encode(['concepts' => [
            ['term' => 'XP', 'definition' => 'Experience', 'category' => 'Core', 'difficulty' => 2],
        ]]);

        $result = $this->parse($json);

        $this->assertCount(1, $result);
        $this->assertSame('XP', $result[0]['term']);
    }

    public function test_parses_bare_array(): void {
        $json = json_encode([
            ['term' => 'Badge', 'definition' => 'A token', 'category' => 'Core', 'difficulty' => 1],
        ]);

        $result = $this->parse($json);

        $this->assertCount(1, $result);
        $this->assertSame('Badge', $result[0]['term']);
    }

    public function test_strips_code_fences(): void {
        $inner = json_encode(['concepts' => [
            ['term' => 'Quest', 'definition' => 'A task', 'category' => 'Flow', 'difficulty' => 3],
        ]]);
        $fence = str_repeat(chr(96), 3);
        $fenced = $fence . "json\n" . $inner . "\n" . $fence;

        $result = $this->parse($fenced);

        $this->assertCount(1, $result);
        $this->assertSame('Quest', $result[0]['term']);
    }

    public function test_invalid_json_throws(): void {
        $this->expectException(\moodle_exception::class);
        $this->parse('not json at all');
    }

    public function test_missing_concepts_throws(): void {
        $this->expectException(\moodle_exception::class);
        $this->parse(json_encode(['something' => 'else']));
    }
}
