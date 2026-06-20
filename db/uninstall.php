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
 * Uninstall hook: drops all plugin tables and cleans user preferences.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Drops all local_playergames tables and removes user preferences.
 *
 * @return bool
 */
function xmldb_local_playergames_uninstall(): bool {
    global $DB;

    $dbman = $DB->get_manager();

    // Listed child-first so foreign-key references are dropped before their parents.
    $tables = [
        'local_playergames_concept_answers',
        'local_playergames_concept_questions',
        'local_playergames_user_achievements',
        'local_playergames_achievements',
        'local_playergames_mission_progress',
        'local_playergames_missions',
        'local_playergames_battle_scores',
        'local_playergames_daily_scores',
        'local_playergames_daily_assignments',
        'local_playergames_season_games',
        'local_playergames_categories',
        'local_playergames_concepts',
        'local_playergames_cartridges',
        'local_playergames_streaks',
        'local_playergames_player_profile',
        'local_playergames_ai_log',
        'local_playergames_seasons',
    ];

    foreach ($tables as $tablename) {
        $table = new xmldb_table($tablename);
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }
    }

    $DB->delete_records_select(
        'user_preferences',
        $DB->sql_like('name', ':prefix'),
        ['prefix' => 'local_playergames_%']
    );

    return true;
}
