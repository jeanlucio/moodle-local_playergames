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
 * Tests of the data generator of PlayerGames.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames;

use local_playergames\hub\season_manager;

/**
 * The generator builds data the games accept, so a feature that fails points at the page, not at its setup.
 *
 * @package    local_playergames
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_playergames_generator
 */
final class generator_test extends \advanced_testcase {
    /**
     * A season is active unless the record says otherwise.
     */
    public function test_a_season_is_active_unless_told_otherwise(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator()->get_plugin_generator('local_playergames');

        $later = $generator->create_season(['name' => 'Later', 'active' => '0']);
        $this->assertNull(season_manager::get_active());

        $now = $generator->create_season(['name' => 'Now']);
        $this->assertSame((int) $now->id, (int) season_manager::get_active()->id);
        $this->assertNotSame((int) $later->id, (int) $now->id);
    }

    /**
     * A concept of an active cartridge becomes the word of PlayerGuess when the words are drawn.
     */
    public function test_a_concept_can_be_drawn_as_the_word_of_the_day(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator()->get_plugin_generator('local_playergames');
        $cartridge = $generator->create_cartridge(['name' => 'Fruits']);
        $concept = $generator->create_concept([
            'cartridgeid' => $cartridge->id, 'term' => 'grape', 'definition' => 'A purple fruit',
        ]);

        \local_playergames\task\assign_daily_games::draw_now();

        $this->assertSame((int) $concept->id, (int) $DB->get_field(
            'local_playergames_daily_assignments',
            'conceptid',
            ['gametype' => 'guess'],
            MUST_EXIST
        ));
    }

    /**
     * As many concepts as PlayerFill asks for fill its board when the words are drawn.
     *
     * PlayerFill never asks for fewer than four words, whatever the setting says: a smaller pool draws nothing.
     */
    public function test_four_concepts_give_playerfill_its_board(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('fill_num_words', 4, 'local_playergames');
        $generator = $this->getDataGenerator()->get_plugin_generator('local_playergames');
        $cartridge = $generator->create_cartridge(['name' => 'Fruits']);
        foreach (['grape', 'lemon', 'mango', 'melon'] as $term) {
            $generator->create_concept(['cartridgeid' => $cartridge->id, 'term' => $term, 'definition' => 'A fruit']);
        }

        \local_playergames\task\assign_daily_games::draw_now();

        $this->assertSame(4, $DB->count_records('local_playergames_daily_assignments', ['gametype' => 'fill']));
    }

    /**
     * A quiz question comes with one right option and four wrong ones, as the quiz loader requires.
     */
    public function test_a_quiz_question_has_one_right_option_and_four_wrong_ones(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator()->get_plugin_generator('local_playergames');
        $cartridge = $generator->create_cartridge(['name' => 'Quiz', 'type' => 'quiz']);

        $question = $generator->create_quiz_question([
            'cartridgeid' => $cartridge->id, 'questiontext' => 'Capital of France?', 'correct' => 'Paris',
            'wrong1' => 'Lyon', 'feedback' => 'Paris it is.',
        ]);

        $this->assertSame('quiz', $DB->get_field('local_playergames_cartridges', 'type', ['id' => $cartridge->id]));
        $this->assertSame('Paris it is.', $DB->get_field(
            'local_playergames_concept_questions',
            'generalfeedback',
            ['id' => $question->id]
        ));
        $answers = $DB->get_records_menu(
            'local_playergames_concept_answers',
            ['questionid' => $question->id],
            'sortorder',
            'answertext, iscorrect'
        );
        $this->assertSame(['Paris', 'Lyon', 'Wrong 2', 'Wrong 3', 'Wrong 4'], array_keys($answers));
        $this->assertSame([1, 0, 0, 0, 0], array_map('intval', array_values($answers)));
    }
}
