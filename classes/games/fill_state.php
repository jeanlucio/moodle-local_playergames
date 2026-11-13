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
 * Stored round state for the daily PlayerFill play.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\games;

/**
 * Keeps the in-progress PlayerFill round (per-word progress and revealed slots) in the database, one
 * row per user and day (see {@see round_store}), mirroring guess_state. A lost round therefore stays
 * lost for the day, whatever the session.
 *
 * The state is discarded and rebuilt whenever today's gamedate or assigned concept set
 * no longer matches, so a stale round from a previous day never leaks into a new one.
 *
 * @package    local_playergames
 */
class fill_state {
    /** @var string Game type the rounds are stored under. */
    private const GAMETYPE = 'fill';

    /**
     * Loads today's round state, (re)building the puzzle when stale.
     *
     * @param int $userid User id.
     * @param int $gamedate Midnight timestamp of the day.
     * @param \stdClass[] $concepts Today's assigned concepts, as returned by
     *     fill_manager::get_daily_concepts().
     * @return array
     */
    public static function load(int $userid, int $gamedate, array $concepts): array {
        $conceptids = array_map(fn($c) => (int) $c->id, $concepts);

        $state = round_store::fetch($userid, self::GAMETYPE, $gamedate);
        if (
            $state === null
            || (int) ($state['gamedate'] ?? 0) !== $gamedate
            || ($state['conceptids'] ?? []) !== $conceptids
        ) {
            $puzzle = fill_manager::build_puzzle($concepts);
            $words = [];
            foreach ($puzzle['words'] as $word) {
                $words[] = $word + [
                    'resolved'     => false,
                    'attemptsused' => 0,
                    'exhausted'    => false,
                ];
            }

            $state = [
                'gamedate'      => $gamedate,
                'conceptids'    => $conceptids,
                'words'         => $words,
                'revealedslots' => [],
                'finished'      => false,
                'won'           => false,
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
