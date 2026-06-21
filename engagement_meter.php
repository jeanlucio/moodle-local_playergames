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
 * Engagement meter page: compares engagement in Player vs non-Player courses.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

require_login();

$context = context_system::instance();
$PAGE->set_context($context);

$days = optional_param('days', 30, PARAM_INT);
if (!in_array($days, [30, 60, 90], true)) {
    $days = 30;
}

// Scope is driven by the entry point, not by role. The site administration
// entry links to ?scope=all (every course); the PlayerGames dashboard card
// uses the default own-courses scope. This lets a user who is both admin and
// teacher see the site-wide view from the admin tree and their own-courses
// view from the dashboard.
$scope = optional_param('scope', 'own', PARAM_ALPHA);
if (!in_array($scope, ['own', 'all'], true)) {
    $scope = 'own';
}

$PAGE->set_url(new moodle_url(
    '/local/playergames/engagement_meter.php',
    ['days' => $days, 'scope' => $scope]
));
$PAGE->set_title(get_string('engmeter_pagetitle', 'local_playergames'));
$PAGE->set_heading(get_string('engmeter_pagetitle', 'local_playergames'));
$PAGE->set_pagelayout('base');

// Staff-only: site managers/admins, or teachers of at least one course.
if (!\local_playergames\local\access::is_staff()) {
    throw new moodle_exception('nopermissions', 'error', '', get_string('engmeter_pagetitle', 'local_playergames'));
}

$report = new \local_playergames\local\engagement_report();

// The capability guard stops a teacher forcing scope=all via the URL.
$canseeall = has_capability('moodle/site:config', $context);
if ($scope === 'all' && $canseeall) {
    $scopeids = null;
} else {
    $scopeids = $report->get_teacher_courseids($USER->id);
}

$data    = $report->compare($days, $scopeids);
$with    = $data['with'];
$without = $data['without'];

$calcdelta = function (float $withval, float $withoutval): string {
    if ($withoutval <= 0.0) {
        return '—';
    }
    $pct = (int) round(($withval - $withoutval) / $withoutval * 100);
    return ($pct >= 0 ? '+' : '') . $pct . '%';
};

$withplayerlabel    = get_string('engmeter_with_player', 'local_playergames');
$withoutplayerlabel = get_string('engmeter_without_player', 'local_playergames');

// Chart 1 — events per enrolled user.
$chartevents = new \core\chart_bar();

$seriesevents = new \core\chart_series(
    get_string('engmeter_chart_events', 'local_playergames'),
    [$with['events_per_user'], $without['events_per_user']]
);
$seriesevents->set_colors(['#198754', '#6c757d']);
$chartevents->add_series($seriesevents);
$chartevents->set_labels([$withplayerlabel, $withoutplayerlabel]);

// Chart 2 — course completion rate.
$chartcomp = new \core\chart_bar();

$seriescomp = new \core\chart_series(
    get_string('engmeter_chart_completion', 'local_playergames'),
    [$with['completion_rate'], $without['completion_rate']]
);
$seriescomp->set_colors(['#198754', '#6c757d']);
$chartcomp->add_series($seriescomp);
$chartcomp->set_labels([$withplayerlabel, $withoutplayerlabel]);

// Chart 3 — average course grade.
$chartgrade = new \core\chart_bar();

$seriesgrade = new \core\chart_series(
    get_string('engmeter_chart_grade', 'local_playergames'),
    [$with['grade_avg'], $without['grade_avg']]
);
$seriesgrade->set_colors(['#198754', '#6c757d']);
$chartgrade->add_series($seriesgrade);
$chartgrade->set_labels([$withplayerlabel, $withoutplayerlabel]);

$dayoptions = [
    ['value' => 30, 'label' => get_string('engmeter_days_30', 'local_playergames'), 'selected' => $days === 30],
    ['value' => 60, 'label' => get_string('engmeter_days_60', 'local_playergames'), 'selected' => $days === 60],
    ['value' => 90, 'label' => get_string('engmeter_days_90', 'local_playergames'), 'selected' => $days === 90],
];

$summary = [
    [
        'metric'        => get_string('engmeter_chart_events', 'local_playergames'),
        'with_value'    => (string) $with['events_per_user'],
        'without_value' => (string) $without['events_per_user'],
        'delta'         => $calcdelta($with['events_per_user'], $without['events_per_user']),
        'unit'          => '',
    ],
    [
        'metric'        => get_string('engmeter_chart_completion', 'local_playergames'),
        'with_value'    => $with['completion_rate'] . '%',
        'without_value' => $without['completion_rate'] . '%',
        'delta'         => $calcdelta($with['completion_rate'], $without['completion_rate']),
        'unit'          => '',
    ],
    [
        'metric'        => get_string('engmeter_chart_grade', 'local_playergames'),
        'with_value'    => $with['grade_avg'] . '%',
        'without_value' => $without['grade_avg'] . '%',
        'delta'         => $calcdelta($with['grade_avg'], $without['grade_avg']),
        'unit'          => '',
    ],
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template(
    'local_playergames/nav_header',
    (new \local_playergames\output\nav_header('engmeter'))->export_for_template($OUTPUT)
);

$templatedata = [
    'pageurl'         => (new moodle_url('/local/playergames/engagement_meter.php'))->out(false),
    'scope'           => $scope,
    'days'            => $days,
    'day_options'     => $dayoptions,
    'has_data'        => $with['course_count'] > 0 || $without['course_count'] > 0,
    'with_count'      => get_string('engmeter_courses_count', 'local_playergames', $with['course_count']),
    'without_count'   => get_string('engmeter_courses_count', 'local_playergames', $without['course_count']),
    'disclaimer'      => get_string('engmeter_disclaimer', 'local_playergames'),
    'chart_events'    => $OUTPUT->render($chartevents),
    'chart_completion' => $OUTPUT->render($chartcomp),
    'chart_grade'     => $OUTPUT->render($chartgrade),
    'summary'         => $summary,
];

echo $OUTPUT->render_from_template('local_playergames/engagement_meter', $templatedata);
echo $OUTPUT->footer();
