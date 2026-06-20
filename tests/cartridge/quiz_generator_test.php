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
 * Tests for the quiz generator response parsers.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\cartridge;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Unit tests for the pure parsing logic in {@see quiz_generator}.
 *
 * Only the response parsers are exercised; no AI provider is contacted.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(quiz_generator::class)]
final class quiz_generator_test extends \advanced_testcase {
    /**
     * Invokes a protected method on the generator via reflection.
     *
     * @param string $method Protected method name.
     * @param array $args Positional arguments.
     * @return mixed The method return value.
     */
    private function invoke(string $method, array $args) {
        $generator = new quiz_generator();
        $rm = new \ReflectionMethod($generator, $method);
        $rm->setAccessible(true);
        return $rm->invokeArgs($generator, $args);
    }

    public function test_parse_standalone_valid(): void {
        $json = json_encode(['questions' => [
            [
                'questiontext' => 'Q1',
                'correct' => 'C1',
                'distractors' => ['a', 'b', 'c', 'd'],
            ],
        ]]);

        $result = $this->invoke('parse_standalone_response', [$json]);

        $this->assertCount(1, $result);
        $this->assertSame('Q1', $result[0]['questiontext']);
        $this->assertSame('C1', $result[0]['correct']);
        $this->assertCount(4, $result[0]['distractors']);
    }

    public function test_parse_standalone_strips_code_fences(): void {
        $inner = json_encode(['questions' => [
            ['questiontext' => 'Q', 'correct' => 'C', 'distractors' => ['a', 'b', 'c', 'd']],
        ]]);
        // Build the markdown code fence without literal backticks (PHPCS-friendly).
        $fence = str_repeat(chr(96), 3);
        $fenced = $fence . "json\n" . $inner . "\n" . $fence;

        $result = $this->invoke('parse_standalone_response', [$fenced]);

        $this->assertCount(1, $result);
    }

    public function test_parse_standalone_skips_insufficient_distractors(): void {
        $json = json_encode(['questions' => [
            ['questiontext' => 'Q', 'correct' => 'C', 'distractors' => ['a', 'b']],
        ]]);

        $result = $this->invoke('parse_standalone_response', [$json]);

        $this->assertCount(0, $result);
    }

    public function test_parse_standalone_invalid_json(): void {
        $this->assertSame([], $this->invoke('parse_standalone_response', ['garbage']));
    }

    public function test_parse_quiz_response_filters_unknown_conceptid(): void {
        $concepts = [
            5 => (object) ['id' => 5, 'term' => 'T', 'definition' => 'D'],
        ];
        $json = json_encode(['questions' => [
            ['conceptid' => 5, 'questiontext' => 'Known', 'distractors' => ['a', 'b', 'c', 'd']],
            ['conceptid' => 99, 'questiontext' => 'Unknown', 'distractors' => ['a', 'b', 'c', 'd']],
        ]]);

        $result = $this->invoke('parse_quiz_response', [$json, $concepts]);

        // Only the question whose conceptid exists in the supplied concepts survives.
        $this->assertCount(1, $result);
        $this->assertSame(5, $result[0]['conceptid']);
        $this->assertSame('Known', $result[0]['questiontext']);
    }
}
