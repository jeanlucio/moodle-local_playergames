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
 * Tests for the configurable level ladder manager.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\hub;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Integration tests for {@see level_manager}.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(level_manager::class)]
final class level_manager_test extends \advanced_testcase {
    public function test_get_levels_seeds_the_default_ladder(): void {
        global $DB;
        $this->resetAfterTest();

        $levels = level_manager::get_levels();

        $this->assertCount(20, $levels);
        $this->assertSame(20, $DB->count_records('local_playergames_levels'));
        $this->assertSame(0, (int) $levels[0]->minxp);
        $this->assertSame(1, (int) $levels[0]->level);
    }

    public function test_level_for_xp_uses_the_ladder(): void {
        $this->resetAfterTest();

        $this->assertSame(1, level_manager::level_for_xp(0));
        $this->assertSame(1, level_manager::level_for_xp(99));
        $this->assertSame(2, level_manager::level_for_xp(100));
        $this->assertSame(3, level_manager::level_for_xp(300));
        $this->assertSame(20, level_manager::level_for_xp(999999));
    }

    public function test_max_level_reflects_the_ladder(): void {
        $this->resetAfterTest();

        $this->assertSame(20, level_manager::max_level());
    }

    public function test_save_ladder_renumbers_and_forces_a_zero_floor(): void {
        global $DB;
        $this->resetAfterTest();

        level_manager::save_ladder([
            ['minxp' => 500, 'title' => 'Mid'],
            ['minxp' => 50, 'title' => 'Low'],
            ['minxp' => 5000, 'title' => 'High'],
        ]);

        $levels = level_manager::get_levels();
        $this->assertCount(3, $levels);
        // Sorted by XP and renumbered, with the lowest forced to 0.
        $this->assertSame([1, 2, 3], array_map(fn($l): int => (int) $l->level, $levels));
        $this->assertSame(0, (int) $levels[0]->minxp);
        $this->assertSame('Low', $levels[0]->title);
        $this->assertSame(5000, (int) $levels[2]->minxp);
        $this->assertSame('High', $levels[2]->title);
    }

    public function test_title_and_xp_lookups_follow_a_custom_ladder(): void {
        $this->resetAfterTest();
        level_manager::save_ladder([
            ['minxp' => 0, 'title' => 'Rookie'],
            ['minxp' => 200, 'title' => 'Pro'],
        ]);

        $this->assertSame('Rookie', level_manager::title_for_level(1));
        $this->assertSame('Pro', level_manager::title_for_level(2));
        $this->assertSame(200, level_manager::minxp_for_level(2));
        $this->assertSame(2, level_manager::level_for_xp(200));
        // Out-of-range level clamps to the top of the custom ladder.
        $this->assertSame('Pro', level_manager::title_for_level(9));
    }

    public function test_restore_defaults_rebuilds_the_standard_ladder(): void {
        $this->resetAfterTest();
        level_manager::save_ladder([['minxp' => 0, 'title' => 'Only']]);

        level_manager::restore_defaults();

        $this->assertSame(20, level_manager::max_level());
    }
}
