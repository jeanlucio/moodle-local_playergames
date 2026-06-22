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
 * Tests for the season game configuration helper.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\games;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Unit tests for {@see season_game_config}.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(season_game_config::class)]
final class season_game_config_test extends \advanced_testcase {
    /**
     * Inserts an active season.
     *
     * @return int Season id.
     */
    private function make_active_season(): int {
        global $DB;
        $now = time();
        return (int) $DB->insert_record('local_playergames_seasons', (object) [
            'name' => 'S', 'startdate' => $now - DAYSECS, 'enddate' => $now + DAYSECS,
            'status' => 'active', 'config_snapshot' => json_encode([]),
            'timecreated' => $now, 'timemodified' => $now,
        ]);
    }

    /**
     * Inserts a season_games row.
     *
     * @param int $seasonid Season id.
     * @param string $gametype Game type.
     * @param int $enabled Whether enabled.
     * @return void
     */
    private function add_game(int $seasonid, string $gametype, int $enabled = 1): void {
        global $DB;
        $DB->insert_record('local_playergames_season_games', (object) [
            'seasonid' => $seasonid, 'gametype' => $gametype, 'enabled' => $enabled,
            'source' => season_game_config::SOURCE_CARTRIDGE, 'cartridgeids' => null, 'auxid' => 0,
        ]);
    }

    public function test_uses_cartridge(): void {
        $this->assertTrue(season_game_config::uses_cartridge(season_game_config::SOURCE_CARTRIDGE));
        $this->assertTrue(season_game_config::uses_cartridge(season_game_config::SOURCE_BOTH));
        $this->assertFalse(season_game_config::uses_cartridge(season_game_config::SOURCE_QUESTIONBANK));
        $this->assertFalse(season_game_config::uses_cartridge(season_game_config::SOURCE_GLOSSARY));
    }

    public function test_uses_secondary(): void {
        $this->assertFalse(season_game_config::uses_secondary(season_game_config::SOURCE_CARTRIDGE));
        $this->assertTrue(season_game_config::uses_secondary(season_game_config::SOURCE_BOTH));
        $this->assertTrue(season_game_config::uses_secondary(season_game_config::SOURCE_QUESTIONBANK));
        $this->assertTrue(season_game_config::uses_secondary(season_game_config::SOURCE_GLOSSARY));
    }

    public function test_get_for_active_season_without_season(): void {
        $this->resetAfterTest();
        $this->assertNull(season_game_config::get_for_active_season('quiz'));
    }

    public function test_get_for_active_season_returns_enabled_record(): void {
        $this->resetAfterTest();
        $seasonid = $this->make_active_season();
        $this->add_game($seasonid, 'quiz', 1);
        $this->add_game($seasonid, 'guess', 0);

        $record = season_game_config::get_for_active_season('quiz');
        $this->assertNotNull($record);
        $this->assertSame('quiz', $record->gametype);

        // A disabled game returns null.
        $this->assertNull(season_game_config::get_for_active_season('guess'));
        // An unconfigured game returns null.
        $this->assertNull(season_game_config::get_for_active_season('fill'));
    }

    public function test_get_all_for_season_keyed_by_gametype(): void {
        $this->resetAfterTest();
        $seasonid = $this->make_active_season();
        $this->add_game($seasonid, 'quiz');
        $this->add_game($seasonid, 'guess');

        $all = season_game_config::get_all_for_season($seasonid);

        $this->assertCount(2, $all);
        $this->assertArrayHasKey('quiz', $all);
        $this->assertArrayHasKey('guess', $all);
    }

    public function test_seed_defaults_creates_enabled_cartridge_rows(): void {
        $this->resetAfterTest();
        $seasonid = $this->make_active_season();

        season_game_config::seed_defaults($seasonid);

        $all = season_game_config::get_all_for_season($seasonid);
        $this->assertCount(count(season_game_config::GAMETYPES), $all);
        foreach (season_game_config::GAMETYPES as $gametype) {
            $this->assertArrayHasKey($gametype, $all);
            $this->assertSame(1, (int) $all[$gametype]->enabled);
            $this->assertSame(season_game_config::SOURCE_CARTRIDGE, $all[$gametype]->source);
        }
    }

    public function test_seed_defaults_preserves_existing_rows(): void {
        $this->resetAfterTest();
        $seasonid = $this->make_active_season();
        // Pre-configure one game as disabled; seeding must not overwrite it.
        $this->add_game($seasonid, 'quiz', 0);

        season_game_config::seed_defaults($seasonid);

        $all = season_game_config::get_all_for_season($seasonid);
        $this->assertCount(count(season_game_config::GAMETYPES), $all);
        $this->assertSame(0, (int) $all['quiz']->enabled);
        $this->assertSame(1, (int) $all['guess']->enabled);
    }
}
