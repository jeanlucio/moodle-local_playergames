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
 * Tests for the XP and level manager.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\hub;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Unit tests for {@see xp_manager}.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(xp_manager::class)]
final class xp_manager_test extends \advanced_testcase {
    /**
     * Creates an active season with the given config snapshot.
     *
     * @param array $snapshot Config snapshot values.
     * @return int Season id.
     */
    private function make_season(array $snapshot): int {
        global $DB;
        $now = time();
        return (int) $DB->insert_record('local_playergames_seasons', (object) [
            'name' => 'Season',
            'startdate' => $now - DAYSECS,
            'enddate' => $now + (DAYSECS * 30),
            'status' => 'active',
            'config_snapshot' => json_encode($snapshot),
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * Inserts a daily_scores row to simulate prior earnings.
     *
     * @param int $userid User id.
     * @param int $gamedate Game date (midnight timestamp).
     * @param string $gametype Game type.
     * @param int $xp XP already awarded.
     * @return void
     */
    private function add_score(int $userid, int $gamedate, string $gametype, int $xp): void {
        global $DB;
        $DB->insert_record('local_playergames_daily_scores', (object) [
            'userid' => $userid,
            'gamedate' => $gamedate,
            'gametype' => $gametype,
            'completed' => 1,
            'xpawarded' => $xp,
            'attempts' => 1,
            'timeplayed' => time(),
        ]);
    }

    public function test_get_level_boundaries(): void {
        $this->resetAfterTest();
        $this->assertSame(1, xp_manager::get_level(0));
        $this->assertSame(1, xp_manager::get_level(1999));
        $this->assertSame(2, xp_manager::get_level(2000));
        $this->assertSame(2, xp_manager::get_level(5999));
        $this->assertSame(3, xp_manager::get_level(6000));
        $this->assertSame(5, xp_manager::get_level(20000));
        // Beyond the top threshold the level is capped at MAX_LEVEL.
        $this->assertSame(5, xp_manager::get_level(999999));
    }

    public function test_get_xp_for_level_clamps(): void {
        $this->resetAfterTest();
        $this->assertSame(0, xp_manager::get_xp_for_level(1));
        $this->assertSame(2000, xp_manager::get_xp_for_level(2));
        $this->assertSame(20000, xp_manager::get_xp_for_level(5));
        // Out-of-range levels are clamped to the 1..MAX_LEVEL band.
        $this->assertSame(0, xp_manager::get_xp_for_level(0));
        $this->assertSame(20000, xp_manager::get_xp_for_level(99));
    }

    public function test_award_below_cap_returns_full_amount(): void {
        global $DB;
        $this->resetAfterTest();
        $this->redirectEvents();
        $user = $this->getDataGenerator()->create_user();
        $seasonid = $this->make_season(['xp_cap_quiz' => 25]);
        $today = mktime(0, 0, 0);

        $awarded = xp_manager::award((int) $user->id, 20, 'quiz', $today, $seasonid);

        $this->assertSame(20, $awarded);
        $profile = $DB->get_record('local_playergames_player_profile', [
            'userid' => $user->id,
            'seasonid' => $seasonid,
        ], '*', MUST_EXIST);
        $this->assertSame(20, (int) $profile->xp);
    }

    public function test_award_respects_remaining_cap(): void {
        $this->resetAfterTest();
        $this->redirectEvents();
        $user = $this->getDataGenerator()->create_user();
        $seasonid = $this->make_season(['xp_cap_quiz' => 25]);
        $today = mktime(0, 0, 0);
        $this->add_score((int) $user->id, $today, 'quiz', 20);

        // Cap 25, already earned 20, so only 5 remaining can be awarded.
        $awarded = xp_manager::award((int) $user->id, 20, 'quiz', $today, $seasonid);

        $this->assertSame(5, $awarded);
    }

    public function test_award_zero_when_cap_reached(): void {
        global $DB;
        $this->resetAfterTest();
        $this->redirectEvents();
        $user = $this->getDataGenerator()->create_user();
        $seasonid = $this->make_season(['xp_cap_quiz' => 25]);
        $today = mktime(0, 0, 0);
        $this->add_score((int) $user->id, $today, 'quiz', 25);

        $awarded = xp_manager::award((int) $user->id, 10, 'quiz', $today, $seasonid);

        $this->assertSame(0, $awarded);
        // No profile is created when the award is fully capped out.
        $this->assertFalse($DB->record_exists('local_playergames_player_profile', [
            'userid' => $user->id,
            'seasonid' => $seasonid,
        ]));
    }

    public function test_award_uncapped_mission_bypasses_cap(): void {
        $this->resetAfterTest();
        $this->redirectEvents();
        $user = $this->getDataGenerator()->create_user();
        // Even with a tiny quiz cap, mission rewards ignore caps entirely.
        $seasonid = $this->make_season(['xp_cap_quiz' => 25]);

        $awarded = xp_manager::award_uncapped((int) $user->id, 500, $seasonid);

        $this->assertSame(500, $awarded);
    }

    public function test_award_updates_level_and_fires_event(): void {
        global $DB;
        $this->resetAfterTest();
        $sink = $this->redirectEvents();
        $user = $this->getDataGenerator()->create_user();
        $seasonid = $this->make_season(['xp_cap_quiz' => 25]);

        // 2500 uncapped XP crosses the level-2 threshold (2000).
        xp_manager::award_uncapped((int) $user->id, 2500, $seasonid);

        $profile = $DB->get_record('local_playergames_player_profile', [
            'userid' => $user->id,
            'seasonid' => $seasonid,
        ], '*', MUST_EXIST);
        $this->assertSame(2, (int) $profile->level);

        $events = array_filter(
            $sink->get_events(),
            fn($e) => $e instanceof \local_playergames\event\level_reached
        );
        $this->assertNotEmpty($events);
    }
}
