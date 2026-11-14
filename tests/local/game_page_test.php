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
 * Tests for the shared game page pieces.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\local;

/**
 * Unit tests for {@see game_page}.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_playergames\local\game_page
 */
final class game_page_test extends \advanced_testcase {
    public function test_no_season_notice_says_so_and_leads_back_to_the_hub(): void {
        $this->resetAfterTest();

        $html = game_page::no_season_html();

        $this->assertStringContainsString(get_string('hub_no_season', 'local_playergames'), $html);
        $this->assertStringContainsString('/local/playergames/hub.php', $html);
    }

    /**
     * Regression test: it must not say the game was already played.
     *
     * The pages counted the plays in the active season, found none, and answered "already played today".
     */
    public function test_no_season_notice_does_not_claim_the_game_was_played(): void {
        $this->resetAfterTest();

        $html = game_page::no_season_html();

        foreach (['quiz', 'guess', 'fill', 'battle'] as $game) {
            $this->assertStringNotContainsString(get_string($game . '_already_played', 'local_playergames'), $html);
        }
    }

    /**
     * The notice for a game the admin switched off leads back to the hub.
     */
    public function test_disabled_notice_says_so_and_leads_back_to_the_hub(): void {
        $this->resetAfterTest();

        $html = game_page::disabled_html();

        $this->assertStringContainsString(get_string('game_disabled', 'local_playergames'), $html);
        $this->assertStringContainsString('/local/playergames/hub.php', $html);
    }
}
