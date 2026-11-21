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
 * Tests for the engagement comparison report.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\local;

/**
 * Unit tests for {@see engagement_report}.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_playergames\local\engagement_report
 */
final class engagement_report_test extends \advanced_testcase {
    public function test_get_metrics_empty_returns_zeros(): void {
        $this->resetAfterTest();
        $metrics = (new engagement_report())->get_metrics([], 30);

        $this->assertSame(0, $metrics['course_count']);
        $this->assertSame(0.0, $metrics['events_per_user']);
        $this->assertSame(0.0, $metrics['completion_rate']);
        $this->assertSame(0.0, $metrics['grade_avg']);
    }

    public function test_get_metrics_counts_courses(): void {
        $this->resetAfterTest();
        $c1 = $this->getDataGenerator()->create_course();
        $c2 = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $c1->id);

        $metrics = (new engagement_report())->get_metrics([(int) $c1->id, (int) $c2->id], 30);

        $this->assertSame(2, $metrics['course_count']);
        $this->assertIsFloat($metrics['events_per_user']);
        $this->assertIsFloat($metrics['completion_rate']);
    }

    public function test_get_player_courseids_excludes_plain_course(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();

        $ids = (new engagement_report())->get_player_courseids();

        $this->assertIsArray($ids);
        // A course with no Player block or activity is not a "Player course".
        $this->assertNotContains((int) $course->id, $ids);
    }

    public function test_compare_splits_scope_into_with_and_without(): void {
        $this->resetAfterTest();
        $c1 = $this->getDataGenerator()->create_course();
        $c2 = $this->getDataGenerator()->create_course();

        $result = (new engagement_report())->compare(30, [(int) $c1->id, (int) $c2->id]);

        // Neither course has Player plugins, so both fall in the "without" group.
        $this->assertSame(0, $result['with']['course_count']);
        $this->assertSame(2, $result['without']['course_count']);
    }

    /**
     * The completion rate counts users, so a user who finished two courses counts once.
     *
     * Regression test: completions were counted against distinct enrolled users, so one student who
     * finished two courses of the group produced a rate of 200%.
     */
    public function test_completion_rate_counts_users_not_completions(): void {
        global $CFG;
        $this->resetAfterTest();
        $CFG->enablecompletion = true;

        $generator = $this->getDataGenerator();
        $c1 = $generator->create_course(['enablecompletion' => 1]);
        $c2 = $generator->create_course(['enablecompletion' => 1]);
        $user = $generator->create_user();
        foreach ([$c1, $c2] as $course) {
            $generator->enrol_user($user->id, $course->id);
            (new \completion_completion(['course' => $course->id, 'userid' => $user->id]))->mark_complete();
        }

        $metrics = (new engagement_report())->get_metrics([(int) $c1->id, (int) $c2->id], 30);

        $this->assertSame(100.0, $metrics['completion_rate']);
    }

    /**
     * The events come from whichever log store the site reads reports from.
     *
     * Regression test: the standard log table was queried directly, so a site that reads another store
     * got zero engagement with no warning.
     */
    public function test_events_are_counted_through_the_log_reader(): void {
        $this->resetAfterTest();
        $this->preventResetByRollback();
        set_config('enabled_stores', 'logstore_standard', 'tool_log');
        set_config('buffersize', 0, 'logstore_standard');
        get_log_manager(true);

        $course = $this->getDataGenerator()->create_course();
        $user   = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        $this->setUser($user);
        \core\event\course_viewed::create(['context' => \context_course::instance($course->id)])->trigger();
        get_log_manager()->dispose();

        $this->assertNotNull(engagement_report::get_log_reader());
        $metrics = (new engagement_report())->get_metrics([(int) $course->id], 30);
        $this->assertGreaterThan(0.0, $metrics['events_per_user']);
    }

    /**
     * The events of the activities count, not only those of the course page.
     *
     * Regression test: the query filtered by the course context, so an activity's own events (views,
     * attempts, submissions) were left out and the metric measured little more than visits to the course page.
     */
    public function test_events_inside_activities_are_counted(): void {
        $this->resetAfterTest();
        $this->preventResetByRollback();
        set_config('enabled_stores', 'logstore_standard', 'tool_log');
        set_config('buffersize', 0, 'logstore_standard');
        get_log_manager(true);

        $course = $this->getDataGenerator()->create_course();
        $page   = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $user   = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        $this->setUser($user);
        get_log_manager()->dispose();
        $before = (new engagement_report())->get_metrics([(int) $course->id], 30)['events_per_user'];

        \mod_page\event\course_module_viewed::create([
            'objectid' => $page->id,
            'context'  => \context_module::instance($page->cmid),
        ])->trigger();
        get_log_manager()->dispose();
        $after = (new engagement_report())->get_metrics([(int) $course->id], 30)['events_per_user'];

        // One enrolled user and one more event, which lives in the module context.
        $this->assertSame($before + 1.0, $after);
    }

    /**
     * With no readable log store there is no reader, and the events metric is zero instead of an error.
     */
    public function test_without_a_log_reader_the_events_metric_is_zero(): void {
        $this->resetAfterTest();
        $this->preventResetByRollback();
        set_config('enabled_stores', '', 'tool_log');
        get_log_manager(true);

        $course = $this->getDataGenerator()->create_course();
        $user   = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $this->assertNull(engagement_report::get_log_reader());
        $this->assertSame(0.0, (new engagement_report())->get_metrics([(int) $course->id], 30)['events_per_user']);
    }

    /**
     * The teacher's courses are the ones where the user can update the course.
     */
    public function test_get_teacher_courseids_lists_only_the_courses_the_user_teaches(): void {
        $this->resetAfterTest();
        $taught = $this->getDataGenerator()->create_course();
        $attended = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $taught->id, 'editingteacher');
        $this->getDataGenerator()->enrol_user($teacher->id, $attended->id, 'student');
        $report = new engagement_report();

        $this->assertSame([(int) $taught->id], $report->get_teacher_courseids((int) $teacher->id));
        $this->assertSame([], $report->get_teacher_courseids((int) $this->getDataGenerator()->create_user()->id));
    }

    /**
     * The site-wide comparison takes every course but the site course.
     */
    /**
     * Without a scope, the comparison takes every course of the site, as the page of the administration asks.
     */
    public function test_compare_without_a_scope_takes_every_course(): void {
        $this->resetAfterTest();
        for ($i = 0; $i < 3; $i++) {
            $this->getDataGenerator()->create_course();
        }

        $result = (new engagement_report())->compare(30);

        $this->assertSame(0, $result['with']['course_count']);
        $this->assertSame(3, $result['without']['course_count']);
    }

    public function test_the_all_courses_scope_excludes_the_site_course(): void {
        $this->resetAfterTest();
        $first = $this->getDataGenerator()->create_course();
        $second = $this->getDataGenerator()->create_course();

        $method = new \ReflectionMethod(engagement_report::class, 'get_all_courseids');
        $ids = $method->invoke(new engagement_report());

        $this->assertEqualsCanonicalizing([(int) $first->id, (int) $second->id], $ids);
    }

    /**
     * The metrics are per active student: teachers, suspended and expired enrolments are not counted,
     * and neither are their events or their completions.
     *
     * Regression test: the divisor was every enrolment and the events were those of any user, so the
     * label "per enrolled student" described something else.
     */
    public function test_only_active_students_are_counted(): void {
        global $CFG;
        $this->resetAfterTest();
        $this->preventResetByRollback();
        $CFG->enablecompletion = true;
        set_config('enabled_stores', 'logstore_standard', 'tool_log');
        set_config('buffersize', 0, 'logstore_standard');
        get_log_manager(true);

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['enablecompletion' => 1]);
        $student = $generator->create_user();
        $teacher = $generator->create_user();
        $suspended = $generator->create_user();
        $expired = $generator->create_user();
        $generator->enrol_user($student->id, $course->id, 'student');
        $generator->enrol_user($teacher->id, $course->id, 'editingteacher');
        $generator->enrol_user($suspended->id, $course->id, 'student', 'manual', 0, 0, ENROL_USER_SUSPENDED);
        $generator->enrol_user($expired->id, $course->id, 'student', 'manual', time() - (2 * DAYSECS), time() - DAYSECS);
        foreach ([$student, $teacher, $suspended, $expired] as $user) {
            $this->setUser($user);
            \core\event\course_viewed::create(['context' => \context_course::instance($course->id)])->trigger();
        }
        foreach ([$teacher, $suspended] as $user) {
            (new \completion_completion(['course' => $course->id, 'userid' => $user->id]))->mark_complete();
        }
        get_log_manager()->dispose();

        $metrics = (new engagement_report())->get_metrics([(int) $course->id], 30);

        // One student, one event of theirs, and nobody of them completed the course.
        $this->assertSame(1.0, $metrics['events_per_user']);
        $this->assertSame(0.0, $metrics['completion_rate']);

        (new \completion_completion(['course' => $course->id, 'userid' => $student->id]))->mark_complete();
        $this->assertSame(100.0, (new engagement_report())->get_metrics([(int) $course->id], 30)['completion_rate']);
    }

    /**
     * The average grade is that of the active students: a teacher's, a suspended enrolment's and a former
     * student's course grades are left out, as in the other two metrics.
     */
    public function test_the_average_grade_counts_only_active_students(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $first = $generator->create_user();
        $second = $generator->create_user();
        $teacher = $generator->create_user();
        $suspended = $generator->create_user();
        $generator->enrol_user($first->id, $course->id, 'student');
        $generator->enrol_user($second->id, $course->id, 'student');
        $generator->enrol_user($teacher->id, $course->id, 'editingteacher');
        $generator->enrol_user($suspended->id, $course->id, 'student', 'manual', 0, 0, ENROL_USER_SUSPENDED);
        $item = \grade_item::fetch_course_item($course->id);
        foreach ([[$first, 80], [$second, 60], [$teacher, 100], [$suspended, 10]] as [$user, $grade]) {
            $item->update_final_grade($user->id, $grade);
        }

        $metrics = (new engagement_report())->get_metrics([(int) $course->id], 30);

        $this->assertSame(70.0, $metrics['grade_avg']);
    }

    /**
     * The comparison is kept per period and set of courses, whatever the order the courses come in.
     *
     * It counts the core log of every course in the period, which took over a second with two million log rows in a
     * load test, and the page was worked out again on every visit.
     */
    public function test_compare_is_kept_per_period_and_set_of_courses(): void {
        global $DB;
        $this->resetAfterTest();
        $c1 = (int) $this->getDataGenerator()->create_course()->id;
        $c2 = (int) $this->getDataGenerator()->create_course()->id;
        $report = new engagement_report();
        $first = $report->compare(30, [$c1, $c2]);

        $queries = $DB->perf_get_queries();
        $again = $report->compare(30, [$c2, $c1]);

        $this->assertSame($first, $again);
        $this->assertSame(0, $DB->perf_get_queries() - $queries);

        // Another period is worked out on its own.
        $queries = $DB->perf_get_queries();
        $report->compare(60, [$c1, $c2]);
        $this->assertGreaterThan($queries, $DB->perf_get_queries());
        $this->assertSame(1, $report->compare(30, [$c1])['without']['course_count']);
    }
}
