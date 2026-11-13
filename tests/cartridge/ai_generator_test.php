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

    /**
     * Fields of the wrong type become empty text and entries that are not objects are dropped.
     *
     * Regression test: a list as a term reached the preview form and the database import as it was.
     */
    public function test_concepts_of_the_wrong_type_are_normalised(): void {
        $json = json_encode(['concepts' => [
            ['term' => ['x'], 'definition' => ['y'], 'category' => ['z'], 'difficulty' => ['w']],
            'a string instead of a concept',
            ['term' => '<b>XP</b>', 'definition' => 'Experience', 'category' => 'Core', 'difficulty' => '9'],
        ]]);

        $result = $this->parse($json);

        $this->assertSame([
            ['term' => '', 'definition' => '', 'category' => '', 'difficulty' => 3],
            ['term' => 'XP', 'definition' => 'Experience', 'category' => 'Core', 'difficulty' => 5],
        ], $result);
    }

    public function test_a_payload_that_is_not_an_object_throws_the_translated_error(): void {
        foreach (['5', '"text"'] as $payload) {
            try {
                $this->parse($payload);
                $this->fail("Accepted: {$payload}");
            } catch (\moodle_exception $e) {
                $this->assertSame('error_cartridge_no_concepts', $e->errorcode, $payload);
            }
        }
    }

    /**
     * Makes the generator reach a core_ai manager that answers with the given response, and nothing else.
     *
     * @param object|null $response What process_action() returns, or null to make it throw.
     * @param string $exceptionmessage Message of the exception thrown when there is no response.
     * @return void
     */
    private function use_core_ai_answering(?object $response, string $exceptionmessage = ''): void {
        if ((new \ReflectionMethod(\core_ai\manager::class, 'get_providers_for_actions'))->isStatic()) {
            // Moodle 4.5 declares it static, which PHPUnit cannot stub; the messages are the same code path.
            $this->markTestSkipped('The core_ai manager cannot be mocked on this Moodle version.');
        }
        $action = \core_ai\aiactions\generate_text::class;
        $manager = $this->createMock(\core_ai\manager::class);
        $manager->method('get_providers_for_actions')->willReturn([$action => [new \stdClass()]]);
        if ($response === null) {
            $manager->method('process_action')->willThrowException(new \RuntimeException($exceptionmessage));
        } else {
            $manager->method('process_action')->willReturn($response);
        }
        \core\di::set(\core_ai\manager::class, $manager);
    }

    /**
     * Calls the core_ai fallback, which is the only AI source when the hub is not installed.
     *
     * @return array Result of call_core_ai().
     */
    private function call_core_ai(): array {
        $generator = new ai_generator();
        $rm = new \ReflectionMethod($generator, 'call_core_ai');
        $rm->setAccessible(true);
        return $rm->invokeArgs($generator, ['', 'prompt']);
    }

    /**
     * Regression test: the failure messages were fixed English text ("core_ai: provider returned
     * failure") shown inside a translated sentence, and the reason the provider gave was thrown away.
     */
    public function test_core_ai_failure_reports_the_providers_reason_in_the_site_language(): void {
        $this->resetAfterTest();
        $response = $this->createMock(\core_ai\aiactions\responses\response_generate_text::class);
        $response->method('get_success')->willReturn(false);
        $response->method('get_errormessage')->willReturn('Invalid API key');
        $this->use_core_ai_answering($response);

        $result = $this->call_core_ai();

        $this->assertFalse($result['success']);
        $this->assertSame(get_string('error_coreai_failed_detail', 'local_playergames', 'Invalid API key'), $result['message']);
        $this->assertStringNotContainsString('core_ai:', $result['message']);
    }

    public function test_core_ai_failure_without_a_reason_uses_the_generic_message(): void {
        $this->resetAfterTest();
        $response = $this->createMock(\core_ai\aiactions\responses\response_generate_text::class);
        $response->method('get_success')->willReturn(false);
        $response->method('get_errormessage')->willReturn('');
        $this->use_core_ai_answering($response);

        $this->assertSame(get_string('error_coreai_failed', 'local_playergames'), $this->call_core_ai()['message']);
    }

    public function test_core_ai_empty_response_uses_the_translated_message(): void {
        $this->resetAfterTest();
        $response = $this->createMock(\core_ai\aiactions\responses\response_generate_text::class);
        $response->method('get_success')->willReturn(true);
        $response->method('get_response_data')->willReturn(['generatedcontent' => '']);
        $this->use_core_ai_answering($response);

        $this->assertSame(get_string('error_coreai_empty', 'local_playergames'), $this->call_core_ai()['message']);
    }

    public function test_core_ai_exception_is_wrapped_in_the_translated_message(): void {
        $this->resetAfterTest();
        $this->use_core_ai_answering(null, 'Quota exhausted');

        $this->assertSame(
            get_string('error_coreai_failed_detail', 'local_playergames', 'Quota exhausted'),
            $this->call_core_ai()['message']
        );
    }
}
