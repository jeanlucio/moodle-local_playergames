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
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Unit tests for {@see hook_callbacks}.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(hook_callbacks::class)]
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
     * Regression test: the staff check runs only when gamification is off.
     *
     * The hook runs on every page, and the staff lookup is a query on the user's
     * role assignments. A student with gamification on already gets the hub link,
     * so that lookup must be skipped: the same student pays one query less per
     * page than after opting out, when the lookup is needed to decide the link.
     */
    public function test_staff_lookup_skipped_when_gamification_is_on(): void {
        $this->resetAfterTest();
        $student = $this->getDataGenerator()->create_user();
        $course  = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);

        $readson = $this->reads_per_page();
        preferences::set((int) $student->id, false);
        $readsoff = $this->reads_per_page();

        $this->assertLessThan($readsoff, $readson);
    }
}
