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
 * Tests for the whole number range admin setting.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\local;

/**
 * Unit tests for {@see setting_int_range}.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_playergames\local\setting_int_range
 */
final class setting_int_range_test extends \advanced_testcase {
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
     * Builds a setting for the given range.
     *
     * @param int $min Smallest value.
     * @param int|null $max Largest value.
     * @return setting_int_range
     */
    private function setting(int $min, ?int $max): setting_int_range {
        return new setting_int_range('local_playergames/season_duration_months', 'Months', 'Help', 6, $min, $max);
    }

    public function test_values_inside_the_range_are_accepted(): void {
        $setting = $this->setting(1, 60);

        foreach (['1', '6', '60'] as $value) {
            $this->assertTrue($setting->validate($value));
        }
    }

    public function test_a_negative_or_zero_value_is_refused(): void {
        $setting = $this->setting(1, 60);

        foreach (['-6', '0'] as $value) {
            $this->assertSame(
                get_string('settings_int_between', 'local_playergames', (object) ['min' => 1, 'max' => 60]),
                $setting->validate($value)
            );
        }
    }

    public function test_a_value_above_the_maximum_is_refused(): void {
        $this->assertNotTrue($this->setting(1, 60)->validate('61'));
    }

    public function test_without_a_maximum_only_the_minimum_counts(): void {
        $setting = $this->setting(0, null);

        $this->assertTrue($setting->validate('0'));
        $this->assertTrue($setting->validate('100000'));
        $this->assertSame(get_string('settings_int_min', 'local_playergames', 0), $setting->validate('-1'));
    }

    public function test_text_that_is_not_a_whole_number_is_refused(): void {
        $this->assertNotTrue($this->setting(1, 60)->validate('abc'));
    }

    /**
     * The whole number settings of the plugin all have a range: none takes a negative value, and the ones a
     * page draws or loops over have a ceiling.
     *
     * Regression test: guess_max_attempts was a plain integer setting, so 1000000 was saved without a word and
     * the PlayerGuess board tried to draw a million rows in every player's browser.
     */
    public function test_the_plugin_settings_refuse_values_out_of_their_range(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $root = admin_get_root(true, true);

        $expected = [
            'local_playergames_games' => [
                'guess_max_attempts' => [1, 10],
                'fill_max_attempts' => [1, 10],
                'fill_num_words' => [4, 8],
                'quiz_question_seconds' => [0, null],
                'quiz_max_attempts' => [0, null],
                'quiz_cooldown_minutes' => [0, null],
            ],
            'local_playergames_seasons' => [
                'seasons_keep' => [1, null],
                'activitylog_keep' => [0, null],
            ],
            'local_playergames_learning' => [
                'learningxp_window_months' => [0, null],
            ],
        ];
        foreach ($expected as $pagename => $settings) {
            $page = $root->locate($pagename);
            $this->assertInstanceOf(\admin_settingpage::class, $page, $pagename);
            foreach ($settings as $name => [$min, $max]) {
                // A page keys its settings by the plugin name followed by the setting name.
                $setting = $page->settings->{"local_playergames{$name}"} ?? null;
                $this->assertInstanceOf(setting_int_range::class, $setting, $name);
                $this->assertTrue($setting->validate((string) $min), $name);
                $this->assertIsString($setting->validate((string) ($min - 1)), $name);
                if ($max !== null) {
                    $this->assertTrue($setting->validate((string) $max), $name);
                    $this->assertIsString($setting->validate((string) ($max + 1)), $name);
                }
            }
        }
    }
}
