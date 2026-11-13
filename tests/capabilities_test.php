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
 * Tests for the capability definitions.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Checks that the capabilities carry the risk flags the role editor shows.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class capabilities_test extends \advanced_testcase {
    /**
     * Regression test: the capability publishes content to every player and carried no risk flag, so the
     * role editor gave no warning when it was granted to a role other than manager.
     */
    public function test_managecartridges_is_flagged_as_a_spam_risk(): void {
        $capability = get_capability_info('local/playergames:managecartridges');

        $this->assertNotNull($capability);
        $this->assertSame(RISK_SPAM, (int) $capability->riskbitmask & RISK_SPAM);
    }
}
