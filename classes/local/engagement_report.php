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
    /** @var int Students per query when they are listed as parameters, well below what a database takes. */
    private const USER_CHUNK = 1000;

    /** @var string[] Plugin module names that identify a "Player course". */
    private const PLAYER_MODULES = ['playerwords', 'playerraid', 'playerpuzzle', 'playergroup'];

    /**
     * Returns the log reader the event counts come from, or null when the site keeps no readable log.
     *
     * @return \core\log\sql_reader|null The first enabled log store that can be queried.
     */
    public static function get_log_reader(): ?\core\log\sql_reader {
        $readers = get_log_manager()->get_readers('\core\log\sql_reader');
        $reader  = reset($readers);
        return $reader ?: null;
    }

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
     * Returns the distinct students of the courses: a role of the gradebook roles in the course and an
     * enrolment that is active now, in an enabled enrolment method.
     *
     * @param int[] $courseids The courses.
     * @return int[] User ids.
     */
    private function get_student_ids(array $courseids): array {
        global $CFG, $DB;

        $roleids = array_filter(array_map('intval', explode(',', (string) ($CFG->gradebookroles ?? ''))));
        if ($courseids === [] || $roleids === []) {
            return [];
        }

        [$incourses, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'sc');
        [$inroles, $roleparams] = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED, 'sr');
        $params += $roleparams;
        $params['now'] = time();
        $params['active'] = ENROL_USER_ACTIVE;
        $params['enabled'] = ENROL_INSTANCE_ENABLED;
        $params['contextlevel'] = CONTEXT_COURSE;

        $ids = $DB->get_fieldset_sql(
            "SELECT DISTINCT ue.userid
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
               JOIN {context} ctx ON ctx.instanceid = e.courseid AND ctx.contextlevel = :contextlevel
               JOIN {role_assignments} ra ON ra.contextid = ctx.id AND ra.userid = ue.userid
               JOIN {user} u ON u.id = ue.userid
              WHERE e.courseid $incourses
                AND ra.roleid $inroles
                AND ue.status = :active
                AND e.status = :enabled
                AND (ue.timestart = 0 OR ue.timestart <= :now)
                AND (ue.timeend = 0 OR ue.timeend > :now2)
                AND u.deleted = 0
                AND u.suspended = 0",
            $params + ['now2' => $params['now']]
        );
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

        // The metrics are per enrolled student: the users with a student role in the course and an active
        // enrolment. Teachers, managers and suspended or expired enrolments belong to no side of the ratios.
        $studentids = $this->get_student_ids($courseids);
        $totalusers = count($studentids);

        // Log events of those students in these courses during the selected period, from whichever log
        // store the site reads reports from.
        $totalevents = 0;
        $reader      = self::get_log_reader();
        if ($reader !== null) {
            [$inevents, $evparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'ev');
            $evparams['evsince'] = $since;
            // The log's course column covers the course page and everything inside it. Filtering by the
            // course context would keep only the course page and drop the activities' own events.
            // The students go in slices: a site can have more of them than one query takes as parameters.
            foreach (array_chunk($studentids, self::USER_CHUNK) as $slice) {
                [$inusers, $userparams] = $DB->get_in_or_equal($slice, SQL_PARAMS_NAMED, 'evu');
                $totalevents += (int) $reader->get_events_select_count(
                    "courseid $inevents AND timecreated > :evsince AND userid $inusers",
                    $evparams + $userparams
                );
            }
        }

        $eventsperuser = $totalusers > 0 ? round($totalevents / $totalusers, 1) : 0.0;

        // Students who completed at least one course in the group. Counting users, not completions,
        // keeps the rate comparable with the distinct students it is divided by.
        $completed = 0;
        [$incomp, $compparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'cc');
        foreach (array_chunk($studentids, self::USER_CHUNK) as $slice) {
            [$inusers, $userparams] = $DB->get_in_or_equal($slice, SQL_PARAMS_NAMED, 'ccu');
            $completed += (int) $DB->get_field_sql(
                "SELECT COUNT(DISTINCT cc.userid)
                   FROM {course_completions} cc
                  WHERE cc.course $incomp
                    AND cc.timecompleted IS NOT NULL
                    AND cc.userid $inusers",
                $compparams + $userparams
            );
        }
        $completionrate = $totalusers > 0 ? round($completed / $totalusers * 100, 1) : 0.0;

        // Average course-level grade of those students, expressed as a percentage. The sum and the count are
        // taken slice by slice and divided at the end: an average of the slices' averages would be wrong.
        $gradesum = 0.0;
        $gradecount = 0;
        [$ingrade, $gradeparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'gr');
        foreach (array_chunk($studentids, self::USER_CHUNK) as $slice) {
            [$inusers, $userparams] = $DB->get_in_or_equal($slice, SQL_PARAMS_NAMED, 'gru');
            $row = $DB->get_record_sql(
                "SELECT SUM(gg.finalgrade / gi.grademax * 100) AS total, COUNT(gg.id) AS amount
                   FROM {grade_items} gi
                   JOIN {grade_grades} gg ON gg.itemid = gi.id
                  WHERE gi.itemtype = 'course'
                    AND gi.courseid $ingrade
                    AND gg.finalgrade IS NOT NULL
                    AND gi.grademax > 0
                    AND gg.userid $inusers",
                $gradeparams + $userparams
            );
            $gradesum += (float) $row->total;
            $gradecount += (int) $row->amount;
        }

        return [
            'course_count'    => count($courseids),
            'events_per_user' => $eventsperuser,
            'completion_rate' => $completionrate,
            'grade_avg'       => $gradecount > 0 ? round($gradesum / $gradecount, 1) : 0.0,
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
