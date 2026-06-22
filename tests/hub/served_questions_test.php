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
 * Tests for the served-questions tracker.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\hub;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Unit tests for {@see served_questions}.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(served_questions::class)]
final class served_questions_test extends \advanced_testcase {
    public function test_record_then_get_excluded_groups_by_source(): void {
        $this->resetAfterTest();
        $today = mktime(0, 0, 0);

        served_questions::record(7, 'quiz', $today, [
            ['source' => 'cartridge', 'sourceid' => 10],
            ['source' => 'cartridge', 'sourceid' => 11],
            ['source' => 'questionbank', 'sourceid' => 99],
        ]);

        $excluded = served_questions::get_excluded(7, 'quiz', $today);

        sort($excluded['cartridge']);
        $this->assertSame([10, 11], $excluded['cartridge']);
        $this->assertSame([99], $excluded['questionbank']);
    }

    public function test_record_is_idempotent_and_scoped(): void {
        global $DB;
        $this->resetAfterTest();
        $today     = mktime(0, 0, 0);
        $yesterday = $today - DAYSECS;

        served_questions::record(7, 'quiz', $today, [['source' => 'cartridge', 'sourceid' => 10]]);
        // Same item again today must not duplicate.
        served_questions::record(7, 'quiz', $today, [['source' => 'cartridge', 'sourceid' => 10]]);

        $this->assertSame(1, $DB->count_records('local_playergames_served_questions', [
            'userid' => 7, 'gamedate' => $today, 'gametype' => 'quiz', 'sourceid' => 10,
        ]));

        // A different day is a different scope and is not excluded today.
        served_questions::record(7, 'quiz', $yesterday, [['source' => 'cartridge', 'sourceid' => 20]]);
        $excluded = served_questions::get_excluded(7, 'quiz', $today);
        $this->assertSame([10], $excluded['cartridge']);
    }

    public function test_invalid_items_are_ignored(): void {
        global $DB;
        $this->resetAfterTest();
        $today = mktime(0, 0, 0);

        served_questions::record(7, 'quiz', $today, [
            ['source' => '', 'sourceid' => 5],
            ['source' => 'cartridge', 'sourceid' => 0],
            ['source' => 'cartridge', 'sourceid' => 12],
        ]);

        $this->assertSame(1, $DB->count_records('local_playergames_served_questions', ['userid' => 7]));
    }
}
