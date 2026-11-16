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
 * Tests for the hall of fame renderable.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\output;

use local_playergames\hub\season_summary;

/**
 * Unit tests for {@see hall_of_fame}.
 *
 * @package    local_playergames
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_playergames\output\hall_of_fame
 */
final class hall_of_fame_test extends \advanced_testcase {
    /**
     * Creates a closed season in which a user ended with a ranked result.
     *
     * @param \stdClass $user The user.
     * @param string $name Season name.
     * @param int $isstaff Whether the user was ranked with the staff.
     * @return int The season id.
     */
    private function add_ranked_result(\stdClass $user, string $name, int $isstaff = 0): int {
        global $DB;
        $seasonid = (int) $DB->insert_record('local_playergames_seasons', (object) [
            'name' => $name, 'startdate' => 1, 'enddate' => 2, 'status' => 'closed',
            'config_snapshot' => json_encode([]), 'timecreated' => 1, 'timemodified' => 1,
        ]);
        $DB->insert_record('local_playergames_season_summary', (object) [
            'userid' => $user->id, 'seasonid' => $seasonid, 'xp' => 150, 'level' => 4, 'missionsdone' => 6,
            'finalrank' => 1, 'rankedof' => 1, 'isstaff' => $isstaff, 'showinhall' => 1, 'timecreated' => 1,
        ]);
        return $seasonid;
    }

    public function test_it_has_nothing_to_show_before_a_season_is_ranked(): void {
        global $PAGE;
        $this->resetAfterTest();

        $data = (new hall_of_fame(2))->export_for_template($PAGE->get_renderer('core'));

        $this->assertFalse($data['hasseasons']);
    }

    public function test_it_marks_the_row_of_the_viewer_and_names_the_groups_only_when_both_exist(): void {
        global $PAGE;
        $this->resetAfterTest();
        global $CFG;
        $student = $this->getDataGenerator()->create_user(['firstname' => 'Ada', 'lastname' => 'Lovelace']);
        $teacher = $this->getDataGenerator()->create_user();
        // An administrator sees the two rankings, so both seasons show.
        $CFG->siteadmins = (string) $student->id;
        $seasonid = $this->add_ranked_result($student, 'Season A & B');
        $this->add_ranked_result($teacher, 'Season C', 1);
        $renderer = $PAGE->get_renderer('core');

        $data = (new hall_of_fame((int) $student->id))->export_for_template($renderer);

        // The most recent season first; the one with a single group does not need a group heading.
        $this->assertSame(['Season C', 'Season A & B'], array_column($data['seasons'], 'name'));
        $this->assertFalse($data['seasons'][0]['bothgroups']);
        $this->assertTrue($data['seasons'][0]['hasstaff']);
        $this->assertFalse($data['seasons'][0]['hasstudents']);
        $row = $data['seasons'][1]['students'][0];
        $this->assertSame(
            [1, 'Ada Lovelace', 150, 4, true],
            [$row['position'], $row['name'], $row['xp'], $row['level'], $row['isself']]
        );
        $this->assertSame($seasonid, season_summary::get_hall()[1]['id']);
    }

    public function test_the_template_renders_the_names_in_a_table_with_row_scope_headers(): void {
        global $PAGE;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['firstname' => 'Ada', 'lastname' => 'Lovelace']);
        $this->add_ranked_result($user, 'Season A');
        $output = $PAGE->get_renderer('core');

        $html = $output->render_from_template(
            'local_playergames/hall_of_fame',
            (new hall_of_fame(0))->export_for_template($output)
        );

        $this->assertStringContainsString('Ada Lovelace', $html);
        $this->assertStringContainsString('<th scope="col"', $html);
        $this->assertStringContainsString(get_string('hall_intro', 'local_playergames'), $html);
    }

    /**
     * Shows the hall to a user of a given kind.
     *
     * @param int $viewerid The viewer.
     * @return array The template data.
     */
    private function hall_for(int $viewerid): array {
        global $PAGE;
        return (new hall_of_fame($viewerid))->export_for_template($PAGE->get_renderer('core'));
    }

    /**
     * Regression test: the hub never shows the staff ranking to a student, and the hall did.
     */
    public function test_a_student_does_not_see_the_staff_ranking_in_the_hall(): void {
        $this->resetAfterTest();
        set_config('allowed_participants', 'both', 'local_playergames');
        $student = $this->getDataGenerator()->create_user();
        $teacher = $this->getDataGenerator()->create_user(['firstname' => 'Grace', 'lastname' => 'Hopper']);
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        \local_playergames\local\access::refresh_staff_ids();
        $this->add_ranked_result($student, 'Season A');
        $this->add_ranked_result($teacher, 'Season B', 1);

        $seenbystudent = $this->hall_for((int) $student->id);
        $seenbyteacher = $this->hall_for((int) $teacher->id);

        // The season with only the staff result has nothing a student may see.
        $this->assertSame(['Season A'], array_column($seenbystudent['seasons'], 'name'));
        $this->assertFalse($seenbystudent['seasons'][0]['hasstaff']);
        // With 'both', the staff sees the two rankings.
        $this->assertSame(['Season B', 'Season A'], array_column($seenbyteacher['seasons'], 'name'));
    }

    public function test_staff_sees_only_the_staff_ranking_when_the_students_take_part_alone(): void {
        $this->resetAfterTest();
        $student = $this->getDataGenerator()->create_user();
        $teacher = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        \local_playergames\local\access::refresh_staff_ids();
        $this->add_ranked_result($student, 'Season A');
        $this->add_ranked_result($teacher, 'Season B', 1);

        $data = $this->hall_for((int) $teacher->id);

        $this->assertSame(['Season B'], array_column($data['seasons'], 'name'));
        $this->assertTrue($data['seasons'][0]['hasstaff']);
    }

    /**
     * Regression test: with the ranking turned off the hub shows no ranking, and the hall still listed names.
     */
    public function test_the_hall_is_empty_and_says_why_when_the_ranking_is_off(): void {
        $this->resetAfterTest();
        $student = $this->getDataGenerator()->create_user();
        $this->add_ranked_result($student, 'Season A');
        set_config('enable_ranking', 0, 'local_playergames');

        $data = $this->hall_for((int) $student->id);

        $this->assertFalse($data['hasseasons']);
        $this->assertTrue($data['rankingoff']);
    }

    public function test_the_hall_link_leaves_the_navigation_when_the_ranking_is_off(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());
        $keys = fn(): array => array_column(
            (new nav_header('hub'))->export_for_template($PAGE->get_renderer('core'))['items'],
            'key'
        );

        $this->assertContains('hall', $keys());

        set_config('enable_ranking', 0, 'local_playergames');
        $this->assertNotContains('hall', $keys());
    }
}
