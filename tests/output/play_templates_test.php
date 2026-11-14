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
 * Tests for how the Guess and Fill templates print a concept's definition.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\output;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * The definition is plain text from the cartridge (or from the AI), so the templates must escape it
 * themselves, once: an escape done in PHP and printed raw is one edit away from live markup.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class play_templates_test extends \advanced_testcase {
    /** @var string A definition with markup in it. */
    private const DEFINITION = 'Tom & Jerry <img src=x onerror=alert(1)>';

    /** @var string That definition escaped once. */
    private const ESCAPED = 'Tom &amp; Jerry &lt;img src=x onerror=alert(1)&gt;';

    public function test_the_guess_template_escapes_the_definition_once(): void {
        global $OUTPUT;
        $this->resetAfterTest();

        $html = $OUTPUT->render_from_template('local_playergames/play_guess', [
            'definition' => self::DEFINITION,
            'str_definition_label' => 'Hint',
        ]);

        $this->assertStringContainsString(self::ESCAPED, $html);
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringNotContainsString('&amp;amp;', $html);
    }

    public function test_the_fill_template_escapes_the_definition_once(): void {
        global $OUTPUT;
        $this->resetAfterTest();

        $html = $OUTPUT->render_from_template('local_playergames/play_fill', [
            'rows' => [['conceptid' => 1, 'definition' => self::DEFINITION, 'tiles' => []]],
            'str_definition_label' => 'Hint',
        ]);

        $this->assertStringContainsString(self::ESCAPED, $html);
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringNotContainsString('&amp;amp;', $html);
    }
}
