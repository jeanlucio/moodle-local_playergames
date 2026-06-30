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
 * Tests for the recompute_learning_xp scheduled task.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\task;

use local_playergames\hub\learning_xp_manager;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Unit tests for {@see recompute_learning_xp}.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(recompute_learning_xp::class)]
final class recompute_learning_xp_test extends \advanced_testcase {
    /**
     * Inserts a monthly bucket row directly.
     *
     * @param int $userid User id.
     * @param string $period 'YYYYMM'.
     * @param int $xp Signed bucket total.
     * @return void
     */
    private function insert_bucket(int $userid, string $period, int $xp): void {
        global $DB;
        $now = time();
        $DB->insert_record('local_playergames_student_xp_monthly', (object) [
            'userid'       => $userid,
            'period'       => $period,
            'xp'           => $xp,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * Runs the task, swallowing its mtrace output.
     *
     * @return void
     */
    private function run_task(): void {
        ob_start();
        (new recompute_learning_xp())->execute();
        ob_get_clean();
    }

    public function test_execute_recomputes_the_cache(): void {
        $this->resetAfterTest();
        $user = (int) $this->getDataGenerator()->create_user()->id;
        $this->insert_bucket($user, learning_xp_manager::period_for(time()), 70);

        $this->run_task();

        $this->assertSame(70, learning_xp_manager::get_windowedxp($user));
    }

    public function test_get_name_returns_a_string(): void {
        $this->assertNotEmpty((new recompute_learning_xp())->get_name());
    }
}
