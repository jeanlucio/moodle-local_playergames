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
 * Tests for the learning XP manager.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\hub;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Unit tests for {@see learning_xp_manager}.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(learning_xp_manager::class)]
final class learning_xp_manager_test extends \advanced_testcase {
    /**
     * Inserts a monthly bucket row directly, bypassing record_change(), so
     * tests can simulate buckets from past months.
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

    public function test_record_change_creates_bucket_and_cache(): void {
        $this->resetAfterTest();
        $user = (int) $this->getDataGenerator()->create_user()->id;

        learning_xp_manager::record_change($user, 50);

        $this->assertSame(50, learning_xp_manager::get_windowedxp($user));
    }

    public function test_record_change_accumulates_within_the_same_month(): void {
        global $DB;
        $this->resetAfterTest();
        $user = (int) $this->getDataGenerator()->create_user()->id;

        learning_xp_manager::record_change($user, 50);
        learning_xp_manager::record_change($user, 30);

        $this->assertSame(80, learning_xp_manager::get_windowedxp($user));
        $this->assertSame(1, $DB->count_records('local_playergames_student_xp_monthly', ['userid' => $user]));
    }

    public function test_record_change_floors_cache_at_zero_on_loss(): void {
        $this->resetAfterTest();
        $user = (int) $this->getDataGenerator()->create_user()->id;

        learning_xp_manager::record_change($user, 20);
        learning_xp_manager::record_change($user, -100);

        $this->assertSame(0, learning_xp_manager::get_windowedxp($user));
    }

    public function test_record_change_zero_delta_is_noop(): void {
        global $DB;
        $this->resetAfterTest();
        $user = (int) $this->getDataGenerator()->create_user()->id;

        learning_xp_manager::record_change($user, 0);

        $this->assertSame(0, $DB->count_records('local_playergames_student_xp_monthly'));
        $this->assertSame(0, $DB->count_records('local_playergames_student_xp_cache'));
    }

    public function test_get_windowedxp_for_unknown_user_is_zero(): void {
        $this->resetAfterTest();
        $this->assertSame(0, learning_xp_manager::get_windowedxp(999999));
    }

    public function test_set_ranking_visibility_persists_opt_in(): void {
        global $DB;
        $this->resetAfterTest();
        $user = (int) $this->getDataGenerator()->create_user()->id;

        learning_xp_manager::set_ranking_visibility($user, true);

        $this->assertSame(1, (int) $DB->get_field(
            'local_playergames_student_xp_cache',
            'showinranking',
            ['userid' => $user]
        ));
    }

    public function test_window_months_defaults_to_twelve(): void {
        $this->resetAfterTest();
        $this->assertSame(12, learning_xp_manager::window_months());
    }

    public function test_window_months_reads_admin_setting(): void {
        $this->resetAfterTest();
        set_config('learningxp_window_months', 6, 'local_playergames');
        $this->assertSame(6, learning_xp_manager::window_months());
    }

    public function test_recompute_all_sums_remaining_buckets_after_pruning(): void {
        $this->resetAfterTest();
        set_config('learningxp_window_months', 3, 'local_playergames');
        $user = (int) $this->getDataGenerator()->create_user()->id;

        $now = time();
        $this->insert_bucket($user, learning_xp_manager::period_for($now), 40);
        $this->insert_bucket($user, learning_xp_manager::period_for(strtotime('-1 month', $now)), 25);
        // Older than the 3-month window: must be pruned and excluded from the sum.
        $this->insert_bucket($user, learning_xp_manager::period_for(strtotime('-9 months', $now)), 999);

        learning_xp_manager::recompute_all();

        global $DB;
        $this->assertSame(65, learning_xp_manager::get_windowedxp($user));
        $this->assertSame(2, $DB->count_records('local_playergames_student_xp_monthly', ['userid' => $user]));
    }

    public function test_recompute_all_keeps_everything_when_window_unlimited(): void {
        $this->resetAfterTest();
        set_config('learningxp_window_months', 0, 'local_playergames');
        $user = (int) $this->getDataGenerator()->create_user()->id;

        $this->insert_bucket($user, '202001', 100);
        $this->insert_bucket($user, learning_xp_manager::period_for(time()), 10);

        learning_xp_manager::recompute_all();

        global $DB;
        $this->assertSame(110, learning_xp_manager::get_windowedxp($user));
        $this->assertSame(2, $DB->count_records('local_playergames_student_xp_monthly', ['userid' => $user]));
    }

    public function test_recompute_all_creates_cache_row_when_missing(): void {
        $this->resetAfterTest();
        $user = (int) $this->getDataGenerator()->create_user()->id;
        $this->insert_bucket($user, learning_xp_manager::period_for(time()), 15);

        global $DB;
        $this->assertSame(0, $DB->count_records('local_playergames_student_xp_cache', ['userid' => $user]));

        learning_xp_manager::recompute_all();

        $this->assertSame(15, learning_xp_manager::get_windowedxp($user));
    }

    public function test_recompute_all_floors_negative_sum_at_zero(): void {
        $this->resetAfterTest();
        $user = (int) $this->getDataGenerator()->create_user()->id;
        $this->insert_bucket($user, learning_xp_manager::period_for(time()), -40);

        learning_xp_manager::recompute_all();

        $this->assertSame(0, learning_xp_manager::get_windowedxp($user));
    }
}
