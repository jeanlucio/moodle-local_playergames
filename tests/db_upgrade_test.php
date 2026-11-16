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
 * Tests for the local_playergames upgrade file.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames;

use advanced_testcase;

/**
 * Unit tests for the local_playergames upgrade file.
 *
 * The plugin is unreleased, so the file holds only the steps the sites that already run it need; each step
 * has a test that rebuilds the state it expects and runs the real function against it.
 *
 * @package    local_playergames
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers ::xmldb_local_playergames_upgrade
 */
final class db_upgrade_test extends advanced_testcase {
    /**
     * The upgrade function loads and reports success for any previous version.
     *
     * @return void
     */
    public function test_upgrade_function_succeeds(): void {
        global $CFG;

        require_once($CFG->libdir . '/upgradelib.php');
        require_once($CFG->dirroot . '/local/playergames/db/upgrade.php');

        // The savepoint refuses to record a version the plugin already has.
        $this->resetAfterTest();
        set_config('version', 2026042700, 'local_playergames');

        $this->assertTrue(xmldb_local_playergames_upgrade(2026042700));
    }

    /**
     * A site without the season summary table and the history index gets both.
     *
     * @return void
     */
    public function test_upgrade_creates_the_season_summary_table_and_the_history_index(): void {
        global $CFG, $DB;

        require_once($CFG->libdir . '/upgradelib.php');
        require_once($CFG->dirroot . '/local/playergames/db/upgrade.php');
        $this->resetAfterTest();
        $this->preventResetByRollback();

        $dbman   = $DB->get_manager();
        $summary = new \xmldb_table('local_playergames_season_summary');
        $log     = new \xmldb_table('local_playergames_activity_log');
        $index   = new \xmldb_index('idx_timecreated', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);
        $dbman->drop_table($summary);
        $dbman->drop_index($log, $index);
        $this->assertFalse($dbman->table_exists($summary));
        $this->assertFalse($dbman->index_exists($log, $index));

        // The savepoint refuses to record a version the plugin already has.
        set_config('version', 2026111501, 'local_playergames');
        $this->assertTrue(xmldb_local_playergames_upgrade(2026111501));

        $this->assertTrue($dbman->table_exists($summary));
        $this->assertTrue($dbman->index_exists($log, $index));
        set_config('version', 2026111501, 'local_playergames');
        $this->assertTrue(xmldb_local_playergames_upgrade(2026111501));
    }
}
