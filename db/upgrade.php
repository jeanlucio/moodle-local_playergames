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
 * The plugin has not been released yet, so install.xml is the single source of the schema and the steps
 * written while it was in development were folded into it. The first step added after a release goes here.
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

    if ($oldversion < 2026111601) {
        // The final result of each user in each closed season, kept after the season's own rows are purged.
        $table = new xmldb_table('local_playergames_season_summary');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('seasonid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('xp', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('level', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1');
            $table->add_field('missionsdone', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('finalrank', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('rankedof', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('showinhall', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('fk_user', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
            $table->add_key('fk_season', XMLDB_KEY_FOREIGN, ['seasonid'], 'local_playergames_seasons', ['id']);
            $table->add_key('uq_userid_seasonid', XMLDB_KEY_UNIQUE, ['userid', 'seasonid']);
            $table->add_index('idx_seasonid_finalrank', XMLDB_INDEX_NOTUNIQUE, ['seasonid', 'finalrank']);
            $dbman->create_table($table);
        }

        // The purge of old history deletes by age alone.
        $log   = new xmldb_table('local_playergames_activity_log');
        $index = new xmldb_index('idx_timecreated', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);
        if (!$dbman->index_exists($log, $index)) {
            $dbman->add_index($log, $index);
        }

        upgrade_plugin_savepoint(true, 2026111601, 'local', 'playergames');
    }

    if ($oldversion < 2026111602) {
        // The staff and the students are ranked apart, and a position alone does not say which one it is.
        $table = new xmldb_table('local_playergames_season_summary');
        $field = new xmldb_field('isstaff', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'rankedof');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
            // A result saved before the column existed was ranked with the staff when its user is staff today.
            foreach (array_chunk(\local_playergames\local\access::get_staff_ids(), 500) as $staffids) {
                [$insql, $params] = $DB->get_in_or_equal($staffids, SQL_PARAMS_NAMED);
                $DB->set_field_select('local_playergames_season_summary', 'isstaff', 1, "userid {$insql}", $params);
            }
        }

        upgrade_plugin_savepoint(true, 2026111602, 'local', 'playergames');
    }

    if ($oldversion < 2026111603) {
        // The question bank is no longer a source the games read: its questions are imported into a cartridge.
        $table = new xmldb_table('local_playergames_season_games');
        foreach (['source', 'auxid'] as $name) {
            $field = new xmldb_field($name);
            if ($dbman->field_exists($table, $field)) {
                $dbman->drop_field($table, $field);
            }
        }

        upgrade_plugin_savepoint(true, 2026111603, 'local', 'playergames');
    }

    if ($oldversion < 2026112200) {
        // The choice of the concept of the day never changed the draw, and the PlayerQuiz cooldown gave way to a
        // lost play closing the day: the settings and the cooldown kept for each user go with them.
        unset_config('concept_day_mode', 'local_playergames');
        unset_config('quiz_cooldown_minutes', 'local_playergames');
        $DB->delete_records('user_preferences', ['name' => 'local_playergames_quizcooldown']);

        upgrade_plugin_savepoint(true, 2026112200, 'local', 'playergames');
    }

    if ($oldversion < 2026112201) {
        // The unlock level of each avatar tier is no longer set by hand: the tiers follow the level ladder.
        foreach (range(1, 4) as $tier) {
            unset_config('avatar_tier' . $tier . '_level', 'local_playergames');
        }

        upgrade_plugin_savepoint(true, 2026112201, 'local', 'playergames');
    }

    if ($oldversion < 2026112300) {
        // The term lengths of PlayerGuess are named after the game.
        foreach (['minlen', 'maxlen'] as $end) {
            $value = get_config('local_playergames', 'wordle_' . $end);
            if ($value !== false) {
                set_config('guess_' . $end, $value, 'local_playergames');
                unset_config('wordle_' . $end, 'local_playergames');
            }
        }

        upgrade_plugin_savepoint(true, 2026112300, 'local', 'playergames');
    }

    if ($oldversion < 2026112301) {
        // No game ever read the difficulty of a concept or question, and the categories lost their only use with the
        // choice of the concept of the day.
        foreach (['local_playergames_concepts', 'local_playergames_concept_questions'] as $name) {
            $table = new xmldb_table($name);
            $index = new xmldb_index('idx_categoryid', XMLDB_INDEX_NOTUNIQUE, ['categoryid']);
            if ($dbman->index_exists($table, $index)) {
                $dbman->drop_index($table, $index);
            }
            foreach (['difficulty', 'categoryid'] as $column) {
                $field = new xmldb_field($column);
                if ($dbman->field_exists($table, $field)) {
                    $dbman->drop_field($table, $field);
                }
            }
        }
        $table = new xmldb_table('local_playergames_categories');
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }

        upgrade_plugin_savepoint(true, 2026112301, 'local', 'playergames');
    }

    return true;
}
