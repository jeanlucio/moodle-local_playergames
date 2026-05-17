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
 * CLI seed: creates demo courses and engagement data for the engagement meter.
 *
 * Run from Moodle root:
 *   php local/playergames/cli/seed_engagement_meter.php
 *   php local/playergames/cli/seed_engagement_meter.php --reset
 *
 * --reset deletes existing seed data and recreates it from scratch.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/testing/generator/lib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/gradelib.php');

// Shortnames used to identify seed data for idempotency and cleanup.
define('PGEM_COURSE_A_SHORT', 'pg-engmeter-seed-player');
define('PGEM_COURSE_B_SHORT', 'pg-engmeter-seed-standard');
define('PGEM_USER_PREFIX', 'pgengmeter_');
define('PGEM_STUDENT_COUNT', 8);

// Events generated per student per course (drives the events_per_user metric).
define('PGEM_EVENTS_A', 25);
define('PGEM_EVENTS_B', 8);

// Grades (0-100) per student index. Course A = higher, Course B = lower.
$gradeplan = [
    PGEM_COURSE_A_SHORT => [72, 78, 85, 91, 88, 74, 95, 69],
    PGEM_COURSE_B_SHORT => [58, 43, 67, 51, 40, 62, 48, 55],
];

// Student indices (0-based) that complete each course.
$completionplan = [
    PGEM_COURSE_A_SHORT => [0, 1, 2, 3, 4, 5, 6],
    PGEM_COURSE_B_SHORT => [0, 1, 2],
];

// Helpers.

$reset = in_array('--reset', $argv ?? [], true);

/**
 * Writes a line to stdout.
 *
 * @param string $msg Message to print.
 */
function pgem_write(string $msg): void {
    cli_writeln($msg);
}

/**
 * Deletes all seed courses and users created by this script.
 */
function pgem_teardown(): void {
    global $DB;

    foreach ([PGEM_COURSE_A_SHORT, PGEM_COURSE_B_SHORT] as $short) {
        $course = $DB->get_record('course', ['shortname' => $short]);
        if ($course) {
            delete_course($course->id, false);
            pgem_write("  Deleted course: $short");
        }
    }

    $like = $DB->sql_like('username', ':prefix');
    $users = $DB->get_records_select('user', "$like AND deleted = 0", ['prefix' => PGEM_USER_PREFIX . '%']);
    foreach ($users as $user) {
        user_delete_user($user);
    }
    if ($users) {
        pgem_write('  Deleted ' . count($users) . ' demo users.');
    }
}

// Guard: detect existing seed data.
if ($DB->record_exists('course', ['shortname' => PGEM_COURSE_A_SHORT])) {
    if ($reset) {
        pgem_write('Removing existing seed data...');
        pgem_teardown();
    } else {
        pgem_write('Seed data already exists. Use --reset to recreate it.');
        pgem_write('');
        pgem_write('  URL: /local/playergames/engagement_meter.php');
        exit(0);
    }
}

// Step 1: create students.
pgem_write('Creating ' . PGEM_STUDENT_COUNT . ' demo students...');
$gen = new testing_data_generator();
$users = [];

for ($i = 1; $i <= PGEM_STUDENT_COUNT; $i++) {
    $users[] = $gen->create_user([
        'username' => PGEM_USER_PREFIX . $i,
        'firstname' => 'Seed',
        'lastname' => "Student $i",
        'email' => 'pgengmeter' . $i . '@example.invalid',
    ]);
}

$userids = array_column($users, 'id');
pgem_write('  ' . count($users) . ' students created.');

// Step 2: create courses.
pgem_write('Creating demo courses...');

$defaultcategory = $DB->get_field_select('course_categories', 'id', 'parent = 0', [], IGNORE_MISSING) ?: 1;
$startdate = strtotime('-60 days');

$coursea = $gen->create_course([
    'fullname' => '[Seed] Curso com Player (Engaj)',
    'shortname' => PGEM_COURSE_A_SHORT,
    'category' => $defaultcategory,
    'enablecompletion' => 1,
    'startdate' => $startdate,
]);

$courseb = $gen->create_course([
    'fullname' => '[Seed] Curso Padrao (Engaj)',
    'shortname' => PGEM_COURSE_B_SHORT,
    'category' => $defaultcategory,
    'enablecompletion' => 1,
    'startdate' => $startdate,
]);

pgem_write("  Created: {$coursea->fullname}");
pgem_write("  Created: {$courseb->fullname}");

// Step 3: add block_playerhud to Course A.
$ctxa = context_course::instance($coursea->id);
$ctxb = context_course::instance($courseb->id);

if ($DB->record_exists('block', ['name' => 'playerhud'])) {
    $blockrecord = new stdClass();
    $blockrecord->blockname = 'playerhud';
    $blockrecord->parentcontextid = $ctxa->id;
    $blockrecord->showinsubcontexts = 0;
    $blockrecord->pagetypepattern = 'course-view-*';
    $blockrecord->subpagepattern = null;
    $blockrecord->defaultregion = 'side-pre';
    $blockrecord->defaultweight = 0;
    $blockrecord->configdata = '';
    $blockrecord->timecreated = time();
    $blockrecord->timemodified = time();
    $DB->insert_record('block_instances', $blockrecord);
    pgem_write('  Added block_playerhud to Course A.');
} else {
    pgem_write('  Warning: block_playerhud not installed.');
    pgem_write('  Course A will not be detected as a Player course by the engagement meter.');
}

