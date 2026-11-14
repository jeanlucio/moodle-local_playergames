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
 * Tests for the term length admin setting.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\local;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Unit tests for {@see setting_word_length}.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(setting_word_length::class)]
final class setting_word_length_test extends \advanced_testcase {
    /**
     * Loads the admin classes the setting extends.
     *
     * @return void
     */
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->libdir . '/adminlib.php');
    }

    /**
     * Builds the minimum or the maximum setting of PlayerGuess.
     *
     * @param string $end 'min' or 'max'.
     * @return setting_word_length
     */
    private function setting(string $end): setting_word_length {
        return $end === 'min'
            ? new setting_word_length('local_playergames/wordle_minlen', 'Min', 'Help', 4, 'min', 'wordle_maxlen', 8)
            : new setting_word_length('local_playergames/wordle_maxlen', 'Max', 'Help', 8, 'max', 'wordle_minlen', 4);
    }

    public function test_a_range_that_works_is_accepted(): void {
        $this->resetAfterTest();

        $this->assertTrue($this->setting('min')->validate('4'));
        $this->assertTrue($this->setting('min')->validate('8'));
        $this->assertTrue($this->setting('max')->validate('4'));
        $this->assertTrue($this->setting('max')->validate('20'));
    }

    public function test_zero_negative_and_non_numeric_values_are_refused(): void {
        $this->resetAfterTest();
        $invalid = get_string('settings_length_invalid', 'local_playergames');

        foreach (['0', '-1', '-12'] as $value) {
            $this->assertSame($invalid, $this->setting('max')->validate($value), $value);
            $this->assertSame($invalid, $this->setting('min')->validate($value), $value);
        }
        $this->assertNotTrue($this->setting('min')->validate('abc'));
        $this->assertNotTrue($this->setting('min')->validate('4.5'));
    }

    /**
     * Regression test: a minimum above the maximum matched no term, so the daily task assigned nothing.
     */
    public function test_a_minimum_above_the_stored_maximum_is_refused(): void {
        $this->resetAfterTest();
        set_config('wordle_maxlen', 6, 'local_playergames');
        $crossed = get_string('settings_length_range_invalid', 'local_playergames');

        $this->assertSame($crossed, $this->setting('min')->validate('10'));
        $this->assertTrue($this->setting('min')->validate('6'));
    }

    public function test_a_maximum_below_the_stored_minimum_is_refused(): void {
        $this->resetAfterTest();
        set_config('wordle_minlen', 5, 'local_playergames');
        $crossed = get_string('settings_length_range_invalid', 'local_playergames');

        $this->assertSame($crossed, $this->setting('max')->validate('3'));
        $this->assertTrue($this->setting('max')->validate('5'));
    }

    /**
     * Both ends are saved by one form, so the other end counts as what was submitted with it.
     */
    public function test_the_other_end_is_read_from_the_submitted_form_first(): void {
        $this->resetAfterTest();
        set_config('wordle_maxlen', 8, 'local_playergames');
        $_POST['s_local_playergames_wordle_maxlen'] = '12';
        try {
            // Raising both at once is valid even though the stored maximum is lower than the new minimum.
            $this->assertTrue($this->setting('min')->validate('10'));

            $_POST['s_local_playergames_wordle_maxlen'] = '3';
            $this->assertNotTrue($this->setting('min')->validate('4'));
        } finally {
            unset($_POST['s_local_playergames_wordle_maxlen']);
        }
    }

    public function test_the_default_of_the_other_end_applies_when_nothing_is_stored(): void {
        $this->resetAfterTest();

        // The stored maximum is absent, so the default 8 is the other end.
        $this->assertNotTrue($this->setting('min')->validate('9'));
        $this->assertTrue($this->setting('min')->validate('8'));
    }
}
