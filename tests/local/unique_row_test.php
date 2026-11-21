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
 * Tests of the get-or-create helper for rows a unique key guards.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\local;

/**
 * Tests of unique_row.
 *
 * The case it exists for, a second request inserting the row the first one just created, needs two requests
 * at the same time. What one request can show is tested here: the row is found or created, and a collision
 * the helper cannot settle reaches the caller instead of passing for a success. The levels table serves as
 * the subject because its only unique key is the level, so a lookup that also names the XP can miss a row
 * whose insert then collides.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_playergames\local\unique_row
 */
final class unique_row_test extends \advanced_testcase {
    /** @var string The table the tests write to. */
    private const TABLE = 'local_playergames_levels';

    /**
     * Puts a row for a level in the table.
     *
     * @param int $level The level.
     * @param int $minxp Its minimum XP.
     * @return int The id of the row.
     */
    private function level(int $level, int $minxp): int {
        global $DB;
        $DB->delete_records(self::TABLE, ['level' => $level]);
        return (int) $DB->insert_record(self::TABLE, (object) ['level' => $level, 'minxp' => $minxp, 'title' => 'L']);
    }

    /**
     * A row that exists is returned as it is, and one that does not is created with the defaults.
     */
    public function test_the_row_is_found_or_created(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();
        $id = $this->level(50, 1000);

        $found = unique_row::get_or_create(self::TABLE, ['level' => 50], ['minxp' => 0, 'title' => 'New']);
        $created = unique_row::get_or_create(self::TABLE, ['level' => 51], ['minxp' => 2000, 'title' => 'New']);

        $this->assertSame($id, (int) $found->id);
        $this->assertSame(1000, (int) $found->minxp);
        $this->assertSame(2000, (int) $DB->get_field(self::TABLE, 'minxp', ['id' => $created->id]));
        $this->assertSame('New', $created->title);
    }

    /**
     * A collision on a row the lookup cannot find again is not taken for the row: the error reaches the caller.
     */
    public function test_a_collision_with_no_row_to_read_back_reaches_the_caller(): void {
        global $DB;
        $this->resetAfterTest();
        // A test runs inside a transaction of its own, which would send every collision down the
        // transaction branch.
        $this->preventResetByRollback();
        $this->level(50, 1000);

        try {
            unique_row::get_or_create(self::TABLE, ['level' => 50, 'minxp' => 5], ['title' => 'New']);
            $this->fail('The collision did not reach the caller.');
        } catch (\dml_write_exception $e) {
            $this->assertFalse($DB->is_transaction_started());
            $this->assertSame(1000, (int) $DB->get_field(self::TABLE, 'minxp', ['level' => 50]));
        }
    }

    /**
     * Inside a transaction a collision is not read back: on PostgreSQL the transaction is already aborted, so
     * the error goes to the code that owns the transaction, which rolls it back.
     */
    public function test_a_collision_inside_a_transaction_reaches_the_owner_of_the_transaction(): void {
        global $DB;
        $this->resetAfterTest();
        // A test runs inside a transaction of its own, which would send every collision down the
        // transaction branch.
        $this->preventResetByRollback();
        $this->level(50, 1000);

        $transaction = $DB->start_delegated_transaction();
        try {
            unique_row::get_or_create(self::TABLE, ['level' => 50, 'minxp' => 5], ['title' => 'New']);
            $this->fail('The collision did not reach the owner of the transaction.');
        } catch (\dml_write_exception $e) {
            $this->assertTrue($DB->is_transaction_started());
        }
        try {
            $transaction->rollback($e);
        } catch (\dml_write_exception $rethrown) {
            $this->assertSame($e, $rethrown);
        }
        $this->assertFalse($DB->is_transaction_started());
    }
}
