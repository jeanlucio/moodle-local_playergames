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
 * Imports a global glossary into a concept cartridge.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\cartridge;

use local_playergames\local\text;

/**
 * Copies the approved entries of a global glossary into a new concept cartridge.
 *
 * The cartridges are site-wide, so what is copied reaches every player. Only a global glossary, which
 * the administrator made site-wide in Moodle, may be copied: a glossary of a course belongs to that
 * course, and copying it would publish what its students and teachers wrote beyond it. Only the text of
 * an entry is copied, never who wrote it, and the cartridge is a copy: later changes to the glossary do
 * not reach it.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class glossary_importer {
    /**
     * The global glossaries, with how many approved entries each has.
     *
     * @return \stdClass[] Keyed by glossary id: id, name, entries.
     */
    public function global_glossaries(): array {
        global $DB;

        $glossaries = $DB->get_records('glossary', ['globalglossary' => 1], 'name ASC', 'id, name');
        if ($glossaries === []) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal(array_keys($glossaries), SQL_PARAMS_NAMED);
        $counts = $DB->get_records_sql(
            "SELECT glossaryid AS id, COUNT(1) AS total
               FROM {glossary_entries}
              WHERE glossaryid $insql AND approved = 1
           GROUP BY glossaryid",
            $params
        );
        foreach ($glossaries as $glossary) {
            $glossary->name    = text::plain($glossary->name);
            $glossary->entries = isset($counts[$glossary->id]) ? (int) $counts[$glossary->id]->total : 0;
        }
        return $glossaries;
    }

    /**
     * Copies a global glossary into a new, active concept cartridge.
     *
     * @param int $glossaryid The glossary.
     * @param int $userid The user who imports it, recorded as the uploader.
     * @return \stdClass The importer result: cartridgeid, count, categories, language.
     * @throws \moodle_exception When the glossary is not a global one, or has nothing to copy.
     */
    public function import(int $glossaryid, int $userid): \stdClass {
        global $DB;

        $glossary = $DB->get_record('glossary', ['id' => $glossaryid, 'globalglossary' => 1], 'id, name');
        if (!$glossary) {
            throw new \moodle_exception('error_glossary_not_global', 'local_playergames');
        }

        $concepts = [];
        $entries = $DB->get_recordset_select(
            'glossary_entries',
            'glossaryid = :glossaryid AND approved = 1',
            ['glossaryid' => $glossaryid],
            'concept ASC',
            'id, concept, definition'
        );
        foreach ($entries as $entry) {
            $term       = text::plain($entry->concept);
            $definition = text::plain($entry->definition);
            if ($term === '' || $definition === '') {
                continue;
            }
            $concepts[] = ['term' => $term, 'definition' => $definition, 'difficulty' => 3];
            if (count($concepts) >= importer::MAX_CONCEPTS) {
                break;
            }
        }
        $entries->close();
        if ($concepts === []) {
            throw new \moodle_exception('error_glossary_empty', 'local_playergames');
        }

        return (new importer())->import(json_encode([
            'name'     => get_string('cartridge_glossary_name', 'local_playergames', text::plain($glossary->name)),
            'version'  => '1.0',
            'language' => current_language(),
            'concepts' => $concepts,
        ]), $userid);
    }
}
