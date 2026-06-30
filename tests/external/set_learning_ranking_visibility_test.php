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
 * Tests for the set_learning_ranking_visibility external function.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\external;

use core_external\external_api;
use local_playergames\hub\learning_xp_manager;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Unit tests for {@see set_learning_ranking_visibility}.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(set_learning_ranking_visibility::class)]
final class set_learning_ranking_visibility_test extends \advanced_testcase {
    public function test_execute_sets_visibility(): void {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();

        $result = set_learning_ranking_visibility::execute(1);
        $result = external_api::clean_returnvalue(set_learning_ranking_visibility::execute_returns(), $result);

        $this->assertSame(1, $result['showinranking']);
        $this->assertSame(1, (int) learning_xp_manager::get_or_create_cache((int) $USER->id)->showinranking);
    }

    public function test_execute_hides_visibility(): void {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();

        set_learning_ranking_visibility::execute(1);
        set_learning_ranking_visibility::execute(0);

        $this->assertSame(0, (int) learning_xp_manager::get_or_create_cache((int) $USER->id)->showinranking);
    }
}
