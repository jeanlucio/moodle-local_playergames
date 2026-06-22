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
 * Tests for the quiz question loader.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\games;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Unit tests for {@see quiz_loader} cartridge source.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(quiz_loader::class)]
final class quiz_loader_test extends \advanced_testcase {
    /**
     * Creates a quiz-type cartridge.
     *
     * @param int $active Whether the cartridge is active.
     * @return int Cartridge id.
     */
    private function make_cartridge(int $active = 1): int {
        global $DB;
        $now = time();
        return (int) $DB->insert_record('local_playergames_cartridges', (object) [
            'name' => 'Quiz', 'version' => '1.0', 'language' => 'en', 'type' => 'quiz',
            'timecreated' => $now, 'timemodified' => $now, 'uploadedby' => 0, 'active' => $active,
        ]);
    }

    /**
     * Adds a question with one correct answer and N distractors.
     *
     * @param int $cartridgeid Owning cartridge.
     * @param int $distractors Number of distractors to add.
     * @param int $difficulty Question difficulty.
     * @param int|null $categoryid Optional category id.
     * @return int Question id.
     */
    private function add_question(
        int $cartridgeid,
        int $distractors = 4,
        int $difficulty = 3,
        ?int $categoryid = null
    ): int {
        global $DB;
        $qid = (int) $DB->insert_record('local_playergames_concept_questions', (object) [
            'conceptid' => null, 'cartridgeid' => $cartridgeid, 'questiontext' => 'Q?',
            'source' => 'manual', 'difficulty' => $difficulty, 'categoryid' => $categoryid,
            'timecreated' => time(),
        ]);
        $DB->insert_record('local_playergames_concept_answers', (object) [
            'questionid' => $qid, 'answertext' => 'Correct', 'iscorrect' => 1, 'sortorder' => 0,
        ], false);
        for ($i = 1; $i <= $distractors; $i++) {
            $DB->insert_record('local_playergames_concept_answers', (object) [
                'questionid' => $qid, 'answertext' => "Wrong {$i}", 'iscorrect' => 0, 'sortorder' => $i,
            ], false);
        }
        return $qid;
    }

    public function test_loads_complete_cartridge_questions(): void {
        $this->resetAfterTest();
        $cartridgeid = $this->make_cartridge();
        $this->add_question($cartridgeid);
        $this->add_question($cartridgeid);

        $questions = (new quiz_loader())->load_session(10, quiz_loader::SOURCE_CARTRIDGES);

        $this->assertCount(2, $questions);
        foreach ($questions as $q) {
            $this->assertSame('cartridge', $q->source);
            // One correct plus four distractors.
            $this->assertCount(5, $q->answers);
            $correct = array_filter($q->answers, fn($a): bool => $a->correct);
            $this->assertCount(1, $correct);
        }
    }

    public function test_season_config_singular_cartridge_source_loads_questions(): void {
        $this->resetAfterTest();
        $cartridgeid = $this->make_cartridge();
        $this->add_question($cartridgeid);
        $this->add_question($cartridgeid);

        // Season config saves the cartridge-only source as the singular
        // 'cartridge'; the loader must treat it like its own 'cartridges'.
        $questions = (new quiz_loader())->load_session(10, season_game_config::SOURCE_CARTRIDGE);

        $this->assertCount(2, $questions);
    }

    public function test_skips_questions_with_too_few_distractors(): void {
        $this->resetAfterTest();
        $cartridgeid = $this->make_cartridge();
        $this->add_question($cartridgeid, 4);
        $this->add_question($cartridgeid, 2);

        $questions = (new quiz_loader())->load_session(10, quiz_loader::SOURCE_CARTRIDGES);

        // The two-distractor question cannot fill five slots and is dropped.
        $this->assertCount(1, $questions);
    }

    public function test_respects_session_size(): void {
        $this->resetAfterTest();
        $cartridgeid = $this->make_cartridge();
        $this->add_question($cartridgeid);
        $this->add_question($cartridgeid);
        $this->add_question($cartridgeid);

        $questions = (new quiz_loader())->load_session(2, quiz_loader::SOURCE_CARTRIDGES);

        $this->assertCount(2, $questions);
    }

    public function test_null_cartridgeids_uses_active_cartridges_only(): void {
        $this->resetAfterTest();
        $active = $this->make_cartridge(1);
        $inactive = $this->make_cartridge(0);
        $this->add_question($active);
        $this->add_question($inactive);

        $questions = (new quiz_loader())->load_session(10, quiz_loader::SOURCE_CARTRIDGES);

        $this->assertCount(1, $questions);
    }

    public function test_specific_cartridgeids_filter(): void {
        $this->resetAfterTest();
        $one = $this->make_cartridge();
        $two = $this->make_cartridge();
        $qone = $this->add_question($one);
        $this->add_question($two);

        $questions = (new quiz_loader())->load_session(
            10,
            quiz_loader::SOURCE_CARTRIDGES,
            0,
            json_encode([$one])
        );

        $this->assertCount(1, $questions);
        $this->assertSame($qone, $questions[0]->sourceid);
    }

    public function test_metadata_is_carried_to_dto(): void {
        $this->resetAfterTest();
        $cartridgeid = $this->make_cartridge();
        // The loader copies categoryid through; 77 is an opaque passthrough value here.
        $this->add_question($cartridgeid, 4, 5, 77);

        $questions = (new quiz_loader())->load_session(10, quiz_loader::SOURCE_CARTRIDGES);

        $this->assertCount(1, $questions);
        $this->assertSame(5, $questions[0]->difficulty);
        $this->assertSame(77, $questions[0]->categoryid);
    }
}
