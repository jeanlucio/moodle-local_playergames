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

/**
 * Unit tests for {@see purge_old_scores}.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_playergames\task\purge_old_scores
 */
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

    /**
     * Inserts a battle_scores row of a season.
     *
     * @param int $seasonid Season id.
     * @param int $gamedate Battle date.
     * @return void
     */
    private function add_battle(int $seasonid, int $gamedate): void {
        global $DB;
        $DB->insert_record('local_playergames_battle_scores', (object) [
            'userid' => 5, 'seasonid' => $seasonid, 'gamedate' => $gamedate, 'bosshpdealt' => 10,
            'questionscorrect' => 1, 'questionstotal' => 2, 'victory' => 1, 'xpawarded' => 10,
            'duration' => 60, 'timeplayed' => $gamedate,
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

    public function test_purges_battle_results_of_seasons_beyond_the_window(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('seasons_keep', 1, 'local_playergames');

        $now = time();
        $older = $this->make_closed_season($now - (DAYSECS * 100), $now - (DAYSECS * 70));
        $newer = $this->make_closed_season($now - (DAYSECS * 60), $now - (DAYSECS * 30));
        $this->add_battle($older, $now - (DAYSECS * 90));
        $this->add_battle($newer, $now - (DAYSECS * 50));

        ob_start();
        (new purge_old_scores())->execute();
        ob_get_clean();

        $this->assertSame(0, $DB->count_records('local_playergames_battle_scores', ['seasonid' => $older]));
        $this->assertSame(1, $DB->count_records('local_playergames_battle_scores', ['seasonid' => $newer]));
    }

    public function test_purges_the_rounds_of_earlier_days_and_keeps_today(): void {
        global $DB;
        $this->resetAfterTest();

        $today = mktime(0, 0, 0);
        foreach ([$today - DAYSECS, $today] as $gamedate) {
            $DB->insert_record('local_playergames_rounds', (object) [
                'userid' => 5, 'gamedate' => $gamedate, 'gametype' => 'guess', 'state' => '{}', 'timemodified' => time(),
            ]);
        }

        ob_start();
        (new purge_old_scores())->execute();
        ob_get_clean();

        $this->assertSame(1, $DB->count_records('local_playergames_rounds'));
        $this->assertSame(1, $DB->count_records('local_playergames_rounds', ['gamedate' => $today]));
    }

    /**
     * The task has a name for the scheduled tasks page.
     */
    public function test_get_name_is_the_task_string(): void {
        $this->resetAfterTest();

        $this->assertSame(get_string('task_purge_old_scores', 'local_playergames'), (new purge_old_scores())->get_name());
    }

    /**
     * The words of earlier days are dropped, even when there is no season old enough to purge.
     *
     * Regression test: nothing ever removed them, so the table grew by the words of every day.
     */
    public function test_purges_the_words_of_earlier_days_and_keeps_todays(): void {
        global $DB;
        $this->resetAfterTest();
        $today = mktime(0, 0, 0);
        foreach ([$today - (2 * DAYSECS), $today - DAYSECS, $today] as $date) {
            $DB->insert_record('local_playergames_daily_assignments', (object) [
                'gamedate' => $date, 'gametype' => 'guess', 'conceptid' => 1,
            ]);
        }

        ob_start();
        (new purge_old_scores())->execute();
        ob_get_clean();

        $this->assertSame(
            [$today],
            array_map('intval', array_values($DB->get_fieldset_select('local_playergames_daily_assignments', 'gamedate', '1 = 1')))
        );
    }

    public function test_saves_the_results_before_purging_the_profiles_of_an_old_season(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('seasons_keep', 1, 'local_playergames');

        $now = time();
        $older = $this->make_closed_season($now - (DAYSECS * 100), $now - (DAYSECS * 70));
        $newer = $this->make_closed_season($now - (DAYSECS * 60), $now - (DAYSECS * 30));
        $user = $this->getDataGenerator()->create_user();
        foreach ([$older, $newer] as $seasonid) {
            $DB->insert_record('local_playergames_player_profile', (object) [
                'userid' => $user->id, 'seasonid' => $seasonid, 'xp' => 70, 'level' => 2,
                'showinranking' => 0, 'timecreated' => 1, 'timemodified' => 1,
            ]);
            $DB->insert_record('local_playergames_mission_progress', (object) [
                'userid' => $user->id, 'missionid' => 1, 'seasonid' => $seasonid,
                'currentvalue' => 1, 'completed' => 1,
            ]);
        }

        ob_start();
        (new purge_old_scores())->execute();
        ob_get_clean();

        $this->assertSame(0, $DB->count_records('local_playergames_player_profile', ['seasonid' => $older]));
        $this->assertSame(0, $DB->count_records('local_playergames_mission_progress', ['seasonid' => $older]));
        $summary = $DB->get_record('local_playergames_season_summary', ['userid' => $user->id, 'seasonid' => $older]);
        $this->assertSame([70, 2, 1], [(int) $summary->xp, (int) $summary->level, (int) $summary->missionsdone]);
        // The season inside the window keeps its profile and has no summary yet.
        $this->assertSame(1, $DB->count_records('local_playergames_player_profile', ['seasonid' => $newer]));
        $this->assertSame(1, $DB->count_records('local_playergames_mission_progress', ['seasonid' => $newer]));
        $this->assertFalse($DB->record_exists('local_playergames_season_summary', ['seasonid' => $newer]));
    }

    public function test_purges_the_history_older_than_the_configured_months(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('activitylog_keep', 6, 'local_playergames');

        $user = $this->getDataGenerator()->create_user();
        $old = strtotime('-7 months');
        $recent = strtotime('-5 months');
        foreach ([$old, $recent] as $time) {
            $DB->insert_record('local_playergames_activity_log', (object) [
                'userid' => $user->id, 'eventtype' => 'season_xp', 'xpdelta' => 5, 'source' => 'checkin',
                'courseid' => 0, 'timecreated' => $time,
            ]);
            $DB->insert_record('local_playergames_freeze_log', (object) [
                'userid' => $user->id, 'action' => 'earned', 'amount' => 1, 'reason' => 'mission',
                'timecreated' => $time,
            ]);
        }

        ob_start();
        (new purge_old_scores())->execute();
        ob_get_clean();

        foreach (['local_playergames_activity_log', 'local_playergames_freeze_log'] as $table) {
            $this->assertSame([$recent], array_map('intval', $DB->get_fieldset_select($table, 'timecreated', '1 = 1')));
        }
    }

    public function test_keeps_all_the_history_when_the_months_are_zero(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('activitylog_keep', 0, 'local_playergames');

        $user = $this->getDataGenerator()->create_user();
        $DB->insert_record('local_playergames_activity_log', (object) [
            'userid' => $user->id, 'eventtype' => 'season_xp', 'xpdelta' => 5, 'source' => 'checkin',
            'courseid' => 0, 'timecreated' => strtotime('-5 years'),
        ]);

        ob_start();
        (new purge_old_scores())->execute();
        ob_get_clean();

        $this->assertSame(1, $DB->count_records('local_playergames_activity_log'));
    }
}
