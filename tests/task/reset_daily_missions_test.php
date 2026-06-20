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
 * Tests for the reset_daily_missions scheduled task.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\task;

use local_playergames\hub\mission_manager;
use local_playergames\hub\streak_manager;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Unit tests for {@see reset_daily_missions}.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(reset_daily_missions::class)]
final class reset_daily_missions_test extends \advanced_testcase {
    public function test_execute_resets_daily_mission_and_breaks_streak(): void {
        global $DB;
        $this->resetAfterTest();
        $this->redirectEvents();
        $user = $this->getDataGenerator()->create_user();

        $now = time();
        $seasonid = (int) $DB->insert_record('local_playergames_seasons', (object) [
            'name' => 'S', 'startdate' => $now - DAYSECS, 'enddate' => $now + (DAYSECS * 30),
            'status' => 'active', 'config_snapshot' => json_encode([]),
            'timecreated' => $now, 'timemodified' => $now,
        ]);

        // Complete the daily mission so the reset has something to clear.
        mission_manager::update((int) $user->id, $seasonid, 'game_played');
        $daily = $DB->get_record('local_playergames_missions', ['type' => 'daily'], '*', MUST_EXIST);
        $this->assertTrue($DB->record_exists('local_playergames_mission_progress', [
            'userid' => $user->id, 'missionid' => $daily->id, 'completed' => 1,
        ]));

        // A streak that should break: last active two days ago, no freeze.
        $streak = streak_manager::get_or_create((int) $user->id);
        $streak->currentstreak = 5;
        $streak->longeststreak = 5;
        $streak->lastactivedate = mktime(0, 0, 0) - (2 * DAYSECS);
        $DB->update_record('local_playergames_streaks', $streak);

        ob_start();
        (new reset_daily_missions())->execute();
        ob_get_clean();

        // Daily mission progress cleared.
        $progress = $DB->get_record('local_playergames_mission_progress', [
            'userid' => $user->id, 'missionid' => $daily->id, 'seasonid' => $seasonid,
        ], '*', MUST_EXIST);
        $this->assertSame(0, (int) $progress->completed);
        $this->assertSame(0, (int) $progress->currentvalue);

        // Streak broken to zero.
        $this->assertSame(0, (int) streak_manager::get_or_create((int) $user->id)->currentstreak);
    }
}
