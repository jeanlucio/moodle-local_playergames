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
 * Tests for the question bank importer.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\cartridge;

/**
 * Unit tests for {@see question_bank_importer}.
 *
 * A category kept for reuse is a shared question bank on Moodle 5 and a category of the system context on
 * Moodle 4.5, so the fixtures follow the version they run on.
 *
 * @package    local_playergames
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_playergames\cartridge\question_bank_importer
 */
final class question_bank_importer_test extends \advanced_testcase {
    /**
     * Creates a category in a context.
     *
     * @param \context $context The context.
     * @param string $name Category name.
     * @return \stdClass The category.
     */
    private function category_in(\context $context, string $name): \stdClass {
        return $this->getDataGenerator()->get_plugin_generator('core_question')
            ->create_question_category(['contextid' => $context->id, 'name' => $name]);
    }

    /**
     * A category kept for reuse: a shared question bank on Moodle 5, the system context on 4.5.
     *
     * @param string $name Category name.
     * @return \stdClass The category.
     */
    private function shared_category(string $name = 'Cells'): \stdClass {
        global $DB;
        if ($DB->get_manager()->table_exists('qbank')) {
            $course = $this->getDataGenerator()->create_course();
            $bank = $this->getDataGenerator()->create_module('qbank', ['course' => $course->id, 'name' => 'Biology bank']);
            return $this->category_in(\context_module::instance($bank->cmid), $name);
        }
        return $this->category_in(\context_system::instance(), $name);
    }

    /**
     * Creates a multiple-choice question with one right answer and four wrong ones.
     *
     * @param \stdClass $category The category.
     * @param array $overrides Fields of the question.
     * @return \stdClass The question.
     */
    private function add_question(\stdClass $category, array $overrides = []): \stdClass {
        global $DB;
        $question = $this->getDataGenerator()->get_plugin_generator('core_question')
            ->create_question('multichoice', 'one_of_four', ['category' => $category->id] + $overrides);
        $this->add_answer((int) $question->id, 'Another wrong', 0);
        return $question;
    }

    /**
     * Adds an answer to a question.
     *
     * @param int $questionid The question (one version of it).
     * @param string $text The answer text, as HTML.
     * @param float $fraction The share of the grade it is worth.
     * @return void
     */
    private function add_answer(int $questionid, string $text, float $fraction): void {
        global $DB;
        $DB->insert_record('question_answers', (object) [
            'question' => $questionid, 'answer' => $text, 'answerformat' => FORMAT_HTML,
            'fraction' => $fraction, 'feedback' => '', 'feedbackformat' => FORMAT_HTML,
        ]);
    }

    /**
     * Imports a category as the administrator.
     *
     * @param \stdClass $category The category.
     * @return \stdClass The result.
     */
    private function import(\stdClass $category): \stdClass {
        return (new question_bank_importer())->import((int) $category->id, (int) get_admin()->id);
    }

    public function test_a_question_is_copied_into_a_new_active_quiz_cartridge(): void {
        global $DB;
        $this->resetAfterTest();
        $category = $this->shared_category('Cells & tissues');
        $question = $this->add_question($category, [
            'questiontext' => ['text' => '<p>What is the <b>nucleus</b>?</p>', 'format' => FORMAT_HTML],
        ]);

        $result = $this->import($category);

        $cartridge = $DB->get_record('local_playergames_cartridges', ['id' => $result->cartridgeid], '*', MUST_EXIST);
        $this->assertSame('quiz', $cartridge->type);
        $this->assertSame(1, (int) $cartridge->active);
        $this->assertSame((int) get_admin()->id, (int) $cartridge->uploadedby);
        $this->assertSame(1, $result->count);
        $this->assertSame([0, 0, 0], [$result->images, $result->unusable, $result->beyond]);
        $copied = $DB->get_record('local_playergames_concept_questions', ['cartridgeid' => $cartridge->id], '*', MUST_EXIST);
        $this->assertSame('What is the nucleus?', $copied->questiontext);
        $answers = $DB->get_records('local_playergames_concept_answers', ['questionid' => $copied->id]);
        $this->assertCount(5, $answers);
        $this->assertCount(1, array_filter($answers, fn($a): bool => (int) $a->iscorrect === 1));
        $this->assertNotEmpty($question->id);
    }

    public function test_the_category_name_becomes_the_category_of_the_questions(): void {
        global $DB;
        $this->resetAfterTest();
        $category = $this->shared_category('Cells');
        $this->add_question($category);

        $result = $this->import($category);

        $this->assertSame(1, $DB->count_records('local_playergames_categories', ['cartridgeid' => $result->cartridgeid]));
        $this->assertNotNull(
            $DB->get_field('local_playergames_concept_questions', 'categoryid', ['cartridgeid' => $result->cartridgeid])
        );
    }

