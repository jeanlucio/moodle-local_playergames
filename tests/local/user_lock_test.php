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
 * Tests for the lock of a user and for the paths that pay a reward under it.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\local;

use local_playergames\games\battle_manager;
use local_playergames\hub\achievement_manager;
use local_playergames\hub\checkin_manager;
use local_playergames\hub\daily_play_manager;
use local_playergames\hub\mission_manager;
use local_playergames\hub\streak_manager;
use local_playergames\hub\xp_manager;

/**
 * Tests for {@see user_lock}, and that every path paying a reward waits for the same lock.
 *
 * PHPUnit cannot run two requests at once, so "another request of the user holds the lock" is made with
 * user_lock::force_busy_for_tests().
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_playergames\local\user_lock
 * @covers \local_playergames\hub\daily_play_manager
 * @covers \local_playergames\hub\checkin_manager
 * @covers \local_playergames\hub\mission_manager
 * @covers \local_playergames\hub\xp_manager
 * @covers \local_playergames\hub\streak_manager
 * @covers \local_playergames\hub\achievement_manager
 * @covers \local_playergames\games\battle_manager
 */
final class user_lock_test extends \advanced_testcase {
    #[\Override]
    protected function tearDown(): void {
        user_lock::reset_for_tests();
        parent::tearDown();
    }

