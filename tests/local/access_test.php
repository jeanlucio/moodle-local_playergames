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

    /**
     * A teacher who got the role from a course enrolment lands on the staff dashboard. The
     * old landing page asked for a capability at the system context, which that role never
     * reaches, so every ordinary teacher was sent to the student hub.
     *
     * @return void
     */
    public function test_landing_url_sends_a_course_teacher_to_the_dashboard(): void {
        $this->resetAfterTest();
        $teacher = $this->getDataGenerator()->create_user();
        $course  = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');

        $this->assertFalse(
            has_capability('moodle/course:update', \context_system::instance(), $teacher),
            'the premise: a course role does not reach the system context'
        );
        $this->assertStringEndsWith('/local/playergames/dashboard.php', access::landing_url((int) $teacher->id)->get_path());
    }

    /**
     * A student, who stays out of the staff area, lands on the hub.
     *
     * @return void
     */
    public function test_landing_url_sends_a_student_to_the_hub(): void {
        $this->resetAfterTest();
        $student = $this->getDataGenerator()->create_user();
        $course  = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');

        $this->assertStringEndsWith('/local/playergames/hub.php', access::landing_url((int) $student->id)->get_path());
    }

    /**
     * A site administrator lands on the dashboard too.
     *
     * @return void
     */
    public function test_landing_url_sends_an_administrator_to_the_dashboard(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->assertStringEndsWith('/local/playergames/dashboard.php', access::landing_url()->get_path());
    }

    /**
     * The opt-out and the allowed_participants setting decide who takes part, for every entry point.
     *
     * Regression test: only the hub and the check-in applied them, so a user the hub turned away
     * could still play and earn XP by opening a game's URL.
     */
    public function test_is_participant_applies_the_optout_and_the_participants_setting(): void {
        $this->resetAfterTest();
        $student = $this->getDataGenerator()->create_user();
        $teacher = $this->getDataGenerator()->create_user();
        $course  = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');

        set_config('allowed_participants', 'students', 'local_playergames');
        $this->assertTrue(access::is_participant((int) $student->id));
        $this->assertFalse(access::is_participant((int) $teacher->id));

        set_config('allowed_participants', 'staff', 'local_playergames');
        $this->assertFalse(access::is_participant((int) $student->id));
        $this->assertTrue(access::is_participant((int) $teacher->id));

        set_config('allowed_participants', 'both', 'local_playergames');
        $this->assertTrue(access::is_participant((int) $student->id));
        set_user_preference('local_playergames_gamification', 0, $student);
        $this->assertFalse(access::is_participant((int) $student->id));
    }

    /**
     * A page or service stops a user who does not take part, and lets one who does through.
     */
    public function test_require_participant_throws_for_a_user_outside_the_participants(): void {
        $this->resetAfterTest();
        $teacher = $this->getDataGenerator()->create_user();
        $course  = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        set_config('allowed_participants', 'students', 'local_playergames');

        $this->setUser($teacher);
        try {
            access::require_participant();
            $this->fail('A teacher was let into a students-only hub.');
        } catch (\moodle_exception $e) {
            $this->assertSame('hub_access_restricted', $e->errorcode);
        }

        $student = $this->getDataGenerator()->create_user();
        $this->setUser($student);
        access::require_participant();
        $this->assertTrue(true);
    }

    /**
     * A service cannot redirect, so a user who turned the gamification off gets an exception.
     */
    public function test_require_participant_as_a_service_throws_for_the_optout(): void {
        $this->resetAfterTest();
        $student = $this->getDataGenerator()->create_user();
        set_user_preference('local_playergames_gamification', 0, $student);
        $this->setUser($student);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('gamification_disabled_notice', 'local_playergames'));
        access::require_participant(true);
    }
}
