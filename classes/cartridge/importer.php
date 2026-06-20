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
 * Concept cartridge importer.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\cartridge;

/**
 * Validates and imports a concept cartridge from a JSON payload.
 */
class importer {
    /** @var category_manager Lazy-initialised category manager instance. */
    private ?category_manager $catmgr = null;
    /** @var int Maximum number of characters allowed in a term. */
    const MAX_TERM_LENGTH = 255;

    /** @var int Maximum number of characters allowed in a definition. */
    const MAX_DEFINITION_LENGTH = 1000;

    /** @var int Maximum number of concepts per cartridge. */
    const MAX_CONCEPTS = 2000;

    /** @var int Maximum number of questions per quiz cartridge. */
    const MAX_QUESTIONS = 2000;

    /**
     * Parses a JSON string, validates its schema, and persists the cartridge to the database.
     *
     * @param string $jsonstring Raw JSON payload from the uploaded file.
     * @param int $uploaderid Moodle user ID who initiated the import.
     * @return \stdClass Result with properties: cartridgeid, count, categories, language.
     * @throws \moodle_exception On validation or schema failure.
     */
    public function import(string $jsonstring, int $uploaderid): \stdClass {
        global $DB;

        $data = json_decode($jsonstring, true);
        if ($data === null) {
            throw new \moodle_exception('error_cartridge_invalid_json', 'local_playergames');
        }

        // A quiz payload is flagged by its type, or inferred from a questions array
        // when there are no concepts.
        $isquiz = (($data['type'] ?? '') === 'quiz')
            || (empty($data['concepts']) && !empty($data['questions']));

        if ($isquiz) {
            $this->validate_quiz_schema($data);
            $cartridge = $this->build_cartridge_record($data, $uploaderid, 'quiz');
            $cartridgeid = $DB->insert_record('local_playergames_cartridges', $cartridge);
            return $this->save_questions($cartridgeid, $data['questions']);
        }

        $this->validate_schema($data);
        $cartridge = $this->build_cartridge_record($data, $uploaderid, 'concept');
        $cartridgeid = $DB->insert_record('local_playergames_cartridges', $cartridge);
        return $this->save_concepts($cartridgeid, $data['concepts']);
    }

    /**
     * Builds the cartridge stdClass shared by concept and quiz imports.
     *
     * @param array $data Decoded cartridge associative array.
     * @param int $uploaderid Moodle user ID who initiated the import.
     * @param string $type Cartridge type: 'concept' or 'quiz'.
     * @return \stdClass Cartridge record ready for DB insertion.
     */
    private function build_cartridge_record(array $data, int $uploaderid, string $type): \stdClass {
        $cartridge = new \stdClass();
        $cartridge->name = \core_text::substr(
            clean_param($data['name'], PARAM_TEXT),
            0,
            255
        );
        $cartridge->version = \core_text::substr(
            clean_param($data['version'] ?? '1.0', PARAM_TEXT),
            0,
            20
        );
        $cartridge->language = \core_text::substr(
            clean_param($data['language'] ?? '', PARAM_TEXT),
            0,
            20
        );
        $cartridge->type = $type;
        $now = time();
        $cartridge->timecreated = $now;
        $cartridge->timemodified = $now;
        $cartridge->uploadedby = $uploaderid;
        $cartridge->author = isset($data['author'])
            ? \core_text::substr(clean_param($data['author'], PARAM_TEXT), 0, 255)
            : null;
        $cartridge->active = 1;
        return $cartridge;
    }

    /**
     * Saves a pre-validated array of raw concept arrays to an existing cartridge.
     *
     * @param int $cartridgeid Target cartridge ID.
     * @param array $concepts Array of raw concept arrays (term, definition, category, difficulty).
     * @return \stdClass Result with properties: cartridgeid, count, categories, language.
     */
    public function save_concepts(int $cartridgeid, array $concepts): \stdClass {
        global $DB;

        if ($this->catmgr === null) {
            $this->catmgr = new category_manager();
        }

        // Pre-resolve all unique category names to IDs (avoids N+1 per-concept queries).
        $catmap = [];
        foreach ($concepts as $raw) {
            $name = trim(clean_param($raw['category'] ?? '', PARAM_TEXT));
            if ($name !== '' && !array_key_exists($name, $catmap)) {
                $catmap[$name] = $this->catmgr->ensure_category($cartridgeid, $name);
            }
        }

        $cartridge = $DB->get_record('local_playergames_cartridges', ['id' => $cartridgeid]);
        $count = 0;

        foreach ($concepts as $raw) {
            $name = trim(clean_param($raw['category'] ?? '', PARAM_TEXT));
            $raw['categoryid'] = ($name !== '' && isset($catmap[$name])) ? $catmap[$name] : null;
            $concept = $this->sanitize_concept($raw, $cartridgeid);
            if ($concept->term === '') {
                continue;
            }
            $DB->insert_record('local_playergames_concepts', $concept);
            $count++;
        }

        $result = new \stdClass();
        $result->cartridgeid = $cartridgeid;
        $result->count = $count;
        $result->categories = !empty($catmap) ? implode(', ', array_keys($catmap)) : '—';
        $result->language = $cartridge ? $cartridge->language : '';
        $result->type = 'concept';
        return $result;
    }

