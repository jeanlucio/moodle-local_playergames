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
 * Tests for the achievement manager.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\hub;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Unit tests for {@see achievement_manager}.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(achievement_manager::class)]
final class achievement_manager_test extends \advanced_testcase {
    /** @var int Arbitrary season id; achievements are not per-season. */
    private const SEASON = 1;

    /**
     * Inserts a completed daily_scores row.
     *
     * @param int $userid User id.
     * @param int $gamedate Game date (midnight timestamp).
     * @param string $gametype Game type.
     * @return void
     */
    private function play(int $userid, int $gamedate, string $gametype): void {
        global $DB;
        $DB->insert_record('local_playergames_daily_scores', (object) [
            'userid' => $userid,
            'gamedate' => $gamedate,
            'gametype' => $gametype,
            'completed' => 1,
            'xpawarded' => 10,
            'attempts' => 1,
            'timeplayed' => time(),
        ]);
    }

    /**
     * Counts how many achievements the user has earned.
     *
     * @param int $userid User id.
     * @return int
     */
    private function earned_count(int $userid): int {
        global $DB;
        return $DB->count_records('local_playergames_user_achievements', ['userid' => $userid]);
    }

    public function test_sync_is_idempotent(): void {
        global $DB;
        $this->resetAfterTest();

        achievement_manager::sync();
        $first = $DB->count_records('local_playergames_achievements');
        achievement_manager::sync();
        $second = $DB->count_records('local_playergames_achievements');

        $this->assertSame(6, $first);
        $this->assertSame($first, $second);
    }

    public function test_check_grants_first_game(): void {
        global $DB;
        $this->resetAfterTest();
        $this->redirectEvents();
        $user = $this->getDataGenerator()->create_user();
        $this->play((int) $user->id, mktime(0, 0, 0), 'quiz');

        achievement_manager::check((int) $user->id, self::SEASON, 'game_played', [
            'level' => 1,
            'streak' => 0,
            'gamedate' => mktime(0, 0, 0),
        ]);

        // First game earned; the 100-games achievement must not be granted yet.
        $first = $DB->get_record('local_playergames_achievements', [
            'namestring' => 'achievement_first_game_name',
        ], '*', MUST_EXIST);
        $this->assertTrue($DB->record_exists('local_playergames_user_achievements', [
            'userid' => $user->id,
            'achievementid' => $first->id,
        ]));
        $this->assertSame(1, $this->earned_count((int) $user->id));
    }

    public function test_check_is_idempotent(): void {
        $this->resetAfterTest();
        $this->redirectEvents();
        $user = $this->getDataGenerator()->create_user();
        $this->play((int) $user->id, mktime(0, 0, 0), 'quiz');

        $ctx = ['level' => 1, 'streak' => 0, 'gamedate' => mktime(0, 0, 0)];
        achievement_manager::check((int) $user->id, self::SEASON, 'game_played', $ctx);
        achievement_manager::check((int) $user->id, self::SEASON, 'game_played', $ctx);

        // Re-checking must not grant the same achievement twice.
        $this->assertSame(1, $this->earned_count((int) $user->id));
    }

    public function test_checkin_row_does_not_count_as_game(): void {
        $this->resetAfterTest();
        $this->redirectEvents();
        $user = $this->getDataGenerator()->create_user();

        // A daily check-in writes a completed daily_scores row, but it is not a
        // game and must never unlock the first-game achievement on its own.
        $this->play((int) $user->id, mktime(0, 0, 0), 'checkin');

        achievement_manager::check((int) $user->id, self::SEASON, 'game_played', [
            'level' => 1,
            'streak' => 0,
            'gamedate' => mktime(0, 0, 0),
        ]);

        $this->assertSame(0, $this->earned_count((int) $user->id));
    }

    public function test_check_grants_level_reached(): void {
        global $DB;
        $this->resetAfterTest();
        $this->redirectEvents();
        $user = $this->getDataGenerator()->create_user();

        achievement_manager::check((int) $user->id, self::SEASON, 'level_reached', [
            'level' => 5,
            'streak' => 0,
        ]);

        $level5 = $DB->get_record('local_playergames_achievements', [
            'namestring' => 'achievement_level5_name',
        ], '*', MUST_EXIST);
        $this->assertTrue($DB->record_exists('local_playergames_user_achievements', [
            'userid' => $user->id,
            'achievementid' => $level5->id,
        ]));
    }

    public function test_check_grants_all_games_day(): void {
        global $DB;
        $this->resetAfterTest();
        $this->redirectEvents();
        $user = $this->getDataGenerator()->create_user();
        $today = mktime(0, 0, 0);
        foreach (['quiz', 'guess', 'fill', 'battle'] as $type) {
            $this->play((int) $user->id, $today, $type);
        }

        achievement_manager::check((int) $user->id, self::SEASON, 'game_played', [
            'level' => 1,
            'streak' => 0,
            'gamedate' => $today,
        ]);

        $allgames = $DB->get_record('local_playergames_achievements', [
            'namestring' => 'achievement_all_games_name',
        ], '*', MUST_EXIST);
        $this->assertTrue($DB->record_exists('local_playergames_user_achievements', [
            'userid' => $user->id,
            'achievementid' => $allgames->id,
        ]));
    }
}
