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
 * Tests for the cartridge exporter and import/export round-trip.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\cartridge;

/**
 * Unit tests for {@see exporter}.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_playergames\cartridge\exporter
 */
final class exporter_test extends \advanced_testcase {
    /**
     * Builds a JSON string for a concept cartridge.
     *
     * @return string Encoded JSON.
     */
    private function concept_json(): string {
        return json_encode([
            'name' => 'Concept pack',
            'version' => '2.3',
            'language' => 'en',
            'author' => 'Jean Lúcio',
            'concepts' => [
                ['term' => 'Alpha', 'definition' => 'First'],
                ['term' => 'Beta', 'definition' => 'Second'],
                ['term' => 'Solo', 'definition' => 'Alone'],
            ],
        ]);
    }

    /**
     * Builds a JSON string for a quiz cartridge.
     *
     * @return string Encoded JSON.
     */
    private function quiz_json(): string {
        return json_encode([
            'name' => 'Quiz pack',
            'version' => '1.4',
            'language' => 'pt_br',
            'author' => 'Quiz Author',
            'type' => 'quiz',
            'questions' => [
                [
                    'questiontext' => 'Capital of France?',
                    'correct' => 'Paris',
                    'distractors' => ['London', 'Berlin', 'Madrid', 'Rome'],
                ],
            ],
        ]);
    }

    public function test_build_concept_export_structure(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $result = (new importer())->import($this->concept_json(), (int) $user->id);
        $cartridge = $DB->get_record('local_playergames_cartridges', ['id' => $result->cartridgeid], '*', MUST_EXIST);

        $data = (new exporter())->build($cartridge);

        $this->assertSame('concept', $data['type']);
        $this->assertSame('Concept pack', $data['name']);
        $this->assertSame('2.3', $data['version']);
        $this->assertSame('en', $data['language']);
        $this->assertSame('Jean Lúcio', $data['author']);
        $this->assertArrayHasKey('concepts', $data);
        $this->assertCount(3, $data['concepts']);

        $this->assertSame(['term' => 'Alpha', 'definition' => 'First'], $data['concepts'][0]);
    }

    public function test_build_quiz_export_structure(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $result = (new importer())->import($this->quiz_json(), (int) $user->id);
        $cartridge = $DB->get_record('local_playergames_cartridges', ['id' => $result->cartridgeid], '*', MUST_EXIST);

        $data = (new exporter())->build($cartridge);

        $this->assertSame('quiz', $data['type']);
        $this->assertSame('Quiz pack', $data['name']);
        $this->assertSame('1.4', $data['version']);
        $this->assertSame('pt_br', $data['language']);
        $this->assertSame('Quiz Author', $data['author']);
        $this->assertArrayHasKey('questions', $data);
        $this->assertCount(1, $data['questions']);
        $q = $data['questions'][0];
        $this->assertSame('Paris', $q['correct']);
        $this->assertCount(4, $q['distractors']);
        $this->assertContains('London', $q['distractors']);
        $this->assertSame(['questiontext', 'correct', 'distractors'], array_keys($q));
    }

    public function test_quiz_export_includes_general_feedback(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        $json = json_encode([
            'name' => 'Feedback pack',
            'version' => '1.0',
            'language' => 'en',
            'author' => 'A',
            'type' => 'quiz',
            'questions' => [
                [
                    'questiontext' => 'With feedback?',
                    'correct' => 'Yes',
                    'distractors' => ['A', 'B', 'C', 'D'],
                    'generalfeedback' => 'Explains the answer.',
                ],
                [
                    'questiontext' => 'Without feedback?',
                    'correct' => 'Yes',
                    'distractors' => ['A', 'B', 'C', 'D'],
                ],
            ],
        ]);
        $result = (new importer())->import($json, (int) $user->id);
        $cartridge = $DB->get_record(
            'local_playergames_cartridges',
            ['id' => $result->cartridgeid],
            '*',
            MUST_EXIST
        );

        $data = (new exporter())->build($cartridge);

        $byquestion = [];
        foreach ($data['questions'] as $q) {
            $byquestion[$q['questiontext']] = $q;
        }
        $this->assertSame('Explains the answer.', $byquestion['With feedback?']['generalfeedback']);
        $this->assertArrayNotHasKey('generalfeedback', $byquestion['Without feedback?']);
    }

    public function test_concept_round_trip(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $importer = new importer();

        $first = $importer->import($this->concept_json(), (int) $user->id);
        $cartridge = $DB->get_record('local_playergames_cartridges', ['id' => $first->cartridgeid], '*', MUST_EXIST);
        $exported = (new exporter())->build($cartridge);

        $second = $importer->import(json_encode($exported), (int) $user->id);

        $this->assertSame($first->count, $second->count);
        $secondcartridge = $DB->get_record(
            'local_playergames_cartridges',
            ['id' => $second->cartridgeid],
            '*',
            MUST_EXIST
        );
        // Root metadata must survive the import.
        $this->assertSame('concept', $secondcartridge->type);
        $this->assertSame('Concept pack', $secondcartridge->name);
        $this->assertSame('2.3', $secondcartridge->version);
        $this->assertSame('en', $secondcartridge->language);
        $this->assertSame('Jean Lúcio', $secondcartridge->author);

        $reexported = (new exporter())->build($secondcartridge);
        // The whole payload — metadata and concepts — must match the first export.
        $this->assertEquals($exported, $reexported);
    }

    public function test_quiz_round_trip(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $importer = new importer();

        $first = $importer->import($this->quiz_json(), (int) $user->id);
        $cartridge = $DB->get_record('local_playergames_cartridges', ['id' => $first->cartridgeid], '*', MUST_EXIST);
        $exported = (new exporter())->build($cartridge);

        $second = $importer->import(json_encode($exported), (int) $user->id);
        $secondcartridge = $DB->get_record(
            'local_playergames_cartridges',
            ['id' => $second->cartridgeid],
            '*',
            MUST_EXIST
        );
        $this->assertSame('quiz', $secondcartridge->type);
        $this->assertSame('Quiz pack', $secondcartridge->name);
        $this->assertSame('1.4', $secondcartridge->version);
        $this->assertSame('pt_br', $secondcartridge->language);
        $this->assertSame('Quiz Author', $secondcartridge->author);

        $reexported = (new exporter())->build($secondcartridge);
        // The whole payload — metadata and questions — must match the first export.
        $this->assertEquals($exported, $reexported);
    }
}
