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
 * Tests for the site-wide access helpers.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\local;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Unit tests for {@see access}.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(access::class)]
final class access_test extends \advanced_testcase {
    public function test_is_staff_true_for_course_level_teacher(): void {
        $this->resetAfterTest();
        $teacher = $this->getDataGenerator()->create_user();
        $course  = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');

        $this->assertTrue(access::is_staff((int) $teacher->id));
    }

    public function test_is_staff_false_for_plain_student(): void {
        $this->resetAfterTest();
        $student = $this->getDataGenerator()->create_user();
        $course  = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');

        $this->assertFalse(access::is_staff((int) $student->id));
    }

    public function test_is_staff_true_for_site_admin(): void {
        global $CFG;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $CFG->siteadmins = (string) $user->id;

        $this->assertTrue(access::is_staff((int) $user->id));
    }

    /**
     * Regression test: get_staff_ids() must agree with is_staff() for a user
     * who only has a course-level teacher role (the common case), not a
     * system-level role. get_users_by_capability() called against the system
     * context misses this case — get_staff_ids() must not use it directly.
     */
    public function test_get_staff_ids_includes_course_level_teacher(): void {
        $this->resetAfterTest();
        $teacher = $this->getDataGenerator()->create_user();
        $course  = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');

        $this->assertContains((int) $teacher->id, access::get_staff_ids());
    }

    public function test_get_staff_ids_excludes_plain_student(): void {
        $this->resetAfterTest();
        $student = $this->getDataGenerator()->create_user();
        $course  = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');

        $this->assertNotContains((int) $student->id, access::get_staff_ids());
    }

    public function test_get_staff_ids_includes_site_admin(): void {
        global $CFG;
        $this->resetAfterTest();
        $admin = $this->getDataGenerator()->create_user();
        $CFG->siteadmins = (string) $admin->id;

        $this->assertContains((int) $admin->id, access::get_staff_ids());
    }

    public function test_get_staff_ids_matches_is_staff_for_every_user(): void {
        $this->resetAfterTest();
        $teacher = $this->getDataGenerator()->create_user();
        $student = $this->getDataGenerator()->create_user();
        $course  = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');

        $staffids = access::get_staff_ids();

        $this->assertSame(access::is_staff((int) $teacher->id), in_array((int) $teacher->id, $staffids, true));
        $this->assertSame(access::is_staff((int) $student->id), in_array((int) $student->id, $staffids, true));
    }

    public function test_can_view_hub_blocks_staff_when_students_only(): void {
        $this->assertFalse(access::can_view_hub(true, false, 'students'));
    }

    public function test_can_view_hub_allows_admin_even_when_students_only(): void {
        $this->assertTrue(access::can_view_hub(true, true, 'students'));
    }

    public function test_can_view_hub_allows_student_when_students_only(): void {
        $this->assertTrue(access::can_view_hub(false, false, 'students'));
    }

    public function test_can_view_hub_blocks_student_when_staff_only(): void {
        $this->assertFalse(access::can_view_hub(false, false, 'staff'));
    }

    public function test_can_view_hub_allows_staff_when_staff_only(): void {
        $this->assertTrue(access::can_view_hub(true, false, 'staff'));
    }

    public function test_can_view_hub_allows_everyone_when_both(): void {
        $this->assertTrue(access::can_view_hub(true, false, 'both'));
        $this->assertTrue(access::can_view_hub(false, false, 'both'));
    }
}