// Step 4: enrol students in both courses.
pgem_write('Enrolling students in both courses...');

foreach ($userids as $uid) {
    $gen->enrol_user($uid, $coursea->id, 'student');
    $gen->enrol_user($uid, $courseb->id, 'student');
}

pgem_write('  ' . count($userids) . ' students enrolled in each course.');

// Step 5: generate log events.
pgem_write('Generating log events...');

$now = time();
$since28 = $now - (28 * DAYSECS);

$eventsets = [
    [$coursea, $ctxa, PGEM_EVENTS_A],
    [$courseb, $ctxb, PGEM_EVENTS_B],
];

$totalevents = 0;
foreach ($eventsets as [$course, $ctx, $count]) {
    foreach ($userids as $uid) {
        $logrows = [];
        for ($e = 0; $e < $count; $e++) {
            $logrows[] = [
                'eventname' => '\core\event\course_viewed',
                'component' => 'core',
                'action' => 'viewed',
                'target' => 'course',
                'objecttable' => 'course',
                'objectid' => $course->id,
                'crud' => 'r',
                'edulevel' => 2,
                'contextid' => $ctx->id,
                'contextlevel' => CONTEXT_COURSE,
                'contextinstanceid' => $course->id,
                'userid' => $uid,
                'courseid' => $course->id,
                'relateduserid' => null,
                'anonymous' => 0,
                'other' => null,
                'timecreated' => rand($since28, $now),
                'ip' => '127.0.0.1',
                'realuserid' => null,
                'origin' => 'web',
            ];
        }
        $DB->insert_records('logstore_standard_log', $logrows);
        $totalevents += $count;
    }
}

pgem_write("  Inserted $totalevents log events.");

// Step 6: generate course completions.
pgem_write('Generating course completions...');

$totalcompletions = 0;
foreach ([$coursea, $courseb] as $course) {
    $indices = $completionplan[$course->shortname];
    foreach ($indices as $idx) {
        $uid = $userids[$idx] ?? null;
        if ($uid === null) {
            continue;
        }
        $completion = new stdClass();
        $completion->userid = $uid;
        $completion->course = $course->id;
        $completion->timeenrolled = $now - (55 * DAYSECS);
        $completion->timestarted = $now - (50 * DAYSECS);
        $completion->timecompleted = $now - rand(1, 20) * DAYSECS;
        $completion->reaggregate = 0;
        $DB->insert_record('course_completions', $completion);
        $totalcompletions++;
    }
}

pgem_write("  Inserted $totalcompletions completion records.");

// Step 7: generate course grades.
pgem_write('Generating course grades...');

$gradesinserted = 0;
foreach ([$coursea, $courseb] as $course) {
    $gradeitem = grade_item::fetch_course_item($course->id);
    if (!$gradeitem || !$gradeitem->id) {
        pgem_write("  Warning: could not get grade item for {$course->shortname}; skipping grades.");
        continue;
    }

    $grades = $gradeplan[$course->shortname];
    foreach (array_values($userids) as $idx => $uid) {
        $finalgrade = (float) ($grades[$idx] ?? 50);
        $graderow = new stdClass();
        $graderow->itemid = $gradeitem->id;
        $graderow->userid = $uid;
        $graderow->finalgrade = $finalgrade;
        $graderow->rawgrade = $finalgrade;
        $graderow->rawgrademax = 100.0;
        $graderow->rawgrademin = 0.0;
        $graderow->information = '';
        $graderow->informationformat = FORMAT_PLAIN;
        $graderow->timecreated = $now - rand(1, 15) * DAYSECS;
        $graderow->timemodified = $now;
        $DB->insert_record('grade_grades', $graderow);
        $gradesinserted++;
    }
}

pgem_write("  Inserted $gradesinserted grade records.");

// Summary.
$avga = round(array_sum($gradeplan[PGEM_COURSE_A_SHORT]) / PGEM_STUDENT_COUNT, 1);
$avgb = round(array_sum($gradeplan[PGEM_COURSE_B_SHORT]) / PGEM_STUDENT_COUNT, 1);
$compa = round(count($completionplan[PGEM_COURSE_A_SHORT]) / PGEM_STUDENT_COUNT * 100, 1);
$compb = round(count($completionplan[PGEM_COURSE_B_SHORT]) / PGEM_STUDENT_COUNT * 100, 1);

pgem_write('');
pgem_write(str_repeat('-', 66));
pgem_write('Seed complete. Expected values in the Engagement Meter (last 30 d):');
pgem_write('');
pgem_write(sprintf('  %-34s  %12s  %12s', 'Metric', 'With Player', 'Without'));
pgem_write(sprintf('  %-34s  %12s  %12s', str_repeat('-', 34), str_repeat('-', 12), str_repeat('-', 12)));
pgem_write(sprintf('  %-34s  %12s  %12s', 'Events per enrolled student', PGEM_EVENTS_A . '.0', PGEM_EVENTS_B . '.0'));
pgem_write(sprintf('  %-34s  %12s  %12s', 'Course completion rate', $compa . '%', $compb . '%'));
pgem_write(sprintf('  %-34s  %12s  %12s', 'Average course grade', $avga . '%', $avgb . '%'));
pgem_write('');
pgem_write('  URL: /local/playergames/engagement_meter.php');
pgem_write(str_repeat('-', 66));
