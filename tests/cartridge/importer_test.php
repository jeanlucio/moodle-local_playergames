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
 * Tests for the cartridge importer.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\cartridge;

/**
 * Unit tests for {@see importer}.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_playergames\cartridge\importer
 */
final class importer_test extends \advanced_testcase {
    /**
     * Builds a JSON string for a concept cartridge.
     *
     * @param array $overrides Values to merge over the default payload.
     * @return string Encoded JSON.
     */
    private function concept_json(array $overrides = []): string {
        $data = array_merge([
            'name' => 'Gamification basics',
            'version' => '2.0',
            'language' => 'en',
            'concepts' => [
                ['term' => 'XP', 'definition' => 'Experience points', 'category' => 'Core', 'difficulty' => 2],
                ['term' => 'Badge', 'definition' => 'A reward token', 'category' => 'Core', 'difficulty' => 9],
                ['term' => 'Quest', 'definition' => 'A task to complete', 'category' => 'Flow', 'difficulty' => 3],
            ],
        ], $overrides);
        return json_encode($data);
    }

    /**
     * Builds a JSON string for a quiz cartridge.
     *
     * @param array $overrides Values to merge over the default payload.
     * @return string Encoded JSON.
     */
    private function quiz_json(array $overrides = []): string {
        $data = array_merge([
            'name' => 'History quiz',
            'version' => '1.0',
            'language' => 'pt_br',
            'type' => 'quiz',
            'questions' => [
                [
                    'questiontext' => 'Who discovered Brazil?',
                    'correct' => 'Pedro Álvares Cabral',
                    'distractors' => ['Vasco da Gama', 'Cristóvão Colombo', 'Américo Vespúcio', 'Bartolomeu Dias'],
                    'category' => 'Discoveries',
                    'difficulty' => 2,
                ],
                [
                    'questiontext' => 'In what year?',
                    'correct' => '1500',
                    'distractors' => ['1492', '1498', '1502', '1510'],
                    'category' => 'Discoveries',
                    'difficulty' => 9,
                ],
            ],
        ], $overrides);
        return json_encode($data);
    }

    public function test_import_concept_cartridge(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        $result = (new importer())->import($this->concept_json(), (int) $user->id);

        $this->assertSame(3, $result->count);
        $this->assertSame('concept', $result->type);

        $cartridge = $DB->get_record('local_playergames_cartridges', ['id' => $result->cartridgeid], '*', MUST_EXIST);
        $this->assertSame('concept', $cartridge->type);
        $this->assertSame('Gamification basics', $cartridge->name);
        $this->assertSame('2.0', $cartridge->version);
        $this->assertSame((int) $user->id, (int) $cartridge->uploadedby);
        $this->assertEquals(1, $cartridge->active);

        $this->assertSame(3, $DB->count_records('local_playergames_concepts', ['cartridgeid' => $result->cartridgeid]));
        // Two distinct category names should produce two categories.
        $this->assertSame(2, $DB->count_records('local_playergames_categories', ['cartridgeid' => $result->cartridgeid]));
    }

    public function test_import_concept_clamps_difficulty(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        $result = (new importer())->import($this->concept_json(), (int) $user->id);

        $badge = $DB->get_record('local_playergames_concepts', [
            'cartridgeid' => $result->cartridgeid,
            'term' => 'Badge',
        ], '*', MUST_EXIST);
        // Difficulty 9 in the payload must be clamped to the 1-5 range.
        $this->assertSame(5, (int) $badge->difficulty);
    }

