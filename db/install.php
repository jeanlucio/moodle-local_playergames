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
 * Post-install hook: creates the default active season.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Creates a default active season covering the next six months.
 *
 * @return bool
 */
function xmldb_local_playergames_install(): bool {
    global $DB;

    $now = time();

    $season = (object) [
        'name'            => get_string('defaultseasonname', 'local_playergames'),
        'startdate'       => $now,
        'enddate'         => strtotime('+6 months', $now),
        'status'          => 'active',
        'config_snapshot' => json_encode([
            'xpcaps' => [
                'quiz'   => 25,
                'guess'  => 25,
                'mix'    => 25,
                'pairs'  => 25,
                'bounce' => 25,
            ],
        ]),
        'timecreated'  => $now,
        'timemodified' => $now,
    ];

    $DB->insert_record('local_playergames_seasons', $season);

    return true;
}
