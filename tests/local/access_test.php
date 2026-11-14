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

    /**
     * Regression test: a role assigned at category level made is_staff() true but never reached the
     * bulk query, which only looked at role assignments in course contexts.
     */
    public function test_get_staff_ids_includes_a_manager_assigned_in_a_category(): void {
        global $DB;
        $this->resetAfterTest();
        $user     = $this->getDataGenerator()->create_user();
        $category = $this->getDataGenerator()->create_category();
        $this->getDataGenerator()->create_course(['category' => $category->id]);
        $managerid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        role_assign($managerid, $user->id, \context_coursecat::instance($category->id));

        $this->assertTrue(access::is_staff((int) $user->id));
        $this->assertContains((int) $user->id, access::get_staff_ids());
    }

    /**
     * Regression test: an override that allows the capability for a role in one course made every holder
     * of that role in any course count as staff, because the query ignored the context of the override.
     */
    public function test_get_staff_ids_ignores_an_override_in_a_course_the_user_is_not_in(): void {
        global $DB;
        $this->resetAfterTest();
        $outsider = $this->getDataGenerator()->create_user();
        $insider  = $this->getDataGenerator()->create_user();
        $other    = $this->getDataGenerator()->create_course();
        $overridden = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($outsider->id, $other->id, 'teacher');
        $this->getDataGenerator()->enrol_user($insider->id, $overridden->id, 'teacher');
        $roleid = (int) $DB->get_field('role', 'id', ['shortname' => 'teacher'], MUST_EXIST);
        assign_capability('moodle/course:update', CAP_ALLOW, $roleid, \context_course::instance($overridden->id), true);

        $staffids = access::get_staff_ids();

        $this->assertFalse(access::is_staff((int) $outsider->id));
        $this->assertNotContains((int) $outsider->id, $staffids);
        $this->assertTrue(access::is_staff((int) $insider->id));
        $this->assertContains((int) $insider->id, $staffids);
    }

    public function test_get_staff_ids_excludes_a_teacher_whose_capability_is_prohibited(): void {
        global $DB;
        $this->resetAfterTest();
        $teacher = $this->getDataGenerator()->create_user();
        $course  = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $roleid = (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        assign_capability('moodle/course:update', CAP_PROHIBIT, $roleid, \context_course::instance($course->id), true);

        $this->assertFalse(access::is_staff((int) $teacher->id));
        $this->assertNotContains((int) $teacher->id, access::get_staff_ids());
    }

    public function test_get_staff_ids_is_cached_for_a_few_minutes(): void {
        $this->resetAfterTest();
        $teacher = $this->getDataGenerator()->create_user();
        $course  = $this->getDataGenerator()->create_course();

        $before = access::get_staff_ids();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $cached = access::get_staff_ids();
        \cache_helper::purge_by_definition('local_playergames', 'staffids');
        $fresh = access::get_staff_ids();

        $this->assertNotContains((int) $teacher->id, $before);
        $this->assertSame($before, $cached);
        $this->assertContains((int) $teacher->id, $fresh);
    }

    public function test_teaches_any_is_true_for_a_teacher_and_false_for_a_student(): void {
        $this->resetAfterTest();
        $teacher = $this->getDataGenerator()->create_user();
        $student = $this->getDataGenerator()->create_user();
        $first   = $this->getDataGenerator()->create_course();
        $second  = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($teacher->id, $first->id, 'editingteacher');
        $this->getDataGenerator()->enrol_user($teacher->id, $second->id, 'editingteacher');
        $this->getDataGenerator()->enrol_user($student->id, $first->id, 'student');

        $this->assertTrue(access::teaches_any((int) $teacher->id));
        $this->assertFalse(access::teaches_any((int) $student->id));
    }

    /**
     * The copy kept for display lags behind a role change, and the check that allows things does not.
     */
    public function test_the_staff_flag_for_display_is_kept_but_is_staff_stays_live(): void {
        $this->resetAfterTest();
        $teacher = $this->getDataGenerator()->create_user();
        $course  = $this->getDataGenerator()->create_course();

        $this->assertFalse(access::is_staff_for_display((int) $teacher->id));
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');

        $this->assertTrue(access::is_staff((int) $teacher->id));
        $this->assertFalse(access::is_staff_for_display((int) $teacher->id));

        \cache_helper::purge_by_definition('local_playergames', 'staffids');
        $this->assertTrue(access::is_staff_for_display((int) $teacher->id));
    }

    public function test_is_participant_takes_a_staff_flag_the_caller_already_has(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        // The setting is "students only": a user who is not staff takes part, one who is does not.
        $this->assertTrue(access::is_participant((int) $user->id, false));
        $this->assertFalse(access::is_participant((int) $user->id, true));
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
