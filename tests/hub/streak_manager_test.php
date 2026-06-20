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
 * Tests for the daily activity streak manager.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\hub;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Unit tests for {@see streak_manager}.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(streak_manager::class)]
final class streak_manager_test extends \advanced_testcase {
    /**
     * Overwrites a user's streak record with controlled values.
     *
     * @param int $userid User id.
     * @param int $current Current streak.
     * @param int $longest Longest streak.
     * @param int|null $lastactive Last active midnight timestamp, or null.
     * @param int $freezes Available freezes.
     * @return void
     */
    private function set_streak(int $userid, int $current, int $longest, ?int $lastactive, int $freezes = 0): void {
        global $DB;
        $rec = streak_manager::get_or_create($userid);
        $rec->currentstreak = $current;
        $rec->longeststreak = $longest;
        $rec->lastactivedate = $lastactive;
        $rec->freezesavailable = $freezes;
        $DB->update_record('local_playergames_streaks', $rec);
    }

    public function test_record_activity_first_time_starts_at_one(): void {
        $this->resetAfterTest();
        $this->redirectEvents();
        $user = $this->getDataGenerator()->create_user();

        streak_manager::record_activity((int) $user->id);

        $streak = streak_manager::get_or_create((int) $user->id);
        $this->assertSame(1, (int) $streak->currentstreak);
        $this->assertSame(1, (int) $streak->longeststreak);
        $this->assertSame(mktime(0, 0, 0), (int) $streak->lastactivedate);
    }

    public function test_record_activity_same_day_is_noop(): void {
        $this->resetAfterTest();
        $this->redirectEvents();
        $user = $this->getDataGenerator()->create_user();
        $this->set_streak((int) $user->id, 3, 3, mktime(0, 0, 0));

        streak_manager::record_activity((int) $user->id);

        $streak = streak_manager::get_or_create((int) $user->id);
        $this->assertSame(3, (int) $streak->currentstreak);
    }

    public function test_record_activity_consecutive_day_increments(): void {
        $this->resetAfterTest();
        $this->redirectEvents();
        $user = $this->getDataGenerator()->create_user();
        $yesterday = mktime(0, 0, 0) - DAYSECS;
        $this->set_streak((int) $user->id, 3, 3, $yesterday);

        streak_manager::record_activity((int) $user->id);

        $streak = streak_manager::get_or_create((int) $user->id);
        $this->assertSame(4, (int) $streak->currentstreak);
        $this->assertSame(4, (int) $streak->longeststreak);
    }

    public function test_record_activity_gap_resets_but_keeps_longest(): void {
        $this->resetAfterTest();
        $this->redirectEvents();
        $user = $this->getDataGenerator()->create_user();
        $threedaysago = mktime(0, 0, 0) - (3 * DAYSECS);
        $this->set_streak((int) $user->id, 5, 5, $threedaysago);

        streak_manager::record_activity((int) $user->id);

        $streak = streak_manager::get_or_create((int) $user->id);
        $this->assertSame(1, (int) $streak->currentstreak);
        $this->assertSame(5, (int) $streak->longeststreak);
    }

    public function test_process_breaks_resets_without_freeze(): void {
        $this->resetAfterTest();
        $this->redirectEvents();
        $user = $this->getDataGenerator()->create_user();
        $twodaysago = mktime(0, 0, 0) - (2 * DAYSECS);
        $this->set_streak((int) $user->id, 5, 5, $twodaysago, 0);

        $broken = streak_manager::process_breaks();

        $this->assertSame(1, $broken);
        $streak = streak_manager::get_or_create((int) $user->id);
        $this->assertSame(0, (int) $streak->currentstreak);
    }

    public function test_process_breaks_consumes_freeze(): void {
        $this->resetAfterTest();
        $this->redirectEvents();
        $user = $this->getDataGenerator()->create_user();
        $twodaysago = mktime(0, 0, 0) - (2 * DAYSECS);
        $this->set_streak((int) $user->id, 5, 5, $twodaysago, 1);

        $broken = streak_manager::process_breaks();

        // A freeze shields the streak: not counted as broken, streak preserved.
        $this->assertSame(0, $broken);
        $streak = streak_manager::get_or_create((int) $user->id);
        $this->assertSame(5, (int) $streak->currentstreak);
        $this->assertSame(0, (int) $streak->freezesavailable);
    }

    public function test_process_breaks_ignores_recent_activity(): void {
        $this->resetAfterTest();
        $this->redirectEvents();
        $user = $this->getDataGenerator()->create_user();
        $yesterday = mktime(0, 0, 0) - DAYSECS;
        $this->set_streak((int) $user->id, 4, 4, $yesterday, 0);

        $broken = streak_manager::process_breaks();

        // Active yesterday is still "alive" today — nothing to break.
        $this->assertSame(0, $broken);
        $streak = streak_manager::get_or_create((int) $user->id);
        $this->assertSame(4, (int) $streak->currentstreak);
    }

    public function test_add_freezes_accumulates(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        streak_manager::add_freezes((int) $user->id, 2);
        streak_manager::add_freezes((int) $user->id);

        $streak = streak_manager::get_or_create((int) $user->id);
        $this->assertSame(3, (int) $streak->freezesavailable);
    }
}
