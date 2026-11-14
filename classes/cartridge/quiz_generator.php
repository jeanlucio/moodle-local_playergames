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
 * AI-powered quiz question generator for concept cartridges.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\cartridge;

/**
 * Generates multiple-choice quiz questions using AI: full MCQs (question, one correct answer and
 * four distractors) for quiz-type cartridges, where the answers are entirely AI-produced.
 */
class quiz_generator extends ai_generator {
    /**
     * Generates standalone MCQ previews from a topic without saving to the database.
     *
     * @param string $topic Topic to generate questions about.
     * @param string $language Language for question and answer text.
     * @param int $quantity Number of questions to generate.
     * @param int $difficulty Target average difficulty (1–5).
     * @param array $categorynames Optional list of category names the AI must use.
     * @param string $context Optional reference text to focus the questions.
     * @return array Non-empty array of arrays with keys: questiontext, correct, distractors[4], category, difficulty.
     * @throws \moodle_exception If no AI source is available, the request fails (with the provider's
     *     message) or the answer holds no usable question.
     */
    public function generate_preview(
        string $topic,
        string $language,
        int $quantity,
        int $difficulty = 3,
        array $categorynames = [],
        string $context = ''
    ): array {
        $prompt = $this->build_standalone_prompt(
            $topic,
            $language,
            $quantity,
            $difficulty,
            $categorynames,
            $context
        );
        $description = get_string('cartridge_ai_usage_quiz', 'local_playergames', $topic);
        $result = $this->call_api('', $prompt, true, $description);
        if (!$result['success']) {
            if (empty($result['message'])) {
                throw new \moodle_exception('error_no_ai_key', 'local_playergames');
            }
            throw new \moodle_exception('error_ai_request_failed', 'local_playergames', '', $result['message']);
        }

        $questions = $this->parse_standalone_response($result['data']);
        if ($questions === []) {
            throw new \moodle_exception('error_ai_no_questions', 'local_playergames');
        }
        return $questions;
    }

    /**
     * Saves a set of standalone MCQs to a quiz-type cartridge.
     *
     * Replaces any existing AI questions for the cartridge before inserting.
     *
     * @param int $cartridgeid Target quiz cartridge.
     * @param array $questions Array from generate_preview(): questiontext, correct, distractors[], category, difficulty.
     * @return int Number of questions saved.
     */
    public function save_standalone(int $cartridgeid, array $questions): int {
        global $DB;

        $existing = $DB->get_fieldset_select(
            'local_playergames_concept_questions',
            'id',
            'cartridgeid = :cid',
            ['cid' => $cartridgeid]
        );
        if (!empty($existing)) {
            [$insql, $inparams] = $DB->get_in_or_equal($existing, SQL_PARAMS_NAMED);
            $DB->delete_records_select('local_playergames_concept_answers', "questionid $insql", $inparams);
            $DB->delete_records_select('local_playergames_concept_questions', "id $insql", $inparams);
        }

        $catmgr = new category_manager();
        $catmap = [];
        $total = 0;
        $now = time();
        foreach ($questions as $qdata) {
            if (!is_array($qdata)) {
                continue;
            }
            $qtext = payload::text($qdata['questiontext'] ?? null);
            $correct = payload::text($qdata['correct'] ?? null);
            $distractors = payload::texts($qdata['distractors'] ?? null);
            if ($qtext === '' || $correct === '' || count($distractors) < 4) {
                continue;
            }

            $catname = payload::text($qdata['category'] ?? null);
            $categoryid = null;
            if ($catname !== '') {
                if (!array_key_exists($catname, $catmap)) {
                    $catmap[$catname] = $catmgr->ensure_category($cartridgeid, $catname);
                }
                $categoryid = $catmap[$catname] ?: null;
            }

            $feedback = payload::text($qdata['generalfeedback'] ?? null);

            $qrecord = new \stdClass();
            $qrecord->conceptid = null;
            $qrecord->cartridgeid = $cartridgeid;
            $qrecord->questiontext = $qtext;
            $qrecord->source = 'ai';
            $qrecord->difficulty = payload::number($qdata['difficulty'] ?? null, 1, 5, 3);
            $qrecord->categoryid = $categoryid;
            $qrecord->generalfeedback = $feedback !== '' ? $feedback : null;
            $qrecord->timecreated = $now;
            $questionid = (int) $DB->insert_record('local_playergames_concept_questions', $qrecord);

            $correctrec = new \stdClass();
            $correctrec->questionid = $questionid;
            $correctrec->answertext = $correct;
            $correctrec->iscorrect = 1;
            $correctrec->sortorder = 0;
            $DB->insert_record('local_playergames_concept_answers', $correctrec, false);

            foreach (array_slice($distractors, 0, 4) as $i => $dtext) {
                $dist = new \stdClass();
                $dist->questionid = $questionid;
                $dist->answertext = $dtext;
                $dist->iscorrect = 0;
                $dist->sortorder = $i + 1;
                $DB->insert_record('local_playergames_concept_answers', $dist, false);
            }

            $total++;
        }

        return $total;
    }

