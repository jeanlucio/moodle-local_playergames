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

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Unit tests for {@see importer}.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(importer::class)]
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
            ['questiontext' => 'Valid?', 'correct' => 'Yes', 'distractors' => ['No', 'Maybe']],
            ['questiontext' => '', 'correct' => 'X', 'distractors' => ['a', 'b']],
            ['questiontext' => 'No correct', 'correct' => '', 'distractors' => ['a', 'b']],
        ];
        $result = (new importer())->save_questions($cartridgeid, $questions);

        // Only the first question is complete.
        $this->assertSame(1, $result->count);
        $this->assertSame(1, $DB->count_records('local_playergames_concept_questions', ['cartridgeid' => $cartridgeid]));
    }
}
