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
 * Tests for the gamification preferences form.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\form;

/**
 * Unit tests for {@see gamification_preferences_form}.
 *
 * @package    local_playergames
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_playergames\form\gamification_preferences_form
 */
final class gamification_preferences_form_test extends \advanced_testcase {
    /**
     * The form asks one yes/no question, with the usual save and cancel buttons.
     */
    public function test_the_form_has_the_opt_in_checkbox_and_the_buttons(): void {
        $this->resetAfterTest();

        $form = new gamification_preferences_form('/local/playergames/gamification_preferences.php');
        $html = $form->render();

        $this->assertStringContainsString('name="enabled"', $html);
        $this->assertStringContainsString(get_string('gamification_pref_label', 'local_playergames'), $html);
        $this->assertStringContainsString(get_string('gamification_pref_intro', 'local_playergames'), $html);
        $this->assertStringContainsString('name="submitbutton"', $html);
        $this->assertStringContainsString('name="cancel"', $html);
    }

    /**
     * The checkbox starts with the value the page loads into it.
     */
    public function test_the_checkbox_shows_the_current_choice(): void {
        $this->resetAfterTest();

        $form = new gamification_preferences_form('/local/playergames/gamification_preferences.php');
        $form->set_data(['enabled' => 1]);

        $this->assertMatchesRegularExpression('/<input[^>]*type="checkbox"[^>]*name="enabled"[^>]*checked/', $form->render());
    }
}
