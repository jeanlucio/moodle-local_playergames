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
 * Tests for the daily-play manager.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\hub;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Unit tests for {@see daily_play_manager}.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(daily_play_manager::class)]
final class daily_play_manager_test extends \advanced_testcase {
    /**
     * Creates an active season with the given config snapshot.
     *
     * @param array $snapshot Config snapshot values.
     * @return \stdClass Season record.
     */
    private function make_season(array $snapshot): \stdClass {
        global $DB;
        $now = time();
        $id  = (int) $DB->insert_record('local_playergames_seasons', (object) [
            'name' => 'Season',
            'startdate' => $now - DAYSECS,
            'enddate' => $now + (DAYSECS * 30),
            'status' => 'active',
            'config_snapshot' => json_encode($snapshot),
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        return $DB->get_record('local_playergames_seasons', ['id' => $id], '*', MUST_EXIST);
    }

    public function test_multiple_plays_split_xp_evenly(): void {
        global $DB;
        $this->resetAfterTest();
        $this->redirectEvents();

        $user    = $this->getDataGenerator()->create_user();
        $context = \context_system::instance();
        $today   = mktime(0, 0, 0);
        $season  = $this->make_season([
            'xp_per_game_quiz' => 8,
            'xp_games_quiz'    => 3,
            'xp_cap_quiz'      => 24,
        ]);

        $first  = daily_play_manager::register_play((int) $user->id, 'quiz', $today, $season, $context);
        $second = daily_play_manager::register_play((int) $user->id, 'quiz', $today, $season, $context);
        $third  = daily_play_manager::register_play((int) $user->id, 'quiz', $today, $season, $context);

        $this->assertSame(8, $first['xpawarded']);
        $this->assertSame(2, $first['remaining']);
        $this->assertSame(8, $third['xpawarded']);
        $this->assertSame(0, $third['remaining']);

        // A single accumulating row holds the whole day's XP and play count.
        $row = $DB->get_record('local_playergames_daily_scores', [
            'userid' => $user->id, 'gamedate' => $today, 'gametype' => 'quiz',
        ]);
        $this->assertSame(24, (int) $row->xpawarded);
        $this->assertSame(3, (int) $row->attempts);
    }

    public function test_play_beyond_daily_quota_is_rejected(): void {
        $this->resetAfterTest();
        $this->redirectEvents();

        $user    = $this->getDataGenerator()->create_user();
        $context = \context_system::instance();
        $today   = mktime(0, 0, 0);
        $season  = $this->make_season([
            'xp_per_game_quiz' => 10,
            'xp_games_quiz'    => 1,
            'xp_cap_quiz'      => 10,
        ]);

        $first  = daily_play_manager::register_play((int) $user->id, 'quiz', $today, $season, $context);
        $second = daily_play_manager::register_play((int) $user->id, 'quiz', $today, $season, $context);

        $this->assertTrue($first['success']);
        $this->assertSame(10, $first['xpawarded']);
        $this->assertFalse($second['success']);
        $this->assertSame(0, $second['xpawarded']);
    }

    public function test_last_play_is_trimmed_to_exact_cap(): void {
        $this->resetAfterTest();
        $this->redirectEvents();

        $user    = $this->getDataGenerator()->create_user();
        $context = \context_system::instance();
        $today   = mktime(0, 0, 0);
        // Per game rounded up (9) so the cap (25) trims the final play to 7.
        $season  = $this->make_season([
            'xp_per_game_quiz' => 9,
            'xp_games_quiz'    => 3,
            'xp_cap_quiz'      => 25,
        ]);

        $awarded = 0;
        for ($i = 0; $i < 3; $i++) {
            $awarded += daily_play_manager::register_play((int) $user->id, 'quiz', $today, $season, $context)['xpawarded'];
        }

        $this->assertSame(25, $awarded);
    }
}
