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
     * Defeats counted today.
     *
     * @param int $userid User id.
     * @param string $gametype One of COUNTED_GAMES.
     * @param int $gamedate Midnight timestamp of the day.
     * @return int
     */
    public static function losses(int $userid, string $gametype, int $gamedate): int {
        return (int) (round_store::fetch($userid, $gametype, $gamedate)['losses'] ?? 0);
    }

    /**
     * Marks a match as being played, so that one left midway can be told from one that ended.
     *
     * @param int $userid User id.
     * @param string $gametype One of COUNTED_GAMES.
     * @param int $gamedate Midnight timestamp of the day.
     * @param string $token What identifies the match.
     * @return void
     */
    public static function begin(int $userid, string $gametype, int $gamedate, string $token): void {
        self::change($userid, $gametype, $gamedate, static function (array $state) use ($token): array {
            $state['open'] = $token;
            return $state;
        });
    }

    /**
     * Whether the match is the one being played.
     *
     * @param int $userid User id.
     * @param string $gametype One of COUNTED_GAMES.
     * @param int $gamedate Midnight timestamp of the day.
     * @param string $token What identifies the match.
     * @return bool
     */
    public static function is_open(int $userid, string $gametype, int $gamedate, string $token): bool {
        return (round_store::fetch($userid, $gametype, $gamedate)['open'] ?? '') === $token;
    }

    /**
     * Ends the match being played without a defeat.
     *
     * @param int $userid User id.
     * @param string $gametype One of COUNTED_GAMES.
     * @param int $gamedate Midnight timestamp of the day.
     * @return void
     */
    public static function finish(int $userid, string $gametype, int $gamedate): void {
        self::change($userid, $gametype, $gamedate, static function (array $state): array {
            unset($state['open']);
            return $state;
        });
    }

    /**
     * Counts a match left midway as a defeat, or only forgets it when the defeats are unlimited.
     *
     * @param int $userid User id.
     * @param string $gametype One of COUNTED_GAMES.
     * @param int $gamedate Midnight timestamp of the day.
     * @param int $allowed Defeats that close the day; 0 never closes it.
     * @return void
     */
    public static function settle(int $userid, string $gametype, int $gamedate, int $allowed): void {
        if (empty(round_store::fetch($userid, $gametype, $gamedate)['open'])) {
            return;
        }
        self::change($userid, $gametype, $gamedate, static function (array $state) use ($allowed): array {
            // Asked again under the lock: another request may have settled it meanwhile.
            if (empty($state['open'])) {
                return $state;
            }
            unset($state['open']);
            if ($allowed > 0) {
                $state['losses'] = (int) ($state['losses'] ?? 0) + 1;
                $state['closed'] = !empty($state['closed']) || $state['losses'] >= $allowed;
            }
            return $state;
        });
    }

    /**
     * Wrong answers kept for the day, so that a new session goes on from them.
     *
     * @param int $userid User id.
     * @param string $gametype One of COUNTED_GAMES.
     * @param int $gamedate Midnight timestamp of the day.
     * @return int
     */
    public static function wrong(int $userid, string $gametype, int $gamedate): int {
        return (int) (round_store::fetch($userid, $gametype, $gamedate)['wrong'] ?? 0);
    }

    /**
     * Adds one wrong answer to the ones kept for the day.
     *
     * @param int $userid User id.
     * @param string $gametype One of COUNTED_GAMES.
     * @param int $gamedate Midnight timestamp of the day.
     * @return int The wrong answers of the day, this one included.
     */
    public static function add_wrong(int $userid, string $gametype, int $gamedate): int {
        $state = self::change($userid, $gametype, $gamedate, static function (array $state): array {
            $state['wrong'] = (int) ($state['wrong'] ?? 0) + 1;
            return $state;
        });
        return (int) $state['wrong'];
    }

    /**
     * Forgets the wrong answers of the day, once the play they belong to was won.
     *
     * @param int $userid User id.
     * @param string $gametype One of COUNTED_GAMES.
     * @param int $gamedate Midnight timestamp of the day.
     * @return void
     */
    public static function clear_wrong(int $userid, string $gametype, int $gamedate): void {
        if (self::wrong($userid, $gametype, $gamedate) === 0) {
            return;
        }
        self::change($userid, $gametype, $gamedate, static function (array $state): array {
            unset($state['wrong']);
            return $state;
        });
    }

    /**
     * Reads, changes and writes the state of the day under the lock of the round.
     *
     * @param int $userid User id.
     * @param string $gametype One of COUNTED_GAMES.
     * @param int $gamedate Midnight timestamp of the day.
     * @param callable $change Receives the state and returns the changed one.
     * @return array The changed state.
     */
    private static function change(int $userid, string $gametype, int $gamedate, callable $change): array {
        $lock = round_store::lock($userid, $gametype);
        try {
            $state = $change(round_store::fetch($userid, $gametype, $gamedate) ?? []);
            round_store::store($userid, $gametype, $gamedate, $state);
            return $state;
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
