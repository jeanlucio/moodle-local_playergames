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
 * Which question bank categories the games may draw from.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\local;

use context;
use context_system;

/**
 * The question bank categories Moodle keeps for reuse across the site.
 *
 * The games are site-wide and have no course, so a category chosen for one is made available to every
 * player. Only a category Moodle itself keeps for sharing may be chosen: a shared question bank on
 * Moodle 5 (an instance of the question bank activity, apart from the internal preview one), or a
 * category of the system context on Moodle 4.5. A category that belongs to a course or to a quiz is
 * private to it and is never offered, whoever asks for it.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class question_bank_reuse {
    /** @var string The permission to use the questions of a bank in one's own quizzes. */
    private const CAPABILITY = 'moodle/question:useall';

    /**
     * Every category kept for reuse, with its bank and how many multiple-choice questions it holds.
     *
     * @return \stdClass[] Keyed by category id: id, name, contextid, bank (empty on Moodle 4.5), questions.
     */
    public static function categories(): array {
        global $DB;

        if ($DB->get_manager()->table_exists('qbank')) {
            $sql = "SELECT qc.id, qc.name, qc.contextid, qb.name AS bank
                      FROM {question_categories} qc
                      JOIN {context} ctx ON ctx.id = qc.contextid AND ctx.contextlevel = :modlevel
                      JOIN {course_modules} cm ON cm.id = ctx.instanceid AND cm.deletioninprogress = 0
                      JOIN {modules} m ON m.id = cm.module AND m.name = 'qbank'
                      JOIN {qbank} qb ON qb.id = cm.instance AND qb.type <> 'preview'
                     WHERE qc.parent <> 0
                  ORDER BY qb.name, qc.name";
            $params = ['modlevel' => CONTEXT_MODULE];
        } else {
            $sql = "SELECT qc.id, qc.name, qc.contextid, '' AS bank
                      FROM {question_categories} qc
                     WHERE qc.contextid = :systemctx AND qc.parent <> 0
                  ORDER BY qc.name";
            $params = ['systemctx' => context_system::instance()->id];
        }
        $categories = $DB->get_records_sql($sql, $params);
        if ($categories === []) {
            return [];
        }

        [$insql, $inparams] = $DB->get_in_or_equal(array_keys($categories), SQL_PARAMS_NAMED);
        $counts = $DB->get_records_sql(
            "SELECT qbe.questioncategoryid AS id, COUNT(DISTINCT qbe.id) AS total
               FROM {question_bank_entries} qbe
               JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
               JOIN {question} q ON q.id = qv.questionid
              WHERE qbe.questioncategoryid $insql AND q.qtype = 'multichoice' AND qv.status = 'ready'
           GROUP BY qbe.questioncategoryid",
            $inparams
        );
        foreach ($categories as $category) {
            $category->questions = isset($counts[$category->id]) ? (int) $counts[$category->id]->total : 0;
        }
        return $categories;
    }

    /**
     * The categories a user may choose: the ones kept for reuse, where they may use every question.
     *
     * @param int $userid The user.
     * @return \stdClass[] Keyed by category id.
     */
    public static function categories_for_user(int $userid): array {
        return array_filter(
            self::categories(),
            static fn(\stdClass $category): bool => has_capability(
                self::CAPABILITY,
                context::instance_by_id((int) $category->contextid),
                $userid
            )
        );
    }

    /**
     * Whether a category is kept for reuse, whoever is asking.
     *
     * @param int $categoryid The category id.
     * @return bool
     */
    public static function is_shared(int $categoryid): bool {
        return $categoryid > 0 && isset(self::categories()[$categoryid]);
    }

    /**
     * Whether a user may choose a category: it is kept for reuse and they may use its questions.
     *
     * @param int $categoryid The category id.
     * @param int $userid The user.
     * @return bool
     */
    public static function can_choose(int $categoryid, int $userid): bool {
        return isset(self::categories_for_user($userid)[$categoryid]);
    }
}
