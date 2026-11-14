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
 * Tests for the local_playergames upgrade file.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames;

use advanced_testcase;

/**
 * Unit tests for the local_playergames upgrade file.
 *
 * The plugin is unreleased, so the file holds no steps yet; the test only guards that it keeps loading and
 * answering, as the Plugin Directory rejects an archive without it. Steps added after the first release
 * need a test each that rebuilds the state they expect and runs the real function against it.
 *
 * @package    local_playergames
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers ::xmldb_local_playergames_upgrade
 */
final class db_upgrade_test extends advanced_testcase {
    /**
     * The upgrade function loads and reports success for any previous version.
     *
     * @return void
     */
    public function test_upgrade_function_succeeds(): void {
        global $CFG;

        require_once($CFG->dirroot . '/local/playergames/db/upgrade.php');

        $this->assertTrue(xmldb_local_playergames_upgrade(2026042700));
    }
}
