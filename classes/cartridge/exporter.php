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
 * Concept and quiz cartridge exporter.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\cartridge;

/**
 * Builds the JSON-serialisable export payload for a cartridge.
 *
 * The payload mirrors the importer's expected schema so concept and quiz
 * cartridges round-trip through export and re-import.
 */
class exporter {
    /**
     * Builds the export payload for a cartridge, dispatching by type.
     *
     * @param \stdClass $cartridge The cartridge record to export.
     * @return array Associative array ready for JSON encoding.
     */
    public function build(\stdClass $cartridge): array {
        if (($cartridge->type ?? 'concept') === 'quiz') {
            return $this->build_quiz($cartridge);
        }
        return $this->build_concept($cartridge);
    }

    /**
     * Builds the export payload for a concept-type cartridge.
     *
     * @param \stdClass $cartridge The cartridge record.
     * @return array Associative array ready for JSON encoding.
     */
    public function build_concept(\stdClass $cartridge): array {
        global $DB;

        $concepts = $DB->get_records(
            'local_playergames_concepts',
            ['cartridgeid' => (int) $cartridge->id],
            'id ASC'
        );

        $catmap = [];
        $catids = array_filter(array_column((array) $concepts, 'categoryid'));
        if (!empty($catids)) {
            [$insql, $inparams] = $DB->get_in_or_equal($catids);
            $catrows = $DB->get_records_sql(
                "SELECT id, name FROM {local_playergames_categories} WHERE id {$insql}",
                $inparams
            );
            foreach ($catrows as $cat) {
                $catmap[(int) $cat->id] = $cat->name;
            }
        }

        $conceptsdata = [];
        foreach ($concepts as $c) {
            $conceptsdata[] = [
                'term' => $c->term,
                'definition' => $c->definition,
                'category' => $catmap[(int) $c->categoryid] ?? '',
                'difficulty' => (int) $c->difficulty,
            ];
        }

        return [
            'name' => $cartridge->name,
            'version' => $cartridge->version,
            'language' => $cartridge->language,
            'author' => $cartridge->author,
            'type' => 'concept',
            'concepts' => $conceptsdata,
        ];
    }

    /**
     * Builds the export payload for a quiz-type cartridge.
     * Answers are loaded in bulk to avoid per-question queries.
     *
     * @param \stdClass $cartridge The cartridge record.
     * @return array Associative array ready for JSON encoding.
     */
    public function build_quiz(\stdClass $cartridge): array {
        global $DB;

        $questions = $DB->get_records(
            'local_playergames_concept_questions',
            ['cartridgeid' => (int) $cartridge->id],
            'id ASC'
        );

        $answersbyquestion = [];
        if (!empty($questions)) {
            [$insql, $inparams] = $DB->get_in_or_equal(array_keys($questions));
            $answers = $DB->get_records_sql(
                "SELECT * FROM {local_playergames_concept_answers}
                  WHERE questionid {$insql}
                  ORDER BY sortorder ASC",
                $inparams
            );
            foreach ($answers as $ans) {
                $answersbyquestion[(int) $ans->questionid][] = $ans;
            }
        }

        $questionsdata = [];
        foreach ($questions as $q) {
            $correct = '';
            $distractors = [];
            foreach ($answersbyquestion[(int) $q->id] ?? [] as $ans) {
                if ($ans->iscorrect) {
                    $correct = $ans->answertext;
                } else {
                    $distractors[] = $ans->answertext;
                }
            }
            $questionsdata[] = [
                'questiontext' => $q->questiontext,
                'correct' => $correct,
                'distractors' => $distractors,
            ];
        }

        return [
            'name' => $cartridge->name,
            'version' => $cartridge->version,
            'language' => $cartridge->language,
            'author' => $cartridge->author,
            'type' => 'quiz',
            'questions' => $questionsdata,
        ];
    }
}
