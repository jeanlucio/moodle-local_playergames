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
 * Tests for the cartridge page controller.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\cartridge;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Unit tests for {@see controller}.
 *
 * Only the actions that matter for what they leave in the database are driven, through reflection:
 * they end in redirect(), which throws under PHPUnit, so each call expects that exception.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(controller::class)]
final class controller_test extends \advanced_testcase {
    /**
     * Posts the AI quiz preview back to the controller and runs the save action.
     *
     * @param array $post The posted fields.
     * @return void
     */
    private function save_ai_quiz(array $post): void {
        $this->setAdminUser();
        $_POST = $post;
        try {
            $controller = new controller(\context_system::instance(), 'ai', 0, 0, 'quiz');
            $method = new \ReflectionMethod($controller, 'action_save_ai_quiz_cartridge');
            $method->setAccessible(true);
            try {
                $method->invoke($controller);
            } catch (\moodle_exception $e) {
                // The action ends by redirecting, which throws under PHPUnit.
                $this->assertSame('redirecterrordetected', $e->errorcode);
            }
        } finally {
            $_POST = [];
        }
    }

    /**
     * Regression test: the cartridge was created before the questions were looked at, so a preview with no
     * usable row (what a failed AI call used to produce) left an active, empty quiz cartridge behind.
     */
    public function test_saving_a_preview_without_questions_creates_no_cartridge(): void {
        global $DB;
        $this->resetAfterTest();

        $this->save_ai_quiz(['ai_cartridge_name' => 'Empty']);

        $this->assertSame(0, $DB->count_records('local_playergames_cartridges'));
    }

    public function test_saving_a_preview_whose_rows_are_all_unusable_creates_no_cartridge(): void {
        global $DB;
        $this->resetAfterTest();

        $this->save_ai_quiz([
            'ai_cartridge_name' => 'Broken',
            'question_text' => [0 => 'Question?'],
            'question_correct' => [0 => 'Right'],
            'question_distractor_0' => [0 => 'One'],
        ]);

        $this->assertSame(0, $DB->count_records('local_playergames_cartridges'));
    }

    public function test_saving_a_good_preview_creates_the_cartridge_with_its_question(): void {
        global $DB;
        $this->resetAfterTest();

        $this->save_ai_quiz([
            'ai_cartridge_name' => 'Good',
            'question_text' => [0 => 'Question?'],
            'question_correct' => [0 => 'Right'],
            'question_distractor_0' => [0 => 'One'],
            'question_distractor_1' => [0 => 'Two'],
            'question_distractor_2' => [0 => 'Three'],
            'question_distractor_3' => [0 => 'Four'],
        ]);

        $cartridge = $DB->get_record('local_playergames_cartridges', ['name' => 'Good'], '*', MUST_EXIST);
        $this->assertSame('quiz', $cartridge->type);
        $this->assertSame(1, $DB->count_records('local_playergames_concept_questions', [
            'cartridgeid' => $cartridge->id,
        ]));
    }
}
