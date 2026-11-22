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
 * Tests for the PlayerQuiz settings.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\games;

/**
 * Unit tests for {@see quiz_settings}.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_playergames\games\quiz_settings
 */
final class quiz_settings_test extends \advanced_testcase {
    public function test_question_seconds_default_when_unset(): void {
        $this->resetAfterTest();
        unset_config('quiz_question_seconds', 'local_playergames');
        $this->assertSame(120, (new quiz_settings())->question_seconds());
    }

    public function test_question_seconds_zero_disables_timer(): void {
        $this->resetAfterTest();
        set_config('quiz_question_seconds', '0', 'local_playergames');
        $this->assertSame(0, (new quiz_settings())->question_seconds());
    }

    public function test_question_seconds_reads_value_and_clamps_negative(): void {
        $this->resetAfterTest();
        set_config('quiz_question_seconds', '90', 'local_playergames');
        $this->assertSame(90, (new quiz_settings())->question_seconds());
        set_config('quiz_question_seconds', '-5', 'local_playergames');
        $this->assertSame(0, (new quiz_settings())->question_seconds());
    }

    public function test_session_size_default_when_unset_or_invalid(): void {
        $this->resetAfterTest();
        $settings = new quiz_settings();
        unset_config('quiz_session_size', 'local_playergames');
        $this->assertSame(20, $settings->session_size());
        set_config('quiz_session_size', '0', 'local_playergames');
        $this->assertSame(20, $settings->session_size());
        set_config('quiz_session_size', '-3', 'local_playergames');
        $this->assertSame(20, $settings->session_size());
    }

    public function test_session_size_reads_configured_value(): void {
        $this->resetAfterTest();
        set_config('quiz_session_size', '50', 'local_playergames');
        $this->assertSame(50, (new quiz_settings())->session_size());
    }

    public function test_max_attempts_default_and_clamp(): void {
        $this->resetAfterTest();
        $settings = new quiz_settings();
        unset_config('quiz_max_attempts', 'local_playergames');
        $this->assertSame(0, $settings->max_attempts());

        set_config('quiz_max_attempts', '3', 'local_playergames');
        $this->assertSame(3, $settings->max_attempts());

        set_config('quiz_max_attempts', '-2', 'local_playergames');
        $this->assertSame(0, $settings->max_attempts());
    }
}
