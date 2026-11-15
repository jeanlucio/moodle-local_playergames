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
 * Tests for the refresh_staff_ids scheduled task.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\task;

use local_playergames\local\access;

/**
 * Unit tests for {@see refresh_staff_ids}.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_playergames\task\refresh_staff_ids
 */
final class refresh_staff_ids_test extends \advanced_testcase {
    /**
     * The task leaves the staff set where the hub reads it, with the teachers of the site in it.
     */
    public function test_execute_keeps_the_staff_set_for_the_hub(): void {
        $this->resetAfterTest();
        $teacher = $this->getDataGenerator()->create_user();
        $student = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');

        ob_start();
        (new refresh_staff_ids())->execute();
        $output = ob_get_clean();

        $kept = \cache::make('local_playergames', 'staffset')->get('set');
        $this->assertContains((int) $teacher->id, $kept['ids']);
        $this->assertNotContains((int) $student->id, $kept['ids']);
        $this->assertSame($kept['ids'], access::get_staff_ids());
        $this->assertStringContainsString('Staff users found: ' . count($kept['ids']), $output);
    }

    /**
     * The task has a name for the scheduled tasks page.
     */
    public function test_get_name_is_the_task_string(): void {
        $this->resetAfterTest();

        $this->assertSame(get_string('task_refresh_staff_ids', 'local_playergames'), (new refresh_staff_ids())->get_name());
    }
}
