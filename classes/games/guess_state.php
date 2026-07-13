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
 * Session-backed round state for the daily PlayerGuess play.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\games;

/**
 * Keeps the in-progress PlayerGuess round (rows guessed so far today) in the
 * user's session, mirroring the pattern proven by mod_playerwords's round
 * service. There is only ever one PlayerGuess round in flight per user, so a
 * single session key is enough — no per-instance keying is needed.
 *
 * The state is discarded and rebuilt whenever the stored gamedate or
 * conceptid no longer matches today's assignment, so a stale round from a
 * previous day (or a re-assigned concept) never leaks into a new day's play.
 *
 * Known limitation: the state lives only in the PHP session, so a round in
 * progress does not survive a logout or a switch to a different browser
 * session. This mirrors mod_playerwords's own tradeoff and avoids adding a
 * dedicated database table for a same-day, single-device play.
 *
 * @package    local_playergames
 */
class guess_state {
    /** @var string Session property holding the round state. */
    private const SESSION_KEY = 'local_playergames_guess';

    /**
     * Loads today's round state, resetting it when stale.
     *
     * @param int $gamedate Midnight timestamp of the day.
     * @param int $conceptid Concept id assigned to today's play.
     * @return array
     */
    public static function load(int $gamedate, int $conceptid): array {
        global $SESSION;

        $state = $SESSION->{self::SESSION_KEY} ?? null;
        if (
            !is_array($state)
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
     * @param array $state Current state, as returned by load().
     * @return void
     */
    public static function save(array $state): void {
        global $SESSION;
        $SESSION->{self::SESSION_KEY} = $state;
    }
}
