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
 * Behat step definitions for local_playergames.
 *
 * @package    local_playergames
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_playergames\hub\season_manager;

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

/**
 * Steps that set up and drive the PlayerQuiz game in acceptance tests.
 *
 * @package    local_playergames
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_local_playergames extends behat_base {
    /**
     * Sets up an active season with a single playable quiz question.
     *
     * The question carries general feedback and exactly one correct option
     * plus four distractors, so the loader accepts it.
     *
     * @Given the PlayerQuiz has a playable question
     */
    public function the_playerquiz_has_a_playable_question(): void {
        global $DB;

        $season = season_manager::create('Behat season', time() - DAYSECS, time() + DAYSECS);
        season_manager::activate((int) $season->id);

        $now = time();
        $cartridgeid = (int) $DB->insert_record('local_playergames_cartridges', (object) [
            'name' => 'Behat quiz',
            'version' => '1.0',
            'language' => 'en',
            'timecreated' => $now,
            'timemodified' => $now,
            'uploadedby' => 2,
            'active' => 1,
            'type' => 'quiz',
        ]);

        $questionid = (int) $DB->insert_record('local_playergames_concept_questions', (object) [
            'conceptid' => null,
            'cartridgeid' => $cartridgeid,
            'questiontext' => 'Behat question?',
            'source' => 'manual',
            'difficulty' => 3,
            'categoryid' => null,
            'generalfeedback' => 'Behat feedback after answering.',
            'timecreated' => $now,
        ]);

        $DB->insert_record('local_playergames_concept_answers', (object) [
            'questionid' => $questionid,
            'answertext' => 'Correct option',
            'iscorrect' => 1,
            'sortorder' => 0,
        ], false);
        for ($i = 1; $i <= 4; $i++) {
            $DB->insert_record('local_playergames_concept_answers', (object) [
                'questionid' => $questionid,
                'answertext' => 'Wrong ' . $i,
                'iscorrect' => 0,
                'sortorder' => $i,
            ], false);
        }
    }

    /**
     * Navigates to the PlayerQuiz play page.
     *
     * @Given I am playing the PlayerQuiz
     */
    public function i_am_playing_the_playerquiz(): void {
        $this->getSession()->visit($this->locate_path('/local/playergames/play_quiz.php'));
    }
}
