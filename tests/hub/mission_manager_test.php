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

/**
 * Unit tests for {@see mission_manager}.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_playergames\hub\mission_manager
 */
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

    public function test_streak_mission_grants_a_freeze(): void {
        $this->resetAfterTest();
        $this->redirectEvents();
        $user = $this->getDataGenerator()->create_user();
        $seasonid = $this->make_season();

        // Completing the streak mission (freezereward = 1) banks one freeze.
        mission_manager::update((int) $user->id, $seasonid, 'streak_updated', ['streak' => 7]);

        $this->assertSame(
            1,
            (int) streak_manager::get_or_create((int) $user->id)->freezesavailable
        );
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

    /**
     * Reads the saved progress of a mission of a type for a user.
     *
     * @param int $userid User id.
     * @param int $seasonid Season id.
     * @param string $type Mission type.
     * @return \stdClass|null
     */
    private function progress_of(int $userid, int $seasonid, string $type): ?\stdClass {
        global $DB;

        $missionid = $DB->get_field('local_playergames_missions', 'id', ['type' => $type], MUST_EXIST);
        $row = $DB->get_record('local_playergames_mission_progress', [
            'userid' => $userid, 'missionid' => $missionid, 'seasonid' => $seasonid,
        ]);
        return $row ?: null;
    }

    /**
     * The missions measured by a total show how far the player is, instead of staying at zero until done.
     *
     * Regression test: the streak and XP missions only wrote their progress on the day the target was reached,
     * so the hub showed "0/100" to a player with 60 XP.
     */
    public function test_totals_are_saved_below_the_target(): void {
        $this->resetAfterTest();
        $this->redirectEvents();
        $user = $this->getDataGenerator()->create_user();
        $seasonid = $this->make_season();
        mission_manager::sync();

        mission_manager::update((int) $user->id, $seasonid, 'xp_earned', ['total_xp' => 60]);
        mission_manager::update((int) $user->id, $seasonid, 'streak_updated', ['streak' => 3]);

        $xp = $this->progress_of((int) $user->id, $seasonid, 'cumulative');
        $streak = $this->progress_of((int) $user->id, $seasonid, 'streak');
        $this->assertSame(60, (int) $xp->currentvalue);
        $this->assertSame(0, (int) $xp->completed);
        $this->assertSame(3, (int) $streak->currentvalue);

        // A streak that broke and started again shows the new, shorter one.
        mission_manager::update((int) $user->id, $seasonid, 'streak_updated', ['streak' => 1]);
        $this->assertSame(1, (int) $this->progress_of((int) $user->id, $seasonid, 'streak')->currentvalue);
        $this->assertSame(0, $this->xp((int) $user->id, $seasonid));

        mission_manager::update((int) $user->id, $seasonid, 'xp_earned', ['total_xp' => 120]);
        $this->assertSame(1, (int) $this->progress_of((int) $user->id, $seasonid, 'cumulative')->completed);
    }

    /**
     * With no check-in at all yesterday every streak is reset, in that season only.
     */
    public function test_reset_missed_checkin_streaks_resets_everyone_when_nobody_checked_in(): void {
        global $DB;
        $this->resetAfterTest();
        $this->redirectEvents();
        $seasonid = $this->make_season();
        $otherseason = $this->make_season();
        mission_manager::sync();
        $missionid = (int) $DB->get_field('local_playergames_missions', 'id', ['type' => 'checkin_streak']);
        $user = $this->getDataGenerator()->create_user();
        foreach ([$seasonid, $otherseason] as $id) {
            $DB->insert_record('local_playergames_mission_progress', (object) [
                'userid' => $user->id, 'missionid' => $missionid, 'seasonid' => $id, 'currentvalue' => 4,
                'completed' => 0, 'timecompleted' => null,
            ]);
        }
        // A check-in of the day before yesterday does not keep a streak alive.
        $DB->insert_record('local_playergames_daily_scores', (object) [
            'userid' => $user->id, 'gamedate' => mktime(0, 0, 0) - (2 * DAYSECS), 'gametype' => 'checkin',
            'completed' => 1, 'xpawarded' => 0, 'attempts' => 1, 'timeplayed' => time(),
        ]);

        mission_manager::reset_missed_checkin_streaks($seasonid, mktime(0, 0, 0) - DAYSECS);

        $value = fn(int $id): int => (int) $DB->get_field('local_playergames_mission_progress', 'currentvalue', [
            'userid' => $user->id, 'missionid' => $missionid, 'seasonid' => $id,
        ]);
        $this->assertSame(0, $value($seasonid));
        $this->assertSame(4, $value($otherseason));
    }
}
