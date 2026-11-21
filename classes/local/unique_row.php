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
 * Reads a row by its unique key, creating it when absent.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\local;

use stdClass;

/**
 * Get-or-create for the per-user rows that a unique key guards (profile, streak, avatar state, progress).
 *
 * The pages read these rows too, outside the lock of the user, and create them on the first visit. Two
 * requests that both find no row both insert it: the unique key refuses the second, and without this the
 * second request ended on a database error page. The row the other request created is read back instead.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class unique_row {
    /**
     * Returns the row matching the unique key, inserting it with the defaults when there is none.
     *
     * @param string $table Table name, without prefix.
     * @param array $key The fields of the unique key and their values.
     * @param array $defaults The other fields of a new row.
     * @return stdClass The row, with its id.
     * @throws \dml_write_exception When the insert fails for another reason, or inside a transaction.
     */
    public static function get_or_create(string $table, array $key, array $defaults): stdClass {
        global $DB;

        $row = $DB->get_record($table, $key);
        if ($row) {
            return $row;
        }

        $row = (object) ($key + $defaults);
        try {
            $row->id = $DB->insert_record($table, $row);
            return $row;
        } catch (\dml_write_exception $e) {
            // Inside a transaction PostgreSQL has already aborted it, so the row cannot be read back: the
            // error goes up to the code that owns the transaction, which rolls it back.
            if ($DB->is_transaction_started()) {
                throw $e;
            }
            $existing = $DB->get_record($table, $key);
            if (!$existing) {
                throw $e;
            }
            return $existing;
        }
    }
}
