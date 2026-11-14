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
 * Tests for the question bank file server.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\local;

use local_playergames\games\season_game_config;
use local_playergames\hub\season_manager;

/**
 * Unit tests for {@see bank_file}.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_playergames\local\bank_file
 */
final class bank_file_test extends \advanced_testcase {
    /**
     * Creates a category in a context, with a multiple-choice question that has a file in its text.
     *
     * @param \context $context The context of the category.
     * @return array [question record, category record]
     */
    private function question_with_file(\context $context): array {
        $qgen = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $qgen->create_question_category(['contextid' => $context->id]);
        $question = $qgen->create_question('multichoice', 'one_of_four', ['category' => $category->id]);
        get_file_storage()->create_file_from_string([
            'contextid' => $context->id, 'component' => 'question', 'filearea' => 'questiontext',
            'itemid' => $question->id, 'filepath' => '/', 'filename' => 'chart.png',
        ], 'png data');
        return [$question, $category];
    }

    /**
     * A context that is a bank kept for reuse: a shared question bank on Moodle 5, the system on 4.5.
     *
     * @return \context
     */
    private function shared_context(): \context {
        global $DB;
        if ($DB->get_manager()->table_exists('qbank')) {
            $course = $this->getDataGenerator()->create_course();
            $bank = $this->getDataGenerator()->create_module('qbank', ['course' => $course->id]);
            return \context_module::instance($bank->cmid);
        }
        return \context_system::instance();
    }

    /**
     * Makes the active season draw the quiz questions from a category.
     *
     * @param \stdClass $category The category.
     * @param string $source Where the quiz draws from.
     * @return void
     */
    private function season_uses(\stdClass $category, string $source = season_game_config::SOURCE_QUESTIONBANK): void {
        global $DB;
        $season = season_manager::create('Season', time() - DAYSECS, time() + DAYSECS);
        season_manager::activate((int) $season->id);
        season_game_config::seed_defaults((int) $season->id);
        $DB->set_field(
            'local_playergames_season_games',
            'source',
            $source,
            ['seasonid' => $season->id, 'gametype' => 'quiz']
        );
        $DB->set_field(
            'local_playergames_season_games',
            'auxid',
            (int) $category->id,
            ['seasonid' => $season->id, 'gametype' => 'quiz']
        );
    }

    public function test_a_file_of_a_question_in_a_shared_bank_is_found(): void {
        $this->resetAfterTest();
        $context = $this->shared_context();
        [$question, $category] = $this->question_with_file($context);
        $this->season_uses($category);

        $file = bank_file::find($context, bank_file::AREA_QUESTION, [$question->id, 'chart.png']);

        $this->assertNotNull($file);
        $this->assertSame('png data', $file->get_content());
    }

    public function test_a_file_of_an_answer_is_found_by_the_answer_id(): void {
        global $DB;
        $this->resetAfterTest();
        $context = $this->shared_context();
        [$question, $category] = $this->question_with_file($context);
        $this->season_uses($category);
        $answerid = (int) $DB->get_field('question_answers', 'id', ['question' => $question->id], IGNORE_MULTIPLE);
        get_file_storage()->create_file_from_string([
            'contextid' => $context->id, 'component' => 'question', 'filearea' => 'answer',
            'itemid' => $answerid, 'filepath' => '/', 'filename' => 'option.png',
        ], 'option data');

        $file = bank_file::find($context, bank_file::AREA_ANSWER, [$answerid, 'option.png']);

        $this->assertNotNull($file);
        $this->assertSame('option data', $file->get_content());
    }

    /**
     * A bank that belongs to a quiz is private to it: its files are never served to the players.
     */
    public function test_a_file_of_a_private_bank_is_not_found(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $context = \context_module::instance($quiz->cmid);
        [$question] = $this->question_with_file($context);

        $this->assertNull(bank_file::find($context, bank_file::AREA_QUESTION, [$question->id, 'chart.png']));
    }

    public function test_the_context_in_the_url_must_be_the_one_of_the_questions_bank(): void {
        $this->resetAfterTest();
        $context = $this->shared_context();
        [$question, $category] = $this->question_with_file($context);
        $this->season_uses($category);
        $course = $this->getDataGenerator()->create_course();

        $this->assertNull(bank_file::find(
            \context_course::instance($course->id),
            bank_file::AREA_QUESTION,
            [$question->id, 'chart.png']
        ));
    }

    public function test_an_unknown_area_a_missing_file_or_a_short_path_is_not_found(): void {
        $this->resetAfterTest();
        $context = $this->shared_context();
        [$question, $category] = $this->question_with_file($context);
        $this->season_uses($category);

        $this->assertNull(bank_file::find($context, 'attachment', [$question->id, 'chart.png']));
        $this->assertNull(bank_file::find($context, bank_file::AREA_QUESTION, [$question->id, 'other.png']));
        $this->assertNull(bank_file::find($context, bank_file::AREA_QUESTION, [$question->id]));
        $this->assertNull(bank_file::find($context, bank_file::AREA_QUESTION, [999999, 'chart.png']));
    }

    /**
     * Being kept for reuse is not enough: another bank of the site, one no season draws from, stays private.
     */
    public function test_a_file_of_a_bank_the_season_does_not_draw_from_is_not_found(): void {
        $this->resetAfterTest();
        $chosencontext = $this->shared_context();
        [, $chosen] = $this->question_with_file($chosencontext);
        $this->season_uses($chosen);
        $othercontext = $this->shared_context();
        [$other] = $this->question_with_file($othercontext);

        $this->assertNull(bank_file::find($othercontext, bank_file::AREA_QUESTION, [$other->id, 'chart.png']));
    }

    public function test_nothing_is_served_without_an_active_season_or_when_the_game_does_not_use_the_bank(): void {
        $this->resetAfterTest();
        $context = $this->shared_context();
        [$question, $category] = $this->question_with_file($context);

        $this->assertNull(bank_file::find($context, bank_file::AREA_QUESTION, [$question->id, 'chart.png']));

        $this->season_uses($category, season_game_config::SOURCE_CARTRIDGE);
        $this->assertNull(bank_file::find($context, bank_file::AREA_QUESTION, [$question->id, 'chart.png']));
    }

    public function test_a_file_of_a_question_that_is_not_ready_is_not_found(): void {
        global $DB;
        $this->resetAfterTest();
        $context = $this->shared_context();
        [$question, $category] = $this->question_with_file($context);
        $this->season_uses($category);
        $DB->set_field('question_versions', 'status', 'draft', ['questionid' => $question->id]);

        $this->assertNull(bank_file::find($context, bank_file::AREA_QUESTION, [$question->id, 'chart.png']));
    }
}
