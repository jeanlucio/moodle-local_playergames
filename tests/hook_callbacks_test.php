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
 * Tests for the hook listener callbacks.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames;

use core\hook\navigation\primary_extend;
use core\navigation\views\primary;
use local_playergames\local\preferences;

/**
 * Unit tests for {@see hook_callbacks}.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_playergames\hook_callbacks
 * @covers \local_playergames\local\preferences
 */
final class hook_callbacks_test extends \advanced_testcase {
    /**
     * Runs the primary navigation hook for the current user.
     *
     * @return primary_extend The dispatched hook, to inspect the primary view.
     */
    private function run_primary_hook(): primary_extend {
        global $PAGE;

        $PAGE->set_url(new \moodle_url('/'));
        $hook = new primary_extend(new primary($PAGE));
        hook_callbacks::extend_primary_navigation($hook);
        return $hook;
    }

    /**
     * Returns the URL of the PlayerGames node, or null when the hook did not add it.
     *
     * @param primary_extend $hook The dispatched hook.
     * @return string|null
     */
    private function node_url(primary_extend $hook): ?string {
        $node = $hook->get_primaryview()->get('local_playergames_hub');
        return $node ? $node->action->out_as_local_url(false) : null;
    }

    /**
     * Counts the database reads one run of the hook makes for the current user.
     *
     * A first run warms the access and preference caches the way require_login()
     * does on a real page, so only the hook's own queries are counted.
     *
     * @return int
     */
    private function reads_per_page(): int {
        global $DB;

        $this->run_primary_hook();
        $before = $DB->perf_get_reads();
        $this->run_primary_hook();
        return $DB->perf_get_reads() - $before;
    }

    /**
     * A student with gamification on gets the hub link.
     */
    public function test_student_with_gamification_lands_on_hub(): void {
        $this->resetAfterTest();
        $student = $this->getDataGenerator()->create_user();
        $course  = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);

        $this->assertSame('/local/playergames/hub.php', $this->node_url($this->run_primary_hook()));
    }

    /**
     * A student who opted out of gamification has no PlayerGames page, so no link.
     */
    public function test_student_without_gamification_gets_no_link(): void {
        $this->resetAfterTest();
        $student = $this->getDataGenerator()->create_user();
        $course  = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);
        preferences::set((int) $student->id, false);

        $this->assertNull($this->node_url($this->run_primary_hook()));
    }

    /**
     * A course teacher who opted out of gamification still lands on the dashboard.
     */
    public function test_teacher_without_gamification_lands_on_dashboard(): void {
        $this->resetAfterTest();
        $teacher = $this->getDataGenerator()->create_user();
        $course  = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);
        preferences::set((int) $teacher->id, false);

        $this->assertSame('/local/playergames/dashboard.php', $this->node_url($this->run_primary_hook()));
    }

    /**
     * Guests get no link.
     */
    public function test_guest_gets_no_link(): void {
        $this->resetAfterTest();
        $this->setGuestUser();

        $this->assertNull($this->node_url($this->run_primary_hook()));
    }

    /**
     * Regression test: with the default "students only" setting a teacher was sent to the hub, which turns
     * staff away, so the only link to the plugin ended in an error page.
     */
    public function test_teacher_with_the_default_setting_lands_on_dashboard(): void {
        $this->resetAfterTest();
        $teacher = $this->getDataGenerator()->create_user();
        $course  = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);

        $this->assertSame('/local/playergames/dashboard.php', $this->node_url($this->run_primary_hook()));
    }

    /**
     * The link follows the participant setting both ways: staff-only turns students away from the hub and
     * leaves them nothing else to open, and lets staff in.
     */
    public function test_the_link_follows_the_participants_setting(): void {
        global $DB;
        $this->resetAfterTest();
        $teacher = $this->getDataGenerator()->create_user();
        $student = $this->getDataGenerator()->create_user();
        $course  = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');

        set_config('allowed_participants', 'staff', 'local_playergames');
        $this->setUser($student);
        $this->assertNull($this->node_url($this->run_primary_hook()));
        $this->setUser($teacher);
        $this->assertSame('/local/playergames/hub.php', $this->node_url($this->run_primary_hook()));

        set_config('allowed_participants', 'both', 'local_playergames');
        \cache_helper::purge_by_definition('local_playergames', 'staffids');
        $this->setUser($student);
        $this->assertSame('/local/playergames/hub.php', $this->node_url($this->run_primary_hook()));
        $this->setUser($teacher);
        $this->assertSame('/local/playergames/hub.php', $this->node_url($this->run_primary_hook()));
    }

    /**
     * An administrator is a participant whatever the setting, so the link goes to the hub.
     */
    public function test_an_administrator_lands_on_the_hub(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->assertSame('/local/playergames/hub.php', $this->node_url($this->run_primary_hook()));
    }

    /**
     * Regression test for the cost of the hook: it runs on every page of the site, and whether the user is
     * staff is not looked up again on each one.
     */
    public function test_the_staff_lookup_is_kept_between_pages(): void {
        $this->resetAfterTest();
        $teacher = $this->getDataGenerator()->create_user();
        $course  = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);

        $reads = $this->reads_per_page();

        $this->assertSame(0, $reads);
    }
}
