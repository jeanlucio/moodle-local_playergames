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
 * Tests for the season summary.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\hub;

use local_playergames\local\access;
use local_playergames\local\preferences;
use local_playergames\task\build_season_summary;

/**
 * Unit tests for {@see season_summary} and {@see build_season_summary}.
 *
 * @package    local_playergames
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_playergames\hub\season_summary
 * @covers \local_playergames\task\build_season_summary
 */
final class season_summary_test extends \advanced_testcase {
    /**
     * Creates a closed season.
     *
     * @return int The season id.
     */
    private function make_season(): int {
        global $DB;
        $now = time();
        return (int) $DB->insert_record('local_playergames_seasons', (object) [
            'name' => 'Season A', 'startdate' => $now - (DAYSECS * 60), 'enddate' => $now - DAYSECS,
            'status' => 'closed', 'config_snapshot' => json_encode([]),
            'timecreated' => $now - (DAYSECS * 60), 'timemodified' => $now,
        ]);
    }

    /**
     * Creates a student or a teacher enrolled in a course.
     *
     * @param string $role The role in the course.
     * @return int The user id.
     */
    private function make_user(string $role): int {
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $role);
        return (int) $user->id;
    }

    /**
     * Writes the profile a user ended a season with.
     *
     * @param int $userid The user.
     * @param int $seasonid The season.
     * @param int $xp Final XP.
     * @param bool $visible Whether the user showed in the ranking.
     * @param int $modified Value of timemodified, the tie-break.
     * @return void
     */
    private function make_profile(int $userid, int $seasonid, int $xp, bool $visible, int $modified = 1000): void {
        global $DB;
        $DB->insert_record('local_playergames_player_profile', (object) [
            'userid' => $userid, 'seasonid' => $seasonid, 'xp' => $xp, 'level' => 3,
            'showinranking' => $visible ? 1 : 0, 'timecreated' => 1, 'timemodified' => $modified,
        ]);
    }

    /**
     * Returns the summary row of a user.
     *
     * @param int $userid The user.
     * @param int $seasonid The season.
     * @return \stdClass|false
     */
    private function summary_of(int $userid, int $seasonid) {
        global $DB;
        return $DB->get_record('local_playergames_season_summary', ['userid' => $userid, 'seasonid' => $seasonid]);
    }

    public function test_build_saves_the_final_result_of_each_player_with_xp(): void {
        $this->resetAfterTest();
        $seasonid = $this->make_season();
        $player = $this->make_user('student');
        $idle = $this->make_user('student');
        $this->make_profile($player, $seasonid, 120, true);
        $this->make_profile($idle, $seasonid, 0, true);

        $this->assertSame(1, season_summary::build($seasonid));

        $row = $this->summary_of($player, $seasonid);
        $this->assertSame(120, (int) $row->xp);
        $this->assertSame(3, (int) $row->level);
        $this->assertFalse($this->summary_of($idle, $seasonid));
    }

    public function test_build_counts_the_missions_completed_in_the_season_only(): void {
        global $DB;
        $this->resetAfterTest();
        $seasonid = $this->make_season();
        $other = $this->make_season();
        $player = $this->make_user('student');
        $this->make_profile($player, $seasonid, 50, false);
        foreach ([[1, $seasonid, 1], [2, $seasonid, 1], [3, $seasonid, 0], [1, $other, 1]] as [$mission, $season, $done]) {
            $DB->insert_record('local_playergames_mission_progress', (object) [
                'userid' => $player, 'missionid' => $mission, 'seasonid' => $season,
                'currentvalue' => 1, 'completed' => $done,
            ]);
        }

        season_summary::build($seasonid);

        $this->assertSame(2, (int) $this->summary_of($player, $seasonid)->missionsdone);
    }

    public function test_the_position_follows_the_order_of_the_hub_in_each_group(): void {
        $this->resetAfterTest();
        set_config('allowed_participants', 'both', 'local_playergames');
        $seasonid = $this->make_season();
        $first = $this->make_user('student');
        $tied = $this->make_user('student');
        $tiedlater = $this->make_user('student');
        $teacher = $this->make_user('editingteacher');
        access::refresh_staff_ids();
        $this->make_profile($tiedlater, $seasonid, 80, true, 3000);
        $this->make_profile($first, $seasonid, 200, true);
        $this->make_profile($tied, $seasonid, 80, true, 2000);
        $this->make_profile($teacher, $seasonid, 30, true);

        season_summary::build($seasonid);

        $this->assertSame([1, 3], [(int) $this->summary_of($first, $seasonid)->finalrank,
            (int) $this->summary_of($first, $seasonid)->rankedof]);
        $this->assertSame(2, (int) $this->summary_of($tied, $seasonid)->finalrank);
        $this->assertSame(3, (int) $this->summary_of($tiedlater, $seasonid)->finalrank);
        // The staff is ranked on its own: first of one.
        $this->assertSame([1, 1], [(int) $this->summary_of($teacher, $seasonid)->finalrank,
            (int) $this->summary_of($teacher, $seasonid)->rankedof]);
    }

    public function test_who_was_not_in_the_ranking_has_no_position_and_stays_out_of_the_hall(): void {
        $this->resetAfterTest();
        $seasonid = $this->make_season();
        $hidden = $this->make_user('student');
        $suspended = $this->getDataGenerator()->create_user(['suspended' => 1]);
        $optedout = $this->make_user('student');
        $shown = $this->make_user('student');
        $this->make_profile($hidden, $seasonid, 500, false);
        $this->make_profile((int) $suspended->id, $seasonid, 400, true);
        $this->make_profile($optedout, $seasonid, 300, true);
        $this->make_profile($shown, $seasonid, 100, true);
        set_user_preference(preferences::PREF_GAMIFICATION, '0', $optedout);

        season_summary::build($seasonid);

        foreach ([$hidden, (int) $suspended->id, $optedout] as $userid) {
            $row = $this->summary_of($userid, $seasonid);
            $this->assertNull($row->finalrank);
            $this->assertNull($row->rankedof);
            $this->assertSame(0, (int) $row->showinhall);
        }
        $row = $this->summary_of($shown, $seasonid);
        $this->assertSame([1, 1, 1], [(int) $row->finalrank, (int) $row->rankedof, (int) $row->showinhall]);
    }

    public function test_a_deleted_user_gets_no_result(): void {
        $this->resetAfterTest();
        $seasonid = $this->make_season();
        $gone = $this->getDataGenerator()->create_user(['deleted' => 1]);
        $this->make_profile((int) $gone->id, $seasonid, 90, true);

        $this->assertSame(0, season_summary::build($seasonid));
    }

    public function test_building_again_writes_only_what_is_missing_and_keeps_a_changed_switch(): void {
        global $DB;
        $this->resetAfterTest();
        $seasonid = $this->make_season();
        $first = $this->make_user('student');
        $this->make_profile($first, $seasonid, 90, true);
        season_summary::build($seasonid);
        $DB->set_field('local_playergames_season_summary', 'showinhall', 0, ['userid' => $first]);
        $late = $this->make_user('student');
        $this->make_profile($late, $seasonid, 40, true);

        $this->assertSame(1, season_summary::build($seasonid));

        $this->assertSame(0, (int) $this->summary_of($first, $seasonid)->showinhall);
        $this->assertNotFalse($this->summary_of($late, $seasonid));
        $this->assertSame(2, $DB->count_records('local_playergames_season_summary'));
    }

    public function test_count_players_reads_the_profiles_and_then_the_summary(): void {
        global $DB;
        $this->resetAfterTest();
        $seasonid = $this->make_season();
        $one = $this->make_user('student');
        $two = $this->make_user('student');
        $idle = $this->make_user('student');
        $this->make_profile($one, $seasonid, 10, false);
        $this->make_profile($two, $seasonid, 20, false);
        $this->make_profile($idle, $seasonid, 0, false);
        season_summary::build($seasonid);

        $this->assertSame(3, season_summary::count_players($seasonid));

        $DB->delete_records('local_playergames_player_profile', ['seasonid' => $seasonid]);
        $this->assertSame(2, season_summary::count_players($seasonid));
    }

    public function test_get_for_user_lists_the_most_recent_season_first(): void {
        global $DB;
        $this->resetAfterTest();
        $older = $this->make_season();
        $newer = $this->make_season();
        $DB->set_field('local_playergames_seasons', 'enddate', time() - (DAYSECS * 100), ['id' => $older]);
        $user = $this->make_user('student');
        $this->make_profile($user, $older, 10, false);
        $this->make_profile($user, $newer, 20, false);
        season_summary::build($older);
        season_summary::build($newer);

        $rows = season_summary::get_for_user($user);

        $this->assertSame([$newer, $older], array_map(fn($r): int => (int) $r->seasonid, $rows));
        $this->assertSame([], season_summary::get_for_user($this->make_user('student')));
    }

    public function test_closing_a_season_queues_the_task_that_saves_the_results(): void {
        global $DB;
        $this->resetAfterTest();
        $seasonid = $this->make_season();
        $DB->set_field('local_playergames_seasons', 'status', 'active', ['id' => $seasonid]);
        $user = $this->make_user('student');
        $this->make_profile($user, $seasonid, 60, true);

        season_manager::close($seasonid);
        $this->assertFalse($this->summary_of($user, $seasonid));

        ob_start();
        $this->runAdhocTasks(build_season_summary::class);
        ob_get_clean();

        $this->assertSame(60, (int) $this->summary_of($user, $seasonid)->xp);
    }

    public function test_the_task_ignores_a_season_that_no_longer_exists(): void {
        global $DB;
        $this->resetAfterTest();
        $task = new build_season_summary();
        $task->set_custom_data(['seasonid' => 99999]);

        ob_start();
        $task->execute();
        ob_get_clean();

        $this->assertSame(0, $DB->count_records('local_playergames_season_summary'));
    }

    public function test_deleting_a_season_deletes_its_results(): void {
        global $DB;
        $this->resetAfterTest();
        $seasonid = $this->make_season();
        $user = $this->make_user('student');
        $this->make_profile($user, $seasonid, 60, true);
        season_summary::build($seasonid);

        season_manager::delete($seasonid);

        $this->assertSame(0, $DB->count_records('local_playergames_season_summary'));
    }
}
