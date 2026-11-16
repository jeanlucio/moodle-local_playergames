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
 * Tests for the past seasons renderable.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\output;

/**
 * Unit tests for {@see past_seasons}.
 *
 * @package    local_playergames
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_playergames\output\past_seasons
 */
final class past_seasons_test extends \advanced_testcase {
    /**
     * Saves a summary row.
     *
     * @param int $userid The user.
     * @param string $name Name of a new closed season.
     * @param int|null $rank Final position, or null when not ranked.
     * @return void
     */
    private function add_result(int $userid, string $name, ?int $rank): void {
        global $DB;
        $seasonid = (int) $DB->insert_record('local_playergames_seasons', (object) [
            'name' => $name, 'startdate' => 1, 'enddate' => 2, 'status' => 'closed',
            'config_snapshot' => json_encode([]), 'timecreated' => 1, 'timemodified' => 1,
        ]);
        $DB->insert_record('local_playergames_season_summary', (object) [
            'userid' => $userid, 'seasonid' => $seasonid, 'xp' => 150, 'level' => 4, 'missionsdone' => 6,
            'finalrank' => $rank, 'rankedof' => $rank === null ? null : 20, 'showinhall' => 0, 'timecreated' => 1,
        ]);
    }

    public function test_a_user_without_results_has_nothing_to_show(): void {
        global $PAGE;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        $data = (new past_seasons((int) $user->id))->export_for_template($PAGE->get_renderer('core'));

        $this->assertFalse($data['hasseasons']);
        $this->assertSame([], $data['seasons']);
    }

    public function test_it_shows_the_result_and_the_position_of_a_ranked_user(): void {
        global $PAGE;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->add_result((int) $user->id, 'Season A & B', 3);

        $data = (new past_seasons((int) $user->id))->export_for_template($PAGE->get_renderer('core'));

        $season = $data['seasons'][0];
        $this->assertSame('Season A & B', $season['name']);
        $this->assertSame([150, 4, 6], [$season['xp'], $season['level'], $season['missions']]);
        $this->assertTrue($season['ranked']);
        $expected = get_string('past_seasons_rank', 'local_playergames', (object) ['rank' => 3, 'total' => 20]);
        $this->assertSame($expected, $season['rank']);
    }

    public function test_a_user_who_was_not_ranked_sees_that_instead_of_a_position(): void {
        global $PAGE;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->add_result((int) $user->id, 'Season A', null);

        $data = (new past_seasons((int) $user->id))->export_for_template($PAGE->get_renderer('core'));

        $this->assertFalse($data['seasons'][0]['ranked']);
        $this->assertSame(get_string('past_seasons_notranked', 'local_playergames'), $data['seasons'][0]['rank']);
    }

    public function test_the_template_renders_the_table_with_the_season_name_as_a_row_header(): void {
        global $PAGE;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->add_result((int) $user->id, 'Season A', 1);
        $output = $PAGE->get_renderer('core');

        $html = $output->render_from_template(
            'local_playergames/past_seasons',
            (new past_seasons((int) $user->id))->export_for_template($output)
        );

        $this->assertStringContainsString('<th scope="row">Season A</th>', $html);
        $this->assertStringContainsString(get_string('past_seasons_heading', 'local_playergames'), $html);
    }

    public function test_the_switch_offers_to_hide_a_visible_name_and_to_show_a_hidden_one(): void {
        global $DB, $PAGE;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->add_result((int) $user->id, 'Season A', 2);
        $renderer = $PAGE->get_renderer('core');

        $DB->set_field('local_playergames_season_summary', 'showinhall', 1, ['userid' => $user->id]);
        $visible = (new past_seasons((int) $user->id))->export_for_template($renderer)['seasons'][0];
        $this->assertSame(0, $visible['hallnext']);
        $this->assertSame(get_string('past_seasons_hall_hide', 'local_playergames'), $visible['halltext']);
        $this->assertStringContainsString('Season A', $visible['halllabel']);

        $DB->set_field('local_playergames_season_summary', 'showinhall', 0, ['userid' => $user->id]);
        $hidden = (new past_seasons((int) $user->id))->export_for_template($renderer)['seasons'][0];
        $this->assertSame(1, $hidden['hallnext']);
        $this->assertSame(get_string('past_seasons_hall_show', 'local_playergames'), $hidden['halltext']);
    }

    public function test_only_a_ranked_season_gets_the_switch_in_the_markup(): void {
        global $PAGE;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->add_result((int) $user->id, 'Ranked season', 1);
        $this->add_result((int) $user->id, 'Unranked season', null);
        $output = $PAGE->get_renderer('core');

        $html = $output->render_from_template(
            'local_playergames/past_seasons',
            (new past_seasons((int) $user->id))->export_for_template($output)
        );

        $this->assertSame(1, substr_count($html, 'name="action" value="hall"'));
        $this->assertStringContainsString('name="sesskey"', $html);
    }
}
