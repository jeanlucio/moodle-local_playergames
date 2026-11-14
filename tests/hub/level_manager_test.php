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

/**
 * Integration tests for {@see level_manager}.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_playergames\hub\level_manager
 */
final class level_manager_test extends \advanced_testcase {
    public function test_get_levels_seeds_the_default_ladder(): void {
        global $DB;
        $this->resetAfterTest();

        $levels = level_manager::get_levels();

        $this->assertCount(5, $levels);
        $this->assertSame(5, $DB->count_records('local_playergames_levels'));
        $this->assertSame(0, (int) $levels[0]->minxp);
        $this->assertSame(1, (int) $levels[0]->level);
    }

    public function test_level_for_xp_uses_the_ladder(): void {
        $this->resetAfterTest();

        $this->assertSame(1, level_manager::level_for_xp(0));
        $this->assertSame(1, level_manager::level_for_xp(1999));
        $this->assertSame(2, level_manager::level_for_xp(2000));
        $this->assertSame(3, level_manager::level_for_xp(6000));
        $this->assertSame(5, level_manager::level_for_xp(999999));
    }

    public function test_max_level_reflects_the_ladder(): void {
        $this->resetAfterTest();

        $this->assertSame(5, level_manager::max_level());
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

        $this->assertSame(5, level_manager::max_level());
    }

    public function test_generate_linear_builds_a_fixed_step_ladder(): void {
        $this->resetAfterTest();

        level_manager::generate_linear(100, 10);

        $levels = level_manager::get_levels();
        $this->assertCount(10, $levels);
        // Level 1 is always 0; each step adds the fixed XP per level.
        $this->assertSame(0, (int) $levels[0]->minxp);
        $this->assertSame(100, (int) $levels[1]->minxp);
        $this->assertSame(900, (int) $levels[9]->minxp);
        // Titles are spread across the five tiers: 10 levels => 2 levels per tier.
        $this->assertSame('Novice', $levels[0]->title);
        $this->assertSame('Master', $levels[9]->title);
    }

    public function test_generate_linear_clamps_bounds(): void {
        $this->resetAfterTest();

        // A maximum above the cap is clamped; XP per level below 1 is forced up.
        level_manager::generate_linear(0, 999);

        $levels = level_manager::get_levels();
        $this->assertCount(level_manager::MAX_GENERATED_LEVELS, $levels);
        $this->assertSame(1, (int) $levels[1]->minxp);
    }

    /**
     * Regression test: every lookup read the ladder again, about six times to build the hub and more on
     * every XP award. It is read once per request.
     */
    public function test_the_ladder_is_read_once_per_request(): void {
        global $DB;
        $this->resetAfterTest();
        level_manager::get_levels();

        $before = $DB->perf_get_reads();
        level_manager::level_for_xp(5000);
        level_manager::max_level();
        level_manager::minxp_for_level(3);
        level_manager::title_for_level(2);
        level_manager::get_levels();

        $this->assertSame(0, $DB->perf_get_reads() - $before);
    }

    public function test_editing_the_ladder_forgets_the_copy(): void {
        $this->resetAfterTest();
        $this->assertSame(5, level_manager::max_level());

        level_manager::save_ladder([
            ['minxp' => 0, 'title' => 'A'],
            ['minxp' => 100, 'title' => 'B'],
        ]);
        $this->assertSame(2, level_manager::max_level());

        level_manager::generate_linear(50, 7);
        $this->assertSame(7, level_manager::max_level());

        level_manager::restore_defaults();
        $this->assertSame(5, level_manager::max_level());
    }

    /**
     * Installs a language pack that translates the level titles, and makes it the current language.
     *
     * @return void
     */
    private function use_translated_titles(): void {
        global $CFG;

        $dir = $CFG->dataroot . '/lang/qq';
        check_dir_exists($dir);
        file_put_contents($dir . '/langconfig.php', "<?php\n\$string['thislanguage'] = 'Test';\n"
            . "\$string['parentlanguage'] = '';\n\$string['decsep'] = '.';\n");
        file_put_contents($dir . '/local_playergames.php', "<?php\n\$string['level_title_1'] = 'Titulo um';\n"
            . "\$string['level_title_5'] = 'Titulo cinco';\n");
        get_string_manager(true);
        force_current_language('qq');
    }

    /**
     * The ladder is seeded in English whatever language the first request reads, and each viewer sees the
     * default titles in their own.
     */
    public function test_default_titles_are_stored_in_english_and_shown_in_the_language_of_the_viewer(): void {
        global $DB;
        $this->resetAfterTest();
        $this->use_translated_titles();

        level_manager::get_levels();

        $this->assertSame('Novice', $DB->get_field('local_playergames_levels', 'title', ['level' => 1]));
        $this->assertSame('Titulo um', level_manager::title_for_level(1));
        $this->assertSame('Titulo cinco', level_manager::title_for_level(5));
        force_current_language('en');
        $this->assertSame('Novice', level_manager::title_for_level(1));
    }

    /**
     * A title an admin wrote is shown as written, in any language.
     */
    public function test_a_title_written_by_an_admin_is_not_translated(): void {
        $this->resetAfterTest();
        $this->use_translated_titles();
        level_manager::save_ladder([
            ['minxp' => 0, 'title' => 'Rookie'],
            ['minxp' => 100, 'title' => 'Pro'],
        ]);

        $this->assertSame('Rookie', level_manager::title_for_level(1));
        $this->assertSame('Pro', level_manager::title_for_level(2));
    }

    /**
     * The editor shows the defaults in the admin's language, and saving them untouched keeps them default
     * instead of freezing that language into the rows.
     */
    public function test_saving_the_titles_the_editor_showed_keeps_the_defaults_translatable(): void {
        global $DB;
        $this->resetAfterTest();
        $this->use_translated_titles();
        level_manager::get_levels();

        $shown = level_manager::get_levels_for_display();
        $this->assertSame('Titulo um', $shown[0]->title);
        level_manager::save_ladder(array_map(
            fn($row): array => ['minxp' => (int) $row->minxp, 'title' => $row->title],
            $shown
        ));

        $this->assertSame('Novice', $DB->get_field('local_playergames_levels', 'title', ['level' => 1]));
    }
}
