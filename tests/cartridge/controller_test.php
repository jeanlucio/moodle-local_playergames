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

/**
 * Unit tests for {@see controller}.
 *
 * Only the actions that matter for what they leave in the database are driven, through reflection:
 * they end in redirect(), which throws under PHPUnit, so each call expects that exception.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_playergames\cartridge\controller
 */
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

    /**
     * Runs a controller action that ends in a redirect, with the given posted fields.
     *
     * @param string $action Name of the private action method.
     * @param array $post The posted fields.
     * @return void
     */
    private function run_action(string $action, array $post): void {
        $this->setAdminUser();
        $_POST = $post;
        try {
            $controller = new controller(\context_system::instance(), 'editor', 0, 0);
            $method = new \ReflectionMethod($controller, $action);
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
     * Creates a concept cartridge.
     *
     * @param string $name Its name.
     * @return int Cartridge id.
     */
    private function make_cartridge(string $name): int {
        global $DB;
        $now = time();
        return (int) $DB->insert_record('local_playergames_cartridges', (object) [
            'name' => $name, 'version' => '1.0', 'language' => 'en', 'type' => 'concept',
            'timecreated' => $now, 'timemodified' => $now, 'uploadedby' => 0, 'active' => 1,
        ]);
    }

    /**
     * Creates a category of a cartridge.
     *
     * @param int $cartridgeid The cartridge.
     * @return int Category id.
     */
    private function make_category(int $cartridgeid): int {
        global $DB;
        return (int) $DB->insert_record('local_playergames_categories', (object) [
            'cartridgeid' => $cartridgeid, 'name' => 'Biology', 'sortorder' => 0, 'timecreated' => time(),
        ]);
    }

    /**
     * Regression test: a hand-edited request could store the category of another cartridge on a concept,
     * which then showed no category in its editor, exported another cartridge's category name and was left
     * dangling when that category was deleted.
     */
    public function test_a_concept_cannot_take_the_category_of_another_cartridge(): void {
        global $DB;
        $this->resetAfterTest();
        $mine = $this->make_cartridge('Mine');
        $other = $this->make_cartridge('Other');
        $foreign = $this->make_category($other);

        $this->run_action('action_add_concept', [
            'cartridgeid' => $mine, 'term' => 'cell', 'definition' => 'A unit.', 'categoryid' => $foreign,
        ]);

        $concept = $DB->get_record('local_playergames_concepts', ['cartridgeid' => $mine], '*', MUST_EXIST);
        $this->assertNull($concept->categoryid);
    }

    public function test_a_concept_keeps_a_category_of_its_own_cartridge(): void {
        global $DB;
        $this->resetAfterTest();
        $mine = $this->make_cartridge('Mine');
        $category = $this->make_category($mine);

        $this->run_action('action_add_concept', [
            'cartridgeid' => $mine, 'term' => 'cell', 'definition' => 'A unit.', 'categoryid' => $category,
        ]);

        $concept = $DB->get_record('local_playergames_concepts', ['cartridgeid' => $mine], '*', MUST_EXIST);
        $this->assertSame($category, (int) $concept->categoryid);
    }

    public function test_editing_a_concept_cannot_move_it_to_the_category_of_another_cartridge(): void {
        global $DB;
        $this->resetAfterTest();
        $mine = $this->make_cartridge('Mine');
        $other = $this->make_cartridge('Other');
        $own = $this->make_category($mine);
        $foreign = $this->make_category($other);
        $conceptid = (int) $DB->insert_record('local_playergames_concepts', (object) [
            'cartridgeid' => $mine, 'term' => 'cell', 'definition' => 'A unit.', 'categoryid' => $own,
            'difficulty' => 3,
        ]);

        $this->run_action('action_edit_concept', [
            'cartridgeid' => $mine, 'concept_id' => $conceptid, 'term' => 'cell', 'definition' => 'A unit.',
            'categoryid' => $foreign,
        ]);

        $this->assertNull($DB->get_field('local_playergames_concepts', 'categoryid', ['id' => $conceptid]));
    }
}
