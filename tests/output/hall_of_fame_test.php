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
        $student = $this->getDataGenerator()->create_user(['firstname' => 'Ada', 'lastname' => 'Lovelace']);
        $teacher = $this->getDataGenerator()->create_user();
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
}
