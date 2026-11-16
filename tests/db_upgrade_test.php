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

    /**
     * A result saved before the staff column existed is marked as staff when its user is staff today.
     *
     * @return void
     */
    public function test_upgrade_adds_the_staff_column_and_marks_the_staff_results(): void {
        global $CFG, $DB;

        require_once($CFG->libdir . '/upgradelib.php');
        require_once($CFG->dirroot . '/local/playergames/db/upgrade.php');
        $this->resetAfterTest();
        $this->preventResetByRollback();

        $teacher = $this->getDataGenerator()->create_user();
        $student = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        \local_playergames\local\access::refresh_staff_ids();
        $seasonid = (int) $DB->insert_record('local_playergames_seasons', (object) [
            'name' => 'S', 'startdate' => 1, 'enddate' => 2, 'status' => 'closed',
            'config_snapshot' => json_encode([]), 'timecreated' => 1, 'timemodified' => 1,
        ]);
        foreach ([$teacher, $student] as $user) {
            $DB->insert_record('local_playergames_season_summary', (object) [
                'userid' => $user->id, 'seasonid' => $seasonid, 'xp' => 5, 'level' => 1, 'missionsdone' => 0,
                'showinhall' => 0, 'timecreated' => 1,
            ]);
        }
        $dbman = $DB->get_manager();
        $table = new \xmldb_table('local_playergames_season_summary');
        $field = new \xmldb_field('isstaff', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'rankedof');
        $dbman->drop_field($table, $field);
        $this->assertFalse($dbman->field_exists($table, $field));

        set_config('version', 2026111601, 'local_playergames');
        $this->assertTrue(xmldb_local_playergames_upgrade(2026111601));

        $this->assertTrue($dbman->field_exists($table, $field));
        $this->assertSame(1, (int) $DB->get_field('local_playergames_season_summary', 'isstaff', ['userid' => $teacher->id]));
        $this->assertSame(0, (int) $DB->get_field('local_playergames_season_summary', 'isstaff', ['userid' => $student->id]));
    }
}
