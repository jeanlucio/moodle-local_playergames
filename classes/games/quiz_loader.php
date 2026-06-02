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
 * Quiz question loader — aggregates cartridge and question-bank sources.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\games;

/**
 * Loads quiz questions from one or both configured sources and returns a
 * shuffled session-sized slice of normalised quiz_question DTOs.
 */
class quiz_loader {
    /** @var string Draw from cartridge concept questions only. */
    const SOURCE_CARTRIDGES = 'cartridges';

    /** @var string Draw from the Moodle question bank only. */
    const SOURCE_QUESTIONBANK = 'questionbank';

    /** @var string Draw from both sources and merge. */
    const SOURCE_BOTH = 'both';

    /**
     * Returns a shuffled set of quiz questions for one game session.
     *
     * @param int $sessionsize Number of questions to return.
     * @param string $sources One of the SOURCE_* constants.
     * @param int $qbankcategoryid Moodle question category id; 0 = system context.
     * @return quiz_question[]
     */
    public function load_session(int $sessionsize, string $sources, int $qbankcategoryid = 0): array {
        $pool = [];

        if ($sources === self::SOURCE_CARTRIDGES || $sources === self::SOURCE_BOTH) {
            $pool = array_merge($pool, $this->load_from_cartridges($sessionsize));
        }

        if ($sources === self::SOURCE_QUESTIONBANK || $sources === self::SOURCE_BOTH) {
            $pool = array_merge($pool, $this->load_from_questionbank($sessionsize, $qbankcategoryid));
        }

        shuffle($pool);
        return array_slice($pool, 0, $sessionsize);
    }

    /**
     * Loads questions from active cartridges in random order.
     *
     * Fetches double the limit to account for questions with incomplete answer
     * sets, then filters and slices.
     *
     * @param int $limit Maximum number of questions to return.
     * @return quiz_question[]
     */
    private function load_from_cartridges(int $limit): array {
        global $DB;

        $sql = "SELECT cq.id, cq.questiontext
                  FROM {local_playergames_concept_questions} cq
                  JOIN {local_playergames_cartridges} c ON c.id = cq.cartridgeid
                 WHERE c.active = 1
                   AND c.type = 'quiz'
              ORDER BY " . $DB->sql_random();

        $rows = $DB->get_records_sql($sql, [], 0, $limit * 2);
        if (empty($rows)) {
            return [];
        }

        $questionids = array_keys($rows);
        [$insql, $inparams] = $DB->get_in_or_equal($questionids, SQL_PARAMS_NAMED);
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

    /**
     * Loads multiple-choice questions from the Moodle question bank.
     *
     * When categoryid is 0, all multichoice questions in the system context are used.
     * Questions with fewer than 4 wrong answers are skipped.
     *
     * @param int $limit Maximum number of questions to return.
     * @param int $categoryid Moodle question category id; 0 = system context.
     * @return quiz_question[]
     */
    private function load_from_questionbank(int $limit, int $categoryid): array {
        global $DB;

        if ($categoryid > 0) {
            $sql = "SELECT q.id, q.questiontext
                      FROM {question} q
                     WHERE q.category = :catid
                       AND q.qtype = 'multichoice'
                       AND q.hidden = 0
                       AND q.parent = 0
                  ORDER BY " . $DB->sql_random();
            $params = ['catid' => $categoryid];
        } else {
            $systemctxid = \context_system::instance()->id;
            $sql = "SELECT q.id, q.questiontext
                      FROM {question} q
                      JOIN {question_categories} qc ON qc.id = q.category
                     WHERE qc.contextid = :ctxid
                       AND q.qtype = 'multichoice'
                       AND q.hidden = 0
                       AND q.parent = 0
                  ORDER BY " . $DB->sql_random();
            $params = ['ctxid' => $systemctxid];
        }

        $rows = $DB->get_records_sql($sql, $params, 0, $limit * 2);
        if (empty($rows)) {
            return [];
        }

        $questionids = array_keys($rows);
        [$insql, $inparams] = $DB->get_in_or_equal($questionids, SQL_PARAMS_NAMED);
        $answers = $DB->get_records_select(
            'question_answers',
            "question $insql",
            $inparams
        );

        $answermap = [];
        foreach ($answers as $a) {
            $answermap[(int) $a->question][] = $a;
        }

        $result = [];
        foreach ($rows as $q) {
            $qid = (int) $q->id;
            $qanswers = $answermap[$qid] ?? [];

            $correct = array_values(
                array_filter($qanswers, fn($a): bool => (float) $a->fraction > 0)
            );
            $wrong = array_values(
                array_filter($qanswers, fn($a): bool => (float) $a->fraction <= 0)
            );

            if (empty($correct) || count($wrong) < 4) {
                continue;
            }

            shuffle($wrong);
            $selected = array_merge([$correct[0]], array_slice($wrong, 0, 4));
            shuffle($selected);

            $dto = new quiz_question();
            $dto->source = 'questionbank';
            $dto->sourceid = $qid;
            $dto->questiontext = $q->questiontext;
            $dto->answers = array_map(function ($a): quiz_answer {
                $ans = new quiz_answer();
                $ans->text = $a->answertext;
                $ans->correct = (float) $a->fraction > 0;
                return $ans;
            }, $selected);

            $result[] = $dto;
        }

        shuffle($result);
        return array_slice($result, 0, $limit);
    }
}
