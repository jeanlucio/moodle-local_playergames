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
 * Tests for the daily check-in manager.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\hub;

use local_playergames\local\preferences;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Integration tests for {@see checkin_manager}.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(checkin_manager::class)]
final class checkin_manager_test extends \advanced_testcase {
    /**
     * Inserts an active season carrying the given config snapshot.
     *
     * @param array $snapshot Config snapshot values.
     * @return int Season id.
     */
    private function make_season(array $snapshot = []): int {
        global $DB;
        $now = time();
        return (int) $DB->insert_record('local_playergames_seasons', (object) [
            'name' => 'Season',
            'startdate' => $now - (DAYSECS * 10),
            'enddate' => $now + (DAYSECS * 30),
            'status' => 'active',
            'config_snapshot' => json_encode($snapshot),
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * Counts the user's check-in rows for the current day.
     *
     * @param int $userid The user id.
     * @return int
     */
    private function todays_checkins(int $userid): int {
        global $DB;
        return $DB->count_records('local_playergames_daily_scores', [
            'userid' => $userid,
            'gamedate' => mktime(0, 0, 0),
            'gametype' => 'checkin',
        ]);
    }

    public function test_record_inserts_checkin_and_awards_xp(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $seasonid = $this->make_season(['xp_checkin_daily' => 5, 'xp_cap_checkin_season' => 150]);

        checkin_manager::record((int) $user->id);

        $this->assertSame(1, $this->todays_checkins((int) $user->id));
        $profile = xp_manager::get_or_create_profile((int) $user->id, $seasonid);
        $this->assertSame(5, (int) $profile->xp);
    }

    public function test_record_is_idempotent_within_a_day(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $seasonid = $this->make_season(['xp_checkin_daily' => 5, 'xp_cap_checkin_season' => 150]);

        checkin_manager::record((int) $user->id);
        checkin_manager::record((int) $user->id);

        $this->assertSame(1, $this->todays_checkins((int) $user->id));
        $profile = xp_manager::get_or_create_profile((int) $user->id, $seasonid);
        $this->assertSame(5, (int) $profile->xp);
    }

    public function test_record_respects_the_season_cap(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->make_season(['xp_checkin_daily' => 5, 'xp_cap_checkin_season' => 10]);

        // Two earlier check-ins already consumed the 10 XP season cap.
        foreach ([DAYSECS, DAYSECS * 2] as $offset) {
            $DB->insert_record('local_playergames_daily_scores', (object) [
                'userid' => $user->id,
                'gamedate' => mktime(0, 0, 0) - $offset,
                'gametype' => 'checkin',
                'completed' => 1,
                'xpawarded' => 5,
                'attempts' => 1,
                'timeplayed' => time(),
            ]);
        }

        checkin_manager::record((int) $user->id);

        $todayrow = $DB->get_record('local_playergames_daily_scores', [
            'userid' => $user->id,
            'gamedate' => mktime(0, 0, 0),
            'gametype' => 'checkin',
        ]);
        $this->assertNotEmpty($todayrow);
        $this->assertSame(0, (int) $todayrow->xpawarded);
    }

    public function test_is_participant_true_by_default(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        $this->assertTrue(checkin_manager::is_participant((int) $user->id));
    }

    public function test_is_participant_false_when_opted_out(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        set_user_preference(preferences::PREF_GAMIFICATION, 0, $user->id);

        $this->assertFalse(checkin_manager::is_participant((int) $user->id));
    }

    public function test_is_participant_excludes_students_when_staff_only(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        set_config('allowed_participants', 'staff', 'local_playergames');

        $this->assertFalse(checkin_manager::is_participant((int) $user->id));
    }

    public function test_is_participant_excludes_staff_when_students_only(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        accesslib_clear_all_caches_for_unit_testing();
        set_config('allowed_participants', 'students', 'local_playergames');

        $this->assertFalse(checkin_manager::is_participant((int) $teacher->id));
    }
}
