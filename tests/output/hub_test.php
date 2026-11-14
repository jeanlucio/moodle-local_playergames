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
 * Tests for the hub renderable.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\output;

/**
 * Unit tests for {@see hub}, limited to who is offered the seasons page while there is no season.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_playergames\output\hub
 */
final class hub_test extends \advanced_testcase {
    /**
     * Exports the hub of a user and renders it.
     *
     * @param bool $isadmin Whether the user manages the site.
     * @return array {data: the exported data, html: the rendered page}
     */
    private function export_and_render(bool $isadmin): array {
        global $PAGE;
        $user     = $this->getDataGenerator()->create_user();
        $renderer = $PAGE->get_renderer('core');
        $data     = (new hub((int) $user->id, false, $isadmin, 'students'))->export_for_template($renderer);
        return ['data' => $data, 'html' => $renderer->render_from_template('local_playergames/hub', $data)];
    }

    public function test_an_administrator_is_offered_the_seasons_page_when_there_is_no_season(): void {
        $this->resetAfterTest();

        ['data' => $data, 'html' => $html] = $this->export_and_render(true);

        $this->assertTrue($data['noseason']);
        $this->assertStringContainsString('/local/playergames/manage_seasons.php', $data['manageurl']);
        $this->assertStringContainsString('manage_seasons.php', $html);
        $this->assertStringContainsString(get_string('season_manage_pagetitle', 'local_playergames'), $html);
    }

    public function test_anyone_else_is_only_told_there_is_no_season(): void {
        $this->resetAfterTest();

        ['data' => $data, 'html' => $html] = $this->export_and_render(false);

        $this->assertTrue($data['noseason']);
        $this->assertSame('', $data['manageurl']);
        $this->assertStringNotContainsString('manage_seasons.php', $html);
        $this->assertStringContainsString(get_string('hub_no_season', 'local_playergames'), $html);
    }
}