    public function test_import_quiz_cartridge_by_type(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        $result = (new importer())->import($this->quiz_json(), (int) $user->id);

        $this->assertSame(2, $result->count);
        $this->assertSame('quiz', $result->type);

        $cartridge = $DB->get_record('local_playergames_cartridges', ['id' => $result->cartridgeid], '*', MUST_EXIST);
        $this->assertSame('quiz', $cartridge->type);

        $questions = $DB->get_records(
            'local_playergames_concept_questions',
            ['cartridgeid' => $result->cartridgeid],
            'id ASC'
        );
        $this->assertCount(2, $questions);

        $first = reset($questions);
        $this->assertSame('import', $first->source);
        $this->assertSame(2, (int) $first->difficulty);
        $this->assertNotNull($first->categoryid);
        $answers = $DB->get_records(
            'local_playergames_concept_answers',
            ['questionid' => $first->id],
            'sortorder ASC'
        );
        // One correct + four distractors.
        $this->assertCount(5, $answers);
        $correct = array_values(array_filter($answers, fn($a) => (int) $a->iscorrect === 1));
        $this->assertCount(1, $correct);
        $this->assertSame('Pedro Álvares Cabral', $correct[0]->answertext);
        $this->assertSame(0, (int) $correct[0]->sortorder);

        // The two questions share one category name, so a single category is created.
        $this->assertSame(1, $DB->count_records('local_playergames_categories', [
            'cartridgeid' => $result->cartridgeid,
        ]));
        // Difficulty 9 in the payload is clamped to the 1-5 range.
        $second = end($questions);
        $this->assertSame(5, (int) $second->difficulty);
    }

    public function test_import_quiz_saves_general_feedback(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        $json = $this->quiz_json(['questions' => [
            [
                'questiontext' => 'With feedback?',
                'correct' => 'Yes',
                'distractors' => ['A', 'B', 'C', 'D'],
                'category' => 'General',
                'difficulty' => 2,
                'generalfeedback' => 'Because it explains the answer.',
            ],
            [
                'questiontext' => 'Without feedback?',
                'correct' => 'Yes',
                'distractors' => ['A', 'B', 'C', 'D'],
                'category' => 'General',
                'difficulty' => 2,
            ],
        ]]);

        $result = (new importer())->import($json, (int) $user->id);

        $questions = array_values($DB->get_records(
            'local_playergames_concept_questions',
            ['cartridgeid' => $result->cartridgeid],
            'id ASC'
        ));
        $this->assertSame('Because it explains the answer.', $questions[0]->generalfeedback);
        $this->assertNull($questions[1]->generalfeedback);
    }

    public function test_import_quiz_inferred_without_type(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        // No 'type' key and no 'concepts': must be inferred as quiz from 'questions'.
        $json = $this->quiz_json(['type' => null]);
        $decoded = json_decode($json, true);
        unset($decoded['type']);

        $result = (new importer())->import(json_encode($decoded), (int) $user->id);

        $cartridge = $DB->get_record('local_playergames_cartridges', ['id' => $result->cartridgeid], '*', MUST_EXIST);
        $this->assertSame('quiz', $cartridge->type);
        $this->assertSame(2, $result->count);
    }

    public function test_import_invalid_json_throws(): void {
        $this->resetAfterTest();
        $this->expectException(\moodle_exception::class);
        (new importer())->import('{not valid json', 1);
    }

    public function test_import_missing_name_throws(): void {
        $this->resetAfterTest();
        $this->expectException(\moodle_exception::class);
        (new importer())->import($this->concept_json(['name' => '']), 1);
    }

    public function test_import_concept_no_concepts_throws(): void {
        $this->resetAfterTest();
        $this->expectException(\moodle_exception::class);
        (new importer())->import(json_encode(['name' => 'Empty', 'concepts' => []]), 1);
    }

    public function test_import_quiz_no_questions_throws(): void {
        $this->resetAfterTest();
        $this->expectException(\moodle_exception::class);
        (new importer())->import(json_encode(['name' => 'Empty quiz', 'type' => 'quiz', 'questions' => []]), 1);
    }

