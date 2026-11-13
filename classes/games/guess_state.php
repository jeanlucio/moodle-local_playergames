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
 * Stored round state for the daily PlayerGuess play.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\games;

/**
 * Keeps the in-progress PlayerGuess round (rows guessed so far today) in the database, one row per
 * user and day (see {@see round_store}), so it survives a logout or a switch of browser: a lost round
 * stays lost for the day instead of starting again with the term already revealed.
 *
 * The state is discarded and rebuilt whenever the stored gamedate or conceptid no longer matches
 * today's assignment, so a stale round (or a re-assigned concept) never leaks into a new play.
 *
 * @package    local_playergames
 */
class guess_state {
    /** @var string Game type the rounds are stored under. */
    private const GAMETYPE = 'guess';

    /**
     * Loads today's round state, resetting it when stale.
     *
     * @param int $userid User id.
     * @param int $gamedate Midnight timestamp of the day.
     * @param int $conceptid Concept id assigned to today's play.
     * @return array
     */
    public static function load(int $userid, int $gamedate, int $conceptid): array {
        $state = round_store::fetch($userid, self::GAMETYPE, $gamedate);
        if (
            $state === null
            || (int) ($state['gamedate'] ?? 0) !== $gamedate
            || (int) ($state['conceptid'] ?? 0) !== $conceptid
        ) {
            $state = [
                'gamedate'  => $gamedate,
                'conceptid' => $conceptid,
                'rows'      => [],
                'finished'  => false,
                'won'       => false,
            ];
        }

        return $state;
    }

    /**
     * Persists the round state.
     *
     * @param int $userid User id.
     * @param array $state Current state, as returned by load().
     * @return void
     */
    public static function save(int $userid, array $state): void {
        round_store::store($userid, self::GAMETYPE, (int) $state['gamedate'], $state);
    }
}
