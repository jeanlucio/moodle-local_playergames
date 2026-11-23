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
 * Tests for the most XP of a season.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\hub;

/**
 * Unit tests for {@see season_xp}.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_playergames\hub\season_xp
 */
final class season_xp_test extends \advanced_testcase {
    /**
     * Creates a season of ten days with the given rules.
     *
     * @param array $snapshot The rules the season froze.
     * @return \stdClass The season.
     */
    private function season(array $snapshot): \stdClass {
        $season = season_manager::create('S', mktime(0, 0, 0, 3, 1, 2027), mktime(23, 59, 59, 3, 10, 2027));
        global $DB;
        $DB->set_field('local_playergames_seasons', 'config_snapshot', json_encode($snapshot), ['id' => $season->id]);
        return $DB->get_record('local_playergames_seasons', ['id' => $season->id], '*', MUST_EXIST);
    }

    /**
     * A season counts its first and its last day.
     */
    public function test_days(): void {
        $this->resetAfterTest();
        $this->assertSame(10, season_xp::days($this->season([])));
    }

    /**
     * Every game won every day, the check-in up to its cap, and each mission the season can reach.
     */
    public function test_ceiling(): void {
        global $DB;
        $this->resetAfterTest();
        $season = $this->season([
            'xp_per_game_quiz' => 20, 'xp_games_quiz' => 2,
            'xp_per_game_guess' => 10, 'xp_games_guess' => 1,
            'xp_per_game_fill' => 10, 'xp_games_fill' => 1,
            'xp_per_game_battle' => 30, 'xp_games_battle' => 1,
            'xp_checkin_daily' => 5, 'xp_cap_checkin_season' => 30,
        ]);
        mission_manager::sync();
        $DB->set_field('local_playergames_season_games', 'enabled', 0, ['seasonid' => $season->id, 'gametype' => 'fill']);

        $ceiling = season_xp::ceiling($season);

        $this->assertSame(['quiz' => 400, 'guess' => 100, 'battle' => 300], $ceiling['games']);
        $this->assertSame(30, $ceiling['checkin']);
        // Daily 5 x 10 days, the streak of 7 days (50), 100 XP (20), first victory (30); the streaks of 14 and 30 days
        // are longer than the season and give nothing.
        $this->assertSame(50 + 50 + 20 + 30, $ceiling['missions']);
        $this->assertSame(800 + 30 + 150, $ceiling['total']);

        $DB->set_field('local_playergames_missions', 'enabled', 0, ['type' => 'daily']);
        $DB->set_field('local_playergames_missions', 'targetvalue', 10, ['namestring' => 'mission_streak14_name']);
        $this->assertSame(50 + 100 + 20 + 30, season_xp::ceiling($season)['missions']);
    }
}
