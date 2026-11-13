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
 * Database store for the in-progress daily round of the single-round games.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\games;

use core\lock\lock;
use core\lock\lock_config;
use moodle_exception;

/**
 * Keeps one round state per user, day and game in the database.
 *
 * The state of PlayerGuess and PlayerFill used to live in the session, so a new login (or another
 * browser) started the round again, with the answer the previous round had just revealed. Here a lost
 * round stays lost for the day. The session lock that used to serialise a user's requests no longer
 * applies, so submissions take {@see self::lock()} around the read-modify-write cycle: without it,
 * parallel requests would each read the same attempts and every one of them would count as the first.
 *
 * @package    local_playergames
 */
class round_store {
    /**
     * Takes the lock that serialises the submissions of one user to one game.
     *
     * @param int $userid User id.
     * @param string $gametype Game type.
     * @return lock The lock, to be released by the caller.
     * @throws moodle_exception When the lock cannot be taken in time.
     */
    public static function lock(int $userid, string $gametype): lock {
        $lock = lock_config::get_lock_factory('local_playergames')->get_lock("round_{$userid}_{$gametype}", 10);
        if (!$lock) {
            throw new moodle_exception('error_round_busy', 'local_playergames');
        }
        return $lock;
    }

    /**
     * Reads the stored state of a round.
     *
     * @param int $userid User id.
     * @param string $gametype Game type.
     * @param int $gamedate Midnight timestamp of the day.
     * @return array|null The state, or null when the round was never started or the row is unreadable.
     */
    public static function fetch(int $userid, string $gametype, int $gamedate): ?array {
        global $DB;

        $json = $DB->get_field(
            'local_playergames_rounds',
            'state',
            ['userid' => $userid, 'gamedate' => $gamedate, 'gametype' => $gametype]
        );
        if ($json === false) {
            return null;
        }
        $state = json_decode((string) $json, true);
        return is_array($state) ? $state : null;
    }

    /**
     * Stores the state of a round, creating or replacing the row for that user, day and game.
     *
     * @param int $userid User id.
     * @param string $gametype Game type.
     * @param int $gamedate Midnight timestamp of the day.
     * @param array $state State to keep.
     * @return void
     */
    public static function store(int $userid, string $gametype, int $gamedate, array $state): void {
        global $DB;

        $key = ['userid' => $userid, 'gamedate' => $gamedate, 'gametype' => $gametype];
        $json = json_encode($state);
        $id = $DB->get_field('local_playergames_rounds', 'id', $key);
        if ($id !== false) {
            $DB->update_record('local_playergames_rounds', (object) [
                'id'           => $id,
                'state'        => $json,
                'timemodified' => time(),
            ]);
            return;
        }
        $DB->insert_record('local_playergames_rounds', (object) ($key + [
            'state'        => $json,
            'timemodified' => time(),
        ]));
    }
}
