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
 * Tests for the local_playergames upgrade steps.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames;

use advanced_testcase;
use xmldb_index;
use xmldb_table;

/**
 * Unit tests for the local_playergames upgrade steps.
 *
 * Nothing else runs upgrade.php: the test database and moodle-plugin-ci both build the schema
 * from install.xml, so a step that goes wrong stays green everywhere and only shows on a site
 * that already has data. These tests rebuild the state each step expects and run the real
 * function against it, on every database the CI matrix covers.
 *
 * @package    local_playergames
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers ::xmldb_local_playergames_upgrade
 */
final class db_upgrade_test extends advanced_testcase {
    /** @var int Version recorded before the step that removes the duplicated indexes. */
    private const PREVIOUS_VERSION = 2026101500;

    /** @var int Version the step saves when it finishes. */
    private const STEP_VERSION = 2026101501;

    /** @var array Columns the step covers: table name => field that carries a foreign key. */
    private const COLUMNS = [
        'local_playergames_concepts' => 'cartridgeid',
        'local_playergames_user_achievements' => 'userid',
    ];

    /**
     * Pulls in the upgrade function and the savepoint helper it calls.
     *
     * @return void
     */
    protected function setUp(): void {
        global $CFG;

        parent::setUp();
        $this->resetAfterTest();

        // The savepoint helper lives in upgradelib.php, which only the real upgrade runner loads.
        require_once($CFG->libdir . '/upgradelib.php');
        require_once($CFG->dirroot . '/local/playergames/db/upgrade.php');
    }

    /**
     * Names of the non-unique indexes whose only field is the given one.
     *
     * @param string $table Table name without prefix.
     * @param string $field Field name.
     * @return string[]
     */
    private function indexes_on(string $table, string $field): array {
        global $DB;

        $indexes = array_filter(
            $DB->get_indexes($table),
            fn(array $index): bool => !$index['unique'] && array_values($index['columns']) === [$field]
        );
        return array_keys($indexes);
    }

    /**
     * Adds a second index identical to the key's own, as install.xml did before the fix.
     *
     * add_index() refuses an index whose fields another index already covers, so the statement
     * comes straight from the DDL generator, which builds it the way installing the old
     * install.xml did.
     *
     * @param string $table Table name without prefix.
     * @param string $field Field name.
     * @return void
     */
    private function add_duplicate_index(string $table, string $field): void {
        global $DB;

        $sql = $DB->get_manager()->generator->getCreateIndexSQL(
            new xmldb_table($table),
            new xmldb_index('duplicate', XMLDB_INDEX_NOTUNIQUE, [$field])
        );
        $DB->change_database_structure($sql, [$table]);
    }

    /**
     * Runs the upgrade from the version that precedes the step.
     *
     * The savepoint refuses anything that is not a step forward, so the older version is
     * recorded first.
     *
     * @return void
     */
    private function run_step(): void {
        set_config('version', self::PREVIOUS_VERSION, 'local_playergames');
        $this->assertTrue(xmldb_local_playergames_upgrade(self::PREVIOUS_VERSION));
        // Later steps run after this one, so the version ends at or past it.
        $this->assertGreaterThanOrEqual(self::STEP_VERSION, (int) get_config('local_playergames', 'version'));
    }

    /**
     * A column holding two identical indexes ends with exactly one.
     *
     * @return void
     */
    public function test_two_identical_indexes_become_one(): void {
        foreach (self::COLUMNS as $table => $field) {
            $this->add_duplicate_index($table, $field);
            $this->assertCount(2, $this->indexes_on($table, $field), "{$table}.{$field} before the step");
        }

        $this->run_step();

        foreach (self::COLUMNS as $table => $field) {
            $this->assertCount(1, $this->indexes_on($table, $field), "{$table}.{$field} after the step");
        }
    }

