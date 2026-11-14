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
 * Tests for the activity history page data.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\output;

use local_playergames\hub\activity_log as log;

/**
 * Unit tests for {@see activity_log}.
 *
 * @package    local_playergames
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_playergames\output\activity_log
 */
final class activity_log_test extends \advanced_testcase {
    /**
     * Exports a page of the history of a user.
     *
     * @param int $userid The user.
     * @param int $page Zero-based page number.
     * @return array The template data.
     */
    private function export(int $userid, int $page = 0): array {
        global $PAGE;

        return (new activity_log($userid, $page))->export_for_template($PAGE->get_renderer('core'));
    }

    /**
     * Gives a row of the log a time, so the order does not depend on the clock.
     *
     * @param int $userid The user.
     * @param string $eventtype Event type of the row.
     * @param int $time Time to set.
     * @return void
     */
    private function date_last_row(int $userid, string $eventtype, int $time): void {
        global $DB;

        $id = $DB->get_field_select(
            'local_playergames_activity_log',
            'MAX(id)',
            'userid = :userid AND eventtype = :type',
            ['userid' => $userid, 'type' => $eventtype]
        );
        $DB->set_field('local_playergames_activity_log', 'timecreated', $time, ['id' => $id]);
    }

    /**
     * A user without events gets the empty message and no rows.
     */
    public function test_a_user_without_events_gets_the_empty_page(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        $data = $this->export((int) $user->id);

        $this->assertFalse($data['hasentries']);
        $this->assertSame([], $data['entries']);
        $this->assertSame(get_string('history_empty', 'local_playergames'), $data['str_empty']);
    }

    /**
     * Each kind of event gets its own description, signed XP and colour flags, and the newest comes first.
     */
    public function test_each_event_is_described_and_the_newest_comes_first(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Maths & Logic']);
        $userid = (int) $user->id;

        $events = [
            [log::TYPE_SEASON_XP, 10, 'quiz', 0],
            [log::TYPE_SEASON_XP, 5, 'checkin', 0],
            [log::TYPE_SEASON_XP, 3, 'unknowngame', 0],
            [log::TYPE_LEARNING_XP, 40, 'block_playerhud', (int) $course->id],
            [log::TYPE_LEARNING_XP, 20, 'block_playerhud', 0],
            [log::TYPE_LEARNING_XP, -15, 'block_playerhud', 999999],
            [log::TYPE_FREEZE_EARNED, 0, 'mission', 0],
            [log::TYPE_FREEZE_USED, 0, 'streak_break', 0],
            [log::TYPE_STREAK_BROKEN, 0, 'cron', 0],
            ['somethingelse', 0, 'x', 0],
        ];
        foreach ($events as $position => [$type, $xp, $source, $courseid]) {
            log::record($userid, $type, $xp, $source, $courseid);
            $this->date_last_row($userid, $type, 2000000000 - $position);
        }
        log::record((int) $other->id, log::TYPE_STREAK_BROKEN, 0, 'cron');

        $data = $this->export($userid);
        $entries = $data['entries'];

        $this->assertCount(10, $entries);
        $this->assertSame(
            get_string('activity_log_season_xp', 'local_playergames', get_string('hub_game_quiz', 'local_playergames')),
            $entries[0]['description']
        );
        $this->assertSame('+10', $entries[0]['xpdelta_display']);
        $this->assertTrue($entries[0]['hasxp']);
        $this->assertTrue($entries[0]['ispositive']);
        $this->assertFalse($entries[0]['isnegative']);
        $this->assertSame(
            get_string(
                'activity_log_season_xp',
                'local_playergames',
                get_string('activity_log_source_checkin', 'local_playergames')
            ),
            $entries[1]['description']
        );
        $this->assertSame(get_string('activity_log_season_xp', 'local_playergames', 'unknowngame'), $entries[2]['description']);
        $this->assertSame(
            get_string('activity_log_learning_xp_course', 'local_playergames', 'Maths & Logic'),
            $entries[3]['description']
        );
        $this->assertSame(get_string('activity_log_learning_xp', 'local_playergames'), $entries[4]['description']);
        $this->assertSame(get_string('activity_log_learning_xp', 'local_playergames'), $entries[5]['description']);
        $this->assertSame('-15', $entries[5]['xpdelta_display']);
        $this->assertTrue($entries[5]['isnegative']);
        $this->assertSame(get_string('activity_log_freeze_earned', 'local_playergames'), $entries[6]['description']);
        $this->assertFalse($entries[6]['hasxp']);
        $this->assertSame(get_string('activity_log_freeze_used', 'local_playergames'), $entries[7]['description']);
        $this->assertSame(get_string('activity_log_streak_broken', 'local_playergames'), $entries[8]['description']);
        $this->assertSame('somethingelse', $entries[9]['description']);
    }

    /**
     * The history is paged, thirty rows at a time, and a page past the end is empty.
     */
    public function test_the_history_is_paged(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        for ($i = 0; $i < 32; $i++) {
            log::record((int) $user->id, log::TYPE_STREAK_BROKEN, 0, 'cron');
        }

        $this->assertCount(30, $this->export((int) $user->id)['entries']);
        $this->assertCount(2, $this->export((int) $user->id, 1)['entries']);
        $this->assertSame([], $this->export((int) $user->id, 2)['entries']);
        $this->assertStringContainsString('page=1', $this->export((int) $user->id)['pagingbar']);
    }
}
