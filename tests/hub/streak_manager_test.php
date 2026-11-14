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

/**
 * Unit tests for {@see streak_manager}.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_playergames\hub\streak_manager
 */
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

    /**
     * Regression test: the freeze was spent and recorded, but the next play still restarted the streak at 1,
     * as it would have without the freeze.
     */
    public function test_a_streak_protected_by_a_freeze_continues_on_the_next_play(): void {
        $this->resetAfterTest();
        $this->redirectEvents();
        $user = $this->getDataGenerator()->create_user();
        $twodaysago = mktime(0, 0, 0) - (2 * DAYSECS);
        $this->set_streak((int) $user->id, 5, 5, $twodaysago, 1);

        streak_manager::process_breaks();
        streak_manager::record_activity((int) $user->id);

        $streak = streak_manager::get_or_create((int) $user->id);
        $this->assertSame(6, (int) $streak->currentstreak);
        $this->assertSame(6, (int) $streak->longeststreak);
        $this->assertSame(mktime(0, 0, 0), (int) $streak->lastactivedate);
    }

    public function test_a_freeze_covers_a_missed_day_only_once(): void {
        $this->resetAfterTest();
        $this->redirectEvents();
        $user = $this->getDataGenerator()->create_user();
        $twodaysago = mktime(0, 0, 0) - (2 * DAYSECS);
        $this->set_streak((int) $user->id, 5, 5, $twodaysago, 2);

        streak_manager::process_breaks();
        $secondrun = streak_manager::process_breaks();

        // The same night run twice must not spend the second freeze on the day already covered.
        $streak = streak_manager::get_or_create((int) $user->id);
        $this->assertSame(0, $secondrun);
        $this->assertSame(1, (int) $streak->freezesavailable);
        $this->assertSame(5, (int) $streak->currentstreak);
    }

    public function test_process_breaks_records_streak_broken_activity_log(): void {
        global $DB;
        $this->resetAfterTest();
        $this->redirectEvents();
        $user = $this->getDataGenerator()->create_user();
        $twodaysago = mktime(0, 0, 0) - (2 * DAYSECS);
        $this->set_streak((int) $user->id, 5, 5, $twodaysago, 0);

        streak_manager::process_breaks();

        $row = $DB->get_record('local_playergames_activity_log', ['userid' => $user->id], '*', MUST_EXIST);
        $this->assertSame(activity_log::TYPE_STREAK_BROKEN, $row->eventtype);
        $this->assertSame(0, (int) $row->xpdelta);
        $this->assertSame('cron', $row->source);
    }

    public function test_process_breaks_consuming_a_freeze_records_activity_log(): void {
        global $DB;
        $this->resetAfterTest();
        $this->redirectEvents();
        $user = $this->getDataGenerator()->create_user();
        $twodaysago = mktime(0, 0, 0) - (2 * DAYSECS);
        $this->set_streak((int) $user->id, 5, 5, $twodaysago, 1);

        streak_manager::process_breaks();

        $row = $DB->get_record('local_playergames_activity_log', ['userid' => $user->id], '*', MUST_EXIST);
        $this->assertSame(activity_log::TYPE_FREEZE_USED, $row->eventtype);
        $this->assertSame('streak_break', $row->source);
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

    public function test_add_freezes_records_activity_log(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        streak_manager::add_freezes((int) $user->id, 2);

        $row = $DB->get_record('local_playergames_activity_log', ['userid' => $user->id], '*', MUST_EXIST);
        $this->assertSame(activity_log::TYPE_FREEZE_EARNED, $row->eventtype);
        $this->assertSame(0, (int) $row->xpdelta);
        $this->assertSame('mission', $row->source);
    }

    public function test_add_freezes_respects_the_cap(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        set_config('freeze_max', 2, 'local_playergames');

        $first  = streak_manager::add_freezes((int) $user->id, 1);
        $second = streak_manager::add_freezes((int) $user->id, 5);
        $third  = streak_manager::add_freezes((int) $user->id, 1);

        // Cap is 2: the second call tops up to 2, the third adds nothing.
        $this->assertSame(1, $first);
        $this->assertSame(1, $second);
        $this->assertSame(0, $third);
        $this->assertSame(2, (int) streak_manager::get_or_create((int) $user->id)->freezesavailable);
    }

    /**
     * The freeze log lists the latest events first and stops at the limit.
     */
    public function test_get_freeze_log_returns_the_latest_events_first(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        set_config('freeze_max', 10, 'local_playergames');

        foreach ([1, 2, 3] as $amount) {
            streak_manager::add_freezes((int) $user->id, $amount);
            $DB->set_field(
                'local_playergames_freeze_log',
                'timecreated',
                1000 + $amount,
                ['userid' => $user->id, 'amount' => $amount]
            );
        }
        streak_manager::add_freezes((int) $other->id, 1);

        $log = streak_manager::get_freeze_log((int) $user->id, 2);

        $this->assertSame([3, 2], array_map(fn($entry) => (int) $entry->amount, $log));
        $this->assertCount(3, streak_manager::get_freeze_log((int) $user->id));
        $this->assertSame([], streak_manager::get_freeze_log((int) $this->getDataGenerator()->create_user()->id));
    }
}
