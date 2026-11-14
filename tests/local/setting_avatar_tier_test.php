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
 * Tests for the avatar tier admin setting.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\local;

use local_playergames\hub\level_manager;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Unit tests for {@see setting_avatar_tier}.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(setting_avatar_tier::class)]
final class setting_avatar_tier_test extends \advanced_testcase {
    /**
     * Loads the admin classes the setting extends.
     *
     * @return void
     */
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        require_once($CFG->libdir . '/adminlib.php');
        $this->setAdminUser();
    }

    /**
     * A level above the top of the ladder is flagged, naming the tier, the level and the top level.
     *
     * @return void
     */
    public function test_level_above_the_ladder_shows_a_warning(): void {
        $this->assertSame(5, level_manager::max_level());

        $html = (new setting_avatar_tier(3))->output_html('9');

        $a = (object) ['tier' => 3, 'level' => 9, 'max' => 5];
        $this->assertStringContainsString(get_string('settings_avatar_tier_unreachable', 'local_playergames', $a), $html);
    }

    /**
     * Automatic (0), the top level itself and a lower level raise no warning.
     *
     * @return void
     */
    public function test_reachable_levels_show_no_warning(): void {
        $setting = new setting_avatar_tier(2);

        foreach (['0', '3', '5'] as $level) {
            $this->assertStringNotContainsString('alert-warning', $setting->output_html($level));
        }
    }

    /**
     * The warning is not left on the setting after rendering.
     *
     * @return void
     */
    public function test_warning_does_not_stick_to_the_description(): void {
        $setting = new setting_avatar_tier(4);
        $setting->output_html('9');

        $this->assertStringNotContainsString('alert-warning', $setting->output_html('1'));
    }
}
