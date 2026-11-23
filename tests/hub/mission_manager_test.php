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

    public function test_get_all_lists_the_missions_a_site_starts_with(): void {
        $this->resetAfterTest();

        $missions = mission_manager::get_all();

        $this->assertSame(array_keys($missions), array_map('intval', array_column($missions, 'id')));
        $this->assertSame(['daily', 'streak', 'cumulative', 'battle_win', 'checkin_streak'], array_column($missions, 'type'));
        $this->assertSame(get_string('mission_streak_name', 'local_playergames'), mission_manager::display_name(
            $missions[array_search('streak', array_column($missions, 'type', 'id'))]
        ));
    }

    /**
     * Adds a mission through the manager.
     *
     * @param string $type The kind.
     * @param int $target The target.
     * @param int $xp The XP reward.
     * @param string $name The name.
     * @return int The mission id.
     */
    private function add_mission(string $type, int $target, int $xp = 10, string $name = ''): int {
        return mission_manager::save((object) [
            'id' => 0, 'type' => $type, 'name' => $name, 'targetvalue' => $target, 'xpreward' => $xp,
            'freezereward' => 0, 'enabled' => 1,
        ]);
    }

    /**
     * Two missions of one kind move on together, each to its own target.
     */
    public function test_missions_of_the_same_kind_each_reach_their_own_target(): void {
        global $DB;
        $this->resetAfterTest();
        $seasonid = $this->make_season();
        $user = $this->getDataGenerator()->create_user();
        mission_manager::sync();
        $three = $this->add_mission('battle_win', 3, 40, 'Triple win');

        mission_manager::update((int) $user->id, $seasonid, 'battle_won');
        mission_manager::update((int) $user->id, $seasonid, 'battle_won');
        $first = $DB->get_record('local_playergames_missions', ['namestring' => 'mission_battle_win_name']);
        $progress = fn(int $id): \stdClass => $DB->get_record(
            'local_playergames_mission_progress',
            ['missionid' => $id, 'userid' => $user->id]
        );
        $this->assertEquals(1, $progress((int) $first->id)->completed);
        $this->assertEquals(0, $progress($three)->completed);
        $this->assertEquals(2, $progress($three)->currentvalue);

        mission_manager::update((int) $user->id, $seasonid, 'battle_won');
        $this->assertEquals(1, $progress($three)->completed);
        $this->assertSame(30 + 40, $this->xp((int) $user->id, $seasonid));
        $named = $DB->get_record('local_playergames_missions', ['id' => $three]);
        $this->assertSame('Triple win', mission_manager::display_name($named));
    }

    /**
     * The description says the target, in the singular where there is one.
     */
    public function test_the_description_follows_the_target(): void {
        $this->resetAfterTest();
        $mission = fn(string $type, int $target): \stdClass => (object) ['type' => $type, 'targetvalue' => $target];

        $this->assertSame(
            get_string('mission_desc_streak', 'local_playergames', 30),
            mission_manager::description($mission('streak', 30))
        );
        $this->assertSame(
            get_string('mission_desc_daily_one', 'local_playergames'),
            mission_manager::description($mission('daily', 1))
        );
        $this->assertSame(
            get_string('mission_desc_daily', 'local_playergames', 3),
            mission_manager::description($mission('daily', 3))
        );
        $this->assertStringContainsString('30', mission_manager::description($mission('streak', 30)));
    }

    /**
     * A mission that is off neither moves on nor shows; a new one can be deleted, one somebody started cannot.
     */
    public function test_a_mission_off_does_not_move_and_one_with_a_history_is_not_deleted(): void {
        global $DB;
        $this->resetAfterTest();
        $seasonid = $this->make_season();
        $user = $this->getDataGenerator()->create_user();
        mission_manager::sync();
        $daily = (int) $DB->get_field('local_playergames_missions', 'id', ['type' => 'daily']);
        $spare = $this->add_mission('daily', 2);

        $this->assertTrue(mission_manager::set_enabled($daily, false));
        $this->assertArrayNotHasKey($daily, mission_manager::get_enabled());
        mission_manager::update((int) $user->id, $seasonid, 'game_played');
        $this->assertFalse($DB->record_exists('local_playergames_mission_progress', ['missionid' => $daily]));
        $this->assertTrue($DB->record_exists('local_playergames_mission_progress', ['missionid' => $spare]));

        $this->assertFalse(mission_manager::delete($spare));
        $this->assertTrue($DB->record_exists('local_playergames_missions', ['id' => $spare]));
        $this->assertTrue(mission_manager::delete($daily));
        $this->assertFalse($DB->record_exists('local_playergames_missions', ['id' => $daily]));
    }

    /**
     * Once somebody completes a mission in the active season, it stays on and keeps its target and rewards until the
     * season ends; only its name changes.
     */
    public function test_a_mission_completed_this_season_is_locked_until_it_ends(): void {
        global $DB;
        $this->resetAfterTest();
        $seasonid = $this->make_season();
        $user = $this->getDataGenerator()->create_user();
        mission_manager::sync();
        $daily = $DB->get_record('local_playergames_missions', ['type' => 'daily'], '*', MUST_EXIST);
        $this->assertFalse(mission_manager::is_locked((int) $daily->id));

        mission_manager::update((int) $user->id, $seasonid, 'game_played');
        $this->assertTrue(mission_manager::is_locked((int) $daily->id));
        $this->assertFalse(mission_manager::set_enabled((int) $daily->id, false));
        mission_manager::save((object) [
            'id' => $daily->id, 'name' => 'Daily player', 'targetvalue' => 5, 'xpreward' => 500, 'freezereward' => 0,
            'enabled' => 0,
        ]);
        $after = $DB->get_record('local_playergames_missions', ['id' => $daily->id], '*', MUST_EXIST);
        $this->assertSame('Daily player', $after->name);
        $this->assertEquals([1, 5, 1], [$after->targetvalue, $after->xpreward, $after->enabled]);

        // The daily mission starts again the next day, and it was still completed this season.
        $DB->set_field('local_playergames_mission_progress', 'timecompleted', time() - 2 * DAYSECS);
        mission_manager::reset_daily($seasonid);
        $this->assertTrue(mission_manager::is_locked((int) $daily->id));

        $DB->set_field('local_playergames_seasons', 'status', 'closed', ['id' => $seasonid]);
        $this->assertFalse(mission_manager::is_locked((int) $daily->id));
        $this->assertTrue(mission_manager::set_enabled((int) $daily->id, false));
    }

    /**
     * The values of a mission stay inside the range of its kind, and a mission gives something.
     */
    public function test_the_values_of_a_mission_are_checked(): void {
        $this->resetAfterTest();
        $this->assertSame([], mission_manager::errors('streak', 30, 100, 1));
        $this->assertSame(['targetvalue' => 'error_mission_target'], mission_manager::errors('streak', 1, 10, 0));
        $this->assertSame(['targetvalue' => 'error_mission_target'], mission_manager::errors('daily', 21, 10, 0));
        $this->assertSame(['xpreward' => 'error_mission_noreward'], mission_manager::errors('daily', 1, 0, 0));
        $this->assertSame(['xpreward' => 'error_mission_xp'], mission_manager::errors('daily', 1, -1, 0));
        $this->assertSame(['freezereward' => 'error_mission_freezes'], mission_manager::errors('daily', 1, 5, 99));

        $this->expectException(\moodle_exception::class);
        $this->add_mission('quiz_master', 3);
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
        // Completed yesterday, as it is when the reset of midnight runs.
        $DB->set_field('local_playergames_mission_progress', 'timecompleted', mktime(0, 0, 0) - 3600, ['userid' => $user->id]);

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

    /**
     * Reads the progress of a user on a mission.
     *
     * @param int $userid The user.
     * @param string $type The type of the mission.
     * @param int $seasonid The season.
     * @return \stdClass The progress row.
     */
    private function progress(int $userid, string $type, int $seasonid): \stdClass {
        global $DB;
        $missionid = (int) $DB->get_field('local_playergames_missions', 'id', ['type' => $type], MUST_EXIST);
        return $DB->get_record(
            'local_playergames_mission_progress',
            ['userid' => $userid, 'missionid' => $missionid, 'seasonid' => $seasonid],
            '*',
            MUST_EXIST
        );
    }

    /**
     * Records a check-in of a user on a day.
     *
     * @param int $userid The user.
     * @param int $gamedate Midnight of the day.
     */
    private function checked_in(int $userid, int $gamedate): void {
        global $DB;
        $DB->insert_record('local_playergames_daily_scores', (object) [
            'userid' => $userid, 'gamedate' => $gamedate, 'gametype' => 'checkin',
            'completed' => 1, 'xpawarded' => 5, 'attempts' => 1, 'timeplayed' => time(),
        ]);
    }

    /**
     * A daily mission completed today stays completed when the reset of midnight runs late.
     *
     * Regression test: the reset cleared every daily mission of the season, so on a site whose cron ran after
     * the first plays of the day those plays could complete the mission, and pay it, a second time.
     */
    public function test_a_late_reset_keeps_the_daily_mission_completed_today(): void {
        $this->resetAfterTest();
        $this->redirectEvents();
        $userid = (int) $this->getDataGenerator()->create_user()->id;
        $seasonid = $this->make_season();
        mission_manager::update($userid, $seasonid, 'game_played');
        $paid = $this->xp($userid, $seasonid);

        mission_manager::reset_daily($seasonid);
        mission_manager::update($userid, $seasonid, 'game_played');

        $this->assertSame(1, (int) $this->progress($userid, 'daily', $seasonid)->completed);
        $this->assertSame($paid, $this->xp($userid, $seasonid));
    }

    /**
     * The first play of the day starts the daily mission again even when the reset of midnight has not run yet.
     */
    public function test_a_play_before_the_late_reset_starts_the_daily_mission_again(): void {
        global $DB;
        $this->resetAfterTest();
        $this->redirectEvents();
        $userid = (int) $this->getDataGenerator()->create_user()->id;
        $seasonid = $this->make_season();
        mission_manager::update($userid, $seasonid, 'game_played');
        $DB->set_field('local_playergames_mission_progress', 'timecompleted', mktime(0, 0, 0) - 3600, ['userid' => $userid]);
        $yesterday = $this->xp($userid, $seasonid);

        mission_manager::update($userid, $seasonid, 'game_played');

        $progress = $this->progress($userid, 'daily', $seasonid);
        $this->assertSame(1, (int) $progress->completed);
        $this->assertGreaterThanOrEqual(mktime(0, 0, 0), (int) $progress->timecompleted);
        $this->assertGreaterThan($yesterday, $this->xp($userid, $seasonid));
    }

    /**
     * A check-in after a missed day starts the streak over even when the reset of midnight has not run yet.
     *
     * Regression test: the check-in added to the broken streak, and the late reset then cleared it, check-in
     * of today included.
     */
    public function test_a_checkin_after_a_missed_day_starts_the_streak_over_before_the_reset_runs(): void {
        global $DB;
        $this->resetAfterTest();
        $this->redirectEvents();
        $userid = (int) $this->getDataGenerator()->create_user()->id;
        $seasonid = $this->make_season();
        mission_manager::sync();
        $this->checked_in($userid, mktime(0, 0, 0) - (2 * DAYSECS));
        for ($i = 0; $i < 3; $i++) {
            mission_manager::update($userid, $seasonid, 'checkin');
        }
        $this->assertSame(1, (int) $this->progress($userid, 'checkin_streak', $seasonid)->currentvalue);
        $DB->set_field('local_playergames_mission_progress', 'currentvalue', 5, ['userid' => $userid]);
        $this->checked_in($userid, mktime(0, 0, 0));

        mission_manager::update($userid, $seasonid, 'checkin');
        mission_manager::reset_missed_checkin_streaks($seasonid, mktime(0, 0, 0) - DAYSECS);

        $this->assertSame(1, (int) $this->progress($userid, 'checkin_streak', $seasonid)->currentvalue);
    }

    /**
     * A check-in that follows one of yesterday adds to the streak.
     */
    public function test_a_checkin_after_one_of_yesterday_adds_to_the_streak(): void {
        global $DB;
        $this->resetAfterTest();
        $this->redirectEvents();
        $userid = (int) $this->getDataGenerator()->create_user()->id;
        $seasonid = $this->make_season();
        mission_manager::update($userid, $seasonid, 'checkin');
        $DB->set_field('local_playergames_mission_progress', 'currentvalue', 5, ['userid' => $userid]);
        $this->checked_in($userid, mktime(0, 0, 0) - DAYSECS);

        mission_manager::update($userid, $seasonid, 'checkin');
        mission_manager::reset_missed_checkin_streaks($seasonid, mktime(0, 0, 0) - DAYSECS);

        $this->assertSame(6, (int) $this->progress($userid, 'checkin_streak', $seasonid)->currentvalue);
    }

    /**
     * The seventh check-in in a row completes the check-in streak mission and pays its XP, once.
     */
    public function test_the_seventh_checkin_in_a_row_completes_the_streak_mission(): void {
        global $DB;
        $this->resetAfterTest();
        $this->redirectEvents();
        $userid = (int) $this->getDataGenerator()->create_user()->id;
        $seasonid = $this->make_season();
        mission_manager::update($userid, $seasonid, 'checkin');
        $DB->set_field('local_playergames_mission_progress', 'currentvalue', 6, ['userid' => $userid]);
        $this->checked_in($userid, mktime(0, 0, 0) - DAYSECS);
        $mission = $DB->get_record('local_playergames_missions', ['type' => 'checkin_streak'], '*', MUST_EXIST);
        $before = $this->xp($userid, $seasonid);

        mission_manager::update($userid, $seasonid, 'checkin');
        mission_manager::update($userid, $seasonid, 'checkin');

        $progress = $this->progress($userid, 'checkin_streak', $seasonid);
        $this->assertSame(1, (int) $progress->completed);
        $this->assertSame(7, (int) $progress->currentvalue);
        $this->assertSame($before + (int) $mission->xpreward, $this->xp($userid, $seasonid));
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
