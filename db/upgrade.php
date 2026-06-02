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

    if ($oldversion < 2026060100) {
        // Create seasons table first (referenced by FK in player_profile and mission_progress).
        $table = new xmldb_table('local_playergames_seasons');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL);
            $table->add_field('startdate', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('enddate', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'upcoming');
            $table->add_field('config_snapshot', XMLDB_TYPE_TEXT, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $dbman->create_table($table);
        }

        // Rename staff_profile to player_profile (audience expanded to students).
        $oldtable = new xmldb_table('local_playergames_staff_profile');
        if ($dbman->table_exists($oldtable)) {
            $dbman->rename_table($oldtable, 'local_playergames_player_profile');
        }

        // Create player_profile if it doesn't exist yet.
        $table = new xmldb_table('local_playergames_player_profile');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('seasonid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('xp', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('level', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1');
            $table->add_field('showinranking', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('fk_user', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
            $table->add_key(
                'fk_season',
                XMLDB_KEY_FOREIGN,
                ['seasonid'],
                'local_playergames_seasons',
                ['id']
            );
            $table->add_key('uq_userid_seasonid', XMLDB_KEY_UNIQUE, ['userid', 'seasonid']);
            $dbman->create_table($table);
        }

        // Create streaks table.
        $table = new xmldb_table('local_playergames_streaks');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('currentstreak', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('longeststreak', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('freezesavailable', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('lastactivedate', XMLDB_TYPE_INTEGER, '10', null, null);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('fk_user', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
            $table->add_key('uq_userid', XMLDB_KEY_UNIQUE, ['userid']);
            $dbman->create_table($table);
        }

        // Create daily_assignments table.
        $table = new xmldb_table('local_playergames_daily_assignments');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('gamedate', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('gametype', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL);
            $table->add_field('conceptid', XMLDB_TYPE_INTEGER, '10', null, null);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('idx_gamedate_gametype', XMLDB_INDEX_NOTUNIQUE, ['gamedate', 'gametype']);
            $dbman->create_table($table);
        }

        // Create daily_scores table.
        $table = new xmldb_table('local_playergames_daily_scores');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('gamedate', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('gametype', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL);
            $table->add_field('completed', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('xpawarded', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('attempts', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timeplayed', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('fk_user', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
            $table->add_key(
                'uq_userid_gamedate_gametype',
                XMLDB_KEY_UNIQUE,
                ['userid', 'gamedate', 'gametype']
            );
            $table->add_index('idx_gamedate_gametype', XMLDB_INDEX_NOTUNIQUE, ['gamedate', 'gametype']);
            $dbman->create_table($table);
        }

        // Create missions table (before mission_progress which has FK to it).
        $table = new xmldb_table('local_playergames_missions');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('type', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL);
            $table->add_field('targetvalue', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('xpreward', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('namestring', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL);
            $table->add_field('descstring', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL);
            $table->add_field('icon', XMLDB_TYPE_CHAR, '100', null, null);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $dbman->create_table($table);
        }

        // Create mission_progress table.
        $table = new xmldb_table('local_playergames_mission_progress');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('missionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('seasonid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('currentvalue', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('completed', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timecompleted', XMLDB_TYPE_INTEGER, '10', null, null);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('fk_user', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
            $table->add_key(
                'fk_mission',
                XMLDB_KEY_FOREIGN,
                ['missionid'],
                'local_playergames_missions',
                ['id']
            );
            $table->add_key(
                'fk_season',
                XMLDB_KEY_FOREIGN,
                ['seasonid'],
                'local_playergames_seasons',
                ['id']
            );
            $table->add_key(
                'uq_userid_missionid_seasonid',
                XMLDB_KEY_UNIQUE,
                ['userid', 'missionid', 'seasonid']
            );
            $dbman->create_table($table);
        }

        // Create achievements table (before user_achievements which has FK to it).
        $table = new xmldb_table('local_playergames_achievements');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('namestring', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL);
            $table->add_field('descstring', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL);
            $table->add_field('icon', XMLDB_TYPE_CHAR, '100', null, null);
            $table->add_field('conditiontype', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL);
            $table->add_field('conditionvalue', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $dbman->create_table($table);
        }

        // Create user_achievements table.
        $table = new xmldb_table('local_playergames_user_achievements');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('achievementid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('fk_user', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
            $table->add_key(
                'fk_achievement',
                XMLDB_KEY_FOREIGN,
                ['achievementid'],
                'local_playergames_achievements',
                ['id']
            );
            $table->add_key('uq_userid_achievementid', XMLDB_KEY_UNIQUE, ['userid', 'achievementid']);
            $table->add_index('idx_userid', XMLDB_INDEX_NOTUNIQUE, ['userid']);
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026060100, 'local', 'playergames');
    }

    if ($oldversion < 2026060101) {
        // Capability resync: viewdashboard extended to teacher and editingteacher.
        upgrade_plugin_savepoint(true, 2026060101, 'local', 'playergames');
    }

    if ($oldversion < 2026060102) {
        // Create concept_questions table.
        $table = new xmldb_table('local_playergames_concept_questions');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('conceptid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('cartridgeid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('questiontext', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);
            $table->add_field('source', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'ai');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key(
                'fk_concept',
                XMLDB_KEY_FOREIGN,
                ['conceptid'],
                'local_playergames_concepts',
                ['id']
            );
            $table->add_key(
                'fk_cartridge',
                XMLDB_KEY_FOREIGN,
                ['cartridgeid'],
                'local_playergames_cartridges',
                ['id']
            );
            $dbman->create_table($table);
        }

        // Create concept_answers table.
        $table = new xmldb_table('local_playergames_concept_answers');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('questionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('answertext', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);
            $table->add_field('iscorrect', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key(
                'fk_question',
                XMLDB_KEY_FOREIGN,
                ['questionid'],
                'local_playergames_concept_questions',
                ['id']
            );
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026060102, 'local', 'playergames');
    }

    if ($oldversion < 2026060103) {
        upgrade_plugin_savepoint(true, 2026060103, 'local', 'playergames');
    }

    if ($oldversion < 2026060105) {
        // Add type field to cartridges (concept | quiz) if not already present.
        $table = new xmldb_table('local_playergames_cartridges');
        $typefield = new xmldb_field('type', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'concept');
        if (!$dbman->field_exists($table, $typefield)) {
            $dbman->add_field($table, $typefield);
            $DB->execute("UPDATE {local_playergames_cartridges} SET type = 'concept'");
        }

        // Make conceptid nullable so quiz-type cartridges can store standalone questions.
        $qtable = new xmldb_table('local_playergames_concept_questions');
        if ($dbman->table_exists($qtable)) {
            // Drop index on conceptid — change_field_notnull requires no dependent indexes.
            $conindex = new xmldb_index('con', XMLDB_INDEX_NOTUNIQUE, ['conceptid']);
            if ($dbman->index_exists($qtable, $conindex)) {
                $dbman->drop_index($qtable, $conindex);
            }

            $conceptidfield = new xmldb_field(
                'conceptid',
                XMLDB_TYPE_INTEGER,
                '10',
                null,
                null,
                null,
                null
            );
            $dbman->change_field_notnull($qtable, $conceptidfield);

            // Re-add index for queries that filter concept-based questions by conceptid.
            if (!$dbman->index_exists($qtable, $conindex)) {
                $dbman->add_index($qtable, $conindex);
            }
        }

        upgrade_plugin_savepoint(true, 2026060105, 'local', 'playergames');
    }

    if ($oldversion < 2026060106) {
        $table = new xmldb_table('local_playergames_season_games');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('seasonid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('gametype', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL);
            $table->add_field('enabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
            $table->add_field('source', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'cartridge');
            $table->add_field('cartridgeids', XMLDB_TYPE_TEXT, null, null, null);
            $table->add_field('auxid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key(
                'fk_season',
                XMLDB_KEY_FOREIGN,
                ['seasonid'],
                'local_playergames_seasons',
                ['id']
            );
            $table->add_key('uq_season_gametype', XMLDB_KEY_UNIQUE, ['seasonid', 'gametype']);
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026060106, 'local', 'playergames');
    }

    return true;
}
