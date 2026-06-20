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
 * Tests for the mission manager.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\hub;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Unit tests for {@see mission_manager}.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(mission_manager::class)]
final class mission_manager_test extends \advanced_testcase {
    /**
     * Creates an active season so XP rewards can be awarded.
     *
     * @return int Season id.
     */
    private function make_season(): int {
        global $DB;
        $now = time();
        return (int) $DB->insert_record('local_playergames_seasons', (object) [
            'name' => 'Season',
            'startdate' => $now - DAYSECS,
            'enddate' => $now + (DAYSECS * 30),
            'status' => 'active',
            'config_snapshot' => json_encode([]),
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * Returns a user's XP in a season.
     *
     * @param int $userid User id.
     * @param int $seasonid Season id.
     * @return int
     */
    private function xp(int $userid, int $seasonid): int {
        global $DB;
        return (int) $DB->get_field('local_playergames_player_profile', 'xp', [
            'userid' => $userid,
            'seasonid' => $seasonid,
        ]);
    }

    public function test_sync_is_idempotent(): void {
        global $DB;
        $this->resetAfterTest();

        mission_manager::sync();
        $first = $DB->count_records('local_playergames_missions');
        mission_manager::sync();

        $this->assertSame(5, $first);
        $this->assertSame(5, $DB->count_records('local_playergames_missions'));
    }

    public function test_get_all_is_keyed_by_type(): void {
        $this->resetAfterTest();

        $missions = mission_manager::get_all();

        $this->assertCount(5, $missions);
        $this->assertArrayHasKey('daily', $missions);
        $this->assertArrayHasKey('checkin_streak', $missions);
    }

    public function test_update_daily_completes_and_awards_xp(): void {
        global $DB;
        $this->resetAfterTest();
        $this->redirectEvents();
        $user = $this->getDataGenerator()->create_user();
        $seasonid = $this->make_season();

        mission_manager::update((int) $user->id, $seasonid, 'game_played');

        $daily = $DB->get_record('local_playergames_missions', ['type' => 'daily'], '*', MUST_EXIST);
        $progress = $DB->get_record('local_playergames_mission_progress', [
            'userid' => $user->id,
            'missionid' => $daily->id,
            'seasonid' => $seasonid,
        ], '*', MUST_EXIST);
        // Daily target is 1, so a single game completes it and awards its XP reward.
        $this->assertSame(1, (int) $progress->completed);
        $this->assertSame((int) $daily->xpreward, $this->xp((int) $user->id, $seasonid));
    }

    public function test_update_streak_completes_only_at_target(): void {
        global $DB;
        $this->resetAfterTest();
        $this->redirectEvents();
        $user = $this->getDataGenerator()->create_user();
        $seasonid = $this->make_season();
        mission_manager::sync();
        $streakmission = $DB->get_record('local_playergames_missions', [
            'type' => 'streak',
        ], '*', MUST_EXIST);

        // Below the target (7): not completed.
        mission_manager::update((int) $user->id, $seasonid, 'streak_updated', ['streak' => 3]);
        $progress = $DB->get_record('local_playergames_mission_progress', [
            'userid' => $user->id,
            'missionid' => $streakmission->id,
        ]);
        $this->assertNotFalse($progress);
        $this->assertSame(0, (int) $progress->completed);

        // At the target: completed.
        mission_manager::update((int) $user->id, $seasonid, 'streak_updated', ['streak' => 7]);
        $progress = $DB->get_record('local_playergames_mission_progress', [
            'userid' => $user->id,
            'missionid' => $streakmission->id,
        ], '*', MUST_EXIST);
        $this->assertSame(1, (int) $progress->completed);
    }

    public function test_reset_daily_clears_progress(): void {
        global $DB;
        $this->resetAfterTest();
        $this->redirectEvents();
        $user = $this->getDataGenerator()->create_user();
        $seasonid = $this->make_season();
        mission_manager::update((int) $user->id, $seasonid, 'game_played');

        mission_manager::reset_daily($seasonid);

        $daily = $DB->get_record('local_playergames_missions', ['type' => 'daily'], '*', MUST_EXIST);
        $progress = $DB->get_record('local_playergames_mission_progress', [
            'userid' => $user->id,
            'missionid' => $daily->id,
            'seasonid' => $seasonid,
        ], '*', MUST_EXIST);
        $this->assertSame(0, (int) $progress->completed);
        $this->assertSame(0, (int) $progress->currentvalue);
        $this->assertNull($progress->timecompleted);
    }

    public function test_reset_missed_checkin_streaks(): void {
        global $DB;
        $this->resetAfterTest();
        $this->redirectEvents();
        $seasonid = $this->make_season();
        mission_manager::sync();
        $missionid = (int) $DB->get_field('local_playergames_missions', 'id', [
            'type' => 'checkin_streak',
        ]);
        $yesterday = mktime(0, 0, 0) - DAYSECS;

        $missed = $this->getDataGenerator()->create_user();
        $kept = $this->getDataGenerator()->create_user();
        foreach ([$missed, $kept] as $u) {
            $DB->insert_record('local_playergames_mission_progress', (object) [
                'userid' => $u->id,
                'missionid' => $missionid,
                'seasonid' => $seasonid,
                'currentvalue' => 5,
                'completed' => 0,
                'timecompleted' => null,
            ]);
        }
        // Only "kept" checked in yesterday.
        $DB->insert_record('local_playergames_daily_scores', (object) [
            'userid' => $kept->id,
            'gamedate' => $yesterday,
            'gametype' => 'checkin',
            'completed' => 1,
            'xpawarded' => 5,
            'attempts' => 1,
            'timeplayed' => time(),
        ]);

        mission_manager::reset_missed_checkin_streaks($seasonid, $yesterday);

        $this->assertSame(0, (int) $DB->get_field('local_playergames_mission_progress', 'currentvalue', [
            'userid' => $missed->id,
            'missionid' => $missionid,
        ]));
        $this->assertSame(5, (int) $DB->get_field('local_playergames_mission_progress', 'currentvalue', [
            'userid' => $kept->id,
            'missionid' => $missionid,
        ]));
    }
}
