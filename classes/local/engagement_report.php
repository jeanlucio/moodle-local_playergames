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
 * Engagement comparison report between Player and non-Player courses.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\local;

/**
 * Computes engagement metrics comparing courses with and without Player plugins.
 */
class engagement_report {
    /** @var string[] Plugin module names that identify a "Player course". */
    private const PLAYER_MODULES = ['playerwords', 'playerraid', 'playerpuzzle', 'playergroup'];

    /**
     * Returns IDs of all courses that have at least one Player plugin installed.
     *
     * A course qualifies when it has the block_playerhud block OR any mod_player* activity.
     *
     * @return int[]
     */
    public function get_player_courseids(): array {
        global $DB;

        $blockids = $DB->get_fieldset_sql(
            "SELECT DISTINCT ctx.instanceid
               FROM {block_instances} bi
               JOIN {context} ctx ON ctx.id = bi.parentcontextid
              WHERE bi.blockname = 'playerhud'
                AND ctx.contextlevel = :ctxlevel",
            ['ctxlevel' => CONTEXT_COURSE]
        );

        [$insql, $modparams] = $DB->get_in_or_equal(self::PLAYER_MODULES, SQL_PARAMS_NAMED, 'mod');
        $modids = $DB->get_fieldset_sql(
            "SELECT DISTINCT cm.course
               FROM {course_modules} cm
               JOIN {modules} m ON m.id = cm.module
              WHERE m.name $insql",
            $modparams
        );

        return array_values(array_unique(array_merge(
            array_map('intval', $blockids),
            array_map('intval', $modids)
        )));
    }

    /**
     * Returns IDs of courses where the given user has editing-teacher capability.
     *
     * @param int $userid
     * @return int[]
     */
    public function get_teacher_courseids(int $userid): array {
        $courses = get_user_capability_course('moodle/course:update', $userid, true, 'id');
        if (!$courses) {
            return [];
        }
        return array_map('intval', array_column($courses, 'id'));
    }

    /**
     * Returns all course IDs on the site (excluding site course).
     *
     * @return int[]
     */
    private function get_all_courseids(): array {
        global $DB;
        $ids = $DB->get_fieldset_select('course', 'id', 'id != :siteid', ['siteid' => SITEID]);
        return array_map('intval', $ids);
    }

    /**
     * Calculates engagement metrics for a given set of courses over a time window.
     *
     * @param int[] $courseids List of course IDs to analyse.
     * @param int   $days      Number of days to look back for log events.
     * @return array{course_count:int, events_per_user:float, completion_rate:float, grade_avg:float}
     */
    public function get_metrics(array $courseids, int $days): array {
        global $DB;

        if (empty($courseids)) {
            return [
                'course_count'    => 0,
                'events_per_user' => 0.0,
                'completion_rate' => 0.0,
                'grade_avg'       => 0.0,
            ];
        }

        $since = time() - ($days * DAYSECS);

        // Total log events in these courses during the selected period.
        [$inevents, $evparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'ev');
        $evparams['evlevel'] = CONTEXT_COURSE;
        $evparams['evsince'] = $since;
        $totalevents = (int) $DB->get_field_sql(
            "SELECT COUNT(l.id)
               FROM {logstore_standard_log} l
               JOIN {context} ctx ON ctx.id = l.contextid
              WHERE ctx.contextlevel = :evlevel
                AND ctx.instanceid $inevents
                AND l.timecreated > :evsince",
            $evparams
        );

        // Total distinct enrolled users across these courses.
        [$inusers, $userparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'eu');
        $totalusers = (int) $DB->get_field_sql(
            "SELECT COUNT(DISTINCT ue.userid)
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE e.courseid $inusers",
            $userparams
        );

        $eventsperuser = $totalusers > 0 ? round($totalevents / $totalusers, 1) : 0.0;

        // Users who completed at least one course in the group.
        [$incomp, $compparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'cc');
        $completed = (int) $DB->get_field_sql(
            "SELECT COUNT(cc.id)
               FROM {course_completions} cc
              WHERE cc.course $incomp
                AND cc.timecompleted IS NOT NULL",
            $compparams
        );
        $completionrate = $totalusers > 0 ? round($completed / $totalusers * 100, 1) : 0.0;

        // Average course-level grade expressed as a percentage.
        [$ingrade, $gradeparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'gr');
        $gradeavgraw = $DB->get_field_sql(
            "SELECT AVG(gg.finalgrade / gi.grademax * 100)
               FROM {grade_items} gi
               JOIN {grade_grades} gg ON gg.itemid = gi.id
              WHERE gi.itemtype = 'course'
                AND gi.courseid $ingrade
                AND gg.finalgrade IS NOT NULL
                AND gi.grademax > 0",
            $gradeparams
        );

        return [
            'course_count'    => count($courseids),
            'events_per_user' => $eventsperuser,
            'completion_rate' => $completionrate,
            'grade_avg'       => $gradeavgraw !== false ? round((float) $gradeavgraw, 1) : 0.0,
        ];
    }

    /**
     * Compares metrics between courses that have Player plugins and those that do not.
     *
     * @param int        $days      Analysis window in days.
     * @param int[]|null $scopeids  When set, only these course IDs are considered (teacher scope).
     * @return array{with:array, without:array}
     */
    public function compare(int $days, ?array $scopeids = null): array {
        $playerids = $this->get_player_courseids();
        $playeridset = array_flip($playerids);

        if ($scopeids !== null) {
            $withplayer    = array_values(array_filter($scopeids, fn($id) => isset($playeridset[$id])));
            $withoutplayer = array_values(array_filter($scopeids, fn($id) => !isset($playeridset[$id])));
        } else {
            $allids        = $this->get_all_courseids();
            $withplayer    = array_values(array_filter($allids, fn($id) => isset($playeridset[$id])));
            $withoutplayer = array_values(array_filter($allids, fn($id) => !isset($playeridset[$id])));
        }

        return [
            'with'    => $this->get_metrics($withplayer, $days),
            'without' => $this->get_metrics($withoutplayer, $days),
        ];
    }
}
