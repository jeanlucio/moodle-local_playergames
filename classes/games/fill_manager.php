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
 * PlayerFill gameplay logic: daily crossword-style puzzle assembly.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\games;

use core_text;
use stdClass;

/**
 * Assembles the daily PlayerFill puzzle from the N concepts assign_daily_games picked
 * for today and scores whole-word guesses against it.
 *
 * Ported from mod_playercross\local\puzzle_builder, simplified for the Player Hub's
 * daily-play model: mod_playercross ciphers one mystery phrase and then greedily
 * selects clue words, from a larger pool, that best cover its letters. PlayerFill has
 * no separate mystery phrase — assign_daily_games already fixed the exact N peer terms
 * for the day, so there is no pool to select from, only a shared slot map to build
 * across all of them (see build_puzzle()).
 *
 * @package    local_playergames
 */
class fill_manager {
    /** @var int Default number of words assigned per day. */
    public const DEFAULT_NUM_WORDS = 5;

    /** @var int Minimum number of words configurable per day. */
    public const MIN_NUM_WORDS = 4;

    /** @var int Maximum number of words configurable per day. */
    public const MAX_NUM_WORDS = 8;

    /** @var int Default maximum guesses allowed per word before the day is lost. */
    public const DEFAULT_MAX_ATTEMPTS = 4;

    /**
     * Returns the number of words assigned to PlayerFill each day.
     *
     * @return int Clamped between MIN_NUM_WORDS and MAX_NUM_WORDS.
     */
    public static function num_words(): int {
        $value = (int) get_config('local_playergames', 'fill_num_words');
        if ($value <= 0) {
            $value = self::DEFAULT_NUM_WORDS;
        }
        return max(self::MIN_NUM_WORDS, min(self::MAX_NUM_WORDS, $value));
    }

    /**
     * Returns the maximum number of guesses allowed per word before the day is lost.
     *
     * @return int At least 1.
     */
    public static function max_attempts(): int {
        $value = (int) get_config('local_playergames', 'fill_max_attempts');
        return $value > 0 ? $value : self::DEFAULT_MAX_ATTEMPTS;
    }

    /**
     * Returns the concepts assigned to PlayerFill for the given day, in a stable order.
     *
     * @param int $gamedate Midnight timestamp of the day.
     * @return stdClass[] Concept records (id, term, definition), keyed 0..n-1, ordered
     *     by their assignment id so the same call always returns the same order for a
     *     given day.
     */
    public static function get_daily_concepts(int $gamedate): array {
        global $DB;

        $sql = "SELECT c.id, c.term, c.definition
                  FROM {local_playergames_daily_assignments} a
                  JOIN {local_playergames_concepts} c ON c.id = a.conceptid
                 WHERE a.gamedate = :gamedate AND a.gametype = :gametype
              ORDER BY a.id ASC";

        return array_values($DB->get_records_sql($sql, ['gamedate' => $gamedate, 'gametype' => 'fill']));
    }

    /**
     * Builds the shared letter-to-slot map across every word and each word's own
     * per-position slot numbers.
     *
     * Every distinct letter across every term, in order of first appearance, gets the
     * next sequential slot number — the same letter anywhere in any term, at any
     * position, reuses the number already assigned to it. This is the simplified,
     * whole-round version of mod_playercross\local\puzzle_builder::cipher_phrase_slots()
     * plus expand_slots_by_letter(): there is no candidate pool to greedily select from
     * here (see the class docblock), so every term always contributes its letters to
     * the shared map, in the order the caller provides them.
     *
     * @param stdClass[] $concepts Concept records (id, term, definition), as returned
     *     by get_daily_concepts().
     * @return array{words: array, slotcount: int} Each word entry has conceptid, word
     *     (normalized term), originalterm (original spelling, reveal-only — see
     *     build_tiles()), definition and slots (one slot number per letter position).
     */
    public static function build_puzzle(array $concepts): array {
        $slotsbyletter = [];
        $nextslot = 1;
        $words = [];

        foreach ($concepts as $concept) {
            $normalized = guess_manager::normalize($concept->term);
            $slots = [];
            foreach (self::chars($normalized) as $char) {
                if (!isset($slotsbyletter[$char])) {
                    $slotsbyletter[$char] = $nextslot++;
                }
                $slots[] = $slotsbyletter[$char];
            }
            $words[] = [
                'conceptid'    => (int) $concept->id,
                'word'         => $normalized,
                'originalterm' => trim((string) $concept->term),
                'definition'   => (string) $concept->definition,
                'slots'        => $slots,
            ];
        }

        return ['words' => $words, 'slotcount' => count($slotsbyletter)];
    }

    /**
     * Builds the per-position tile view-model for one word, for template rendering.
     *
     * @param string $word Normalized word.
     * @param int[] $slots This word's own per-position slot numbers, as built by
     *     build_puzzle().
     * @param int[] $revealedslots Slot numbers already revealed round-wide.
     * @return array<int, array{letter: string, revealed: bool, slotnum: string}>
     */
    public static function build_tiles(string $word, array $slots, array $revealedslots): array {
        $tiles = [];
        foreach (self::chars($word) as $index => $char) {
            $slot = $slots[$index];
            $revealed = in_array($slot, $revealedslots, true);
            $tiles[] = [
                'letter'   => $revealed ? core_text::strtoupper($char) : '',
                'revealed' => $revealed,
                'slotnum'  => $revealed ? '' : (string) $slot,
            ];
        }
        return $tiles;
    }

    /**
     * Auto-resolves any still-pending word whose every slot is already revealed.
     *
     * A correct guess reveals its own word's slots round-wide, which can incidentally
     * complete a different pending word too when it happens to share every one of its
     * letters with words already solved. Ported from mod_playercross\local\round_service
     * ::resolve_fully_revealed_clues(), without the per-attempt scoring that only exists
     * there because of course-activity grading.
     *
     * @param array $words Current words array (see fill_state).
     * @param int[] $revealedslots Slot numbers revealed round-wide.
     * @return array Updated words array.
     */
    public static function apply_cascade(array $words, array $revealedslots): array {
        foreach ($words as $index => $word) {
            if ($word['resolved'] || $word['exhausted']) {
                continue;
            }
            if (array_diff($word['slots'], $revealedslots) !== []) {
                continue;
            }
            $words[$index]['resolved'] = true;
        }
        return $words;
    }

    /**
     * Builds the per-word response payload shared by every play_fill.php POST outcome.
     *
     * @param array $state Current round state (see fill_state).
     * @param bool $revealanswers Whether to include every unresolved word's spelling,
     *     used once the round has finished in a loss.
     * @return array
     */
    public static function build_words_payload(array $state, bool $revealanswers): array {
        $payload = [];
        foreach ($state['words'] as $word) {
            $tiles = self::build_tiles($word['word'], $word['slots'], $state['revealedslots']);
            $payload[] = [
                'conceptid'    => $word['conceptid'],
                'resolved'     => (bool) $word['resolved'],
                'exhausted'    => (bool) $word['exhausted'],
                'attemptsused' => (int) $word['attemptsused'],
                'tiles'        => array_values($tiles),
                'revealword'   => ($word['resolved'] || $revealanswers) ? $word['originalterm'] : '',
            ];
        }
        return $payload;
    }

    /**
     * Splits a normalized word into its individual Unicode characters.
     *
     * @param string $normalizedword Already-normalized word (see guess_manager::normalize()).
     * @return string[]
     */
    private static function chars(string $normalizedword): array {
        return preg_split('//u', $normalizedword, -1, PREG_SPLIT_NO_EMPTY);
    }
}
