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

    return true;
}
