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
 * Upgrade steps for local_playergames.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Runs upgrade steps between plugin versions.
 *
 * @param int $oldversion Previous installed version.
 * @return bool
 */
function xmldb_local_playergames_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026042700) {
        // Create categories table.
        $table = new xmldb_table('local_playergames_categories');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('cartridgeid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('name', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL);
        $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key(
            'fk_cartridge',
            XMLDB_KEY_FOREIGN,
            ['cartridgeid'],
            'local_playergames_cartridges',
            ['id']
        );
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Add categoryid field to concepts (after the existing category text field).
        $conceptstable = new xmldb_table('local_playergames_concepts');
        $categoryidfield = new xmldb_field(
            'categoryid',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            null,
            null,
            null,
            'difficulty'
        );
        if (!$dbman->field_exists($conceptstable, $categoryidfield)) {
            $dbman->add_field($conceptstable, $categoryidfield);
        }

        // Add index on categoryid.
        $categoryidindex = new xmldb_index('idx_categoryid', XMLDB_INDEX_NOTUNIQUE, ['categoryid']);
        if (!$dbman->index_exists($conceptstable, $categoryidindex)) {
            $dbman->add_index($conceptstable, $categoryidindex);
        }

        // Migrate existing text categories to category records.
        $rows = $DB->get_records_sql(
            "SELECT MIN(id) AS id, cartridgeid, category
               FROM {local_playergames_concepts}
              WHERE category IS NOT NULL AND category <> ''
           GROUP BY cartridgeid, category"
        );
        $catmap = [];
        $sortorder = 0;
        foreach ($rows as $row) {
            $cid = (int) $row->cartridgeid;
            $name = trim($row->category);
            if ($name === '') {
                continue;
            }
            $catrecord = new stdClass();
            $catrecord->cartridgeid = $cid;
            $catrecord->name = $name;
            $catrecord->sortorder = $sortorder++;
            $catrecord->timecreated = time();
            $catid = $DB->insert_record('local_playergames_categories', $catrecord);
            $catmap[$cid][$name] = (int) $catid;
        }

        // Update concepts with resolved categoryid.
        foreach ($catmap as $cid => $namemap) {
            foreach ($namemap as $name => $catid) {
                $DB->set_field_select(
                    'local_playergames_concepts',
                    'categoryid',
                    $catid,
                    'cartridgeid = ? AND category = ?',
                    [$cid, $name]
                );
            }
        }

        // Drop old category text column.
        $categoryfield = new xmldb_field('category');
        if ($dbman->field_exists($conceptstable, $categoryfield)) {
            $dbman->drop_field($conceptstable, $categoryfield);
        }

        upgrade_plugin_savepoint(true, 2026042700, 'local', 'playergames');
    }

    if ($oldversion < 2026042800) {
        $table = new xmldb_table('local_playergames_cartridges');

        // Rename timeuploaded to timecreated.
        $field = new xmldb_field('timeuploaded', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        if ($dbman->field_exists($table, $field)) {
            $dbman->rename_field($table, $field, 'timecreated');
        }

        // Add timemodified field.
        $timemodifiedfield = new xmldb_field(
            'timemodified',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'timecreated'
        );
        if (!$dbman->field_exists($table, $timemodifiedfield)) {
            $dbman->add_field($table, $timemodifiedfield);
        }

        // Add author field (nullable free-text).
        $authorfield = new xmldb_field(
            'author',
            XMLDB_TYPE_CHAR,
            '255',
            null,
            null,
            null,
            null,
            'uploadedby'
        );
        if (!$dbman->field_exists($table, $authorfield)) {
            $dbman->add_field($table, $authorfield);
        }

        // Populate timemodified with timecreated for existing rows.
        $DB->execute(
            'UPDATE {local_playergames_cartridges} SET timemodified = timecreated WHERE timemodified = 0'
        );

        upgrade_plugin_savepoint(true, 2026042800, 'local', 'playergames');
    }

    if ($oldversion < 2026042801) {
        $table = new xmldb_table('local_playergames_ai_log');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('provider', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL);
        $table->add_field('model', XMLDB_TYPE_CHAR, '100', null, null);
        $table->add_field('topic', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL);
        $table->add_field('conceptcount', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key(
            'fk_user',
            XMLDB_KEY_FOREIGN,
            ['userid'],
            'user',
            ['id']
        );
        $table->add_index(
            'idx_userid_timecreated',
            XMLDB_INDEX_NOTUNIQUE,
            ['userid', 'timecreated']
        );
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026042801, 'local', 'playergames');
    }

    if ($oldversion < 2026052201) {
        $oldtable = new xmldb_table('local_playergames_bounce_scores');
        if ($dbman->table_exists($oldtable)) {
            $dbman->rename_table($oldtable, 'local_playergames_battle_scores');
        }

        $table = new xmldb_table('local_playergames_battle_scores');

        $scorefield = new xmldb_field('score');
        if ($dbman->field_exists($table, $scorefield)) {
            $dbman->drop_field($table, $scorefield);
        }

        $difficultyfield = new xmldb_field('difficultylevel');
        if ($dbman->field_exists($table, $difficultyfield)) {
            $dbman->drop_field($table, $difficultyfield);
        }

        $bosshpfield = new xmldb_field(
            'bosshpdealt',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'gamedate'
        );
        if (!$dbman->field_exists($table, $bosshpfield)) {
            $dbman->add_field($table, $bosshpfield);
        }

        $correctfield = new xmldb_field(
            'questionscorrect',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'bosshpdealt'
        );
        if (!$dbman->field_exists($table, $correctfield)) {
            $dbman->add_field($table, $correctfield);
        }

        $totalfield = new xmldb_field(
            'questionstotal',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'questionscorrect'
        );
        if (!$dbman->field_exists($table, $totalfield)) {
            $dbman->add_field($table, $totalfield);
        }

        $victoryfield = new xmldb_field(
            'victory',
            XMLDB_TYPE_INTEGER,
            '1',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'questionstotal'
        );
        if (!$dbman->field_exists($table, $victoryfield)) {
            $dbman->add_field($table, $victoryfield);
        }

        upgrade_plugin_savepoint(true, 2026052201, 'local', 'playergames');
    }

    return true;
}
