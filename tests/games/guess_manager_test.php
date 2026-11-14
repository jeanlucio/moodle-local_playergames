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

/**
 * Unit tests for {@see guess_manager}.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_playergames\games\guess_manager
 * @covers \local_playergames\games\guess_state
 */
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

    /**
     * Opens an active season with PlayerGuess on and today's concept assigned.
     *
     * @param string $term Today's term.
     * @return \stdClass Season record.
     */
    private function open_round(string $term = 'planet'): \stdClass {
        global $DB;
        $this->redirectEvents();

        $now = time();
        $seasonid = (int) $DB->insert_record('local_playergames_seasons', (object) [
            'name' => 'Season', 'startdate' => $now - DAYSECS, 'enddate' => $now + (DAYSECS * 30),
            'status' => 'active', 'config_snapshot' => json_encode(['xp_per_game_guess' => 10, 'xp_games_guess' => 1]),
            'timecreated' => $now, 'timemodified' => $now,
        ]);
        $DB->insert_record('local_playergames_season_games', (object) [
            'seasonid' => $seasonid, 'gametype' => 'guess', 'enabled' => 1, 'source' => 'cartridge', 'auxid' => 0,
        ]);
        $conceptid = $this->add_concept($this->make_cartridge(), $term);
        $DB->insert_record('local_playergames_daily_assignments', (object) [
            'gamedate' => mktime(0, 0, 0), 'gametype' => 'guess', 'conceptid' => $conceptid,
        ]);
        set_config('guess_max_attempts', 2, 'local_playergames');

        return $DB->get_record('local_playergames_seasons', ['id' => $seasonid], '*', MUST_EXIST);
    }

    /**
     * Regression test: the round used to live in the session, so a new login gave the lost round again,
     * with the term the loss had just revealed, and the player won it on the first try.
     */
    public function test_a_lost_round_stays_lost_after_a_new_session(): void {
        global $DB, $SESSION;
        $this->resetAfterTest();
        $season = $this->open_round('planet');
        $user = $this->getDataGenerator()->create_user();
        $today = mktime(0, 0, 0);
        $context = \context_system::instance();

        $first = guess_manager::submit_guess((int) $user->id, 'zzzzzz', $today, $season, $context);
        $this->assertTrue($first['success']);
        $second = guess_manager::submit_guess((int) $user->id, 'yyyyyy', $today, $season, $context);
        $this->assertTrue($second['finished']);
        $this->assertFalse($second['won']);
        $this->assertSame('planet', $second['target']);

        // Logging in again or opening another browser starts with an empty session.
        $SESSION = new \stdClass();
        $replay = guess_manager::submit_guess((int) $user->id, 'planet', $today, $season, $context);

        $this->assertSame(['success' => false, 'reason' => 'finished'], $replay);
        // The loss pays nothing, and the page still finds the round over when it loads the state.
        $this->assertFalse($DB->record_exists('local_playergames_daily_scores', ['userid' => $user->id]));
        $state = guess_state::load((int) $user->id, $today, (int) guess_manager::get_daily_concept($today)->id);
        $this->assertTrue($state['finished']);
        $this->assertFalse($state['won']);
    }

    public function test_the_attempts_used_survive_a_new_session(): void {
        global $SESSION;
        $this->resetAfterTest();
        $season = $this->open_round('planet');
        $user = $this->getDataGenerator()->create_user();
        $today = mktime(0, 0, 0);
        $context = \context_system::instance();

        $first = guess_manager::submit_guess((int) $user->id, 'zzzzzz', $today, $season, $context);
        $SESSION = new \stdClass();
        $second = guess_manager::submit_guess((int) $user->id, 'yyyyyy', $today, $season, $context);

        $this->assertSame(1, $first['attemptsleft']);
        $this->assertSame(0, $second['attemptsleft']);
        $this->assertTrue($second['finished']);
    }

    public function test_a_win_pays_the_xp_once_and_closes_the_round(): void {
        global $DB;
        $this->resetAfterTest();
        $season = $this->open_round('planet');
        $user = $this->getDataGenerator()->create_user();
        $today = mktime(0, 0, 0);
        $context = \context_system::instance();

        $won = guess_manager::submit_guess((int) $user->id, 'Planet', $today, $season, $context);
        $again = guess_manager::submit_guess((int) $user->id, 'planet', $today, $season, $context);

        $this->assertTrue($won['won']);
        $this->assertSame(10, $won['xpawarded']);
        $this->assertSame('finished', $again['reason']);
        $this->assertSame(1, (int) $DB->get_field('local_playergames_daily_scores', 'attempts', [
            'userid' => $user->id, 'gamedate' => $today, 'gametype' => 'guess',
        ]));
    }

    public function test_the_term_is_not_revealed_while_the_round_is_open(): void {
        $this->resetAfterTest();
        $season = $this->open_round('planet');
        $user = $this->getDataGenerator()->create_user();

        $answer = guess_manager::submit_guess(
            (int) $user->id,
            'zzzzzz',
            mktime(0, 0, 0),
            $season,
            \context_system::instance()
        );

        $this->assertFalse($answer['finished']);
        $this->assertNull($answer['target']);
    }

    public function test_an_invalid_guess_does_not_use_an_attempt(): void {
        $this->resetAfterTest();
        $season = $this->open_round('planet');
        $user = $this->getDataGenerator()->create_user();
        $today = mktime(0, 0, 0);
        $context = \context_system::instance();

        $invalid = guess_manager::submit_guess((int) $user->id, 'abc', $today, $season, $context);
        $valid = guess_manager::submit_guess((int) $user->id, 'zzzzzz', $today, $season, $context);

        $this->assertSame(['success' => false, 'reason' => 'invalid'], $invalid);
        $this->assertSame(1, $valid['attemptsleft']);
    }

    public function test_submit_is_unavailable_without_a_concept_for_the_day(): void {
        $this->resetAfterTest();
        $season = $this->open_round('planet');
        $user = $this->getDataGenerator()->create_user();

        $answer = guess_manager::submit_guess(
            (int) $user->id,
            'planet',
            mktime(0, 0, 0) - DAYSECS,
            $season,
            \context_system::instance()
        );

        $this->assertSame(['success' => false, 'reason' => 'unavailable'], $answer);
    }
}