    public function test_save_questions_skips_incomplete(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        $cartridge = (object) [
            'name' => 'Manual quiz',
            'version' => '1.0',
            'language' => 'en',
            'type' => 'quiz',
            'timecreated' => time(),
            'timemodified' => time(),
            'uploadedby' => (int) $user->id,
            'active' => 1,
        ];
        $cartridgeid = (int) $DB->insert_record('local_playergames_cartridges', $cartridge);

        $questions = [
            ['questiontext' => 'Valid?', 'correct' => 'Yes', 'distractors' => ['No', 'Maybe', 'Never', 'Rarely']],
            ['questiontext' => '', 'correct' => 'X', 'distractors' => ['a', 'b', 'c', 'd']],
            ['questiontext' => 'No correct', 'correct' => '', 'distractors' => ['a', 'b', 'c', 'd']],
            ['questiontext' => 'Too few', 'correct' => 'X', 'distractors' => ['a', 'b', 'c']],
            ['questiontext' => 'Blank one', 'correct' => 'X', 'distractors' => ['a', 'b', 'c', '  ']],
        ];
        $result = (new importer())->save_questions($cartridgeid, $questions);

        // Only the first question is complete: the game needs four distractors that are not empty.
        $this->assertSame(1, $result->count);
        $this->assertSame(1, $DB->count_records('local_playergames_concept_questions', ['cartridgeid' => $cartridgeid]));
    }

    /**
     * A payload that is not a JSON object is reported as invalid, never as a coding error.
     *
     * Regression test: a bare number or string decoded to a non-null value and failed the array
     * signature of the schema check.
     */
    public function test_import_payloads_that_are_not_objects_are_invalid(): void {
        $this->resetAfterTest();

        foreach (['5', '"text"', 'true', 'null'] as $payload) {
            try {
                (new importer())->import($payload, 1);
                $this->fail("Accepted: {$payload}");
            } catch (\moodle_exception $e) {
                $this->assertSame('error_cartridge_invalid_json', $e->errorcode, $payload);
            }
        }
    }

    /**
     * Fields that are not text are reported as missing or skipped, never thrown on by clean_param().
     *
     * Regression test: a name, a term or a category written as a list passed the emptiness checks and
     * then made clean_param() throw a coding exception, halfway through the import.
     */
    public function test_import_fields_of_the_wrong_type_are_handled(): void {
        global $DB;
        $this->resetAfterTest();

        $cases = [
            [$this->concept_json(['name' => ['x']]), 'error_cartridge_missing_field'],
            [$this->concept_json(['concepts' => [['term' => ['x'], 'definition' => 'd']]]), 'error_concept_empty_term'],
            [$this->concept_json(['concepts' => [['term' => 't', 'definition' => ['d']]]]), 'error_concept_empty_definition'],
            [$this->concept_json(['concepts' => ['a string']]), 'error_concept_empty_term'],
            [$this->quiz_json(['questions' => [['questiontext' => ['q'], 'correct' => 'c']]]), 'error_question_empty_text'],
            [$this->quiz_json(['questions' => [['questiontext' => 'q', 'correct' => ['c']]]]), 'error_question_empty_correct'],
        ];
        foreach ($cases as [$payload, $errorcode]) {
            try {
                (new importer())->import($payload, 1);
                $this->fail("Accepted: {$payload}");
            } catch (\moodle_exception $e) {
                $this->assertSame($errorcode, $e->errorcode, $payload);
            }
        }
        $this->assertSame(0, $DB->count_records('local_playergames_cartridges'));

        // Optional fields of the wrong type are ignored, and the rest of the cartridge is imported.
        $result = (new importer())->import($this->concept_json([
            'author' => ['x'],
            'version' => ['1'],
            'concepts' => [['term' => 'XP', 'definition' => 'Experience', 'category' => ['Core'], 'difficulty' => ['x']]],
        ]), 1);
        $this->assertSame(1, $result->count);
        $cartridge = $DB->get_record('local_playergames_cartridges', ['id' => $result->cartridgeid], '*', MUST_EXIST);
        $this->assertNull($cartridge->author);
        $this->assertSame('1.0', $cartridge->version);
        $concept = $DB->get_record('local_playergames_concepts', ['cartridgeid' => $result->cartridgeid], '*', MUST_EXIST);
        $this->assertNull($concept->categoryid);
        $this->assertSame(3, (int) $concept->difficulty);
    }

