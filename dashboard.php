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
 * Player ecosystem dashboard entry point.
 *
 * Shows a quick-access nav grid and an SVG map of all Player plugins with
 * their installation status and dependency arrows.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_playergames\local\access;
use local_playergames\output\dashboard;

require_login();

$context = context_system::instance();

// The dashboard is the staff landing. Non-staff (students) go to the hub.
if (!access::is_staff()) {
    redirect(new moodle_url('/local/playergames/hub.php'));
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/playergames/dashboard.php'));
$PAGE->set_pagelayout('base');

$title = get_string('dashboard_pagetitle', 'local_playergames');
$PAGE->set_title($title);
$PAGE->set_heading(get_string('pluginname', 'local_playergames'));

$PAGE->requires->js_call_amd('local_playergames/dashboard', 'init');

echo $OUTPUT->header();
echo $OUTPUT->render_from_template(
    'local_playergames/nav_header',
    (new \local_playergames\output\nav_header('ecosystem'))->export_for_template($OUTPUT)
);

$renderable = new dashboard();
echo $OUTPUT->render_from_template(
    'local_playergames/dashboard',
    $renderable->export_for_template($OUTPUT)
);

echo $OUTPUT->footer();
