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

        $this->validate_schema($data);

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
        $now = time();
        $cartridge->timecreated = $now;
        $cartridge->timemodified = $now;
        $cartridge->uploadedby = $uploaderid;
        $cartridge->author = isset($data['author'])
            ? \core_text::substr(clean_param($data['author'], PARAM_TEXT), 0, 255)
            : null;
        $cartridge->active = 1;

        $cartridgeid = $DB->insert_record('local_playergames_cartridges', $cartridge);

        return $this->save_concepts($cartridgeid, $data['concepts']);
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