    /**
     * Distractors that are not text are left out, and questions that are not objects are skipped.
     */
    public function test_save_questions_survives_fields_of_the_wrong_type(): void {
        global $DB;
        $this->resetAfterTest();

        $result = (new importer())->import($this->quiz_json(['questions' => [
            [
                'questiontext' => 'Q',
                'correct' => 'C',
                'distractors' => ['a', ['b'], 'c', 'd', 'e'],
                'category' => ['x'],
                'generalfeedback' => ['y'],
            ],
        ]]), 1);

        $this->assertSame(1, $result->count);
        $this->assertSame(
            ['C', 'a', 'c', 'd', 'e'],
            array_values($DB->get_fieldset_select(
                'local_playergames_concept_answers',
                'answertext',
                'questionid = (SELECT MIN(id) FROM {local_playergames_concept_questions} WHERE cartridgeid = ?) ORDER BY sortorder',
                [$result->cartridgeid]
            ))
        );
        $result = (new importer())->save_questions($result->cartridgeid, ['a string instead of a question']);
        $this->assertSame(0, $result->count);
    }

    /**
     * A quiz question with fewer than four distractors that are not empty is refused with the file.
     *
     * Regression test: it was saved and counted as imported, but the game drops it, so the cartridge
     * looked full while the quiz found no question; a blank distractor reached the player as an empty button.
     */
    public function test_import_refuses_a_question_without_four_real_distractors(): void {
        global $DB;
        $this->resetAfterTest();

        $cases = [
            ['Vasco', 'Colombo', 'Américo'],
            ['Vasco', 'Colombo', 'Américo', ''],
            ['Vasco', 'Colombo', 'Américo', ['x']],
            'single',
        ];
        foreach ($cases as $distractors) {
            try {
                (new importer())->import($this->quiz_json(['questions' => [
                    ['questiontext' => 'Q', 'correct' => 'C', 'distractors' => $distractors],
                ]]), 1);
                $this->fail('Accepted: ' . json_encode($distractors));
            } catch (\moodle_exception $e) {
                $this->assertSame('error_question_distractors', $e->errorcode);
            }
        }
        $this->assertSame(0, $DB->count_records('local_playergames_cartridges'));
    }

    /**
     * The cartridge and its items are saved inside one transaction of their own.
     *
     * Regression test: the cartridge row was inserted first and the items after, so a failure on the way
     * left an empty or half filled cartridge in the list. The PHPUnit runner already keeps a transaction
     * open around every test, so what is compared is the nesting depth while the items are saved.
     */
    public function test_import_saves_the_cartridge_and_its_items_in_one_transaction(): void {
        global $DB;
        $this->resetAfterTest();
        $depth = static function (): int {
            global $DB;
            return count((new \ReflectionProperty(\moodle_database::class, 'transactions'))->getValue($DB));
        };
        $before = $depth();
        $seen = [];
        $importer = new class ($depth, $seen) extends importer {
            /** @var callable Reads the transaction depth. */
            private $depth;
            /** @var array Depths seen while saving. */
            private array $seen;

            /**
             * Creates the importer.
             *
             * @param callable $depth Reads the transaction depth.
             * @param array $seen Receives the depths seen while saving.
             */
            public function __construct(callable $depth, array &$seen) {
                $this->depth = $depth;
                $this->seen = &$seen;
            }

            #[\Override]
            public function save_concepts(int $cartridgeid, array $concepts): \stdClass {
                $this->seen[] = ($this->depth)();
                return parent::save_concepts($cartridgeid, $concepts);
            }
        };

        $importer->import($this->concept_json(), 1);

        $this->assertSame([$before + 1], $seen);
        $this->assertSame($before, $depth(), 'The import left its transaction open.');
        $this->assertSame(1, $DB->count_records('local_playergames_cartridges'));
    }
}
