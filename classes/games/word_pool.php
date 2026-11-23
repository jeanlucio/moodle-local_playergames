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
 * The words PlayerGuess and PlayerFill may draw from the concept cartridges.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\games;

use core_text;

/**
 * The one rule of which terms serve each word game, read by the daily draw, the hub, the season page and the AI prompt.
 *
 * A term serves a game when it is a single word made only of letters, with a length inside the range the game's
 * settings accept: a guess can only contain letters, so any other term could never be typed.
 */
final class word_pool {
    /** @var int[][] Default [minimum, maximum] length of a word, by game. */
    private const DEFAULT_LENGTHS = ['guess' => [4, 8], 'fill' => [3, 12]];

    /**
     * The games that draw words.
     *
     * @return string[] Game types.
     */
    public static function games(): array {
        return array_keys(self::DEFAULT_LENGTHS);
    }

    /**
     * The length range as the settings have it, which may be one that matches no word.
     *
     * @param string $gametype 'guess' or 'fill'.
     * @return int[] [minimum, maximum]
     */
    public static function configured_range(string $gametype): array {
        [$defaultmin, $defaultmax] = self::DEFAULT_LENGTHS[$gametype];
        return [
            (int) get_config('local_playergames', $gametype . '_minlen') ?: $defaultmin,
            (int) get_config('local_playergames', $gametype . '_maxlen') ?: $defaultmax,
        ];
    }

    /**
     * The length range a game draws from, which is the default one when the settings match no word.
     *
     * The settings page refuses a range that crosses itself, but one saved before that, or set another way, would
     * leave the game without a word every day.
     *
     * @param string $gametype 'guess' or 'fill'.
     * @return int[] [minimum, maximum]
     */
    public static function length_range(string $gametype): array {
        [$min, $max] = self::configured_range($gametype);
        return $min < 1 || $max < $min ? self::DEFAULT_LENGTHS[$gametype] : [$min, $max];
    }

    /**
     * The length range that serves both word games, or the widest of the two when they have no length in common.
     *
     * @return int[] [minimum, maximum]
     */
    public static function shared_range(): array {
        [$guessmin, $guessmax] = self::length_range('guess');
        [$fillmin, $fillmax] = self::length_range('fill');
        $min = max($guessmin, $fillmin);
        $max = min($guessmax, $fillmax);
        return $min <= $max ? [$min, $max] : [min($guessmin, $fillmin), max($guessmax, $fillmax)];
    }

    /**
     * How many words a game needs to have a day of play.
     *
     * @param string $gametype 'guess' or 'fill'.
     * @return int
     */
    public static function needed(string $gametype): int {
        return $gametype === 'fill' ? fill_manager::num_words() : 1;
    }

    /**
     * Whether a term serves a game.
     *
     * @param string $term The term as it is stored.
     * @param int[] $range [minimum, maximum] length the game accepts.
     * @return bool
     */
    public static function fits(string $term, array $range): bool {
        $length = core_text::strlen($term);
        if ($length < $range[0] || $length > $range[1]) {
            return false;
        }
        $normalized = guess_manager::normalize($term);
        return guess_manager::is_valid_guess($normalized, core_text::strlen($normalized));
    }

    /**
     * The active concept cartridges a game draws from in the active season.
     *
     * @param string $gametype 'guess' or 'fill'.
     * @return int[] Cartridge ids.
     */
    public static function cartridges(string $gametype): array {
        return season_game_config::usable_cartridges(
            $gametype,
            season_game_config::active_cartridge_types(),
            season_game_config::get_for_active_season($gametype)
        );
    }

    /**
     * The concepts of the given cartridges whose term serves a game.
     *
     * @param string $gametype 'guess' or 'fill'.
     * @param int[] $cartridgeids Cartridge ids.
     * @param int $limit How many are enough, 0 for all of them.
     * @return array Terms keyed by concept id.
     */
    public static function terms(string $gametype, array $cartridgeids, int $limit = 0): array {
        global $DB;

        if ($cartridgeids === []) {
            return [];
        }
        $range = self::length_range($gametype);
        [$insql, $params] = $DB->get_in_or_equal($cartridgeids, SQL_PARAMS_NAMED, 'cid');
        $params += ['minlen' => $range[0], 'maxlen' => $range[1]];
        $length = $DB->sql_length('term');

        // The length in SQL counts every character and only narrows the rows; fits() has the last word.
        $rows = $DB->get_recordset_select(
            'local_playergames_concepts',
            "cartridgeid {$insql} AND {$length} >= :minlen AND {$length} <= :maxlen",
            $params,
            '',
            'id, term'
        );
        $terms = [];
        foreach ($rows as $row) {
            if (self::fits($row->term, $range)) {
                $terms[(int) $row->id] = $row->term;
            }
            if ($limit > 0 && count($terms) >= $limit) {
                break;
            }
        }
        $rows->close();
        return $terms;
    }

    /**
     * Whether the cartridges have the words a game needs.
     *
     * @param string $gametype 'guess' or 'fill'.
     * @param int[] $cartridgeids Cartridge ids.
     * @return bool
     */
    public static function has_enough(string $gametype, array $cartridgeids): bool {
        $needed = self::needed($gametype);
        return count(self::terms($gametype, $cartridgeids, $needed)) >= $needed;
    }

    /**
     * How many terms of each concept cartridge serve each game.
     *
     * @return array Counts keyed by cartridge id, each with 'total' and one count per game. A cartridge without
     *               concepts is left out.
     */
    public static function usage(): array {
        global $DB;

        $ranges = [];
        foreach (self::games() as $gametype) {
            $ranges[$gametype] = self::length_range($gametype);
        }
        $usage = [];
        $rows = $DB->get_recordset('local_playergames_concepts', null, '', 'id, cartridgeid, term');
        foreach ($rows as $row) {
            $id = (int) $row->cartridgeid;
            $usage[$id] ??= ['total' => 0] + array_fill_keys(self::games(), 0);
            $usage[$id]['total']++;
            foreach ($ranges as $gametype => $range) {
                $usage[$id][$gametype] += (int) self::fits($row->term, $range);
            }
        }
        $rows->close();
        return $usage;
    }
}
