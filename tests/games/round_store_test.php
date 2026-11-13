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
 * Tests for the round store.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\games;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Unit tests for {@see round_store}.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(round_store::class)]
final class round_store_test extends \advanced_testcase {
    public function test_fetch_returns_null_when_the_round_was_never_stored(): void {
        $this->resetAfterTest();
        $this->assertNull(round_store::fetch(5, 'guess', mktime(0, 0, 0)));
    }

    public function test_store_then_fetch_round_trips_the_state(): void {
        $this->resetAfterTest();
        $today = mktime(0, 0, 0);
        $state = ['gamedate' => $today, 'rows' => [['guess' => 'ação']], 'finished' => false, 'won' => false];

        round_store::store(5, 'guess', $today, $state);

        $this->assertSame($state, round_store::fetch(5, 'guess', $today));
    }

    public function test_store_twice_keeps_one_row_with_the_latest_state(): void {
        global $DB;
        $this->resetAfterTest();
        $today = mktime(0, 0, 0);

        round_store::store(5, 'guess', $today, ['finished' => false]);
        round_store::store(5, 'guess', $today, ['finished' => true]);

        $this->assertSame(1, $DB->count_records('local_playergames_rounds', ['userid' => 5]));
        $this->assertSame(['finished' => true], round_store::fetch(5, 'guess', $today));
    }

    public function test_rounds_are_kept_apart_by_user_game_and_day(): void {
        $this->resetAfterTest();
        $today = mktime(0, 0, 0);

        round_store::store(5, 'guess', $today, ['who' => 'five-guess']);
        round_store::store(6, 'guess', $today, ['who' => 'six-guess']);
        round_store::store(5, 'fill', $today, ['who' => 'five-fill']);

        $this->assertSame(['who' => 'five-guess'], round_store::fetch(5, 'guess', $today));
        $this->assertSame(['who' => 'six-guess'], round_store::fetch(6, 'guess', $today));
        $this->assertSame(['who' => 'five-fill'], round_store::fetch(5, 'fill', $today));
        $this->assertNull(round_store::fetch(5, 'guess', $today - DAYSECS));
    }

    public function test_an_unreadable_row_counts_as_no_round(): void {
        global $DB;
        $this->resetAfterTest();
        $today = mktime(0, 0, 0);
        $DB->insert_record('local_playergames_rounds', (object) [
            'userid' => 5, 'gamedate' => $today, 'gametype' => 'guess', 'state' => 'not json', 'timemodified' => time(),
        ]);

        $this->assertNull(round_store::fetch(5, 'guess', $today));
    }

    public function test_lock_is_released_so_it_can_be_taken_again(): void {
        $this->resetAfterTest();

        round_store::lock(5, 'guess')->release();
        $lock = round_store::lock(5, 'guess');

        $this->assertNotNull($lock);
        $lock->release();
    }
}
