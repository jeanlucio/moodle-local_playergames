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

/**
 * Unit tests for the pure parsing logic in {@see ai_generator}.
 *
 * Only parse_concepts is exercised; no AI provider is contacted.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_playergames\cartridge\ai_generator
 */
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

    /**
     * Builds a generator whose AI call answers with a fixed result and records what it was asked.
     *
     * @param array $result What the call returns.
     * @return ai_generator
     */
    private function generator_answering(array $result): ai_generator {
        return new class ($result) extends ai_generator {
            /** @var array The calls received: system, user, jsonmode and description of each. */
            public array $calls = [];

            /** @var array What call_api() returns. */
            private array $result;

            /**
             * Keeps the fixed answer.
             *
             * @param array $result What call_api() returns.
             */
            public function __construct(array $result) {
                $this->result = $result;
            }

            #[\Override]
            protected function call_api(string $system, string $user, bool $jsonmode, string $description = ''): array {
                $this->calls[] = compact('system', 'user', 'jsonmode', 'description');
                return $this->result;
            }
        };
    }

    /**
     * Builds a generator whose only AI source is a core_ai that answers with a fixed result.
     *
     * @param bool $hascoreai Whether core_ai has a provider.
     * @param array $coreresult What the core_ai call returns.
     * @return ai_generator
     */
    private function generator_with_core_ai(bool $hascoreai, array $coreresult = []): ai_generator {
        return new class ($hascoreai, $coreresult) extends ai_generator {
            /** @var array The calls to core_ai: system and user of each. */
            public array $corecalls = [];

            /** @var bool Whether core_ai has a provider. */
            private bool $hascoreai;

            /** @var array What call_core_ai() returns. */
            private array $coreresult;

            /**
             * Keeps the core_ai setup.
             *
             * @param bool $hascoreai Whether core_ai has a provider.
             * @param array $coreresult What call_core_ai() returns.
             */
            public function __construct(bool $hascoreai, array $coreresult) {
                $this->hascoreai = $hascoreai;
                $this->coreresult = $coreresult;
            }

            #[\Override]
            protected function has_core_ai(): bool {
                return $this->hascoreai;
            }

            #[\Override]
            protected function call_core_ai(string $system, string $user): array {
                $this->corecalls[] = compact('system', 'user');
                return $this->coreresult;
            }

            /**
             * Runs the protected call.
             *
             * @return array The result of call_api().
             */
            public function ask(): array {
                return $this->call_api('rules', 'question', false);
            }
        };
    }

    /**
     * Builds the concept prompt.
     *
     * @param array $args Arguments of build_prompt().
     * @return string
     */
    private function prompt(array $args): string {
        $generator = new ai_generator();
        $method = new \ReflectionMethod($generator, 'build_prompt');
        return $method->invokeArgs($generator, $args);
    }

    /**
     * The prompt carries the topic, the count, the difficulty and the language, and asks for raw JSON.
     */
    public function test_the_prompt_states_what_to_generate(): void {
        $prompt = $this->prompt(['Photosynthesis', 'Portuguese', 25, 4]);

        $this->assertStringContainsString('Generate exactly 25 educational concepts about the topic: "Photosynthesis"', $prompt);
        $this->assertStringContainsString('Target average difficulty: 4 out of 5', $prompt);
        $this->assertStringContainsString('Respond in language: Portuguese.', $prompt);
        $this->assertStringContainsString('only raw JSON', $prompt);
        $this->assertStringContainsString('{"concepts":[{"term":', $prompt);
        $this->assertStringNotContainsString('reference text', $prompt);
    }

    /**
     * Without a language the prompt asks for English, and without categories the model is told to keep few.
     */
    public function test_the_prompt_defaults_to_english_and_free_categories(): void {
        $prompt = $this->prompt(['Topic', '', 10, 3]);

        $this->assertStringContainsString('Respond in language: English.', $prompt);
        $this->assertStringContainsString('Use at most 3 distinct categories', $prompt);
        $this->assertStringContainsString('MUST be written in English', $prompt);
    }

    /**
     * Given category names, the model must use exactly those.
     */
    public function test_the_prompt_restricts_the_categories_when_they_are_given(): void {
        $prompt = $this->prompt(['Topic', 'English', 10, 3, ['Biology', 'Chemistry']]);

        $this->assertStringContainsString('MUST be exactly one of these values (verbatim): "Biology", "Chemistry"', $prompt);
        $this->assertStringNotContainsString('at most 3 distinct categories', $prompt);
    }

    /**
     * A reference text is passed on between separators, so the model uses it.
     */
    public function test_the_prompt_includes_the_reference_text(): void {
        $prompt = $this->prompt(['Topic', 'English', 10, 3, [], 'Chlorophyll absorbs light.']);

        $this->assertStringContainsString("---\nChlorophyll absorbs light.\n---", $prompt);
        $this->assertStringContainsString('reference text', $prompt);
    }

    /**
     * A good answer becomes concepts, and the call states the topic for the usage log and asks for JSON.
     */
    public function test_generate_parses_the_answer_and_names_the_topic(): void {
        $this->resetAfterTest();
        $answer = json_encode(['concepts' => [
            ['term' => 'XP', 'definition' => 'Experience', 'category' => 'Core', 'difficulty' => 2],
        ]]);
        $generator = $this->generator_answering(['success' => true, 'data' => $answer]);

        $concepts = $generator->generate('Games', 'English', 10, 2);

        $this->assertSame('XP', $concepts[0]['term']);
        $this->assertSame('', $generator->calls[0]['system']);
        $this->assertTrue($generator->calls[0]['jsonmode']);
        $this->assertSame(
            get_string('cartridge_ai_usage_concepts', 'local_playergames', 'Games'),
            $generator->calls[0]['description']
        );
        $this->assertStringContainsString('"Games"', $generator->calls[0]['user']);
    }

    /**
     * A failure with a reason shows the reason, and one without says there is no AI source.
     */
    public function test_generate_reports_why_it_failed(): void {
        $this->resetAfterTest();

        try {
            $this->generator_answering(['success' => false, 'message' => 'Invalid key'])->generate('T', 'English', 10, 3);
            $this->fail('A failed request returned concepts.');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_ai_request_failed', $e->errorcode);
            $this->assertSame('Invalid key', $e->a);
        }

        try {
            $this->generator_answering(['success' => false, 'message' => ''])->generate('T', 'English', 10, 3);
            $this->fail('A request without a source returned concepts.');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_no_ai_key', $e->errorcode);
        }
    }

    /**
     * An answer that is not the JSON asked for is refused instead of saved.
     */
    public function test_generate_refuses_an_answer_that_is_not_json(): void {
        $this->resetAfterTest();
        $generator = $this->generator_answering(['success' => true, 'data' => 'Sorry, I cannot do that.']);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('error_cartridge_invalid_json', 'local_playergames'));
        $generator->generate('T', 'English', 10, 3);
    }

    /**
     * The raw prompt goes out as it is, in JSON mode, with no system text.
     */
    public function test_send_passes_the_prompt_on(): void {
        $generator = $this->generator_answering(['success' => true, 'data' => 'ok']);

        $this->assertSame(['success' => true, 'data' => 'ok'], $generator->send('Hello'));
        $this->assertSame(
            ['system' => '', 'user' => 'Hello', 'jsonmode' => true, 'description' => ''],
            $generator->calls[0]
        );
    }

    /**
     * Free text goes out with its system instruction, and comes back as text.
     */
    public function test_generate_text_returns_the_text_and_reports_failures(): void {
        $generator = $this->generator_answering(['success' => true, 'data' => 'A story']);

        $this->assertSame('A story', $generator->generate_text('Be brief', 'Tell one', false));
        $this->assertSame(
            ['system' => 'Be brief', 'user' => 'Tell one', 'jsonmode' => false, 'description' => ''],
            $generator->calls[0]
        );

        try {
            $this->generator_answering(['success' => false, 'message' => 'Quota'])->generate_text('', 'x');
            $this->fail('A failed request returned text.');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_ai_request_failed', $e->errorcode);
            $this->assertSame('Quota', $e->a);
        }

        $this->expectExceptionMessage(get_string('error_no_ai_key', 'local_playergames'));
        $this->generator_answering(['success' => false, 'message' => ''])->generate_text('', 'x');
    }

    /**
     * When core_ai has a provider it answers, and its failure with a reason is the one reported.
     */
    public function test_call_api_falls_back_to_core_ai(): void {
        $this->resetAfterTest();
        $answer = ['success' => true, 'data' => 'text', 'provider' => 'Moodle AI'];
        $generator = $this->generator_with_core_ai(true, $answer);

        $this->assertSame($answer, $generator->ask());
        $this->assertSame([['system' => 'rules', 'user' => 'question']], $generator->corecalls);

        $failure = ['success' => false, 'message' => 'Provider down'];
        $this->assertSame('Provider down', $this->generator_with_core_ai(true, $failure)->ask()['message']);
    }

    /**
     * With no source at all the answer is a failure with nothing to say, which callers turn into "no key".
     */
    public function test_call_api_without_any_source_fails_quietly(): void {
        $this->resetAfterTest();
        $generator = $this->generator_with_core_ai(false);

        $result = $generator->ask();

        $this->assertFalse($result['success']);
        $this->assertSame([], $generator->corecalls);
    }

    /**
     * A core_ai that has no provider and says nothing does not hide that no source answered.
     */
    public function test_call_api_ignores_a_silent_core_ai_failure(): void {
        $this->resetAfterTest();

        $result = $this->generator_with_core_ai(true, ['success' => false, 'message' => ''])->ask();

        $this->assertFalse($result['success']);
    }

    /**
     * Whether an AI source exists follows core_ai when the hub has no key.
     */
    public function test_has_key_follows_core_ai_when_the_hub_has_no_key(): void {
        $this->resetAfterTest();
        if (class_exists(\local_aihub\ai::class) && \local_aihub\ai::is_available()) {
            $this->markTestSkipped('The AI hub of this site has a key, which is a source in itself.');
        }
        $this->use_core_ai_answering($this->createMock(\core_ai\aiactions\responses\response_generate_text::class));

        $this->assertTrue((new ai_generator())->has_key());
    }

    /**
     * Without a hub key or a core_ai provider there is no source.
     */
    public function test_has_key_is_false_without_any_source(): void {
        $this->resetAfterTest();
        if (class_exists(\local_aihub\ai::class) && \local_aihub\ai::is_available()) {
            $this->markTestSkipped('The AI hub of this site has a key, which is a source in itself.');
        }

        $this->assertFalse((new ai_generator())->has_key());
    }
}