    /**
     * Saves a pre-validated array of raw question arrays to an existing quiz cartridge.
     *
     * @param int $cartridgeid Target cartridge ID.
     * @param array $questions Array of raw question arrays (questiontext, correct, distractors).
     * @return \stdClass Result with properties: cartridgeid, count, categories, language, type.
     */
    public function save_questions(int $cartridgeid, array $questions): \stdClass {
        global $DB;

        if ($this->catmgr === null) {
            $this->catmgr = new category_manager();
        }

        $cartridge = $DB->get_record('local_playergames_cartridges', ['id' => $cartridgeid]);
        $catmap = [];
        $count = 0;
        $now = time();

        foreach ($questions as $raw) {
            $qtext = trim(clean_param($raw['questiontext'] ?? '', PARAM_TEXT));
            $correct = trim(clean_param($raw['correct'] ?? '', PARAM_TEXT));
            if ($qtext === '' || $correct === '') {
                continue;
            }
            $distractors = [];
            foreach ((array) ($raw['distractors'] ?? []) as $d) {
                $distractors[] = trim(clean_param($d, PARAM_TEXT));
            }

            $catname = trim(clean_param($raw['category'] ?? '', PARAM_TEXT));
            $categoryid = null;
            if ($catname !== '') {
                if (!array_key_exists($catname, $catmap)) {
                    $catmap[$catname] = $this->catmgr->ensure_category($cartridgeid, $catname);
                }
                $categoryid = $catmap[$catname] ?: null;
            }

            $qrecord = new \stdClass();
            $qrecord->conceptid = null;
            $qrecord->cartridgeid = $cartridgeid;
            $qrecord->questiontext = $qtext;
            $qrecord->source = 'import';
            $qrecord->difficulty = max(1, min(5, (int) ($raw['difficulty'] ?? 3)));
            $qrecord->categoryid = $categoryid;
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

            $count++;
        }

        $result = new \stdClass();
        $result->cartridgeid = $cartridgeid;
        $result->count = $count;
        $result->categories = '—';
        $result->language = $cartridge ? $cartridge->language : '';
        $result->type = 'quiz';
        return $result;
    }

    /**
     * Validates the required fields and structure of decoded cartridge data.
     *
     * @param array $data Decoded cartridge associative array.
     * @throws \moodle_exception On schema violation.
     */
    protected function validate_schema(array $data): void {
        if (empty($data['name'])) {
            throw new \moodle_exception(
                'error_cartridge_missing_field',
                'local_playergames',
                '',
                'name'
            );
        }
        if (empty($data['concepts']) || !is_array($data['concepts'])) {
            throw new \moodle_exception('error_cartridge_no_concepts', 'local_playergames');
        }
        if (count($data['concepts']) > self::MAX_CONCEPTS) {
            throw new \moodle_exception(
                'error_cartridge_tooconcepts',
                'local_playergames',
                '',
                self::MAX_CONCEPTS
            );
        }
        foreach ($data['concepts'] as $concept) {
            if (empty($concept['term'])) {
                throw new \moodle_exception('error_concept_empty_term', 'local_playergames');
            }
            if (empty($concept['definition'])) {
                throw new \moodle_exception('error_concept_empty_definition', 'local_playergames');
            }
        }
    }

    /**
     * Validates the required fields and structure of a decoded quiz cartridge.
     *
     * @param array $data Decoded cartridge associative array.
     * @throws \moodle_exception On schema violation.
     */
    protected function validate_quiz_schema(array $data): void {
        if (empty($data['name'])) {
            throw new \moodle_exception(
                'error_cartridge_missing_field',
                'local_playergames',
                '',
                'name'
            );
        }
        if (empty($data['questions']) || !is_array($data['questions'])) {
            throw new \moodle_exception('error_cartridge_no_questions', 'local_playergames');
        }
        if (count($data['questions']) > self::MAX_QUESTIONS) {
            throw new \moodle_exception(
                'error_cartridge_tooquestions',
                'local_playergames',
                '',
                self::MAX_QUESTIONS
            );
        }
        foreach ($data['questions'] as $question) {
            if (empty($question['questiontext'])) {
                throw new \moodle_exception('error_question_empty_text', 'local_playergames');
            }
            if (empty($question['correct'])) {
                throw new \moodle_exception('error_question_empty_correct', 'local_playergames');
            }
        }
    }

    /**
     * Sanitizes and normalises a raw concept array into a DB-ready stdClass.
     *
     * @param array $raw Raw concept data (may come from JSON or POST).
     * @param int $cartridgeid Parent cartridge ID.
     * @return \stdClass Sanitized concept object ready for DB insertion.
     */
    public function sanitize_concept(array $raw, int $cartridgeid): \stdClass {
        $concept = new \stdClass();
        $concept->cartridgeid = $cartridgeid;
        $concept->term = \core_text::substr(
            clean_param($raw['term'] ?? '', PARAM_TEXT),
            0,
            self::MAX_TERM_LENGTH
        );
        $concept->definition = \core_text::substr(
            clean_param($raw['definition'] ?? '', PARAM_TEXT),
            0,
            self::MAX_DEFINITION_LENGTH
        );
        $rawcatid = isset($raw['categoryid']) ? (int) $raw['categoryid'] : 0;
        $concept->categoryid = $rawcatid > 0 ? $rawcatid : null;
        $difficulty = isset($raw['difficulty']) ? (int) $raw['difficulty'] : 3;
        $concept->difficulty = max(1, min(5, $difficulty));
        $concept->language = null;
        return $concept;
    }
}
