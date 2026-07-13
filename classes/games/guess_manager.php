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
 * PlayerGuess gameplay logic: daily term resolution and Wordle-style feedback.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\games;

use core_text;
use stdClass;

/**
 * Resolves the concept assigned to PlayerGuess for a given day and scores
 * guesses against its term using Wordle-style per-letter feedback.
 *
 * @package    local_playergames
 */
class guess_manager {
    /** @var int Default maximum guesses allowed before the term is revealed. */
    public const DEFAULT_MAX_ATTEMPTS = 6;

    /**
     * Returns the maximum number of guesses allowed per day before the term is
     * revealed.
     *
     * @return int At least 1.
     */
    public static function max_attempts(): int {
        $value = (int) get_config('local_playergames', 'guess_max_attempts');
        return $value > 0 ? $value : self::DEFAULT_MAX_ATTEMPTS;
    }

    /**
     * Returns the concept assigned to PlayerGuess for the given day, or null
     * when no assignment exists (e.g. no eligible term in any active cartridge).
     *
     * @param int $gamedate Midnight timestamp of the day.
     * @return stdClass|null
     */
    public static function get_daily_concept(int $gamedate): ?stdClass {
        global $DB;

        $conceptid = $DB->get_field('local_playergames_daily_assignments', 'conceptid', [
            'gamedate' => $gamedate,
            'gametype' => 'guess',
        ]);
        if (empty($conceptid)) {
            return null;
        }

        $concept = $DB->get_record('local_playergames_concepts', ['id' => (int) $conceptid]);
        return $concept ?: null;
    }

    /**
     * Normalizes one term for comparison: lowercased and accent-stripped.
     *
     * @param string $value Raw term or guess.
     * @return string
     */
    public static function normalize(string $value): string {
        return core_text::strtolower(core_text::specialtoascii(core_text::strtolower(trim($value))));
    }

    /**
     * Builds per-letter Wordle-style feedback for a guess against the target term.
     *
     * Assumes both strings have already been normalized and have equal length;
     * callers must validate that before calling this method.
     *
     * @param string $guess Normalized guess.
     * @param string $target Normalized target term.
     * @return string[] One of 'correct', 'present', 'absent' per letter position.
     */
    public static function build_letter_feedback(string $guess, string $target): array {
        $guessletters = preg_split('//u', $guess, -1, PREG_SPLIT_NO_EMPTY);
        $targetletters = preg_split('//u', $target, -1, PREG_SPLIT_NO_EMPTY);

        $feedback = [];
        $remaining = [];

        foreach ($targetletters as $index => $letter) {
            if (($guessletters[$index] ?? null) === $letter) {
                $feedback[$index] = 'correct';
                continue;
            }
            if (!isset($remaining[$letter])) {
                $remaining[$letter] = 0;
            }
            $remaining[$letter]++;
        }

        foreach ($guessletters as $index => $letter) {
            if (isset($feedback[$index])) {
                continue;
            }
            if (!empty($remaining[$letter])) {
                $feedback[$index] = 'present';
                $remaining[$letter]--;
            } else {
                $feedback[$index] = 'absent';
            }
        }

        ksort($feedback);
        return $feedback;
    }

    /**
     * Validates a raw guess against the target term's length and alphabet.
     *
     * @param string $normalizedguess Already-normalized guess.
     * @param int $targetlength Required length, in characters.
     * @return bool
     */
    public static function is_valid_guess(string $normalizedguess, int $targetlength): bool {
        if ($normalizedguess === '' || core_text::strlen($normalizedguess) !== $targetlength) {
            return false;
        }
        return (bool) preg_match('/^[\p{L}]+$/u', $normalizedguess);
    }
}
