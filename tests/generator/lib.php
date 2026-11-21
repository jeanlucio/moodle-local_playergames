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
 * Data generator of PlayerGames.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_playergames\hub\season_manager;

/**
 * Creates the seasons, cartridges, concepts and quiz questions the tests play with.
 *
 * @package    local_playergames
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_playergames_generator extends component_generator_base {
    /**
     * Creates a season, active unless told otherwise, from yesterday to a month ahead by default.
     *
     * @param array $record name, and optionally startdate, enddate (timestamps) and active (0 or 1).
     * @return stdClass The season.
     */
    public function create_season(array $record): stdClass {
        $season = season_manager::create(
            (string) $record['name'],
            (int) ($record['startdate'] ?? time() - DAYSECS),
            (int) ($record['enddate'] ?? time() + 30 * DAYSECS)
        );
        if (!empty($record['active']) || !array_key_exists('active', $record)) {
            season_manager::activate((int) $season->id);
        }
        return $season;
    }

    /**
     * Creates a cartridge, a concept one unless the type says quiz.
     *
     * @param array $record name, and optionally type (concept or quiz), language and active (0 or 1).
     * @return stdClass The cartridge.
     */
    public function create_cartridge(array $record): stdClass {
        global $DB;
        $now = time();
        $cartridge = (object) [
            'name' => (string) $record['name'],
            'version' => '1.0',
            'language' => (string) ($record['language'] ?? 'en'),
            'type' => ($record['type'] ?? 'concept') === 'quiz' ? 'quiz' : 'concept',
            'timecreated' => $now,
            'timemodified' => $now,
            'uploadedby' => 0,
            'active' => (int) ($record['active'] ?? 1),
        ];
        $cartridge->id = (int) $DB->insert_record('local_playergames_cartridges', $cartridge);
        return $cartridge;
    }

    /**
     * Creates a concept of a cartridge.
     *
     * @param array $record cartridgeid, term, definition, and optionally difficulty (1 to 5).
     * @return stdClass The concept.
     */
    public function create_concept(array $record): stdClass {
        global $DB;
        $concept = (object) [
            'cartridgeid' => (int) $record['cartridgeid'],
            'term' => (string) $record['term'],
            'definition' => (string) $record['definition'],
            'difficulty' => (int) ($record['difficulty'] ?? 3),
        ];
        $concept->id = (int) $DB->insert_record('local_playergames_concepts', $concept);
        return $concept;
    }

    /**
     * Creates a quiz question with one right option and four wrong ones.
     *
     * @param array $record cartridgeid, questiontext, correct, and optionally the wrong options as wrong1 to wrong4
     *     (Wrong 1 to Wrong 4 by default) and feedback.
     * @return stdClass The question.
     */
    public function create_quiz_question(array $record): stdClass {
        global $DB;
        $question = (object) [
            'conceptid' => null,
            'cartridgeid' => (int) $record['cartridgeid'],
            'questiontext' => (string) $record['questiontext'],
            'source' => 'manual',
            'difficulty' => 3,
            'categoryid' => null,
            'generalfeedback' => (string) ($record['feedback'] ?? ''),
            'timecreated' => time(),
        ];
        $question->id = (int) $DB->insert_record('local_playergames_concept_questions', $question);

        $answers = [(object) [
            'questionid' => $question->id, 'answertext' => (string) $record['correct'], 'iscorrect' => 1, 'sortorder' => 0,
        ]];
        for ($i = 1; $i <= 4; $i++) {
            $answers[] = (object) [
                'questionid' => $question->id,
                'answertext' => (string) ($record['wrong' . $i] ?? 'Wrong ' . $i),
                'iscorrect' => 0,
                'sortorder' => $i,
            ];
        }
        $DB->insert_records('local_playergames_concept_answers', $answers);
        return $question;
    }
}
