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
 * Tests for the purge_old_scores scheduled task.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\task;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Unit tests for {@see purge_old_scores}.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(purge_old_scores::class)]
final class purge_old_scores_test extends \advanced_testcase {
    /**
     * Inserts a closed season spanning the given dates.
     *
     * @param int $start Start timestamp.
     * @param int $end End timestamp.
     * @return int Season id.
     */
    private function make_closed_season(int $start, int $end): int {
        global $DB;
        return (int) $DB->insert_record('local_playergames_seasons', (object) [
            'name' => 'S', 'startdate' => $start, 'enddate' => $end, 'status' => 'closed',
            'config_snapshot' => json_encode([]), 'timecreated' => $start, 'timemodified' => $end,
        ]);
    }

    /**
     * Inserts a daily_scores row on a given date.
     *
     * @param int $gamedate Score date.
     * @return void
     */
    private function add_score(int $gamedate): void {
        global $DB;
        $DB->insert_record('local_playergames_daily_scores', (object) [
            'userid' => 5, 'gamedate' => $gamedate, 'gametype' => 'quiz',
            'completed' => 1, 'xpawarded' => 10, 'attempts' => 1, 'timeplayed' => $gamedate,
        ]);
    }

    public function test_purges_scores_beyond_retention_window(): void {
        global $DB;
        $this->resetAfterTest();
        // Keep only the single most-recent closed season.
        set_config('seasons_keep', 1, 'local_playergames');

        $now = time();
        $olderstart = $now - (DAYSECS * 100);
        $olderend   = $now - (DAYSECS * 70);
        $newerstart = $now - (DAYSECS * 60);
        $newerend   = $now - (DAYSECS * 30);
        $this->make_closed_season($olderstart, $olderend);
        $this->make_closed_season($newerstart, $newerend);

        $this->add_score($olderstart + DAYSECS);
        $this->add_score($newerstart + DAYSECS);

        ob_start();
        (new purge_old_scores())->execute();
        ob_get_clean();

        // The older season's scores are purged; the kept season's remain.
        $this->assertSame(0, $DB->count_records_select(
            'local_playergames_daily_scores',
            'gamedate BETWEEN :s AND :e',
            ['s' => $olderstart, 'e' => $olderend]
        ));
        $this->assertSame(1, $DB->count_records_select(
            'local_playergames_daily_scores',
            'gamedate BETWEEN :s AND :e',
            ['s' => $newerstart, 'e' => $newerend]
        ));
    }

    public function test_keeps_everything_within_window(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('seasons_keep', 2, 'local_playergames');

        $now = time();
        $this->make_closed_season($now - (DAYSECS * 60), $now - (DAYSECS * 30));
        $this->add_score($now - (DAYSECS * 45));

        ob_start();
        (new purge_old_scores())->execute();
        ob_get_clean();

        // Only one closed season exists, below the keep threshold: nothing purged.
        $this->assertSame(1, $DB->count_records('local_playergames_daily_scores'));
    }
}
