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

use core_text;
use local_playergames\games\fill_manager;
use local_playergames\games\guess_manager;

/**
 * Picks a random concept from active cartridges for each concept-based game
 * and writes a row to local_playergames_daily_assignments.
 *
 * PlayerBattle is excluded (questions are drawn dynamically from cartridges
 * during gameplay). Check-in has no concept. Quiz and guess each get a single
 * concept for the day. PlayerFill gets fill_manager::num_words() concepts instead
 * — one crossword-style puzzle needs several peer terms, not one — handled
 * separately by assign_fill() rather than through CONCEPT_GAMES below.
 *
 * Idempotent: if an assignment already exists for today and a given gametype,
 * it is left unchanged.
 *
 * Wordle-style filter for PlayerGuess: only concepts whose term length falls
 * between wordle_minlen and wordle_maxlen (admin settings) are eligible, and
 * whose term is a single alphabetic word — the same rule guess_manager
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
    private const CONCEPT_GAMES = ['quiz', 'guess'];

    /**
     * Returns the human-readable task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_assign_daily_games', 'local_playergames');
    }

    /**
     * Assigns a concept to each concept-based game for today.
     *
     * @return void
     */
    public function execute(): void {
        global $DB;
        $today = mktime(0, 0, 0, (int) date('n'), (int) date('j'), (int) date('Y'));

        $activeids = $DB->get_fieldset_select(
            'local_playergames_cartridges',
            'id',
            'active = 1'
        );
        if (empty($activeids)) {
            mtrace('No active cartridges — skipping assignment.');
            return;
        }

        $minlen = (int) get_config('local_playergames', 'wordle_minlen') ?: 4;
        $maxlen = (int) get_config('local_playergames', 'wordle_maxlen') ?: 8;

        foreach (self::CONCEPT_GAMES as $gametype) {
            $alreadyassigned = $DB->record_exists(
                'local_playergames_daily_assignments',
                ['gamedate' => $today, 'gametype' => $gametype]
            );
            if ($alreadyassigned) {
                mtrace("Assignment already exists for {$gametype} today — skipping.");
                continue;
            }

            $conceptid = $this->pick_concept($activeids, $gametype, $minlen, $maxlen);
            if ($conceptid === null) {
                mtrace("No eligible concept found for {$gametype} — skipping.");
                continue;
            }

            $record           = new \stdClass();
            $record->gamedate = $today;
            $record->gametype = $gametype;
            $record->conceptid = $conceptid;
            $DB->insert_record('local_playergames_daily_assignments', $record);
            mtrace("Assigned concept {$conceptid} to {$gametype} for today.");
        }

        $this->assign_fill($activeids, $today);
    }

    /**
     * Assigns fill_manager::num_words() concepts to PlayerFill for today.
     *
     * Candidates are filtered the same way as PlayerGuess (letters-only after
     * normalization, within a configured length range — fill_minlen/fill_maxlen here
     * instead of wordle_minlen/wordle_maxlen), since every assigned term is tiled as a
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
            mtrace('Assignment already exists for fill today — skipping.');
            return;
        }

        $numwords = fill_manager::num_words();
        $minlen = (int) get_config('local_playergames', 'fill_minlen') ?: 3;
        $maxlen = (int) get_config('local_playergames', 'fill_maxlen') ?: 12;

        [$insql, $params] = $DB->get_in_or_equal($cartridgeids, SQL_PARAMS_NAMED, 'cid');
        $where = "cartridgeid {$insql}";
        $params['minlen'] = $minlen;
        $params['maxlen'] = $maxlen;
        $where .= ' AND ' . $DB->sql_length('term') . ' >= :minlen';
        $where .= ' AND ' . $DB->sql_length('term') . ' <= :maxlen';

        $rows = $DB->get_records_select('local_playergames_concepts', $where, $params, '', 'id, term');
        $ids = [];
        foreach ($rows as $row) {
            $normalized = guess_manager::normalize($row->term);
            if (guess_manager::is_valid_guess($normalized, core_text::strlen($normalized))) {
                $ids[] = (int) $row->id;
            }
        }

        if (count($ids) < $numwords) {
            mtrace('Not enough eligible concepts for fill — skipping.');
            return;
        }

        shuffle($ids);
        $chosen = array_slice($ids, 0, $numwords);

        $records = [];
        foreach ($chosen as $conceptid) {
            $records[] = (object) ['gamedate' => $today, 'gametype' => 'fill', 'conceptid' => $conceptid];
        }
        $DB->insert_records('local_playergames_daily_assignments', $records);
        mtrace('Assigned ' . count($chosen) . ' concepts to fill for today.');
    }

    /**
     * Picks a random eligible concept for a given game type.
     *
     * @param int[] $cartridgeids Active cartridge IDs.
     * @param string $gametype Game type to pick for.
     * @param int $minlen Minimum term length (used for 'guess').
     * @param int $maxlen Maximum term length (used for 'guess').
     * @return int|null Concept ID or null if none found.
     */
    private function pick_concept(array $cartridgeids, string $gametype, int $minlen, int $maxlen): ?int {
        global $DB;

        [$insql, $params] = $DB->get_in_or_equal($cartridgeids, SQL_PARAMS_NAMED, 'cid');
        $where = "cartridgeid {$insql}";

        if ($gametype === 'guess') {
            $params['minlen'] = $minlen;
            $params['maxlen'] = $maxlen;
            $where .= ' AND ' . $DB->sql_length('term') . ' >= :minlen';
            $where .= ' AND ' . $DB->sql_length('term') . ' <= :maxlen';
        }

        if ($gametype === 'guess') {
            // The SQL length filter above counts every character, but a guess
            // can only ever contain letters. Re-check each candidate in PHP so
            // a term with a space, digit or hyphen (still within the length
            // bounds) is never assigned.
            $rows = $DB->get_records_select('local_playergames_concepts', $where, $params, '', 'id, term');
            $ids = [];
            foreach ($rows as $row) {
                $normalized = guess_manager::normalize($row->term);
                if (guess_manager::is_valid_guess($normalized, core_text::strlen($normalized))) {
                    $ids[] = (int) $row->id;
                }
            }
        } else {
            $ids = $DB->get_fieldset_select('local_playergames_concepts', 'id', $where, $params);
        }

        if (empty($ids)) {
            return null;
        }

        return (int) $ids[array_rand($ids)];
    }
}
