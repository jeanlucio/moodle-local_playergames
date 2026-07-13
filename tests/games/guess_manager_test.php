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
 * Tests for the PlayerGuess gameplay manager.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\games;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Unit tests for {@see guess_manager}.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(guess_manager::class)]
final class guess_manager_test extends \advanced_testcase {
    /**
     * Creates a concept-type cartridge.
     *
     * @return int Cartridge id.
     */
    private function make_cartridge(): int {
        global $DB;
        $now = time();
        return (int) $DB->insert_record('local_playergames_cartridges', (object) [
            'name' => 'Concepts', 'version' => '1.0', 'language' => 'en', 'type' => 'concept',
            'timecreated' => $now, 'timemodified' => $now, 'uploadedby' => 0, 'active' => 1,
        ]);
    }

    /**
     * Adds one concept to a cartridge.
     *
     * @param int $cartridgeid Owning cartridge.
     * @param string $term Term text.
     * @return int Concept id.
     */
    private function add_concept(int $cartridgeid, string $term): int {
        global $DB;
        return (int) $DB->insert_record('local_playergames_concepts', (object) [
            'cartridgeid' => $cartridgeid, 'term' => $term, 'definition' => 'A definition.',
            'difficulty' => 1,
        ]);
    }

    public function test_normalize_lowercases_and_strips_accents(): void {
        $this->assertSame('fotossintese', guess_manager::normalize('Fotossíntese'));
        $this->assertSame('acao', guess_manager::normalize('  Ação  '));
    }

    public function test_build_letter_feedback_marks_correct_present_and_absent(): void {
        // Comparing "crate" against "react": every letter of "react" appears in
        // "crate", and the "a" happens to already sit in its correct position
        // (index 2).
        $feedback = guess_manager::build_letter_feedback('crate', 'react');
        $this->assertSame(['present', 'present', 'correct', 'present', 'present'], $feedback);

        $feedback = guess_manager::build_letter_feedback('react', 'react');
        $this->assertSame(['correct', 'correct', 'correct', 'correct', 'correct'], $feedback);

        $feedback = guess_manager::build_letter_feedback('zzzzz', 'react');
        $this->assertSame(['absent', 'absent', 'absent', 'absent', 'absent'], $feedback);
    }

    public function test_build_letter_feedback_handles_duplicate_letters(): void {
        // Target has one 'o'; guess has two — only the correctly placed one is
        // marked, the extra occurrence is absent rather than a second 'present'.
        $feedback = guess_manager::build_letter_feedback('mooed', 'coder');
        $this->assertSame('correct', $feedback[3]);
        $presentcount = count(array_filter($feedback, fn($f) => $f === 'present'));
        $absentcount = count(array_filter($feedback, fn($f) => $f === 'absent'));
        $this->assertLessThanOrEqual(1, $presentcount);
        $this->assertGreaterThanOrEqual(1, $absentcount);
    }

    public function test_max_attempts_default_and_configured(): void {
        $this->resetAfterTest();
        unset_config('guess_max_attempts', 'local_playergames');
        $this->assertSame(guess_manager::DEFAULT_MAX_ATTEMPTS, guess_manager::max_attempts());

        set_config('guess_max_attempts', '4', 'local_playergames');
        $this->assertSame(4, guess_manager::max_attempts());

        set_config('guess_max_attempts', '0', 'local_playergames');
        $this->assertSame(guess_manager::DEFAULT_MAX_ATTEMPTS, guess_manager::max_attempts());
    }

    public function test_is_valid_guess_checks_length_and_alphabet(): void {
        $this->assertTrue(guess_manager::is_valid_guess('react', 5));
        $this->assertFalse(guess_manager::is_valid_guess('react', 4));
        $this->assertFalse(guess_manager::is_valid_guess('re4ct', 5));
        $this->assertFalse(guess_manager::is_valid_guess('', 0));
    }

    public function test_get_daily_concept_returns_null_when_unassigned(): void {
        $this->resetAfterTest();
        $this->assertNull(guess_manager::get_daily_concept(time()));
    }

    public function test_get_daily_concept_returns_assigned_concept(): void {
        global $DB;
        $this->resetAfterTest();

        $cartridgeid = $this->make_cartridge();
        $conceptid = $this->add_concept($cartridgeid, 'planet');
        $gamedate = mktime(0, 0, 0, 1, 1, 2026);

        $DB->insert_record('local_playergames_daily_assignments', (object) [
            'gamedate' => $gamedate, 'gametype' => 'guess', 'conceptid' => $conceptid,
        ]);

        $concept = guess_manager::get_daily_concept($gamedate);
        $this->assertNotNull($concept);
        $this->assertSame('planet', $concept->term);
    }
}
