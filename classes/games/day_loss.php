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
 * The defeat that closes a game until the next day.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\games;

/**
 * Records the defeats of a day and tells whether a game is closed until tomorrow.
 *
 * The four games follow one rule: a lost day is lost. PlayerGuess and PlayerFill keep their own round, whose state
 * already says when it was lost. PlayerQuiz and PlayerBattle draw new questions on every play, so they count their
 * defeats here, in the same table of rounds: kept in the database, a defeat holds in any browser, and the nightly
 * purge of earlier rounds clears it.
 *
 * @package    local_playergames
 */
class day_loss {
    /** @var string[] Games that keep their defeats here rather than in a round of their own. */
    public const COUNTED_GAMES = ['quiz', 'battle'];

    /**
     * Whether the user lost the game today and cannot play it again until tomorrow.
     *
     * @param int $userid User id.
     * @param string $gametype Game type.
     * @param int $gamedate Midnight timestamp of the day.
     * @return bool
     */
    public static function is_closed(int $userid, string $gametype, int $gamedate): bool {
        $state = round_store::fetch($userid, $gametype, $gamedate);
        return $state !== null && self::is_lost($gametype, $state);
    }

    /**
     * The games the user lost today, read in one query.
     *
     * @param int $userid User id.
     * @param int $gamedate Midnight timestamp of the day.
     * @return string[] Game types.
     */
    public static function closed_games(int $userid, int $gamedate): array {
        global $DB;
        $rows = $DB->get_records_menu(
            'local_playergames_rounds',
            ['userid' => $userid, 'gamedate' => $gamedate],
            '',
            'gametype, state'
        );
        $closed = [];
        foreach ($rows as $gametype => $json) {
            $state = json_decode((string) $json, true);
            if (is_array($state) && self::is_lost((string) $gametype, $state)) {
                $closed[] = (string) $gametype;
            }
        }
        return $closed;
    }

    /**
     * Counts one defeat and closes the game for the day once the allowed number is reached.
     *
     * @param int $userid User id.
     * @param string $gametype One of COUNTED_GAMES.
     * @param int $gamedate Midnight timestamp of the day.
     * @param int $allowed Defeats that close the day; 0 never closes it.
     * @return bool Whether the game is closed for the day now.
     */
    public static function record(int $userid, string $gametype, int $gamedate, int $allowed): bool {
        if (!in_array($gametype, self::COUNTED_GAMES, true)) {
            throw new \coding_exception("The defeats of {$gametype} are kept in its own round.");
        }
        // Two defeats sent at the same moment would both read the same count without the lock.
        $lock = round_store::lock($userid, $gametype);
        try {
            $state = round_store::fetch($userid, $gametype, $gamedate) ?? [];
            $losses = (int) ($state['losses'] ?? 0) + 1;
            $closed = !empty($state['closed']) || ($allowed > 0 && $losses >= $allowed);
            round_store::store($userid, $gametype, $gamedate, ['losses' => $losses, 'closed' => $closed]);
            return $closed;
        } finally {
            $lock->release();
        }
    }

    /**
     * Whether the state of a round says the day was lost.
     *
     * @param string $gametype Game type.
     * @param array $state State of the round.
     * @return bool
     */
    private static function is_lost(string $gametype, array $state): bool {
        if (in_array($gametype, self::COUNTED_GAMES, true)) {
            return !empty($state['closed']);
        }
        return !empty($state['finished']) && empty($state['won']);
    }
}
