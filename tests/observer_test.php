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
 * Tests for the gamification event observer.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames;

use local_playergames\event\game_completed;
use local_playergames\hub\streak_manager;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Integration tests for {@see observer}.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(observer::class)]
final class observer_test extends \advanced_testcase {
    /**
     * Creates an active season with the given config snapshot.
     *
     * @param array $snapshot Config snapshot values.
     * @return int Season id.
     */
    private function make_season(array $snapshot = []): int {
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

    public function test_game_completed_drives_streak_mission_and_achievement(): void {
        global $DB;
        $this->resetAfterTest();
        $this->redirectEvents();
        $user = $this->getDataGenerator()->create_user();
        $seasonid = $this->make_season();
        $today = mktime(0, 0, 0);

        // The game POST handler writes the score before the event fires.
        $scoreid = (int) $DB->insert_record('local_playergames_daily_scores', (object) [
            'userid' => $user->id,
            'gamedate' => $today,
            'gametype' => 'quiz',
            'completed' => 1,
            'xpawarded' => 10,
            'attempts' => 1,
            'timeplayed' => time(),
        ]);

        $event = game_completed::create([
            'objectid' => $scoreid,
            'context' => \context_system::instance(),
            'userid' => (int) $user->id,
            'other' => [
                'seasonid' => $seasonid,
                'gametype' => 'quiz',
                'gamedate' => $today,
                'xpawarded' => 10,
            ],
        ]);
        observer::game_completed($event);

        // Streak advanced to 1.
        $streak = streak_manager::get_or_create((int) $user->id);
        $this->assertSame(1, (int) $streak->currentstreak);

        // Daily mission completed (target 1).
        $daily = $DB->get_record('local_playergames_missions', ['type' => 'daily'], '*', MUST_EXIST);
        $this->assertTrue($DB->record_exists('local_playergames_mission_progress', [
            'userid' => $user->id,
            'missionid' => $daily->id,
            'seasonid' => $seasonid,
            'completed' => 1,
        ]));

        // First-game achievement granted.
        $first = $DB->get_record('local_playergames_achievements', [
            'namestring' => 'achievement_first_game_name',
        ], '*', MUST_EXIST);
        $this->assertTrue($DB->record_exists('local_playergames_user_achievements', [
            'userid' => $user->id,
            'achievementid' => $first->id,
        ]));
    }

    public function test_game_completed_without_season_only_records_streak(): void {
        global $DB;
        $this->resetAfterTest();
        $this->redirectEvents();
        $user = $this->getDataGenerator()->create_user();
        $today = mktime(0, 0, 0);

        $scoreid = (int) $DB->insert_record('local_playergames_daily_scores', (object) [
            'userid' => $user->id,
            'gamedate' => $today,
            'gametype' => 'quiz',
            'completed' => 1,
            'xpawarded' => 0,
            'attempts' => 1,
            'timeplayed' => time(),
        ]);

        $event = game_completed::create([
            'objectid' => $scoreid,
            'context' => \context_system::instance(),
            'userid' => (int) $user->id,
            'other' => ['seasonid' => 0, 'gametype' => 'quiz', 'gamedate' => $today, 'xpawarded' => 0],
        ]);
        observer::game_completed($event);

        // Streak still recorded, but no mission progress without a season.
        $this->assertSame(1, (int) streak_manager::get_or_create((int) $user->id)->currentstreak);
        $this->assertSame(0, $DB->count_records('local_playergames_mission_progress'));
    }
}
