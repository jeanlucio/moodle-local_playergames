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
 * Scheduled task: assign daily game concepts.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\task;

use local_playergames\games\fill_manager;
use local_playergames\games\guess_manager;
use local_playergames\games\word_pool;
use local_playergames\local\day;

/**
 * Picks a random concept from active cartridges for each concept-based game
 * and writes a row to local_playergames_daily_assignments.
 *
 * PlayerQuiz and PlayerBattle are excluded: they draw their own questions when played. Check-in has
 * no concept. PlayerGuess gets a single concept for the day. PlayerFill gets
 * fill_manager::num_words() concepts instead — one crossword-style puzzle needs several peer
 * terms, not one — handled separately by assign_fill() rather than through CONCEPT_GAMES below.
 *
 * Idempotent: if an assignment already exists for today and a given gametype,
 * it is left unchanged.
 *
 * Wordle-style filter for PlayerGuess: only concepts whose term length falls
 * between guess_minlen and guess_maxlen (admin settings) are eligible, and
 * whose term is a single alphabetic word (see word_pool) — the same rule guess_manager
 * enforces on every submitted guess. A term with a space, digit or hyphen
 * (e.g. "AI-5") would sit inside the length range yet could never be typed by
 * a letters-only guess, making that day's play unwinnable for everyone.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assign_daily_games extends \core\task\scheduled_task {
    /** @var string[] Game types that each receive a single concept assignment. */
    private const CONCEPT_GAMES = ['guess'];

    /** @var bool Whether the run reports nothing, as it must when a web request draws the words. */
    private bool $quiet = false;

    /**
     * Returns the human-readable task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_assign_daily_games', 'local_playergames');
    }

    /**
     * Draws today's words from a web request, for the games that lost theirs, without the task's report.
     *
     * Safe to call at any time: a game that still has its words for today is left as it is.
     *
     * @return void
     */
    public static function draw_now(): void {
        // Not mtrace() inside an output buffer: mtrace() flushes, which sends the headers of the page, and the
        // redirect that ends every such request then prints a page instead of answering with a redirection.
        $task = new self();
        $task->quiet = true;
        $task->execute();
    }

    /**
     * Writes a line of the task's report, unless the run is a quiet one.
     *
     * @param string $line The line.
     * @return void
     */
    private function report(string $line): void {
        if (!$this->quiet) {
            mtrace($line);
        }
    }

    /**
     * Assigns a concept to each concept-based game for today.
     *
     * @return void
     */
    public function execute(): void {
        global $DB;
        $today = day::today();

        if (!$DB->record_exists('local_playergames_cartridges', ['active' => 1, 'type' => 'concept'])) {
            $this->report('No active cartridges — skipping assignment.');
            return;
        }

        foreach (word_pool::games() as $gametype) {
            $this->report_broken_range($gametype);
        }

        foreach (self::CONCEPT_GAMES as $gametype) {
            $alreadyassigned = $DB->record_exists(
                'local_playergames_daily_assignments',
                ['gamedate' => $today, 'gametype' => $gametype]
            );
            if ($alreadyassigned) {
                $this->report("Assignment already exists for {$gametype} today — skipping.");
                continue;
            }

            $terms = word_pool::terms($gametype, word_pool::cartridges($gametype));
            $conceptid = $this->pick_concept($terms, $this->terms_assigned_today('fill', $today));
            if ($conceptid === null) {
                $this->report("No eligible concept found for {$gametype} — skipping.");
                continue;
            }

            $record           = new \stdClass();
            $record->gamedate = $today;
            $record->gametype = $gametype;
            $record->conceptid = $conceptid;
            $DB->insert_record('local_playergames_daily_assignments', $record);
            $this->report("Assigned concept {$conceptid} to {$gametype} for today.");
        }

        $fillpool = word_pool::cartridges('fill');
        if ($fillpool === []) {
            $this->report('No cartridge kept for fill in the active season — skipping assignment.');
            return;
        }
        $this->assign_fill($fillpool, $today);
    }

    /**
     * Reports a length range that can match no word, which the draw replaces with the default one.
     *
     * @param string $gametype 'guess' or 'fill'.
     * @return void
     */
    private function report_broken_range(string $gametype): void {
        [$min, $max] = word_pool::configured_range($gametype);
        [$usedmin, $usedmax] = word_pool::length_range($gametype);
        if ([$min, $max] !== [$usedmin, $usedmax]) {
            $this->report(
                "The {$gametype} term length range {$min}-{$max} cannot match any term — using {$usedmin}-{$usedmax}."
            );
        }
    }

    /**
     * Assigns fill_manager::num_words() concepts to PlayerFill for today.
     *
     * Candidates are filtered the same way as PlayerGuess (letters-only after
     * normalization, within a configured length range — fill_minlen/fill_maxlen here
     * instead of guess_minlen/guess_maxlen), since every assigned term is tiled as a
     * guessable word. Skips assignment entirely, rather than assigning a partial set,
     * when the eligible pool is smaller than the configured word count — the same
     * graceful degradation the other concept games apply when no eligible concept
     * exists at all.
     *
     * @param int[] $cartridgeids Active cartridge ids.
     * @param int $today Midnight timestamp of today.
     * @return void
     */
    private function assign_fill(array $cartridgeids, int $today): void {
        global $DB;

        $alreadyassigned = $DB->record_exists(
            'local_playergames_daily_assignments',
            ['gamedate' => $today, 'gametype' => 'fill']
        );
        if ($alreadyassigned) {
            $this->report('Assignment already exists for fill today — skipping.');
            return;
        }

        $numwords = fill_manager::num_words();
        $terms = word_pool::terms('fill', $cartridgeids);
        [$fresh, $avoided] = $this->split_by_terms($terms, $this->terms_assigned_today('guess', $today));

        if (count($fresh) + count($avoided) < $numwords) {
            $this->report('Not enough eligible concepts for fill — skipping.');
            return;
        }

        // The word of PlayerGuess goes in only when the others are not enough for the puzzle.
        shuffle($fresh);
        shuffle($avoided);
        $chosen = array_slice(array_merge($fresh, $avoided), 0, $numwords);

        $records = [];
        foreach ($chosen as $conceptid) {
            $records[] = (object) ['gamedate' => $today, 'gametype' => 'fill', 'conceptid' => $conceptid];
        }
        $DB->insert_records('local_playergames_daily_assignments', $records);
        $this->report('Assigned ' . count($chosen) . ' concepts to fill for today.');
    }

    /**
     * Picks a random concept PlayerGuess can use.
     *
     * @param array $terms Terms the game may draw, keyed by concept id.
     * @param array $avoid Normalized terms already in another game today, keyed by term: used only when nothing else
     *                     is left.
     * @return int|null Concept ID or null if none found.
     */
    private function pick_concept(array $terms, array $avoid = []): ?int {
        [$fresh, $avoided] = $this->split_by_terms($terms, $avoid);
        // A word another game uses today is drawn only when no other is left.
        $choices = $fresh !== [] ? $fresh : $avoided;
        if ($choices === []) {
            return null;
        }
        return (int) $choices[array_rand($choices)];
    }

    /**
     * Splits the concepts into the ones whose term is free and the ones in $avoid.
     *
     * @param array $terms Terms keyed by concept id.
     * @param array $avoid Normalized terms to keep apart, keyed by term.
     * @return array [int[] $fresh, int[] $avoided]
     */
    private function split_by_terms(array $terms, array $avoid): array {
        $fresh = [];
        $avoided = [];
        foreach ($terms as $conceptid => $term) {
            if (isset($avoid[guess_manager::normalize($term)])) {
                $avoided[] = $conceptid;
            } else {
                $fresh[] = $conceptid;
            }
        }
        return [$fresh, $avoided];
    }

    /**
     * The normalized terms another game was given today.
     *
     * The same word in PlayerGuess and PlayerFill on one day gives the answer of one game away in the other.
     *
     * @param string $gametype The game whose words to read.
     * @param int $today Midnight of today.
     * @return array Keyed by normalized term.
     */
    private function terms_assigned_today(string $gametype, int $today): array {
        global $DB;
        $terms = $DB->get_fieldset_sql(
            "SELECT c.term
               FROM {local_playergames_daily_assignments} a
               JOIN {local_playergames_concepts} c ON c.id = a.conceptid
              WHERE a.gamedate = :today AND a.gametype = :gametype",
            ['today' => $today, 'gametype' => $gametype]
        );
        $avoid = [];
        foreach ($terms as $term) {
            $avoid[guess_manager::normalize($term)] = true;
        }
        return $avoid;
    }
}