    /**
     * Builds a prompt for generating standalone MCQs about a topic.
     *
     * @param string $topic Subject matter.
     * @param string $language Target language.
     * @param int $quantity Number of questions to request.
     * @param int $difficulty Target average difficulty (1–5).
     * @param array $categorynames Optional list of category names the AI must use verbatim.
     * @param string $context Optional reference text to focus the questions.
     * @return string Prompt text.
     */
    protected function build_standalone_prompt(
        string $topic,
        string $language,
        int $quantity,
        int $difficulty = 3,
        array $categorynames = [],
        string $context = ''
    ): string {
        $langname = $language !== '' ? $language : 'English';
        $qty = max(1, $quantity);
        $example = '{"questions":['
            . '{"questiontext":"Who invented the telephone?",'
            . '"correct":"Alexander Graham Bell",'
            . '"distractors":["Thomas Edison","Nikola Tesla","Guglielmo Marconi","James Watt"],'
            . '"category":"History","difficulty":2,'
            . '"generalfeedback":"Bell patented the first practical telephone in 1876."},'
            . '{"questiontext":"In what year did World War II end?",'
            . '"correct":"1945",'
            . '"distractors":["1939","1941","1943","1918"],'
            . '"category":"History","difficulty":3,'
            . '"generalfeedback":"The war ended in 1945 with the surrender of Germany and Japan."}]}';

        if (!empty($categorynames)) {
            $catlist = '"' . implode('", "', $categorynames) . '"';
            $categoryrule = "- category: MUST be exactly one of these values (verbatim): {$catlist}";
        } else {
            $categoryrule = '- category: a broad subject-area label in ' . $langname
                . ' identifying the field of knowledge. Use at most 3 distinct categories'
                . ' for the whole set. The value MUST be written in ' . $langname . '.';
        }

        $parts = [
            "You are an educator creating multiple-choice quiz questions about: {$topic}.",
            "Generate exactly {$qty} questions.",
            "Target average difficulty: {$difficulty} out of 5 (1 = very easy, 5 = very hard).",
            "Write all text in language: {$langname}.",
        ];

        if ($context !== '') {
            $parts[] = 'The following reference text provides specific details about this topic.'
                . ' Use it to generate targeted questions rather than generic ones:';
            $parts[] = '---' . "\n" . $context . "\n" . '---';
        }

        $parts = array_merge($parts, [
            '',
            'For each question provide:',
            '- questiontext: a concise question stem (max 20 words).',
            '- correct: exactly ONE correct answer — use the same style as the distractors.',
            '- distractors: exactly FOUR plausible but wrong answers.',
            $categoryrule,
            '- difficulty: integer 1–5 reflecting how hard the question is.',
            '- generalfeedback: one short sentence (max 25 words) explaining why the correct'
                . ' answer is right, shown to the learner after they answer. Write it in '
                . $langname . '.',
            '',
            'CRITICAL RULE — format consistency:',
            '- The correct answer and ALL four distractors MUST have the same format and'
                . ' approximate length.',
            '- If the correct answer is a name (e.g. "Pedro Álvares Cabral"),'
                . ' all distractors must also be names.',
            '- If the correct answer is a date or year (e.g. "1822"), all distractors must'
                . ' also be dates or years.',
            '- If the correct answer is a short phrase (e.g. "Rio de Janeiro"),'
                . ' all distractors must also be short phrases of the same type.',
            '- NEVER mix short answers with full sentences. A student must not be able to'
                . ' identify the correct answer by its format alone.',
            '',
            'Rules for distractors:',
            '- Must be plausible to a student who does not know the topic'
                . ' but clearly wrong to someone who does.',
            '- Do NOT use the words from the correct answer inside a distractor.',
            '',
            'IMPORTANT: Reply ONLY with valid JSON matching this exact format — no code fences:',
            $example,
        ]);

        return implode("\n", $parts);
    }

    /**
     * Parses the AI response for standalone MCQs.
     *
     * @param string $json Raw response from the AI provider.
     * @return array Array of arrays with keys: questiontext, correct, distractors[4], category, difficulty.
     */
    protected function parse_standalone_response(string $json): array {
        $json = preg_replace("/^\x60{3}(?:json)?\s*/i", '', trim($json));
        $json = preg_replace("/\s*\x60{3}\$/m", '', $json);

        $data = json_decode($json, true);
        if (!is_array($data) || !isset($data['questions']) || !is_array($data['questions'])) {
            return [];
        }

        $result = [];
        foreach ($data['questions'] as $q) {
            if (!is_array($q) || !isset($q['questiontext'], $q['correct'], $q['distractors'])) {
                continue;
            }
            $distractors = payload::texts($q['distractors']);
            if (!is_array($q['distractors']) || count($distractors) < 4) {
                continue;
            }
            $result[] = [
                'questiontext'    => payload::text($q['questiontext']),
                'correct'         => payload::text($q['correct']),
                'distractors'     => array_slice($distractors, 0, 4),
                'category'        => payload::text($q['category'] ?? null),
                'difficulty'      => payload::number($q['difficulty'] ?? null, 1, 5, 3),
                'generalfeedback' => payload::text($q['generalfeedback'] ?? null),
            ];
        }

        return $result;
    }
}
