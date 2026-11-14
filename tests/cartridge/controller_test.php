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

    /**
     * Posts a request to the controller the way the page does, as an administrator.
     *
     * @param array $post The posted fields, including the action.
     * @param string $tab The tab the page was on.
     * @return string The error code of what the action ended with: 'redirecterrordetected' for a redirect
     *     (which the test environment turns into an exception), another code for a refusal, '' for none.
     */
    private function dispatch(array $post, string $tab = 'library'): string {
        $this->setAdminUser();
        $_POST = $post;
        try {
            (new controller(\context_system::instance(), $tab, 0, 0))->handle_post();
        } catch (\moodle_exception $e) {
            return $e->errorcode;
        } finally {
            $_POST = [];
        }
        return '';
    }

    /**
     * Creates a quiz cartridge.
     *
     * @param string $name Its name.
     * @return int Cartridge id.
     */
    private function make_quiz_cartridge(string $name = 'Quiz'): int {
        global $DB;
        $id = $this->make_cartridge($name);
        $DB->set_field('local_playergames_cartridges', 'type', 'quiz', ['id' => $id]);
        return $id;
    }

    /**
     * Adds a multiple-choice question to a quiz cartridge, as the editor saves it.
     *
     * @param int $cartridgeid The cartridge.
     * @param string $text The question.
     * @return int Question id.
     */
    private function make_question(int $cartridgeid, string $text = 'Capital of France?'): int {
        global $DB;
        $questionid = (int) $DB->insert_record('local_playergames_concept_questions', (object) [
            'conceptid' => null, 'cartridgeid' => $cartridgeid, 'questiontext' => $text, 'source' => 'manual',
            'difficulty' => 3, 'categoryid' => null, 'timecreated' => time(),
        ]);
        foreach (['Paris', 'Rome', 'Madrid', 'Berlin', 'Lisbon'] as $order => $answer) {
            $DB->insert_record('local_playergames_concept_answers', (object) [
                'questionid' => $questionid, 'answertext' => $answer, 'iscorrect' => (int) ($order === 0),
                'sortorder' => $order,
            ], false);
        }
        return $questionid;
    }

    /**
     * An action name the controller does not know does nothing, and no action is refused at all.
     */
    public function test_an_unknown_action_does_nothing(): void {
        global $DB;
        $this->resetAfterTest();

        $this->assertSame('', $this->dispatch(['action' => 'unknown']));
        $this->assertSame(0, $DB->count_records('local_playergames_cartridges'));
    }

    /**
     * A cartridge is created from scratch with the type, language and author given, trimmed to fit.
     */
    public function test_creating_a_cartridge_stores_what_was_typed(): void {
        global $DB;
        $this->resetAfterTest();
        $admin = get_admin();

        $code = $this->dispatch([
            'action' => 'create_cartridge', 'cartridge_name' => str_repeat('n', 300), 'cartridge_language' => 'pt_br',
            'cartridge_type' => 'quiz', 'cartridge_author' => 'Ana',
        ]);
        $this->dispatch(['action' => 'create_cartridge', 'cartridge_name' => 'Odd', 'cartridge_type' => 'weird']);

        $this->assertSame('redirecterrordetected', $code);
        $quiz = $DB->get_record_select('local_playergames_cartridges', 'name <> :odd', ['odd' => 'Odd'], '*', MUST_EXIST);
        $this->assertSame(255, \core_text::strlen($quiz->name));
        $this->assertSame('quiz', $quiz->type);
        $this->assertSame('pt_br', $quiz->language);
        $this->assertSame('Ana', $quiz->author);
        $this->assertSame((int) $admin->id, (int) $quiz->uploadedby);
        $this->assertSame(1, (int) $quiz->active);
        $odd = $DB->get_record('local_playergames_cartridges', ['name' => 'Odd'], '*', MUST_EXIST);
        $this->assertSame('concept', $odd->type);
        $this->assertNull($odd->author);
    }

    /**
     * A concept goes into the cartridge the request names, and an empty term saves nothing.
     */
    public function test_adding_editing_and_deleting_a_concept(): void {
        global $DB;
        $this->resetAfterTest();
        $mine = $this->make_cartridge('Mine');
        $other = $this->make_cartridge('Other');

        $this->dispatch(['action' => 'add_concept', 'cartridgeid' => $mine, 'term' => ' ', 'definition' => 'Empty']);
        $this->assertSame(0, $DB->count_records('local_playergames_concepts'));

        $this->dispatch(['action' => 'add_concept', 'cartridgeid' => $mine, 'term' => 'cell', 'definition' => 'A unit']);
        $conceptid = (int) $DB->get_field('local_playergames_concepts', 'id', ['cartridgeid' => $mine], MUST_EXIST);

        $this->dispatch([
            'action' => 'edit_concept', 'cartridgeid' => $mine, 'concept_id' => $conceptid,
            'term' => 'cell', 'definition' => 'The basic unit', 'difficulty' => 4,
        ]);
        $this->assertSame('The basic unit', $DB->get_field('local_playergames_concepts', 'definition', ['id' => $conceptid]));
        $this->assertSame(4, (int) $DB->get_field('local_playergames_concepts', 'difficulty', ['id' => $conceptid]));

        $this->dispatch([
            'action' => 'edit_concept', 'cartridgeid' => $mine, 'concept_id' => $conceptid, 'term' => '', 'definition' => 'x',
        ]);
        $this->assertSame('cell', $DB->get_field('local_playergames_concepts', 'term', ['id' => $conceptid]));

        $this->dispatch(['action' => 'delete_concept', 'cartridgeid' => $other, 'concept_id' => $conceptid]);
        $this->assertTrue($DB->record_exists('local_playergames_concepts', ['id' => $conceptid]));

        $this->dispatch(['action' => 'delete_concept', 'cartridgeid' => $mine, 'concept_id' => $conceptid]);
        $this->assertFalse($DB->record_exists('local_playergames_concepts', ['id' => $conceptid]));
    }

    /**
     * A concept cannot be added to, or edited in, a cartridge that does not hold it.
     */
    public function test_a_concept_of_another_cartridge_cannot_be_reached(): void {
        global $DB;
        $this->resetAfterTest();
        $mine = $this->make_cartridge('Mine');
        $other = $this->make_cartridge('Other');
        $conceptid = (int) $DB->insert_record('local_playergames_concepts', (object) [
            'cartridgeid' => $other, 'term' => 'cell', 'definition' => 'A unit', 'difficulty' => 3,
        ]);

        $this->assertSame(
            'error_cartridge_notfound',
            $this->dispatch(['action' => 'add_concept', 'cartridgeid' => 999999, 'term' => 'x', 'definition' => 'y'])
        );
        $this->assertSame('error_cartridge_notfound', $this->dispatch([
            'action' => 'edit_concept', 'cartridgeid' => $mine, 'concept_id' => $conceptid, 'term' => 'x', 'definition' => 'y',
        ]));
        $this->assertSame('cell', $DB->get_field('local_playergames_concepts', 'term', ['id' => $conceptid]));
    }

    /**
     * A cartridge is switched on and off, and one that does not exist is refused.
     */
    public function test_toggling_a_cartridge_flips_its_state(): void {
        global $DB;
        $this->resetAfterTest();
        $id = $this->make_cartridge('Pack');

        $this->dispatch(['action' => 'toggle_active', 'cartridgeid' => $id]);
        $this->assertSame(0, (int) $DB->get_field('local_playergames_cartridges', 'active', ['id' => $id]));
        $this->dispatch(['action' => 'toggle_active', 'cartridgeid' => $id]);
        $this->assertSame(1, (int) $DB->get_field('local_playergames_cartridges', 'active', ['id' => $id]));

        $this->assertSame('error_cartridge_notfound', $this->dispatch(['action' => 'toggle_active', 'cartridgeid' => 999999]));
    }

    /**
     * Deleting a cartridge takes it with its content and tells the log, once.
     */
    public function test_deleting_a_cartridge_removes_it_and_logs_the_event(): void {
        global $DB;
        $this->resetAfterTest();
        $sink = $this->redirectEvents();
        $id = $this->make_cartridge('Pack');
        $DB->insert_record('local_playergames_concepts', (object) [
            'cartridgeid' => $id, 'term' => 'cell', 'definition' => 'A unit', 'difficulty' => 3,
        ]);

        $this->dispatch(['action' => 'delete_cartridge', 'cartridgeid' => $id]);
        $this->dispatch(['action' => 'delete_cartridge', 'cartridgeid' => $id]);

        $this->assertFalse($DB->record_exists('local_playergames_cartridges', ['id' => $id]));
        $this->assertSame(0, $DB->count_records('local_playergames_concepts'));
        $events = array_filter(
            $sink->get_events(),
            fn($event): bool => $event instanceof \local_playergames\event\cartridge_deleted
        );
        $this->assertCount(1, $events);
    }

    /**
     * Categories are added, renamed and deleted inside the cartridge the request names only.
     */
    public function test_adding_renaming_and_deleting_a_category(): void {
        global $DB;
        $this->resetAfterTest();
        $mine = $this->make_cartridge('Mine');
        $other = $this->make_cartridge('Other');

        $this->dispatch(['action' => 'add_category', 'cartridgeid' => $mine, 'category_name' => 'Biology']);
        $categoryid = (int) $DB->get_field('local_playergames_categories', 'id', ['cartridgeid' => $mine], MUST_EXIST);
        $this->assertSame('error_cartridge_notfound', $this->dispatch([
            'action' => 'add_category', 'cartridgeid' => 999999, 'category_name' => 'Ghost',
        ]));

        $this->dispatch([
            'action' => 'rename_category', 'cartridgeid' => $mine, 'category_id' => $categoryid, 'category_name' => 'Botany',
        ]);
        $this->assertSame('Botany', $DB->get_field('local_playergames_categories', 'name', ['id' => $categoryid]));
        $this->dispatch([
            'action' => 'rename_category', 'cartridgeid' => $other, 'category_id' => $categoryid, 'category_name' => 'Stolen',
        ]);
        $this->assertSame('Botany', $DB->get_field('local_playergames_categories', 'name', ['id' => $categoryid]));

        $this->dispatch(['action' => 'delete_category', 'cartridgeid' => $other, 'category_id' => $categoryid]);
        $this->assertTrue($DB->record_exists('local_playergames_categories', ['id' => $categoryid]));
        $this->dispatch(['action' => 'delete_category', 'cartridgeid' => $mine, 'category_id' => $categoryid]);
        $this->assertFalse($DB->record_exists('local_playergames_categories', ['id' => $categoryid]));
    }

    /**
     * A question is added with its right answer and four distractors, or refused when any is missing.
     */
    public function test_adding_a_quiz_question(): void {
        global $DB;
        $this->resetAfterTest();
        $quiz = $this->make_quiz_cartridge();
        $category = $this->make_category($quiz);
        $post = [
            'action' => 'add_quiz_question', 'cartridgeid' => $quiz, 'questiontext' => 'Capital of France?',
            'correct' => 'Paris', 'distractor_0' => 'Rome', 'distractor_1' => 'Madrid', 'distractor_2' => 'Berlin',
            'distractor_3' => 'Lisbon', 'difficulty' => 9, 'categoryid' => $category, 'generalfeedback' => 'Seine',
        ];

        $this->dispatch(array_merge($post, ['distractor_3' => ' ']));
        $this->assertSame(0, $DB->count_records('local_playergames_concept_questions'));

        $this->dispatch($post);

        $question = $DB->get_record('local_playergames_concept_questions', ['cartridgeid' => $quiz], '*', MUST_EXIST);
        $this->assertSame('manual', $question->source);
        $this->assertSame(5, (int) $question->difficulty);
        $this->assertSame($category, (int) $question->categoryid);
        $this->assertSame('Seine', $question->generalfeedback);
        $answers = $DB->get_records('local_playergames_concept_answers', ['questionid' => $question->id], 'sortorder ASC');
        $this->assertSame(['Paris', 'Rome', 'Madrid', 'Berlin', 'Lisbon'], array_values(array_column($answers, 'answertext')));
        $this->assertSame([1, 0, 0, 0, 0], array_map('intval', array_values(array_column($answers, 'iscorrect'))));
    }

    /**
     * A question only goes into a quiz cartridge.
     */
    public function test_a_question_cannot_be_added_to_a_concept_cartridge(): void {
        global $DB;
        $this->resetAfterTest();
        $concepts = $this->make_cartridge('Concepts');

        $code = $this->dispatch([
            'action' => 'add_quiz_question', 'cartridgeid' => $concepts, 'questiontext' => 'Q?', 'correct' => 'A',
            'distractor_0' => 'B', 'distractor_1' => 'C', 'distractor_2' => 'D', 'distractor_3' => 'E',
        ]);

        $this->assertSame('invalidrecord', $code);
        $this->assertSame(0, $DB->count_records('local_playergames_concept_questions'));
    }

    /**
     * Editing a question replaces its answers, keeps the right one marked and refuses an incomplete form.
     */
    public function test_editing_a_quiz_question(): void {
        global $DB;
        $this->resetAfterTest();
        $quiz = $this->make_quiz_cartridge();
        $questionid = $this->make_question($quiz);
        $post = [
            'action' => 'edit_quiz_question', 'cartridgeid' => $quiz, 'question_id' => $questionid,
            'questiontext' => 'Capital of Italy?', 'correct' => 'Rome', 'distractor_0' => 'Paris',
            'distractor_1' => 'Madrid', 'distractor_2' => 'Berlin', 'distractor_3' => 'Lisbon', 'difficulty' => 0,
        ];

        $this->dispatch(array_merge($post, ['correct' => '']));
        $this->assertSame(
            'Capital of France?',
            $DB->get_field('local_playergames_concept_questions', 'questiontext', ['id' => $questionid])
        );

        $this->dispatch($post);

        $question = $DB->get_record('local_playergames_concept_questions', ['id' => $questionid], '*', MUST_EXIST);
        $this->assertSame('Capital of Italy?', $question->questiontext);
        $this->assertSame(1, (int) $question->difficulty);
        $this->assertNull($question->generalfeedback);
        $answers = $DB->get_records('local_playergames_concept_answers', ['questionid' => $questionid], 'sortorder ASC');
        $this->assertCount(5, $answers);
        $this->assertSame('Rome', reset($answers)->answertext);
        $this->assertSame(1, (int) reset($answers)->iscorrect);
    }

    /**
     * A question is only reachable through the cartridge that holds it.
     */
    public function test_a_question_of_another_cartridge_cannot_be_edited_or_deleted(): void {
        global $DB;
        $this->resetAfterTest();
        $mine = $this->make_quiz_cartridge('Mine');
        $other = $this->make_quiz_cartridge('Other');
        $questionid = $this->make_question($other);

        $edit = $this->dispatch([
            'action' => 'edit_quiz_question', 'cartridgeid' => $mine, 'question_id' => $questionid,
            'questiontext' => 'Hijacked?', 'correct' => 'A', 'distractor_0' => 'B', 'distractor_1' => 'C',
            'distractor_2' => 'D', 'distractor_3' => 'E',
        ]);
        $delete = $this->dispatch(['action' => 'delete_quiz_question', 'cartridgeid' => $mine, 'question_id' => $questionid]);

        $this->assertSame('invalidrecord', $edit);
        $this->assertSame('invalidrecord', $delete);
        $this->assertSame(
            'Capital of France?',
            $DB->get_field('local_playergames_concept_questions', 'questiontext', ['id' => $questionid])
        );
        $this->assertSame(5, $DB->count_records('local_playergames_concept_answers', ['questionid' => $questionid]));
    }

    /**
     * Deleting a question takes its answers with it.
     */
    public function test_deleting_a_quiz_question_removes_its_answers(): void {
        global $DB;
        $this->resetAfterTest();
        $quiz = $this->make_quiz_cartridge();
        $questionid = $this->make_question($quiz);
        $keptid = $this->make_question($quiz, 'Kept?');

        $this->dispatch(['action' => 'delete_quiz_question', 'cartridgeid' => $quiz, 'question_id' => $questionid]);

        $this->assertFalse($DB->record_exists('local_playergames_concept_questions', ['id' => $questionid]));
        $this->assertSame(0, $DB->count_records('local_playergames_concept_answers', ['questionid' => $questionid]));
        $this->assertSame(5, $DB->count_records('local_playergames_concept_answers', ['questionid' => $keptid]));
    }

    /**
     * An uploaded file that PHP did not receive through an upload creates nothing.
     */
    public function test_importing_without_a_real_upload_creates_nothing(): void {
        global $DB;
        $this->resetAfterTest();
        $path = make_request_directory() . '/cartridge.json';
        file_put_contents($path, json_encode(['name' => 'N', 'concepts' => [['term' => 'a', 'definition' => 'b']]]));

        $this->assertSame('redirecterrordetected', $this->dispatch(['action' => 'import_json']));
        $_FILES = ['jsonfile' => ['error' => UPLOAD_ERR_NO_FILE, 'tmp_name' => '']];
        $this->assertSame('redirecterrordetected', $this->dispatch(['action' => 'import_json']));
        // A path the request did not receive from the upload is never read, even when the file exists.
        $_FILES = ['jsonfile' => ['error' => UPLOAD_ERR_OK, 'tmp_name' => $path]];
        $this->assertSame('redirecterrordetected', $this->dispatch(['action' => 'import_json']));
        $_FILES = [];

        $this->assertSame(0, $DB->count_records('local_playergames_cartridges'));
    }

    /**
     * A global glossary is copied into a cartridge, and one that cannot be copied leaves nothing behind.
     */
    public function test_importing_a_glossary_creates_a_cartridge(): void {
        global $DB;
        $this->resetAfterTest();
        $sink = $this->redirectEvents();
        $course = $this->getDataGenerator()->create_course();
        $glossary = $this->getDataGenerator()->create_module('glossary', ['course' => $course->id, 'name' => 'Terms']);
        $DB->set_field('glossary', 'globalglossary', 1, ['id' => $glossary->id]);
        $this->getDataGenerator()->get_plugin_generator('mod_glossary')->create_content($glossary, [
            'concept' => 'Mitosis', 'definition' => 'Cell division', 'approved' => 1,
        ]);

        $this->dispatch(['action' => 'import_glossary', 'glossaryid' => 999999]);
        $this->assertSame(0, $DB->count_records('local_playergames_cartridges'));

        $this->dispatch(['action' => 'import_glossary', 'glossaryid' => $glossary->id]);

        $cartridge = $DB->get_record('local_playergames_cartridges', [], '*', MUST_EXIST);
        $this->assertSame(1, $DB->count_records('local_playergames_concepts', ['cartridgeid' => $cartridge->id]));
        $imported = array_filter(
            $sink->get_events(),
            fn($event): bool => $event instanceof \local_playergames\event\cartridge_imported
        );
        $this->assertCount(1, $imported);
    }

    /**
     * The concepts of an AI preview become a cartridge with the author given, and the log hears of it.
     */
    public function test_saving_an_ai_preview_creates_the_cartridge_with_its_concepts(): void {
        global $DB;
        $this->resetAfterTest();
        $sink = $this->redirectEvents();

        $this->dispatch([
            'action' => 'save_ai_cartridge', 'ai_cartridge_name' => 'Cells', 'ai_language' => 'en', 'ai_author' => 'Bot',
            'concept_term' => [0 => 'Nucleus', 1 => 'Membrane'],
            'concept_definition' => [0 => 'Holds the DNA', 1 => 'Wraps the cell'],
            'concept_category' => [0 => 'Parts', 1 => 'Parts'],
            'concept_difficulty' => [0 => 2, 1 => 3],
        ], 'ai');

        $cartridge = $DB->get_record('local_playergames_cartridges', ['name' => 'Cells'], '*', MUST_EXIST);
        $this->assertSame('Bot', $cartridge->author);
        $this->assertSame(2, $DB->count_records('local_playergames_concepts', ['cartridgeid' => $cartridge->id]));
        $this->assertSame(1, $DB->count_records('local_playergames_categories', ['cartridgeid' => $cartridge->id]));
        $this->assertNotEmpty(array_filter(
            $sink->get_events(),
            fn($event): bool => $event instanceof \local_playergames\event\cartridge_imported
        ));
    }

    /**
     * Runs a generation action, which does not redirect, and reads the state the page then shows.
     *
     * @param string $action Name of the private action method.
     * @param array $post The posted fields.
     * @return array The controller state: tab, aisubtab, aierror, aipreview, quizerror, quizpreview, aiformdata, quizformdata.
     */
    private function generate(string $action, array $post): array {
        $this->setAdminUser();
        $_POST = $post;
        try {
            $controller = new controller(\context_system::instance(), 'ai', 0, 0);
            (new \ReflectionMethod($controller, $action))->invoke($controller);
        } finally {
            $_POST = [];
        }
        $state = [];
        foreach (['tab', 'aisubtab', 'aierror', 'aipreview', 'quizerror', 'quizpreview', 'aiformdata', 'quizformdata'] as $name) {
            $state[$name] = (new \ReflectionProperty($controller, $name))->getValue($controller);
        }
        return $state;
    }

    /**
     * Makes core_ai answer every request with the given text, the way a configured provider would.
     *
     * @param string $text What the model writes.
     * @return void
     */
    private function core_ai_writes(string $text): void {
        if ((new \ReflectionMethod(\core_ai\manager::class, 'get_providers_for_actions'))->isStatic()) {
            $this->markTestSkipped('The core_ai manager cannot be mocked on this Moodle version.');
        }
        $response = $this->createMock(\core_ai\aiactions\responses\response_generate_text::class);
        $response->method('get_success')->willReturn(true);
        $response->method('get_response_data')->willReturn(['generatedcontent' => $text]);
        $manager = $this->createMock(\core_ai\manager::class);
        $manager->method('get_providers_for_actions')
            ->willReturn([\core_ai\aiactions\generate_text::class => [new \stdClass()]]);
        $manager->method('process_action')->willReturn($response);
        \core\di::set(\core_ai\manager::class, $manager);
    }

    /**
     * With no AI source the page shows why, instead of a preview.
     */
    public function test_generating_concepts_without_an_ai_source_reports_it(): void {
        $this->resetAfterTest();
        if (class_exists(\local_aihub\ai::class) && \local_aihub\ai::is_available()) {
            $this->markTestSkipped('The AI hub of this site has a key, which is a source in itself.');
        }

        $state = $this->generate('action_generate_ai', ['topic' => 'Cells', 'quantity' => 2, 'difficulty' => 9]);

        $this->assertNull($state['aipreview']);
        $this->assertNotSame('', $state['aierror']);
        // What was typed is kept, within the limits the form allows.
        $this->assertSame('Cells', $state['aiformdata']->topic);
        $this->assertSame(10, $state['aiformdata']->quantity);
        $this->assertSame(5, $state['aiformdata']->difficulty);
        $this->assertSame('ai', $state['tab']);
    }

    /**
     * The concepts the model writes are indexed for the preview form, with difficulty held between 1 and 5.
     */
    public function test_generating_concepts_builds_the_preview(): void {
        $this->resetAfterTest();
        $this->core_ai_writes(json_encode(['concepts' => [
            ['term' => 'Nucleus', 'definition' => 'Holds the DNA', 'category' => 'Parts', 'difficulty' => 2],
            ['term' => 'Membrane', 'definition' => 'Wraps the cell', 'category' => 'Parts', 'difficulty' => 9],
        ]]));

        $state = $this->generate('action_generate_ai', [
            'topic' => 'Cells', 'quantity' => 500, 'difficulty' => 0, 'ai_language' => 'English',
            'ai_categories' => 'Parts, ,Functions', 'ai_context' => 'Biology notes',
        ]);

        $this->assertSame('', $state['aierror']);
        $this->assertSame('Cells', $state['aipreview']['cartridge_name']);
        $this->assertSame('English', $state['aipreview']['language']);
        $this->assertSame([0, 1], array_column($state['aipreview']['concepts'], 'index'));
        $this->assertSame(['Nucleus', 'Membrane'], array_column($state['aipreview']['concepts'], 'term'));
        $this->assertSame([2, 5], array_column($state['aipreview']['concepts'], 'difficulty'));
        $this->assertSame(100, $state['aiformdata']->quantity);
        $this->assertSame(1, $state['aiformdata']->difficulty);
        $this->assertSame('Parts, ,Functions', $state['aiformdata']->categories);
    }

    /**
     * The quiz questions the model writes are laid out for the preview form, four options each.
     */
    public function test_generating_quiz_questions_builds_the_preview(): void {
        $this->resetAfterTest();
        $this->core_ai_writes(json_encode(['questions' => [
            [
                'questiontext' => 'Capital of France?', 'correct' => 'Paris',
                'distractors' => ['Rome', 'Madrid', 'Berlin', 'Lisbon', 'Vienna'],
                'category' => 'Europe', 'difficulty' => 1, 'generalfeedback' => 'On the Seine.',
            ],
            ['questiontext' => 'Too few options?', 'correct' => 'Yes', 'distractors' => ['No']],
        ]]));

        $state = $this->generate('action_generate_ai_quiz', [
            'topic' => 'Capitals', 'quantity' => 1, 'ai_categories' => 'Europe',
        ]);

        $this->assertSame('', $state['quizerror']);
        $this->assertSame('quiz', $state['aisubtab']);
        $this->assertSame(5, $state['quizformdata']->quantity);
        $this->assertSame(3, $state['quizformdata']->difficulty);
        $questions = $state['quizpreview']['questions'];
        $this->assertCount(1, $questions);
        $this->assertSame('Paris', $questions[0]['correct']);
        $this->assertSame('On the Seine.', $questions[0]['generalfeedback']);
        $this->assertSame(['Rome', 'Madrid', 'Berlin', 'Lisbon'], array_column($questions[0]['distractors'], 'text'));
        $this->assertSame([1, 2, 3, 4], array_column($questions[0]['distractors'], 'number'));
    }

    /**
     * An answer without a single usable question is reported instead of previewed.
     */
    public function test_generating_quiz_questions_reports_an_unusable_answer(): void {
        $this->resetAfterTest();
        $this->core_ai_writes('I could not do that.');

        $state = $this->generate('action_generate_ai_quiz', ['topic' => 'Capitals', 'quantity' => 10]);

        $this->assertNull($state['quizpreview']);
        $this->assertSame(get_string('error_ai_no_questions', 'local_playergames'), $state['quizerror']);
    }

    /**
     * Renders a page of the controller for the given state.
     *
     * @param string $tab The tab.
     * @param int $cartridgeid The cartridge being edited, or 0.
     * @param int $editconceptid The concept being edited, or 0.
     * @param string $aisubtab The AI sub-tab.
     * @param int $editquestionid The question being edited, or 0.
     * @return array The data of the page.
     */
    private function page(
        string $tab,
        int $cartridgeid = 0,
        int $editconceptid = 0,
        string $aisubtab = 'concepts',
        int $editquestionid = 0
    ): array {
        global $PAGE;
        $this->setAdminUser();
        $controller = new controller(\context_system::instance(), $tab, $cartridgeid, $editconceptid, $aisubtab, $editquestionid);
        return $controller->get_renderable($PAGE->get_renderer('core'));
    }

    /**
     * The library lists every cartridge with how many items it holds, by its type.
     */
    public function test_the_library_lists_the_cartridges_with_their_item_counts(): void {
        global $DB;
        $this->resetAfterTest();
        $concepts = $this->make_cartridge('Cell biology');
        $DB->insert_record('local_playergames_concepts', (object) [
            'cartridgeid' => $concepts, 'term' => 'cell', 'definition' => 'A unit', 'difficulty' => 3,
        ]);
        $quiz = $this->make_quiz_cartridge('Capitals');
        $this->make_question($quiz);
        $this->make_question($quiz, 'Another?');

        $data = $this->page('library');

        $this->assertFalse($data['editing']);
        $this->assertStringContainsString('Cell biology', $data['content_html']);
        $this->assertStringContainsString('Capitals', $data['content_html']);
        $this->assertStringContainsString('action=export_cartridge', $data['content_html']);
        $this->assertCount(5, $data['tabs']);
    }

    /**
     * Without a cartridge the library says it is empty, and every tab of the first level renders.
     */
    public function test_every_first_level_tab_renders(): void {
        $this->resetAfterTest();

        foreach (['library', 'import', 'glossary', 'create', 'editor', 'ai'] as $tab) {
            $data = $this->page($tab);
            $this->assertNotSame('', trim($data['content_html']), $tab);
            $this->assertFalse($data['editing'], $tab);
        }
        $this->assertStringContainsString('name="action"', $this->page('import')['content_html']);
        $this->assertStringContainsString('create_cartridge', $this->page('create')['content_html']);
        $this->assertStringContainsString('create_cartridge', $this->page('editor')['content_html']);
    }

    /**
     * The glossary tab offers the global glossaries.
     */
    public function test_the_glossary_tab_offers_the_global_glossaries(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $glossary = $this->getDataGenerator()->create_module('glossary', ['course' => $course->id, 'name' => 'Shared terms']);
        $DB->set_field('glossary', 'globalglossary', 1, ['id' => $glossary->id]);
        $this->getDataGenerator()->get_plugin_generator('mod_glossary')->create_content($glossary, [
            'concept' => 'Mitosis', 'definition' => 'Cell division', 'approved' => 1,
        ]);

        $this->assertStringContainsString('Shared terms', $this->page('glossary')['content_html']);
    }

    /**
     * Without an AI source the AI tab says so instead of offering a form that cannot work.
     */
    public function test_the_ai_tab_asks_for_a_source_when_there_is_none(): void {
        $this->resetAfterTest();
        if (class_exists(\local_aihub\ai::class) && \local_aihub\ai::is_available()) {
            $this->markTestSkipped('The AI hub of this site has a key, which is a source in itself.');
        }

        $html = $this->page('ai')['content_html'];

        // The wording depends on whether the AI hub is installed; the warning and the missing form do not.
        $this->assertStringContainsString('alert-warning', $html);
        $this->assertStringNotContainsString('name="topic"', $html);
    }

    /**
     * With a source the tab offers the form, and shows the preview a generation left, on its own sub-tab.
     */
    public function test_the_ai_tab_shows_the_previews_a_generation_left(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->core_ai_writes(json_encode(['concepts' => [
            ['term' => 'Nucleus', 'definition' => 'Holds the DNA', 'category' => 'Parts', 'difficulty' => 2],
        ]]));
        $_POST = ['topic' => 'Cells', 'quantity' => 30, 'difficulty' => 4];
        $concepts = new controller(\context_system::instance(), 'ai', 0, 0);
        (new \ReflectionMethod($concepts, 'action_generate_ai'))->invoke($concepts);

        $this->core_ai_writes(json_encode(['questions' => [[
            'questiontext' => 'Capital of France?', 'correct' => 'Paris',
            'distractors' => ['Rome', 'Madrid', 'Berlin', 'Lisbon'],
        ]]]));
        $_POST = ['topic' => 'Capitals', 'quantity' => 12];
        $quiz = new controller(\context_system::instance(), 'ai', 0, 0);
        (new \ReflectionMethod($quiz, 'action_generate_ai_quiz'))->invoke($quiz);
        $_POST = [];

        $conceptshtml = $concepts->get_renderable($PAGE->get_renderer('core'))['content_html'];
        $quizhtml = $quiz->get_renderable($PAGE->get_renderer('core'))['content_html'];

        $this->assertStringContainsString('name="topic"', $conceptshtml);
        $this->assertStringContainsString('Nucleus', $conceptshtml);
        $this->assertStringContainsString('save_ai_cartridge', $conceptshtml);
        $this->assertStringContainsString('Capital of France?', $quizhtml);
        $this->assertStringContainsString('save_ai_quiz_cartridge', $quizhtml);
    }

    /**
     * Editing a concept cartridge lists its concepts and categories, and fills the form of the one chosen.
     */
    public function test_the_concept_editor_lists_concepts_and_fills_the_form_of_the_chosen_one(): void {
        global $DB;
        $this->resetAfterTest();
        $cartridge = $this->make_cartridge('Cell biology');
        $categoryid = $this->make_category($cartridge);
        $nucleus = (int) $DB->insert_record('local_playergames_concepts', (object) [
            'cartridgeid' => $cartridge, 'term' => 'nucleus', 'definition' => 'Holds the DNA', 'difficulty' => 2,
            'categoryid' => $categoryid,
        ]);
        $DB->insert_record('local_playergames_concepts', (object) [
            'cartridgeid' => $cartridge, 'term' => 'ribosome', 'definition' => 'Makes proteins', 'difficulty' => 3,
        ]);

        $listing = $this->page('editor', $cartridge);
        $editing = $this->page('editor', $cartridge, $nucleus);
        $missing = $this->page('editor', $cartridge, 999999);

        $this->assertTrue($listing['editing']);
        $this->assertStringContainsString('nucleus', $listing['content_html']);
        $this->assertStringContainsString('ribosome', $listing['content_html']);
        $this->assertStringContainsString('Biology', $listing['content_html']);
        $this->assertStringContainsString('add_concept', $listing['content_html']);
        $this->assertStringContainsString('edit_concept', $editing['content_html']);
        $this->assertStringContainsString('add_concept', $missing['content_html']);
    }

    /**
     * Editing a quiz cartridge lists its questions, and fills the form of the one chosen with its answers.
     */
    public function test_the_quiz_editor_lists_questions_and_fills_the_form_of_the_chosen_one(): void {
        $this->resetAfterTest();
        $quiz = $this->make_quiz_cartridge('Capitals');
        $this->make_category($quiz);
        $first = $this->make_question($quiz);
        $this->make_question($quiz, 'Capital of Spain?');

        $listing = $this->page('editor', $quiz);
        $editing = $this->page('editor', $quiz, 0, 'concepts', $first);

        $this->assertTrue($listing['editing']);
        $this->assertStringContainsString('Capital of France?', $listing['content_html']);
        $this->assertStringContainsString('Capital of Spain?', $listing['content_html']);
        $this->assertStringContainsString('add_quiz_question', $listing['content_html']);
        $this->assertStringContainsString('edit_quiz_question', $editing['content_html']);
        $this->assertStringContainsString('Paris', $editing['content_html']);
        $this->assertStringContainsString('Lisbon', $editing['content_html']);
    }

    /**
     * Asking to edit a cartridge that is gone sends the user back to the library.
     */
    public function test_editing_a_missing_cartridge_redirects(): void {
        $this->resetAfterTest();

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('redirecterrordetected', 'error'));
        $this->page('editor', 999999);
    }

    /**
     * The export only acts on its own action, and refuses a cartridge that does not exist.
     */
    public function test_export_ignores_other_actions_and_refuses_a_missing_cartridge(): void {
        $this->resetAfterTest();
        $id = $this->make_cartridge('Pack');

        controller::handle_export($id, 'other');
        controller::handle_export(0, 'export_cartridge');

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('error_cartridge_notfound', 'local_playergames'));
        controller::handle_export(999999, 'export_cartridge');
    }
}
