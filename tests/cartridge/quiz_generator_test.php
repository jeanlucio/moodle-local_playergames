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

/**
 * Unit tests for the pure parsing logic in {@see quiz_generator}.
 *
 * Only the response parsers are exercised; no AI provider is contacted.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_playergames\cartridge\quiz_generator
 */
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
                'category' => 'Cat',
                'difficulty' => 4,
            ],
        ]]);

        $result = $this->invoke('parse_standalone_response', [$json]);

        $this->assertCount(1, $result);
        $this->assertSame('Q1', $result[0]['questiontext']);
        $this->assertSame('C1', $result[0]['correct']);
        $this->assertCount(4, $result[0]['distractors']);
        $this->assertSame('Cat', $result[0]['category']);
        $this->assertSame(4, $result[0]['difficulty']);
    }

    public function test_parse_standalone_keeps_general_feedback(): void {
        $json = json_encode(['questions' => [
            [
                'questiontext' => 'Q1',
                'correct' => 'C1',
                'distractors' => ['a', 'b', 'c', 'd'],
                'generalfeedback' => 'Why C1 is right.',
            ],
        ]]);

        $result = $this->invoke('parse_standalone_response', [$json]);

        $this->assertSame('Why C1 is right.', $result[0]['generalfeedback']);
    }

    public function test_parse_standalone_defaults_general_feedback_empty(): void {
        $json = json_encode(['questions' => [
            [
                'questiontext' => 'Q1',
                'correct' => 'C1',
                'distractors' => ['a', 'b', 'c', 'd'],
            ],
        ]]);

        $result = $this->invoke('parse_standalone_response', [$json]);

        $this->assertSame('', $result[0]['generalfeedback']);
    }

    public function test_parse_standalone_defaults_category_and_difficulty(): void {
        $json = json_encode(['questions' => [
            ['questiontext' => 'Q', 'correct' => 'C', 'distractors' => ['a', 'b', 'c', 'd']],
        ]]);

        $result = $this->invoke('parse_standalone_response', [$json]);

        // Missing metadata falls back to empty category and difficulty 3.
        $this->assertSame('', $result[0]['category']);
        $this->assertSame(3, $result[0]['difficulty']);
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

    public function test_save_standalone_persists_category_and_difficulty(): void {
        global $DB;
        $this->resetAfterTest();
        $now = time();
        $cartridgeid = (int) $DB->insert_record('local_playergames_cartridges', (object) [
            'name' => 'Quiz',
            'version' => '1.0',
            'language' => 'en',
            'type' => 'quiz',
            'timecreated' => $now,
            'timemodified' => $now,
            'uploadedby' => 0,
            'active' => 1,
        ]);

        $questions = [
            [
                'questiontext' => 'Q1', 'correct' => 'C1',
                'distractors' => ['a', 'b', 'c', 'd'], 'category' => 'Science', 'difficulty' => 2,
            ],
            [
                'questiontext' => 'Q2', 'correct' => 'C2',
                'distractors' => ['a', 'b', 'c', 'd'], 'category' => 'Science', 'difficulty' => 9,
            ],
            [
                'questiontext' => 'Q3', 'correct' => 'C3',
                'distractors' => ['a', 'b', 'c', 'd'],
            ],
        ];

        $saved = (new quiz_generator())->save_standalone($cartridgeid, $questions);

        $this->assertSame(3, $saved);
        // The two questions sharing a category name create a single category.
        $this->assertSame(1, $DB->count_records('local_playergames_categories', [
            'cartridgeid' => $cartridgeid,
        ]));

        $rows = array_values($DB->get_records(
            'local_playergames_concept_questions',
            ['cartridgeid' => $cartridgeid],
            'id ASC'
        ));
        $this->assertSame('ai', $rows[0]->source);
        $this->assertSame(2, (int) $rows[0]->difficulty);
        $this->assertNotNull($rows[0]->categoryid);
        // Difficulty 9 is clamped to the 1-5 range.
        $this->assertSame(5, (int) $rows[1]->difficulty);
        // No category given falls back to null with the default difficulty 3.
        $this->assertNull($rows[2]->categoryid);
        $this->assertSame(3, (int) $rows[2]->difficulty);
    }

    public function test_save_standalone_persists_general_feedback(): void {
        global $DB;
        $this->resetAfterTest();
        $now = time();
        $cartridgeid = (int) $DB->insert_record('local_playergames_cartridges', (object) [
            'name' => 'Quiz',
            'version' => '1.0',
            'language' => 'en',
            'type' => 'quiz',
            'timecreated' => $now,
            'timemodified' => $now,
            'uploadedby' => 0,
            'active' => 1,
        ]);

        $saved = (new quiz_generator())->save_standalone($cartridgeid, [
            [
                'questiontext' => 'With fb', 'correct' => 'C',
                'distractors' => ['a', 'b', 'c', 'd'], 'generalfeedback' => 'Saved explanation.',
            ],
            [
                'questiontext' => 'No fb', 'correct' => 'C',
                'distractors' => ['a', 'b', 'c', 'd'], 'generalfeedback' => '',
            ],
        ]);

        $this->assertSame(2, $saved);
        // Insertion order is preserved, so row 0 has feedback and row 1 none.
        $rows = array_values($DB->get_records(
            'local_playergames_concept_questions',
            ['cartridgeid' => $cartridgeid],
            'id ASC'
        ));
        $this->assertSame('Saved explanation.', $rows[0]->generalfeedback);
        $this->assertNull($rows[1]->generalfeedback);
    }

    /**
     * Fields of the wrong type become empty text instead of throwing in trim() or clean_param().
     *
     * Regression test: array_map('trim') on a distractor that was a list is a TypeError, and a cast of a
     * list became the word Array.
     */
    public function test_parse_standalone_survives_fields_of_the_wrong_type(): void {
        $json = json_encode(['questions' => [
            'a string instead of a question',
            [
                'questiontext' => ['x'],
                'correct' => ['y'],
                'distractors' => ['a', ['b'], 'c', 'd', 'e'],
                'category' => ['z'],
                'difficulty' => ['w'],
                'generalfeedback' => ['v'],
            ],
        ]]);

        $result = $this->invoke('parse_standalone_response', [$json]);

        $this->assertCount(1, $result);
        $this->assertSame('', $result[0]['questiontext']);
        $this->assertSame('', $result[0]['correct']);
        $this->assertSame(['a', 'c', 'd', 'e'], $result[0]['distractors']);
        $this->assertSame(3, $result[0]['difficulty']);
        $this->assertStringNotContainsString('Array', json_encode($result));
    }

    /**
     * A response whose distractors leave fewer than four real ones is dropped, not saved with blanks.
     */
    public function test_parse_standalone_drops_questions_with_blank_distractors(): void {
        $blank = ['a', '', 'c', 'd'];

        $standalone = $this->invoke('parse_standalone_response', [json_encode(['questions' => [
            ['questiontext' => 'Q', 'correct' => 'C', 'distractors' => $blank],
        ]])]);
        $this->assertSame([], $standalone);
    }

    /**
     * A generator whose AI call answers with the given result, and nothing else.
     *
     * @param array $result What the AI call returns.
     * @return quiz_generator
     */
    private function generator_answering(array $result): quiz_generator {
        return new class ($result) extends quiz_generator {
            /** @var array The canned answer of the AI call. */
            private array $canned;

            /**
             * Keeps the answer to give.
             *
             * @param array $canned What the AI call returns.
             */
            public function __construct(array $canned) {
                $this->canned = $canned;
            }

            #[\Override]
            protected function call_api(string $system, string $user, bool $jsonmode, string $description = ''): array {
                return $this->canned;
            }
        };
    }

    /**
     * Regression test: a failure of the AI call came back as an empty list, which the page showed as an
     * empty preview with a Save button, and the provider's message was lost.
     */
    public function test_generate_preview_reports_the_providers_failure(): void {
        $generator = $this->generator_answering(['success' => false, 'message' => 'Invalid API key']);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage('Invalid API key');
        $generator->generate_preview('Topic', 'en', 5);
    }

    public function test_generate_preview_without_an_ai_source_says_so(): void {
        $generator = $this->generator_answering(['success' => false, 'message' => '']);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('error_no_ai_key', 'local_playergames'));
        $generator->generate_preview('Topic', 'en', 5);
    }

    public function test_generate_preview_rejects_an_answer_with_no_usable_question(): void {
        foreach (['not json at all', '{"questions": []}', '{"other": 1}'] as $answer) {
            $generator = $this->generator_answering(['success' => true, 'data' => $answer]);
            try {
                $generator->generate_preview('Topic', 'en', 5);
                $this->fail("No exception for: {$answer}");
            } catch (\moodle_exception $e) {
                $this->assertSame(get_string('error_ai_no_questions', 'local_playergames'), $e->getMessage());
            }
        }
    }

    public function test_generate_preview_returns_the_questions_of_a_good_answer(): void {
        $json = json_encode(['questions' => [[
            'questiontext' => 'Q?', 'correct' => 'A', 'distractors' => ['B', 'C', 'D', 'E'],
        ]]]);
        $generator = $this->generator_answering(['success' => true, 'data' => $json]);

        $questions = $generator->generate_preview('Topic', 'en', 5);

        $this->assertCount(1, $questions);
        $this->assertSame('Q?', $questions[0]['questiontext']);
    }
}
