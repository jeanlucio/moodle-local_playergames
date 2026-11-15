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
 * Accessibility checks that do not need a browser: what the templates and the scripts promise a screen reader.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\output;

use local_playergames\hub\season_manager;
use local_playergames\hub\xp_manager;

/**
 * Static accessibility regressions found with axe-core on a live site.
 *
 * @package    local_playergames
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class accessibility_test extends \advanced_testcase {
    /**
     * The language strings the scripts ask for exist in English and in Portuguese.
     *
     * Regression test: a script that asks for a string the plugin does not define shows the key between
     * square brackets, and the string checks of the commit hook only read the PHP files.
     */
    public function test_every_string_the_scripts_ask_for_is_defined(): void {
        global $CFG;
        $this->resetAfterTest();
        $strings = [];
        foreach (['en', 'pt_br'] as $lang) {
            $string = [];
            require($CFG->dirroot . '/local/playergames/lang/' . $lang . '/local_playergames.php');
            $strings[$lang] = $string;
        }

        $checked = 0;
        foreach (glob($CFG->dirroot . '/local/playergames/amd/src/*.js') as $file) {
            $source = file_get_contents($file);
            preg_match_all("/getString\('([a-z0-9_]+)',\s*'local_playergames'/", $source, $single);
            foreach ($single[1] as $key) {
                $this->assertArrayHasKey($key, $strings['en'], basename($file) . " asks for {$key}, missing in en.");
                $this->assertArrayHasKey($key, $strings['pt_br'], basename($file) . " asks for {$key}, missing in pt_br.");
                $checked++;
            }
        }
        $this->assertGreaterThan(5, $checked);
        // The states of the guess tiles are built from a template literal, so they are named here.
        foreach (['correct', 'present', 'absent'] as $state) {
            $this->assertArrayHasKey('guess_state_' . $state, $strings['en']);
            $this->assertArrayHasKey('guess_state_' . $state, $strings['pt_br']);
        }
    }

    /**
     * The games announce what happens to a screen reader through a live region.
     */
    public function test_the_games_have_a_live_region_for_what_the_screen_does_not_say(): void {
        global $OUTPUT;
        $this->resetAfterTest();

        $guess = $OUTPUT->render_from_template('local_playergames/play_guess', ['definition' => 'Hint']);
        $quiz = $OUTPUT->render_from_template('local_playergames/play_quiz', ['questions' => []]);

        $this->assertMatchesRegularExpression('/id="pg-guess-live"[^>]*aria-live="polite"/', $guess);
        $this->assertMatchesRegularExpression('/id="pg-quiz-live"[^>]*aria-live="polite"/', $quiz);
    }

    /**
     * Every progress bar of the hub has a name, and the page keeps the heading levels in order.
     */
    public function test_the_hub_names_its_progress_bars_and_keeps_its_headings_in_order(): void {
        global $PAGE;
        $this->resetAfterTest();
        $season = season_manager::create('Season 1', time() - DAYSECS, time() + DAYSECS);
        season_manager::activate((int) $season->id);
        $user = $this->getDataGenerator()->create_user();
        xp_manager::get_or_create_profile((int) $user->id, (int) $season->id);
        $renderer = $PAGE->get_renderer('core');

        $html = $renderer->render_from_template(
            'local_playergames/hub',
            (new hub((int) $user->id, false, false, 'students'))->export_for_template($renderer)
        );

        preg_match_all('/<div[^>]*role="progressbar"[^>]*>/', $html, $bars);
        $this->assertGreaterThan(1, count($bars[0]));
        foreach ($bars[0] as $bar) {
            $this->assertStringContainsString('aria-label="', $bar);
        }
        // The page heading is the first level, so the sections under it start at the second.
        preg_match_all('/<h([1-6])[\s>]/', $html, $levels);
        $this->assertNotEmpty($levels[1]);
        $this->assertSame('2', $levels[1][0], 'The first heading of the hub is not a second level.');
        $this->assertNotContains('5', $levels[1]);
        $this->assertNotContains('6', $levels[1]);
    }

    /**
     * The green badges are the plugin's own, since a theme decides how light its success colour is.
     */
    public function test_the_green_badges_do_not_depend_on_the_theme(): void {
        global $CFG;
        $this->resetAfterTest();
        $styles = file_get_contents($CFG->dirroot . '/local/playergames/styles.css');

        $this->assertMatchesRegularExpression('/\.badge\.pg-badge-success\s*\{[^}]*background-color:\s*#15803d/', $styles);
        foreach (glob($CFG->dirroot . '/local/playergames/templates/*.mustache') as $file) {
            $this->assertDoesNotMatchRegularExpression(
                '/class="[^"]*\bbadge\b[^"]*\bbg-success\b/',
                file_get_contents($file),
                basename($file) . ' has a badge on the theme\'s success colour.'
            );
        }
    }

    /**
     * No template puts a grey badge on white text: Boost's secondary colour is light.
     */
    public function test_no_template_sets_white_text_on_a_secondary_badge(): void {
        global $CFG;
        $this->resetAfterTest();
        $styles = file_get_contents($CFG->dirroot . '/local/playergames/styles.css');

        $this->assertMatchesRegularExpression('/\.badge\.bg-secondary\s*\{[^}]*color:\s*var\(--bs-dark/', $styles);
        foreach (glob($CFG->dirroot . '/local/playergames/templates/*.mustache') as $file) {
            $this->assertDoesNotMatchRegularExpression(
                '/class="[^"]*bg-secondary[^"]*text-white|class="[^"]*text-white[^"]*bg-secondary/',
                file_get_contents($file),
                basename($file) . ' sets white text on a secondary badge.'
            );
        }
    }
}
