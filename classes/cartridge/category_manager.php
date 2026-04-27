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
 * Concept category manager.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\cartridge;

/**
 * Provides CRUD operations for concept categories within a cartridge.
 */
class category_manager {
    /**
     * Returns all categories for a cartridge ordered by sortorder then name.
     *
     * @param int $cartridgeid Target cartridge ID.
     * @return array Array of category stdClass records.
     */
    public function get_categories(int $cartridgeid): array {
        global $DB;
        return array_values(
            $DB->get_records(
                'local_playergames_categories',
                ['cartridgeid' => $cartridgeid],
                'sortorder ASC, name ASC'
            )
        );
    }

    /**
     * Returns the category ID for the given name, creating the record if it does not exist.
     *
     * @param int $cartridgeid Target cartridge ID.
     * @param string $name Category name (will be trimmed).
     * @return int Category ID, or 0 if name is empty.
     */
    public function ensure_category(int $cartridgeid, string $name): int {
        global $DB;
        $name = trim($name);
        if ($name === '') {
            return 0;
        }
        $existing = $DB->get_record(
            'local_playergames_categories',
            ['cartridgeid' => $cartridgeid, 'name' => $name]
        );
        if ($existing) {
            return (int) $existing->id;
        }
        return $this->create($cartridgeid, $name);
    }

    /**
     * Creates a new category in the cartridge.
     *
     * @param int $cartridgeid Target cartridge ID.
     * @param string $name Category name.
     * @return int New category ID.
     * @throws \moodle_exception If the name is empty or already exists.
     */
    public function create(int $cartridgeid, string $name): int {
        global $DB;
        $name = trim(\core_text::substr(clean_param($name, PARAM_TEXT), 0, 100));
        if ($name === '') {
            throw new \moodle_exception('error_category_empty_name', 'local_playergames');
        }
        $maxorder = (int) $DB->get_field_sql(
            'SELECT COALESCE(MAX(sortorder), -1) FROM {local_playergames_categories}
              WHERE cartridgeid = ?',
            [$cartridgeid]
        );
        $record = new \stdClass();
        $record->cartridgeid = $cartridgeid;
        $record->name = $name;
        $record->sortorder = $maxorder + 1;
        $record->timecreated = time();
        return (int) $DB->insert_record('local_playergames_categories', $record);
    }

    /**
     * Renames an existing category.
     *
     * @param int $categoryid Category ID.
     * @param int $cartridgeid Owning cartridge ID (for ownership check).
     * @param string $name New name.
     * @throws \moodle_exception If the name is empty or the category is not found.
     */
    public function rename(int $categoryid, int $cartridgeid, string $name): void {
        global $DB;
        $name = trim(\core_text::substr(clean_param($name, PARAM_TEXT), 0, 100));
        if ($name === '') {
            throw new \moodle_exception('error_category_empty_name', 'local_playergames');
        }
        $record = $DB->get_record(
            'local_playergames_categories',
            ['id' => $categoryid, 'cartridgeid' => $cartridgeid]
        );
        if (!$record) {
            throw new \moodle_exception('error_category_notfound', 'local_playergames');
        }
        $record->name = $name;
        $DB->update_record('local_playergames_categories', $record);
    }

    /**
     * Deletes a category and sets categoryid to NULL on its concepts.
     *
     * @param int $categoryid Category ID.
     * @param int $cartridgeid Owning cartridge ID (for ownership check).
     */
    public function delete(int $categoryid, int $cartridgeid): void {
        global $DB;
        $DB->set_field('local_playergames_concepts', 'categoryid', null, ['categoryid' => $categoryid]);
        $DB->delete_records(
            'local_playergames_categories',
            ['id' => $categoryid, 'cartridgeid' => $cartridgeid]
        );
    }
}
