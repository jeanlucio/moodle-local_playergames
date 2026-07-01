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
 * Renderable for the user's own activity history page.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\output;

use local_playergames\hub\activity_log as activity_log_manager;
use moodle_url;
use paging_bar;
use renderable;
use renderer_base;
use stdClass;
use templatable;

/**
 * Paginated, chronological list of a user's own local_playergames_activity_log rows.
 *
 * A single table, unlike block_playerhud's 3-table UNION ALL history — PlayerGames
 * events are already homogeneous signed-delta rows, so a simple ORDER BY suffices.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class activity_log implements renderable, templatable {
    /** @var int Rows fetched per page. */
    private const PER_PAGE = 30;

    /** @var array<string, string> Lang key per game type source, for season_xp rows. */
    private const GAME_NAME_KEYS = [
        'quiz'    => 'hub_game_quiz',
        'guess'   => 'hub_game_guess',
        'fill'    => 'hub_game_fill',
        'battle'  => 'hub_game_battle',
        'checkin' => 'activity_log_source_checkin',
        'mission' => 'activity_log_source_mission',
    ];

    /** @var int User whose log is being displayed. */
    private int $userid;

    /** @var int Zero-based page number. */
    private int $page;

    /**
     * Constructs the renderable.
     *
     * @param int $userid User whose activity log is displayed.
     * @param int $page Zero-based page number.
     */
    public function __construct(int $userid, int $page = 0) {
        $this->userid = $userid;
        $this->page   = $page;
    }

    /**
     * Exports the paginated activity list for the Mustache template.
     *
     * @param renderer_base $output Moodle renderer, used to render the paging bar.
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        global $DB;

        $total = $DB->count_records('local_playergames_activity_log', ['userid' => $this->userid]);

        $rows = $DB->get_records(
            'local_playergames_activity_log',
            ['userid' => $this->userid],
            'timecreated DESC, id DESC',
            '*',
            $this->page * self::PER_PAGE,
            self::PER_PAGE
        );

        $coursenames = $this->bulk_course_names($rows);

        $entries = [];
        foreach ($rows as $row) {
            $entries[] = $this->build_entry($row, $coursenames);
        }

        $pagingbar = new paging_bar($total, $this->page, self::PER_PAGE, new moodle_url('/local/playergames/history.php'));

        return [
            'str_pagetitle'      => get_string('history_pagetitle', 'local_playergames'),
            'str_col_date'       => get_string('history_col_date', 'local_playergames'),
            'str_col_description' => get_string('history_col_description', 'local_playergames'),
            'str_col_xp'         => get_string('history_col_xp', 'local_playergames'),
            'str_empty'          => get_string('history_empty', 'local_playergames'),
            'entries'            => $entries,
            'hasentries'         => !empty($entries),
            'pagingbar'          => $output->render($pagingbar),
        ];
    }

    /**
     * Bulk-fetches display names for every course referenced by the given rows.
     *
     * @param stdClass[] $rows Rows from local_playergames_activity_log.
     * @return array<int, string> Course id => formatted fullname.
     */
    private function bulk_course_names(array $rows): array {
        global $DB;

        $courseids = [];
        foreach ($rows as $row) {
            if ((int) $row->courseid > 0) {
                $courseids[(int) $row->courseid] = true;
            }
        }
        if (empty($courseids)) {
            return [];
        }

        $courses = $DB->get_records_list('course', 'id', array_keys($courseids), '', 'id, fullname');
        $names   = [];
        foreach ($courses as $id => $course) {
            $names[$id] = format_string($course->fullname);
        }
        return $names;
    }

    /**
     * Builds one template-ready row.
     *
     * @param stdClass $row Row from local_playergames_activity_log.
     * @param array<int, string> $coursenames Bulk-fetched course names.
     * @return array
     */
    private function build_entry(stdClass $row, array $coursenames): array {
        $xpdelta = (int) $row->xpdelta;
        return [
            'timeformatted'  => userdate((int) $row->timecreated, get_string('strftimedatetime', 'langconfig')),
            'description'    => $this->describe($row, $coursenames),
            'hasxp'          => $xpdelta !== 0,
            'xpdelta_display' => ($xpdelta > 0 ? '+' : '') . $xpdelta,
            'ispositive'     => $xpdelta > 0,
            'isnegative'     => $xpdelta < 0,
        ];
    }

    /**
     * Builds the human-readable description for a row.
     *
     * Keyed by eventtype first (never by source alone), since the same source
     * value ('mission') means different things for season_xp vs. freeze_earned.
     *
     * @param stdClass $row Row from local_playergames_activity_log.
     * @param array<int, string> $coursenames Bulk-fetched course names.
     * @return string
     */
    private function describe(stdClass $row, array $coursenames): string {
        switch ($row->eventtype) {
            case activity_log_manager::TYPE_SEASON_XP:
                return get_string('activity_log_season_xp', 'local_playergames', $this->game_name($row->source));
            case activity_log_manager::TYPE_LEARNING_XP:
                $courseid = (int) $row->courseid;
                if ($courseid > 0 && isset($coursenames[$courseid])) {
                    return get_string(
                        'activity_log_learning_xp_course',
                        'local_playergames',
                        $coursenames[$courseid]
                    );
                }
                return get_string('activity_log_learning_xp', 'local_playergames');
            case activity_log_manager::TYPE_FREEZE_EARNED:
                return get_string('activity_log_freeze_earned', 'local_playergames');
            case activity_log_manager::TYPE_FREEZE_USED:
                return get_string('activity_log_freeze_used', 'local_playergames');
            case activity_log_manager::TYPE_STREAK_BROKEN:
                return get_string('activity_log_streak_broken', 'local_playergames');
            default:
                return $row->eventtype;
        }
    }

    /**
     * Returns the display name for a season_xp source (gametype).
     *
     * @param string $source Gametype value stored on the row.
     * @return string
     */
    private function game_name(string $source): string {
        $key = self::GAME_NAME_KEYS[$source] ?? null;
        return $key !== null ? get_string($key, 'local_playergames') : $source;
    }
}