    /**
     * Inserts an active season.
     *
     * @return \stdClass The season.
     */
    private function make_season(): \stdClass {
        global $DB;
        $now = time();
        $id  = (int) $DB->insert_record('local_playergames_seasons', (object) [
            'name' => 'Season',
            'startdate' => $now - DAYSECS,
            'enddate' => $now + (DAYSECS * 30),
            'status' => 'active',
            'config_snapshot' => json_encode(['xp_per_game_battle' => 10, 'xp_cap_battle' => 10, 'xp_checkin_daily' => 5]),
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        return $DB->get_record('local_playergames_seasons', ['id' => $id], '*', MUST_EXIST);
    }

    public function test_the_lock_is_reentrant_and_released_with_the_last_hold(): void {
        $this->resetAfterTest();
        $userid = (int) $this->getDataGenerator()->create_user()->id;

        $outer = user_lock::acquire($userid);
        $inner = user_lock::acquire($userid);
        $this->assertNotNull($outer);
        $this->assertNotNull($inner);

        $inner->release();
        $this->assertTrue(user_lock::is_held($userid));
        // A second release of the same hold does not give back the outer one.
        $inner->release();
        $this->assertTrue(user_lock::is_held($userid));

        $outer->release();
        $this->assertFalse(user_lock::is_held($userid));
    }

    public function test_run_returns_the_result_and_releases_on_an_exception(): void {
        $this->resetAfterTest();
        $userid = (int) $this->getDataGenerator()->create_user()->id;

        $this->assertSame(42, user_lock::run($userid, fn(): int => 42));
        $this->assertFalse(user_lock::is_held($userid));

        try {
            user_lock::run($userid, function (): void {
                throw new \RuntimeException('boom');
            });
            $this->fail('The exception should have gone up.');
        } catch (\RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }
        $this->assertFalse(user_lock::is_held($userid));
    }

    public function test_a_busy_lock_is_reported_and_run_refuses_to_work(): void {
        $this->resetAfterTest();
        $userid = (int) $this->getDataGenerator()->create_user()->id;
        user_lock::force_busy_for_tests($userid);

        $this->assertNull(user_lock::acquire($userid));
        $this->expectException(\moodle_exception::class);
        user_lock::run($userid, fn() => null);
    }

    /**
     * A game of one type cannot pay while another request of the user holds the lock.
     *
     * Regression test: the lock was per user and game, so a PlayerBattle play and a PlayerQuiz play of the
     * same moment took two different locks, read the same open mission and both paid it.
     */
    public function test_a_game_waits_for_the_lock_of_the_user_not_of_the_game(): void {
        global $DB;
        $this->resetAfterTest();
        $this->redirectEvents();
        $userid = (int) $this->getDataGenerator()->create_user()->id;
        $season = $this->make_season();
        user_lock::force_busy_for_tests($userid);

        $result = daily_play_manager::register_play($userid, 'battle', mktime(0, 0, 0), $season, \context_system::instance());

        $this->assertFalse($result['success']);
        $this->assertFalse($DB->record_exists('local_playergames_daily_scores', ['userid' => $userid]));
        $this->assertFalse($DB->record_exists('local_playergames_player_profile', ['userid' => $userid]));
    }

    /**
     * The check-in waits for the same lock as the games.
     *
     * Regression test: the check-in took no lock, so it moved the missions and the XP of the season at the
     * same time as a game.
     */
    public function test_the_checkin_waits_for_the_lock_of_the_user(): void {
        global $DB;
        $this->resetAfterTest();
        $userid = (int) $this->getDataGenerator()->create_user()->id;
        $this->make_season();
        user_lock::force_busy_for_tests($userid);

        checkin_manager::record($userid);

        $this->assertFalse($DB->record_exists('local_playergames_daily_scores', ['userid' => $userid]));
        $this->assertFalse($DB->record_exists('local_playergames_mission_progress', ['userid' => $userid]));

        // Once the lock is free, the next page checks in.
        user_lock::reset_for_tests();
        checkin_manager::record($userid);
        $this->assertTrue($DB->record_exists('local_playergames_daily_scores', ['userid' => $userid, 'gametype' => 'checkin']));
    }

    /**
     * The writers a game or the check-in reach refuse to run without the lock, instead of writing unguarded.
     */
    public function test_the_reward_writers_refuse_to_run_without_the_lock(): void {
        global $DB;
        $this->resetAfterTest();
        $userid = (int) $this->getDataGenerator()->create_user()->id;
        $season = $this->make_season();
        user_lock::force_busy_for_tests($userid);

        $writers = [
            'xp' => fn() => xp_manager::award_uncapped($userid, 10, (int) $season->id),
            'mission' => fn() => mission_manager::update($userid, (int) $season->id, 'battle_won'),
            'streak' => fn() => streak_manager::record_activity($userid),
            'freeze' => fn() => streak_manager::add_freezes($userid, 1),
            'achievement' => fn() => achievement_manager::check($userid, (int) $season->id, 'game_played', ['level' => 99]),
        ];
        foreach ($writers as $name => $writer) {
            try {
                $writer();
                $this->fail("The {$name} writer ran without the lock.");
            } catch (\moodle_exception $e) {
                $this->assertSame('error_round_busy', $e->errorcode, $name);
                $this->assertStringNotContainsString('[[', $e->getMessage(), $name);
            }
        }

        foreach (['player_profile', 'mission_progress', 'streaks', 'user_achievements'] as $table) {
            $this->assertFalse($DB->record_exists('local_playergames_' . $table, ['userid' => $userid]), $table);
        }
    }

    /**
     * A battle result waits for the lock too, and the battle stays open for when it is free.
     *
     * Regression test: the open battle was read and then deleted outside any lock, so two results sent at
     * the same time from two sessions both found it open and both added to the day's score.
     */
    public function test_a_battle_result_waits_for_the_lock_of_the_user(): void {
        global $DB;
        $this->resetAfterTest();
        $this->redirectEvents();
        $userid = (int) $this->getDataGenerator()->create_user()->id;
        $season = $this->make_season();
        \cache::make_from_params(\cache_store::MODE_SESSION, 'local_playergames', 'battle')->set($userid, [
            'gamedate' => mktime(0, 0, 0),
            'started' => time() - 600,
            'answered' => 5,
            'correct' => 5,
            'judging' => [],
        ]);
        user_lock::force_busy_for_tests($userid);

        $result = (new battle_manager())->submit_result($userid, $season, \context_system::instance(), ['victory' => true]);

        $this->assertFalse($result['success']);
        $this->assertFalse($DB->record_exists('local_playergames_battle_scores', ['userid' => $userid]));
        $this->assertIsArray(
            \cache::make_from_params(\cache_store::MODE_SESSION, 'local_playergames', 'battle')->get($userid)
        );
    }

    /**
     * The nightly break of streaks leaves alone the user a request is serving, and does the others.
     */
    public function test_the_streak_break_skips_a_user_whose_lock_is_taken(): void {
        global $DB;
        $this->resetAfterTest();
        $busy = (int) $this->getDataGenerator()->create_user()->id;
        $free = (int) $this->getDataGenerator()->create_user()->id;
        $old  = day::yesterday() - DAYSECS;
        foreach ([$busy, $free] as $userid) {
            $streak = streak_manager::get_or_create($userid);
            $DB->update_record('local_playergames_streaks', (object) [
                'id' => $streak->id,
                'currentstreak' => 4,
                'lastactivedate' => $old,
            ]);
        }
        user_lock::force_busy_for_tests($busy);

        $this->assertSame(1, streak_manager::process_breaks());

        $this->assertSame(4, (int) $DB->get_field('local_playergames_streaks', 'currentstreak', ['userid' => $busy]));
        $this->assertSame(0, (int) $DB->get_field('local_playergames_streaks', 'currentstreak', ['userid' => $free]));
    }
}