    /**
     * A column left with no index gets the one its foreign key needs.
     *
     * @return void
     */
    public function test_column_without_index_gets_the_key_index(): void {
        global $DB;

        $dbman = $DB->get_manager();
        foreach (self::COLUMNS as $table => $field) {
            $dbman->drop_index(new xmldb_table($table), new xmldb_index('any', XMLDB_INDEX_NOTUNIQUE, [$field]));
            $this->assertCount(0, $this->indexes_on($table, $field), "{$table}.{$field} before the step");
        }

        $this->run_step();

        foreach (self::COLUMNS as $table => $field) {
            $this->assertCount(1, $this->indexes_on($table, $field), "{$table}.{$field} after the step");
        }
    }

    /**
     * A column that already has a single index keeps that same index.
     *
     * Comparing the names shows the step neither dropped nor rebuilt it.
     *
     * @return void
     */
    public function test_single_index_is_left_untouched(): void {
        $before = [];
        foreach (self::COLUMNS as $table => $field) {
            $before[$table] = $this->indexes_on($table, $field);
            $this->assertCount(1, $before[$table], "{$table}.{$field} before the step");
        }

        $this->run_step();

        foreach (self::COLUMNS as $table => $field) {
            $this->assertSame($before[$table], $this->indexes_on($table, $field), "{$table}.{$field} after the step");
        }
    }

    /**
     * What was saved for the concept games is put as they behave, and a quiz game keeps its choice.
     *
     * @return void
     */
    public function test_concept_games_lose_the_glossary_source_and_the_category(): void {
        global $DB;

        $rows = [
            ['guess', 'glossary', 5],
            ['fill', 'both', 3],
            ['quiz', 'questionbank', 7],
            ['battle', 'glossary', 0],
        ];
        foreach ($rows as [$game, $source, $auxid]) {
            $DB->insert_record('local_playergames_season_games', (object) [
                'seasonid' => 1, 'gametype' => $game, 'enabled' => 1, 'source' => $source, 'auxid' => $auxid,
            ]);
        }

        set_config('version', 2026110903, 'local_playergames');
        $this->assertTrue(xmldb_local_playergames_upgrade(2026110903));
        $this->assertGreaterThanOrEqual(2026111201, get_config('local_playergames', 'version'));

        $saved = $DB->get_records('local_playergames_season_games', ['seasonid' => 1], '', 'gametype, source, auxid');
        $this->assertSame(['cartridge', 0], [$saved['guess']->source, (int) $saved['guess']->auxid]);
        $this->assertSame(['cartridge', 0], [$saved['fill']->source, (int) $saved['fill']->auxid]);
        $this->assertSame(['questionbank', 7], [$saved['quiz']->source, (int) $saved['quiz']->auxid]);
        $this->assertSame('cartridge', $saved['battle']->source);
    }

    /**
     * A season still open that ended at midnight now ends at the last second of that day.
     *
     * Regression test: the admin form stored the end date as 00:00 of the last day, and the task that
     * closes expired seasons shut the season that night, so the announced last day could not be played.
     *
     * @return void
     */
    public function test_open_seasons_end_on_the_last_second_of_their_last_day(): void {
        global $DB;

        $midnight = mktime(0, 0, 0, 12, 31, 2026);
        $noon     = mktime(12, 0, 0, 12, 31, 2026);
        $ids      = [];
        foreach (['active' => $midnight, 'upcoming' => $midnight, 'closed' => $midnight, 'manual' => $noon] as $name => $end) {
            $ids[$name] = $DB->insert_record('local_playergames_seasons', (object) [
                'name' => $name,
                'startdate' => mktime(0, 0, 0, 7, 1, 2026),
                'enddate' => $end,
                'status' => $name === 'manual' ? 'active' : $name,
                'timecreated' => time(),
                'timemodified' => time(),
            ]);
        }

        set_config('version', 2026110904, 'local_playergames');
        $this->assertTrue(xmldb_local_playergames_upgrade(2026110904));

        $lastsecond = mktime(23, 59, 59, 12, 31, 2026);
        $this->assertEquals($lastsecond, $DB->get_field('local_playergames_seasons', 'enddate', ['id' => $ids['active']]));
        $this->assertEquals($lastsecond, $DB->get_field('local_playergames_seasons', 'enddate', ['id' => $ids['upcoming']]));
        // A closed season is history, and an end that is not midnight was not typed by the old form.
        $this->assertEquals($midnight, $DB->get_field('local_playergames_seasons', 'enddate', ['id' => $ids['closed']]));
        $this->assertEquals($noon, $DB->get_field('local_playergames_seasons', 'enddate', ['id' => $ids['manual']]));
    }