    public function test_a_question_with_a_medium_is_left_out_and_counted(): void {
        global $DB;
        $this->resetAfterTest();
        $category = $this->shared_category();
        $this->add_question($category);
        $this->add_question($category, [
            'questiontext' => ['text' => 'See <img src="@@PLUGINFILE@@/map.png" alt="map">', 'format' => FORMAT_HTML],
        ]);
        $this->add_question($category, [
            'questiontext' => ['text' => 'Listen: <audio src="https://example.com/a.mp3"></audio>', 'format' => FORMAT_HTML],
        ]);
        $wronganswers = $this->add_question($category);
        $DB->set_field('question_answers', 'answer', '<img src="https://example.com/x.png">', [
            'question' => $wronganswers->id, 'fraction' => 0,
        ]);
        // A medium in the fifth wrong answer, which the cartridge would not keep, is no reason to leave it out.
        $unused = $this->add_question($category);
        $this->add_answer((int) $unused->id, '<img src="https://example.com/y.png">', 0);

        $result = $this->import($category);

        $this->assertSame(2, $result->count);
        $this->assertSame(3, $result->images);
    }

    public function test_a_question_without_one_full_credit_answer_and_four_wrong_ones_is_unusable(): void {
        global $DB;
        $this->resetAfterTest();
        $category = $this->shared_category();
        $this->add_question($category);
        // Partial credit only: no answer is worth the whole grade.
        $partial = $this->add_question($category);
        $DB->set_field('question_answers', 'fraction', 0.5, ['question' => $partial->id, 'fraction' => 1]);
        // Three wrong answers only.
        $few = $this->getDataGenerator()->get_plugin_generator('core_question')
            ->create_question('multichoice', 'one_of_four', ['category' => $category->id]);
        $DB->delete_records('question_answers', ['question' => $few->id, 'fraction' => 0]);

        $result = $this->import($category);

        $this->assertSame(1, $result->count);
        $this->assertSame(2, $result->unusable);
    }

    public function test_only_multiple_choice_questions_of_the_latest_ready_version_are_copied(): void {
        global $DB;
        $this->resetAfterTest();
        $category = $this->shared_category();
        $qgen = $this->getDataGenerator()->get_plugin_generator('core_question');
        $first = $qgen->create_question('multichoice', 'one_of_four', [
            'category' => $category->id, 'questiontext' => ['text' => 'Old statement', 'format' => FORMAT_HTML],
        ]);
        $second = $qgen->update_question($first, 'one_of_four', [
            'category' => $category->id, 'questiontext' => ['text' => 'Corrected statement', 'format' => FORMAT_HTML],
        ]);
        $this->add_answer((int) $first->id, 'Another wrong', 0);
        $this->add_answer((int) $second->id, 'Another wrong', 0);
        $qgen->create_question('essay', null, ['category' => $category->id]);

        $result = $this->import($category);

        $texts = $DB->get_fieldset_select(
            'local_playergames_concept_questions',
            'questiontext',
            'cartridgeid = :id',
            ['id' => $result->cartridgeid]
        );
        $this->assertSame(['Corrected statement'], $texts);
    }

    public function test_a_category_with_nothing_to_import_creates_no_cartridge(): void {
        global $DB;
        $this->resetAfterTest();
        $category = $this->shared_category();
        $this->getDataGenerator()->get_plugin_generator('core_question')
            ->create_question('essay', null, ['category' => $category->id]);

        try {
            $this->import($category);
            $this->fail('An empty import must fail.');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_qbank_empty', $e->errorcode);
        }
        $this->assertSame(0, $DB->count_records('local_playergames_cartridges'));
    }

    public function test_a_category_that_is_private_or_that_the_user_may_not_use_cannot_be_imported(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $private = $this->category_in(\context_module::instance($quiz->cmid), 'Private');
        $this->add_question($private);
        $shared = $this->shared_category();
        $this->add_question($shared);
        $student = $this->getDataGenerator()->create_user();

        foreach ([[$private, get_admin()], [$shared, $student]] as [$category, $user]) {
            try {
                (new question_bank_importer())->import((int) $category->id, (int) $user->id);
                $this->fail('The import must be refused.');
            } catch (\moodle_exception $e) {
                $this->assertSame('error_qbank_category_unavailable', $e->errorcode);
            }
        }
        $this->assertSame(0, $DB->count_records('local_playergames_cartridges'));
    }

    public function test_the_categories_offered_carry_the_bank_name_and_the_question_count(): void {
        $this->resetAfterTest();
        $shared = $this->shared_category('Cells');
        $this->add_question($shared);
        $this->add_question($shared);

        $categories = (new question_bank_importer())->categories((int) get_admin()->id);

        $this->assertSame(2, $categories[$shared->id]->questions);
        $this->assertStringContainsString('Cells', $categories[$shared->id]->name);
    }

    public function test_the_text_keeps_its_case_its_entities_and_its_line_breaks_as_spaces(): void {
        global $DB;
        $this->resetAfterTest();
        $category = $this->shared_category();
        $this->add_question($category, [
            'questiontext' => ['text' => '<p>Tom &amp; <strong>Jerry</strong></p><p>chase<br>cats</p>', 'format' => FORMAT_HTML],
        ]);

        $result = $this->import($category);

        $this->assertSame(
            'Tom & Jerry chase cats',
            $DB->get_field('local_playergames_concept_questions', 'questiontext', ['cartridgeid' => $result->cartridgeid])
        );
    }
}
