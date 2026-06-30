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
 * Tests for the set_avatar external function.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\external;

use core_external\external_api;
use local_playergames\hub\avatar_manager;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Unit tests for {@see set_avatar}.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(set_avatar::class)]
final class set_avatar_test extends \advanced_testcase {
    public function test_execute_equips_unlocked_avatar(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $result = set_avatar::execute('🤖');
        $result = external_api::clean_returnvalue(set_avatar::execute_returns(), $result);

        $this->assertSame('🤖', $result['equipped']);
    }

    public function test_execute_unequips_with_empty_string(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        set_avatar::execute('🤖');
        $result = set_avatar::execute('');
        $result = external_api::clean_returnvalue(set_avatar::execute_returns(), $result);

        $this->assertSame('', $result['equipped']);
    }

    public function test_execute_rejects_locked_avatar(): void {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();

        // Tier-3 avatar ('🧝') needs level 10; a fresh user is at best level 1.
        $this->assertFalse(avatar_manager::is_unlocked('🧝', (int) avatar_manager::get_state((int) $USER->id)->bestlevel));
        $this->expectException(\moodle_exception::class);
        set_avatar::execute('🧝');
    }
}