    /**
     * The "games per day" settings of PlayerGuess and PlayerFill are dropped; the other games keep theirs.
     *
     * @return void
     */
    public function test_games_per_day_settings_of_the_single_round_games_are_removed(): void {
        global $DB;

        set_config('xp_games_guess', 3, 'local_playergames');
        set_config('xp_games_fill', 2, 'local_playergames');
        set_config('xp_games_quiz', 4, 'local_playergames');

        set_config('version', 2026111201, 'local_playergames');
        $this->assertTrue(xmldb_local_playergames_upgrade(2026111201));

        $this->assertFalse(get_config('local_playergames', 'xp_games_guess'));
        $this->assertFalse(get_config('local_playergames', 'xp_games_fill'));
        $this->assertEquals(4, get_config('local_playergames', 'xp_games_quiz'));
        $this->assertTrue($DB->get_manager()->table_exists('local_playergames_rounds'));
    }

    /**
     * Avatar tier levels still on the old defaults become automatic; a customised set is kept.
     *
     * @return void
     */
    public function test_avatar_tiers_on_the_old_defaults_become_automatic(): void {
        foreach ([1 => 1, 2 => 5, 3 => 10, 4 => 20] as $tier => $level) {
            set_config('avatar_tier' . $tier . '_level', $level, 'local_playergames');
        }

        set_config('version', 2026111301, 'local_playergames');
        $this->assertTrue(xmldb_local_playergames_upgrade(2026111301));

        foreach ([1, 2, 3, 4] as $tier) {
            $this->assertEquals(0, get_config('local_playergames', 'avatar_tier' . $tier . '_level'));
        }
        $this->assertSame(1, \local_playergames\hub\avatar_manager::tier_threshold(1));
        $this->assertSame(4, \local_playergames\hub\avatar_manager::tier_threshold(4));
    }

    /**
     * An admin who picked any tier level keeps all four exactly as saved.
     *
     * @return void
     */
    public function test_customised_avatar_tiers_are_kept(): void {
        foreach ([1 => 1, 2 => 3, 3 => 10, 4 => 20] as $tier => $level) {
            set_config('avatar_tier' . $tier . '_level', $level, 'local_playergames');
        }

        set_config('version', 2026111301, 'local_playergames');
        $this->assertTrue(xmldb_local_playergames_upgrade(2026111301));

        $this->assertEquals(3, get_config('local_playergames', 'avatar_tier2_level'));
        $this->assertEquals(10, get_config('local_playergames', 'avatar_tier3_level'));
        $this->assertEquals(20, get_config('local_playergames', 'avatar_tier4_level'));
    }

    /**
     * A site that already has the other achievements gets the one for 50 games.
     *
     * @return void
     */
    public function test_the_fifty_games_achievement_is_added_to_a_site_that_has_the_others(): void {
        global $DB;
        \local_playergames\hub\achievement_manager::sync();
        $DB->delete_records('local_playergames_achievements', ['namestring' => 'achievement_50_games_name']);

        set_config('version', 2026111401, 'local_playergames');
        $this->assertTrue(xmldb_local_playergames_upgrade(2026111401));

        $this->assertTrue($DB->record_exists('local_playergames_achievements', ['namestring' => 'achievement_50_games_name']));
        $this->assertSame(7, $DB->count_records('local_playergames_achievements'));
    }
}
