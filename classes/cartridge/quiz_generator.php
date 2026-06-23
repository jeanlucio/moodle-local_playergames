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
 * Generates multiple-choice quiz questions using AI.
 *
 * Supports two modes:
 *  - Standalone: generates full MCQs (question + 1 correct + 4 distractors) for
 *    quiz-type cartridges, where answers are entirely AI-produced.
 *  - Concept-derived: generates question stems and distractors for concept-type
 *    cartridges; the correct answer comes from the concept definition already in DB.
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
     * @return array Array of arrays with keys: questiontext, correct, distractors[4], category, difficulty.
     * @throws \moodle_exception If no AI key is available.
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
        $result = $this->call_api('', $prompt, true);
        if (!$result['success']) {
            return [];
        }
        $questions = $this->parse_standalone_response($result['data']);
        $this->log_usage($result['provider'] ?? '', $result['model'] ?? '', $topic, count($questions));
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
            $qtext = trim((string) ($qdata['questiontext'] ?? ''));
            $correct = trim((string) ($qdata['correct'] ?? ''));
            $distractors = array_map('trim', (array) ($qdata['distractors'] ?? []));
            if ($qtext === '' || $correct === '' || count($distractors) < 4) {
                continue;
            }

            $catname = trim((string) ($qdata['category'] ?? ''));
            $categoryid = null;
            if ($catname !== '') {
                if (!array_key_exists($catname, $catmap)) {
                    $catmap[$catname] = $catmgr->ensure_category($cartridgeid, $catname);
                }
                $categoryid = $catmap[$catname] ?: null;
            }

            $feedback = trim((string) ($qdata['generalfeedback'] ?? ''));

            $qrecord = new \stdClass();
            $qrecord->conceptid = null;
            $qrecord->cartridgeid = $cartridgeid;
            $qrecord->questiontext = $qtext;
            $qrecord->source = 'ai';
            $qrecord->difficulty = max(1, min(5, (int) ($qdata['difficulty'] ?? 3)));
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
            if (!isset($q['questiontext'], $q['correct'], $q['distractors'])) {
                continue;
            }
            if (!is_array($q['distractors']) || count($q['distractors']) < 4) {
                continue;
            }
            $result[] = [
                'questiontext'    => trim((string) $q['questiontext']),
                'correct'         => trim((string) $q['correct']),
                'distractors'     => array_map('trim', array_slice($q['distractors'], 0, 4)),
                'category'        => trim((string) ($q['category'] ?? '')),
                'difficulty'      => max(1, min(5, (int) ($q['difficulty'] ?? 3))),
                'generalfeedback' => trim((string) ($q['generalfeedback'] ?? '')),
            ];
        }

        return $result;
    }
    /**
     * Generates and persists quiz questions for every concept in a cartridge.
     *
     * Existing AI-generated questions for the cartridge are replaced. Concepts
     * are processed in batches of 10 to keep prompts within provider limits.
     *
     * @param int $cartridgeid Target cartridge.
     * @return int Number of questions successfully saved.
     * @throws \moodle_exception If no AI key is available.
     */
    public function generate_for_cartridge(int $cartridgeid): int {
        global $DB;

        $cartridge = $DB->get_record(
            'local_playergames_cartridges',
            ['id' => $cartridgeid],
            '*',
            MUST_EXIST
        );
        $language = $cartridge->language ?: 'English';

        $concepts = $DB->get_records('local_playergames_concepts', ['cartridgeid' => $cartridgeid]);
        if (empty($concepts)) {
            return 0;
        }

        // Remove all previous AI-generated questions so the set stays consistent.
        $existingids = $DB->get_fieldset_select(
            'local_playergames_concept_questions',
            'id',
            'cartridgeid = :cid AND source = :src',
            ['cid' => $cartridgeid, 'src' => 'ai']
        );
        if (!empty($existingids)) {
            [$insql, $inparams] = $DB->get_in_or_equal($existingids, SQL_PARAMS_NAMED);
            $DB->delete_records_select('local_playergames_concept_answers', "questionid $insql", $inparams);
            $DB->delete_records_select(
                'local_playergames_concept_questions',
                "id $insql",
                $inparams
            );
        }

        $batches = array_chunk(array_values($concepts), 10);
        $total = 0;

        foreach ($batches as $batch) {
            $batchbyid = [];
            foreach ($batch as $c) {
                $batchbyid[(int) $c->id] = $c;
            }

            $prompt = $this->build_quiz_prompt($batch, $language);
            $result = $this->call_api('', $prompt, true);

            if (!$result['success']) {
                continue;
            }

            $questions = $this->parse_quiz_response($result['data'], $batchbyid);

            foreach ($questions as $qdata) {
                $concept = $batchbyid[$qdata['conceptid']] ?? null;
                if ($concept === null) {
                    continue;
                }

                $qrecord = new \stdClass();
                $qrecord->conceptid = $qdata['conceptid'];
                $qrecord->cartridgeid = $cartridgeid;
                $qrecord->questiontext = $qdata['questiontext'];
                $qrecord->source = 'ai';
                // Concept-derived questions inherit the source concept's metadata.
                $qrecord->difficulty = max(1, min(5, (int) $concept->difficulty));
                $qrecord->categoryid = isset($concept->categoryid) ? (int) $concept->categoryid : null;
                $qrecord->timecreated = time();
                $questionid = (int) $DB->insert_record(
                    'local_playergames_concept_questions',
                    $qrecord
                );

                // Correct answer: the concept's own definition (not repeated in AI response).
                $correct = new \stdClass();
                $correct->questionid = $questionid;
                $correct->answertext = $concept->definition;
                $correct->iscorrect = 1;
                $correct->sortorder = 0;
                $DB->insert_record('local_playergames_concept_answers', $correct, false);

                // Four AI-generated distractors.
                foreach ($qdata['distractors'] as $i => $dtext) {
                    $distractor = new \stdClass();
                    $distractor->questionid = $questionid;
                    $distractor->answertext = $dtext;
                    $distractor->iscorrect = 0;
                    $distractor->sortorder = $i + 1;
                    $DB->insert_record('local_playergames_concept_answers', $distractor, false);
                }

                $total++;
            }
        }

        return $total;
    }

    /**
     * Builds the prompt asking the AI for question stems and distractors.
     *
     * The AI is NOT asked to reproduce the correct answer — that comes from the
     * concept's definition stored in the database.
     *
     * @param array $concepts Array of concept stdClass objects (term, definition).
     * @param string $language Target language name (e.g. 'Portuguese', 'English').
     * @return string Prompt text.
     */
    protected function build_quiz_prompt(array $concepts, string $language): string {
        $langname = $language !== '' ? $language : 'English';

        $conceptlist = [];
        foreach ($concepts as $concept) {
            $conceptlist[] = [
                'id' => (int) $concept->id,
                'term' => $concept->term,
                'definition' => $concept->definition,
            ];
        }

        $conceptjson = json_encode($conceptlist, JSON_UNESCAPED_UNICODE);
        $examplejson = '{"questions":['
            . '{"conceptid":1,"questiontext":"What is photosynthesis?",'
            . '"distractors":["The process of cellular respiration that converts glucose into ATP",'
            . '"A type of cell division that produces two identical daughter cells",'
            . '"The breakdown of proteins into amino acids by digestive enzymes",'
            . '"A method by which roots absorb water from the surrounding soil"]}]}';

        $parts = [
            'You are an educator creating multiple-choice quiz questions from a set of educational concepts.',
            "For EACH concept below, write exactly ONE question stem and exactly FOUR plausible but wrong distractors.",
            "Write all text in language: {$langname}.",
            '',
            'Rules for distractors:',
            '- Each distractor must be a complete sentence that sounds like a valid definition.',
            '- Distractors must be plausible to a student who does not know the topic'
                . ' but clearly wrong to someone who does.',
            '- Do NOT copy key words or phrases from the correct definition into any distractor.',
            '- Vary the length and sentence structure across the four distractors.',
            '- Never use meta-phrases such as "incorrect definition" or "not the right answer".',
            '',
            'Rules for question stems:',
            '- Vary the phrasing across concepts: use forms such as'
                . ' "What is...?", "Which concept...?", "Define...", "In the context of X, what is Y?".',
            '- Keep the stem concise (maximum 20 words).',
            '',
            'IMPORTANT: Reply ONLY with valid JSON matching this format exactly — no extra text, no code fences:',
            $examplejson,
            '',
            'Concepts:',
            $conceptjson,
        ];

        return implode("\n", $parts);
    }

    /**
     * Parses the JSON response from the AI into structured question data.
     *
     * @param string $json Raw response string from the AI provider.
     * @param array $concepts Concepts indexed by id (used to validate conceptids).
     * @return array Array of arrays with keys: conceptid, questiontext, distractors[].
     */
    protected function parse_quiz_response(string $json, array $concepts): array {
        $json = preg_replace("/^\x60{3}(?:json)?\s*/i", '', trim($json));
        $json = preg_replace("/\s*\x60{3}\$/m", '', $json);

        $data = json_decode($json, true);
        if (!is_array($data) || !isset($data['questions']) || !is_array($data['questions'])) {
            return [];
        }

        $result = [];
        foreach ($data['questions'] as $q) {
            if (!isset($q['conceptid'], $q['questiontext'], $q['distractors'])) {
                continue;
            }
            $conceptid = (int) $q['conceptid'];
            if (!isset($concepts[$conceptid])) {
                continue;
            }
            if (!is_array($q['distractors']) || count($q['distractors']) < 4) {
                continue;
            }
            $result[] = [
                'conceptid' => $conceptid,
                'questiontext' => trim((string) $q['questiontext']),
                'distractors' => array_map('trim', array_slice($q['distractors'], 0, 4)),
            ];
        }

        return $result;
    }
}
