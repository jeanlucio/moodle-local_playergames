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
 * Serves the files embedded in question bank questions to the players.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\local;

use context;
use context_system;
use local_playergames\games\season_game_config;
use local_playergames\hub\season_manager;
use stored_file;

/**
 * The questions of a shared bank may hold images and other files, written as @@PLUGINFILE@@ in their
 * text and kept in the bank's context, where a player has no access. The quiz games rewrite those
 * references to this plugin's own file areas, and this class answers them: a file is served to a
 * participant only when it belongs to a ready question of the category the active season draws from, and
 * never from any other bank: a category merely being kept for reuse is not enough, or a player could read
 * the images of the question banks of courses they are not in.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class bank_file {
    /** @var string File area of the question text; the item id is the question id. */
    public const AREA_QUESTION = 'qbquestiontext';

    /** @var string File area of the general feedback; the item id is the question id. */
    public const AREA_FEEDBACK = 'qbfeedback';

    /** @var string File area of an answer; the item id is the answer id. */
    public const AREA_ANSWER = 'qbanswer';

    /** @var string[] Core question file area behind each file area of this plugin. */
    private const CORE_AREAS = [
        self::AREA_QUESTION => 'questiontext',
        self::AREA_FEEDBACK => 'generalfeedback',
        self::AREA_ANSWER   => 'answer',
    ];

    /**
     * Serves a question file, or ends the request with "file not found".
     *
     * @param context $context The context in the URL, which must be the one of a shared bank.
     * @param string $filearea File area of this plugin.
     * @param array $args The rest of the URL: item id, then the path of the file.
     * @param bool $forcedownload Whether to force a download.
     * @param array $options Options for send_stored_file().
     * @return bool False when there is nothing to serve; otherwise the request ends here.
     */
    public static function serve(context $context, string $filearea, array $args, bool $forcedownload, array $options): bool {
        require_login();
        require_capability('local/playergames:viewhub', context_system::instance());
        access::require_participant();

        $file = self::find($context, $filearea, $args);
        if ($file === null) {
            return false;
        }

        send_stored_file($file, DAYSECS, 0, $forcedownload, $options);
        return true;
    }

    /**
     * Finds the file a URL asks for, when it may be served.
     *
     * @param context $context The context in the URL.
     * @param string $filearea File area of this plugin.
     * @param array $args The rest of the URL: item id, then the path of the file.
     * @return stored_file|null Null when the area is unknown, the question is not a ready one of the category
     *     the active season draws from in that context, or the file does not exist.
     */
    public static function find(context $context, string $filearea, array $args): ?stored_file {
        global $DB;

        if (!isset(self::CORE_AREAS[$filearea]) || count($args) < 2) {
            return null;
        }

        $itemid   = (int) array_shift($args);
        $filename = array_pop($args);
        $filepath = $args === [] ? '/' : '/' . implode('/', $args) . '/';

        $questionid = $filearea === self::AREA_ANSWER
            ? (int) $DB->get_field('question_answers', 'question', ['id' => $itemid])
            : $itemid;
        $categoryid = $questionid > 0 ? $DB->get_field_sql(
            "SELECT qbe.questioncategoryid
               FROM {question_versions} qv
               JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
               JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
              WHERE qv.questionid = :questionid AND qc.contextid = :contextid AND qv.status = 'ready'",
            ['questionid' => $questionid, 'contextid' => $context->id]
        ) : false;
        if ($categoryid === false || !self::is_in_play((int) $categoryid)) {
            return null;
        }

        $file = get_file_storage()->get_file(
            $context->id,
            'question',
            self::CORE_AREAS[$filearea],
            $itemid,
            $filepath,
            $filename
        );

        return $file && !$file->is_directory() ? $file : null;
    }

    /**
     * Whether the active season draws questions from a category, for a game that is switched on.
     *
     * The bank is still checked as one kept for reuse, in case the category was moved or its bank removed
     * after the season was set up.
     *
     * @param int $categoryid The question category.
     * @return bool
     */
    private static function is_in_play(int $categoryid): bool {
        global $DB;

        $season = season_manager::get_active();
        if ($season === null || !question_bank_reuse::is_shared($categoryid)) {
            return false;
        }
        $rows = $DB->get_records(
            'local_playergames_season_games',
            ['seasonid' => (int) $season->id, 'enabled' => 1, 'auxid' => $categoryid],
            '',
            'id, source'
        );
        foreach ($rows as $row) {
            if (season_game_config::uses_secondary((string) $row->source)) {
                return true;
            }
        }
        return false;
    }
}
