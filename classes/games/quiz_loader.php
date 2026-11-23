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
 * Quiz question loader — draws the questions of the quiz cartridges.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\games;

/**
 * Loads quiz questions from the quiz cartridges and returns a shuffled session-sized slice of
 * normalised quiz_question DTOs.
 */
class quiz_loader {
    /**
     * Returns a shuffled set of quiz questions for one game session.
     *
     * @param int $sessionsize Number of questions to return.
     * @param string|null $cartridgeids JSON-encoded array of cartridge IDs; null = all active quiz cartridges.
     * @param array $exclude Map of source => int[] of sourceids to skip (already seen today).
     * @return quiz_question[]
     */
    public function load_session(int $sessionsize, ?string $cartridgeids = null, array $exclude = []): array {
        $pool = $this->load_from_cartridges($sessionsize, $cartridgeids, $exclude['cartridge'] ?? []);

        shuffle($pool);
        return array_slice($pool, 0, $sessionsize);
    }

    /**
     * Selects a random sample of rows from a candidate-id query.
     *
     * Moodle has no portable SQL random function, so the whole candidate pool
     * is fetched as ids only (cheap), shuffled in PHP and sliced before the
     * full rows are loaded. This samples the entire pool rather than always
     * returning the lowest-id rows, so repeated daily plays vary.
     *
     * @param string $idsql Query selecting a single id column from the pool.
     * @param array $params Named parameters for the id query.
     * @param string $table Table to load the full rows from, by id.
     * @param string $fields Fields to select from the table.
     * @param int $limit Session size; twice this many rows are sampled.
     * @param int[] $excludeids Sourceids to drop from the pool (already seen today).
     * @return \stdClass[] Rows keyed by id.
     */
    private function fetch_random_rows(
        string $idsql,
        array $params,
        string $table,
        string $fields,
        int $limit,
        array $excludeids = []
    ): array {
        global $DB;

        $allids = $DB->get_fieldset_sql($idsql, $params);
        if (empty($allids)) {
            return [];
        }

        if (!empty($excludeids)) {
            $fresh = array_diff(array_map('intval', $allids), array_map('intval', $excludeids));
            // Prefer unseen questions, but if the whole pool has been seen today
            // fall back to repeating it rather than blocking the play.
            if (!empty($fresh)) {
                $allids = $fresh;
            }
        }

        $allids = array_values($allids);
        shuffle($allids);
        $allids = array_slice($allids, 0, $limit * 2);

        [$insql, $inparams] = $DB->get_in_or_equal($allids, SQL_PARAMS_NAMED, 'pick');
        return $DB->get_records_select($table, "id $insql", $inparams, '', $fields);
    }

    /**
     * Loads questions from cartridges in the pool.
     *
     * When $cartridgeids is provided, only those cartridges are queried; an inactive one is skipped
     * even when the season kept it, so deactivating a cartridge in the library takes it out of the games.
     * When null, all cartridges with active=1 and type='quiz' are used.
     * Fetches double the limit to account for questions with incomplete answer
     * sets, then filters and slices.
     *
     * @param int $limit Maximum number of questions to return.
     * @param string|null $cartridgeids JSON-encoded array of cartridge IDs.
     * @param int[] $excludeids Question ids already seen today.
     * @return quiz_question[]
     */
    private function load_from_cartridges(int $limit, ?string $cartridgeids = null, array $excludeids = []): array {
        global $DB;

        if ($cartridgeids !== null) {
            $ids = json_decode($cartridgeids, true);
            if (empty($ids)) {
                return [];
            }
            [$insql, $params] = $DB->get_in_or_equal(
                array_map('intval', $ids),
                SQL_PARAMS_NAMED,
                'cid'
            );
            $sql = "SELECT cq.id
                      FROM {local_playergames_concept_questions} cq
                      JOIN {local_playergames_cartridges} c ON c.id = cq.cartridgeid
                     WHERE c.id $insql
                       AND c.active = 1
                       AND c.type = 'quiz'";
        } else {
            $sql = "SELECT cq.id
                      FROM {local_playergames_concept_questions} cq
                      JOIN {local_playergames_cartridges} c ON c.id = cq.cartridgeid
                     WHERE c.active = 1
                       AND c.type = 'quiz'";
            $params = [];
        }

        $rows = $this->fetch_random_rows(
            $sql,
            $params,
            'local_playergames_concept_questions',
            'id, questiontext, generalfeedback',
            $limit,
            $excludeids
        );
        if (empty($rows)) {
            return [];
        }

        $questionids = array_keys($rows);
        [$insql, $inparams] = $DB->get_in_or_equal($questionids, SQL_PARAMS_NAMED, 'qid');
        $answers = $DB->get_records_select(
            'local_playergames_concept_answers',
            "questionid $insql",
            $inparams
        );

        $answermap = [];
        foreach ($answers as $a) {
            $answermap[(int) $a->questionid][] = $a;
        }

        $result = [];
        foreach ($rows as $q) {
            $qid = (int) $q->id;
            $qanswers = $answermap[$qid] ?? [];

            $correct = array_values(array_filter($qanswers, fn($a): bool => (bool) $a->iscorrect));
            $wrong = array_values(array_filter($qanswers, fn($a): bool => !(bool) $a->iscorrect));

            if (empty($correct) || count($wrong) < 4) {
                continue;
            }

            shuffle($wrong);
            $selected = array_merge([$correct[0]], array_slice($wrong, 0, 4));
            shuffle($selected);

            $dto = new quiz_question();
            $dto->source = 'cartridge';
            $dto->sourceid = $qid;
            $dto->questiontext = $q->questiontext;
            $dto->generalfeedback = $q->generalfeedback !== null ? (string) $q->generalfeedback : null;
            $dto->answers = array_map(function ($a): quiz_answer {
                $ans = new quiz_answer();
                $ans->text = $a->answertext;
                $ans->correct = (bool) $a->iscorrect;
                return $ans;
            }, $selected);

            $result[] = $dto;
        }

        shuffle($result);
        return array_slice($result, 0, $limit);
    }
}
