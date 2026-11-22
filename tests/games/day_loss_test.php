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
 * Tests for the defeat that closes a game until the next day.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\games;

use local_playergames\local\day;

/**
 * Unit tests for {@see day_loss}.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_playergames\games\day_loss
 */
final class day_loss_test extends \advanced_testcase {
    public function test_the_allowed_defeats_close_the_day(): void {
        $this->resetAfterTest();
        $userid = (int) $this->getDataGenerator()->create_user()->id;
        $today = day::today();

        $this->assertFalse(day_loss::record($userid, 'battle', $today, 3));
        $this->assertFalse(day_loss::record($userid, 'battle', $today, 3));
        $this->assertFalse(day_loss::is_closed($userid, 'battle', $today));
        $this->assertTrue(day_loss::record($userid, 'battle', $today, 3));
        $this->assertTrue(day_loss::is_closed($userid, 'battle', $today));
    }

    public function test_unlimited_never_closes_the_day(): void {
        $this->resetAfterTest();
        $userid = (int) $this->getDataGenerator()->create_user()->id;
        $today = day::today();

        for ($i = 0; $i < 20; $i++) {
            $this->assertFalse(day_loss::record($userid, 'battle', $today, 0));
        }
        $this->assertFalse(day_loss::is_closed($userid, 'battle', $today));
    }

    /**
     * A day once closed stays closed, even when the admin raises the limit or sets it to Unlimited afterwards.
     */
    public function test_a_closed_day_stays_closed(): void {
        $this->resetAfterTest();
        $userid = (int) $this->getDataGenerator()->create_user()->id;
        $today = day::today();

        $this->assertTrue(day_loss::record($userid, 'quiz', $today, 1));
        $this->assertTrue(day_loss::record($userid, 'quiz', $today, 0));
        $this->assertTrue(day_loss::is_closed($userid, 'quiz', $today));
    }

    /**
     * The defeats belong to one user, one game and one day.
     */
    public function test_a_defeat_closes_only_its_own_game_user_and_day(): void {
        $this->resetAfterTest();
        $userid = (int) $this->getDataGenerator()->create_user()->id;
        $other = (int) $this->getDataGenerator()->create_user()->id;
        $today = day::today();

        day_loss::record($userid, 'quiz', $today, 1);

        $this->assertFalse(day_loss::is_closed($userid, 'battle', $today));
        $this->assertFalse(day_loss::is_closed($other, 'quiz', $today));
        $this->assertFalse(day_loss::is_closed($userid, 'quiz', day::yesterday()));
    }

    /**
     * PlayerGuess and PlayerFill are read from their own round: lost when it finished without a win.
     */
    public function test_the_word_games_are_read_from_their_round(): void {
        $this->resetAfterTest();
        $userid = (int) $this->getDataGenerator()->create_user()->id;
        $today = day::today();

        round_store::store($userid, 'guess', $today, ['finished' => true, 'won' => false]);
        round_store::store($userid, 'fill', $today, ['finished' => true, 'won' => true]);

        $this->assertTrue(day_loss::is_closed($userid, 'guess', $today));
        $this->assertFalse(day_loss::is_closed($userid, 'fill', $today));

        round_store::store($userid, 'fill', $today, ['finished' => false, 'won' => false]);
        $this->assertFalse(day_loss::is_closed($userid, 'fill', $today));
    }

    public function test_closed_games_lists_the_games_lost_today(): void {
        $this->resetAfterTest();
        $userid = (int) $this->getDataGenerator()->create_user()->id;
        $today = day::today();
        round_store::store($userid, 'guess', $today, ['finished' => true, 'won' => false]);
        round_store::store($userid, 'fill', $today, ['finished' => true, 'won' => true]);
        day_loss::record($userid, 'battle', $today, 1);
        day_loss::record($userid, 'quiz', $today, 2);
        day_loss::record($userid, 'quiz', day::yesterday(), 1);

        $closed = day_loss::closed_games($userid, $today);
        sort($closed);

        $this->assertSame(['battle', 'guess'], $closed);
    }

    public function test_a_word_game_keeps_its_defeat_in_its_own_round(): void {
        $this->resetAfterTest();
        $userid = (int) $this->getDataGenerator()->create_user()->id;

        $this->expectException(\coding_exception::class);
        day_loss::record($userid, 'guess', day::today(), 1);
    }
}
