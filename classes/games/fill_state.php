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
 * Session-backed round state for the daily PlayerFill play.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\games;

/**
 * Keeps the in-progress PlayerFill round (per-word progress and revealed slots) in the
 * user's session, mirroring guess_state and, further back, mod_playercross's own
 * session-only round state (mod_playercross\local\round_service).
 *
 * The state is discarded and rebuilt whenever today's gamedate or assigned concept set
 * no longer matches, so a stale round from a previous day never leaks into a new one.
 *
 * @package    local_playergames
 */
class fill_state {
    /** @var string Session property holding the round state. */
    private const SESSION_KEY = 'local_playergames_fill';

    /**
     * Loads today's round state, (re)building the puzzle when stale.
     *
     * @param int $gamedate Midnight timestamp of the day.
     * @param \stdClass[] $concepts Today's assigned concepts, as returned by
     *     fill_manager::get_daily_concepts().
     * @return array
     */
    public static function load(int $gamedate, array $concepts): array {
        global $SESSION;

        $conceptids = array_map(fn($c) => (int) $c->id, $concepts);

        $state = $SESSION->{self::SESSION_KEY} ?? null;
        if (
            !is_array($state)
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
     * @param array $state Current state, as returned by load().
     * @return void
     */
    public static function save(array $state): void {
        global $SESSION;
        $SESSION->{self::SESSION_KEY} = $state;
    }
}
