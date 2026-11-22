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
 * Tests for deleting all the PlayerGames data.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\local;

use local_playergames\hub\activity_log;
use local_playergames\hub\streak_manager;
use local_playergames\hub\xp_manager;

/**
 * Unit tests for {@see data_reset}.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_playergames\local\data_reset
 */
final class data_reset_test extends \advanced_testcase {
    /**
     * The tables the plugin installs, read from its install.xml.
     *
     * @return string[]
     */
    private function installed_tables(): array {
        global $CFG;
        $xml = file_get_contents($CFG->dirroot . '/local/playergames/db/install.xml');
        preg_match_all('/<TABLE NAME="([a-z_]+)"/', $xml, $matches);
        return $matches[1];
    }

    /**
     * Every table of the plugin is either deleted or kept, once: a table added later fails here until it is placed,
     * so it never escapes the reset by being forgotten.
     */
    public function test_every_table_is_deleted_or_kept(): void {
        $classified = array_merge(array_merge(...array_values(data_reset::DELETED)), data_reset::KEPT);

        $this->assertSame(count($classified), count(array_unique($classified)), 'A table is listed twice.');
        $installed = $this->installed_tables();
        sort($installed);
        sort($classified);
        $this->assertSame($installed, $classified);
    }

    /**
     * Every table the privacy provider holds personal data in is deleted.
     */
    public function test_every_table_with_personal_data_is_deleted(): void {
        $usertables = (new \ReflectionClassConstant(\local_playergames\privacy\provider::class, 'USER_TABLES'))->getValue();
        $deleted = array_merge(...array_values(data_reset::DELETED));

        $this->assertSame([], array_values(array_diff($usertables, $deleted)));
    }

    /**
     * The seasons count the seasons alone, not the rows of their game settings.
     */
    public function test_the_seasons_count_the_seasons_alone(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator()->get_plugin_generator('local_playergames');
        $generator->create_season(['name' => 'One', 'active' => 0]);
        $generator->create_season(['name' => 'Two', 'active' => 0]);

        $this->assertSame(2, data_reset::counts()['seasons']);
    }

    /**
     * The reset deletes the seasons and what the players earned, keeps the content, the settings and each user's
     * choice to take part, and logs who did it.
     */
    public function test_run_deletes_the_play_and_keeps_the_content(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator()->get_plugin_generator('local_playergames');
        $season = $generator->create_season(['name' => 'Live']);
        $cartridge = $generator->create_cartridge(['name' => 'Kept']);
        $generator->create_concept(['cartridgeid' => $cartridge->id, 'term' => 'atom', 'definition' => 'Small']);
        $player = (int) $this->getDataGenerator()->create_user()->id;
        set_user_preference('local_playergames_gamification', 1, $player);
        xp_manager::award($player, 30, 'quiz', day::today(), (int) $season->id);
        streak_manager::record_activity($player);
        activity_log::record($player, activity_log::TYPE_FREEZE_EARNED, 0, 'mission');
        set_config('xp_per_game_quiz', 40, 'local_playergames');
        \cache::make('local_playergames', 'ranking')->set('season_' . $season->id . '_students', [['userid' => $player]]);
        $before = array_sum(data_reset::counts());
        $kept = [];
        foreach (data_reset::KEPT as $table) {
            $kept[$table] = $DB->count_records($table);
        }
        $sink = $this->redirectEvents();

        $deleted = data_reset::run();

        $this->assertGreaterThan(0, $deleted);
        $this->assertSame($before, $deleted);
        foreach (data_reset::DELETED as $tables) {
            foreach ($tables as $table) {
                $this->assertSame(0, $DB->count_records($table), $table);
            }
        }
        foreach ($kept as $table => $count) {
            $this->assertSame($count, $DB->count_records($table), $table);
        }
        $this->assertSame(1, (int) $DB->count_records('local_playergames_concepts', ['cartridgeid' => $cartridge->id]));
        $this->assertSame('40', get_config('local_playergames', 'xp_per_game_quiz'));
        $this->assertSame('1', get_user_preferences('local_playergames_gamification', null, $player));
        $this->assertFalse(\cache::make('local_playergames', 'ranking')->get('season_' . $season->id . '_students'));

        $events = array_values(array_filter(
            $sink->get_events(),
            static fn($event): bool => $event instanceof \local_playergames\event\data_reset
        ));
        $this->assertCount(1, $events);
        $this->assertSame($deleted, $events[0]->other['records']);
        $this->assertSame((int) get_admin()->id, (int) $events[0]->userid);
    }
}
