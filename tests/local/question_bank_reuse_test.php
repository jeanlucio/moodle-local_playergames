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
 * Tests for the question bank categories kept for reuse.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\local;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Unit tests for {@see question_bank_reuse}.
 *
 * The category kept for reuse is a shared question bank on Moodle 5 and a category of the system
 * context on Moodle 4.5, so the fixtures follow the version they run on.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(question_bank_reuse::class)]
final class question_bank_reuse_test extends \advanced_testcase {
    /**
     * Creates a category in a context.
     *
     * @param \context $context The context.
     * @return \stdClass The category.
     */
    private function category_in(\context $context): \stdClass {
        return $this->getDataGenerator()->get_plugin_generator('core_question')
            ->create_question_category(['contextid' => $context->id, 'name' => 'Cat ' . $context->id]);
    }

    /**
     * A category kept for reuse: a shared question bank on Moodle 5, the system context on 4.5.
     *
     * @return \stdClass The category.
     */
    private function shared_category(): \stdClass {
        global $DB;
        if ($DB->get_manager()->table_exists('qbank')) {
            $course = $this->getDataGenerator()->create_course();
            $bank = $this->getDataGenerator()->create_module('qbank', ['course' => $course->id, 'name' => 'Shared bank']);
            return $this->category_in(\context_module::instance($bank->cmid));
        }
        return $this->category_in(\context_system::instance());
    }

    /**
     * A category that belongs to a quiz, which is private to it on every version.
     *
     * @return \stdClass The category.
     */
    private function private_category(): \stdClass {
        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        return $this->category_in(\context_module::instance($quiz->cmid));
    }

    /**
     * Adds a question of a type to a category.
     *
     * @param \stdClass $category The category.
     * @param string $qtype The question type.
     * @return void
     */
    private function add_question(\stdClass $category, string $qtype): void {
        $this->getDataGenerator()->get_plugin_generator('core_question')->create_question(
            $qtype,
            $qtype === 'multichoice' ? 'one_of_four' : null,
            ['category' => $category->id]
        );
    }

    public function test_only_a_category_kept_for_reuse_is_offered(): void {
        $this->resetAfterTest();
        $shared  = $this->shared_category();
        $private = $this->private_category();

        $ids = array_keys(question_bank_reuse::categories());

        $this->assertContains((int) $shared->id, $ids);
        $this->assertNotContains((int) $private->id, $ids);
    }

    public function test_is_shared_is_true_only_for_a_category_kept_for_reuse(): void {
        $this->resetAfterTest();
        $shared  = $this->shared_category();
        $private = $this->private_category();

        $this->assertTrue(question_bank_reuse::is_shared((int) $shared->id));
        $this->assertFalse(question_bank_reuse::is_shared((int) $private->id));
        $this->assertFalse(question_bank_reuse::is_shared(0));
        $this->assertFalse(question_bank_reuse::is_shared(-3));
        $this->assertFalse(question_bank_reuse::is_shared(999999));
    }

    public function test_the_count_is_of_multiple_choice_questions_only(): void {
        $this->resetAfterTest();
        $shared = $this->shared_category();
        $this->add_question($shared, 'multichoice');
        $this->add_question($shared, 'multichoice');
        $this->add_question($shared, 'essay');

        $this->assertSame(2, question_bank_reuse::categories()[$shared->id]->questions);
    }

    public function test_a_user_chooses_only_what_they_may_use(): void {
        $this->resetAfterTest();
        $shared  = $this->shared_category();
        $private = $this->private_category();
        $student = $this->getDataGenerator()->create_user();
        $admin   = get_admin();

        $this->assertTrue(question_bank_reuse::can_choose((int) $shared->id, (int) $admin->id));
        $this->assertFalse(question_bank_reuse::can_choose((int) $private->id, (int) $admin->id));
        $this->assertFalse(question_bank_reuse::can_choose((int) $shared->id, (int) $student->id));
        $this->assertSame([], question_bank_reuse::categories_for_user((int) $student->id));
    }
}
