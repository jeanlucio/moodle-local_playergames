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
 * Imports a question bank category into a quiz cartridge.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\cartridge;

use local_playergames\local\question_bank_reuse;
use local_playergames\local\text;

/**
 * Copies the multiple-choice questions of a shared question bank category into a new quiz cartridge.
 *
 * The cartridges are site-wide, so what is copied reaches every player. Only a category Moodle keeps for
 * reuse may be copied (see question_bank_reuse), and only by someone who may use all its questions: a
 * category of a course holds that course's questions, and copying it would publish them beyond it.
 *
 * A cartridge holds plain text and no files, so a question that has an image, a sound or a video, in its
 * statement, its feedback or the options it would offer, is left out: shown without the figure it refers
 * to, it could not be answered. Only the latest ready version of each question is taken, as the core quiz
 * does, and the cartridge is a copy: later changes in the bank do not reach it.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_bank_importer {
    /** @var float Smallest fraction taken as a full-credit answer (stored with 7 decimals). */
    private const FULL_CREDIT = 0.9999999;

    /** @var string Marks a file of the bank in a text, and the tags that bring a figure or a medium. */
    private const MEDIA_PATTERN = '/@@PLUGINFILE@@|<\s*(img|video|audio|source|object|embed|iframe|svg|canvas|picture)\b/i';

    /**
     * The categories a user may import from, with how many multiple-choice questions each has.
     *
     * @param int $userid The user.
     * @return \stdClass[] Keyed by category id: id, name (with the bank), questions.
     */
    public function categories(int $userid): array {
        $categories = question_bank_reuse::categories_for_user($userid);
        foreach ($categories as $category) {
            $name = text::plain($category->name);
            $bank = text::plain((string) $category->bank);
            $category->name = $bank !== '' ? "{$bank} / {$name}" : $name;
        }
        return $categories;
    }

    /**
     * Copies a category into a new, active quiz cartridge.
     *
     * @param int $categoryid The question bank category.
     * @param int $userid The user who imports it, recorded as the uploader.
     * @return \stdClass The importer result plus images, unusable and beyond: how many questions were
     *     left out because of a medium, because they have no single right answer and four wrong ones, and
     *     because the cartridge is full.
     * @throws \moodle_exception When the category may not be imported, or has nothing to copy.
     */
    public function import(int $categoryid, int $userid): \stdClass {
        global $DB;

        if (!question_bank_reuse::can_choose($categoryid, $userid)) {
            throw new \moodle_exception('error_qbank_category_unavailable', 'local_playergames');
        }
        $category = $DB->get_record('question_categories', ['id' => $categoryid], 'id, name', MUST_EXIST);

        $left = ['images' => 0, 'unusable' => 0, 'beyond' => 0];
        $questions = [];
        foreach ($this->read_questions($categoryid) as $row) {
            $converted = $this->convert($row);
            if ($converted === 'images' || $converted === 'unusable') {
                $left[$converted]++;
            } else if (count($questions) >= importer::MAX_QUESTIONS) {
                $left['beyond']++;
            } else {
                $questions[] = $converted;
            }
        }
        if ($questions === []) {
            throw new \moodle_exception('error_qbank_empty', 'local_playergames');
        }

        $result = (new importer())->import(json_encode([
            'name'      => get_string('cartridge_qbank_name', 'local_playergames', text::plain($category->name)),
            'version'   => '1.0',
            'language'  => current_language(),
            'type'      => 'quiz',
            'questions' => $questions,
        ]), $userid);

        $result->images   = $left['images'];
        $result->unusable = $left['unusable'];
        $result->beyond   = $left['beyond'];
        return $result;
    }

    /**
     * Returns the latest ready version of every multiple-choice question of a category, with its answers.
     *
     * Editing a question adds a version and leaves the earlier ones ready, so an older version, which still
     * carries the statement or the answer key the teacher corrected, is never taken.
     *
     * @param int $categoryid The category.
     * @return \stdClass[] Each with the question fields and an answers list, in the order of the answers.
     */
    private function read_questions(int $categoryid): array {
        global $DB;

        $questions = $DB->get_records_sql(
            "SELECT q.id, q.questiontext, q.questiontextformat, q.generalfeedback, q.generalfeedbackformat
               FROM {question} q
               JOIN {question_versions} qv ON qv.questionid = q.id
               JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
              WHERE qbe.questioncategoryid = :categoryid
                AND q.qtype = 'multichoice'
                AND qv.status = 'ready'
                AND qv.version = (SELECT MAX(lv.version)
                                    FROM {question_versions} lv
                                   WHERE lv.questionbankentryid = qbe.id
                                     AND lv.status = 'ready')
           ORDER BY q.id",
            ['categoryid' => $categoryid]
        );
        if ($questions === []) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal(array_keys($questions), SQL_PARAMS_NAMED);
        $answers = $DB->get_records_select(
            'question_answers',
            "question $insql",
            $params,
            'id ASC',
            'id, question, answer, answerformat, fraction'
        );
        foreach ($questions as $question) {
            $question->answers = [];
        }
        foreach ($answers as $answer) {
            $questions[(int) $answer->question]->answers[] = $answer;
        }
        return array_values($questions);
    }

    /**
     * Turns a bank question into a cartridge question.
     *
     * Only an answer worth the whole grade is the right one, and the cartridge needs four wrong ones: a
     * question with partial credit, or with several answers worth a share each, has no single key.
     *
     * @param \stdClass $row A question read by read_questions().
     * @return array|string The cartridge question, 'images' when it has a medium, 'unusable' when it has
     *     no single right answer with four wrong ones.
     */
    private function convert(\stdClass $row) {
        $correct = [];
        $wrong = [];
        foreach ($row->answers as $answer) {
            if ((float) $answer->fraction >= self::FULL_CREDIT) {
                $correct[] = $answer;
            } else if ((float) $answer->fraction <= 0) {
                $wrong[] = $answer;
            }
        }
        if ($correct === [] || count($wrong) < 4) {
            return 'unusable';
        }

        $used = array_merge([$correct[0]], array_slice($wrong, 0, 4));
        $texts = [(string) $row->questiontext, (string) $row->generalfeedback];
        foreach ($used as $answer) {
            $texts[] = (string) $answer->answer;
        }
        foreach ($texts as $html) {
            if (preg_match(self::MEDIA_PATTERN, $html)) {
                return 'images';
            }
        }

        $question = [
            'questiontext'    => $this->flatten((string) $row->questiontext, (int) $row->questiontextformat),
            'correct'         => $this->flatten((string) $correct[0]->answer, (int) $correct[0]->answerformat),
            'distractors'     => array_map(
                fn(\stdClass $answer): string => $this->flatten((string) $answer->answer, (int) $answer->answerformat),
                array_slice($wrong, 0, 4)
            ),
            'generalfeedback' => $this->flatten((string) $row->generalfeedback, (int) $row->generalfeedbackformat),
        ];
        // A text that is empty once flattened (only tags, for instance) leaves nothing to show.
        $hasblank = $question['questiontext'] === '' || $question['correct'] === ''
            || in_array('', $question['distractors'], true);
        if ($hasblank) {
            return 'unusable';
        }
        return $question;
    }

    /**
     * Turns a text of the bank, in any format, into the plain single-line text a cartridge holds.
     *
     * @param string $content The text.
     * @param int $format Its format.
     * @return string
     */
    private function flatten(string $content, int $format): string {
        if ($format === FORMAT_MARKDOWN) {
            $content = markdown_to_html($content);
        }
        if ($format !== FORMAT_PLAIN) {
            // The core text conversion writes bold and heading text in capitals, which changes a statement.
            $content = preg_replace('~<\s*(br|/p|/div|/li|/tr|/h[1-6])\b[^>]*>~i', ' ', $content);
            $content = html_entity_decode(strip_tags((string) $content), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        return trim((string) preg_replace('/[\s\x{00A0}]+/u', ' ', $content));
    }
}
