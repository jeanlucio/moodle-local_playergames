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
 * Player achievements page — grid of all achievements with earned/locked state.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

require_login();

$context = context_system::instance();
require_capability('local/playergames:viewhub', $context);

// Respect the per-user gamification opt-out.
if (!\local_playergames\local\preferences::is_gamification_enabled($USER->id)) {
    redirect(
        new moodle_url('/my/'),
        get_string('gamification_disabled_notice', 'local_playergames'),
        null,
        \core\output\notification::NOTIFY_INFO
    );
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/playergames/achievements.php'));
$PAGE->set_title(get_string('achievements_pagetitle', 'local_playergames'));
$PAGE->set_heading(get_string('pluginname', 'local_playergames'));
$PAGE->set_pagelayout('base');

$achdata = new \local_playergames\output\achievements($USER->id);
$output  = $PAGE->get_renderer('core');

echo $output->header();
echo $output->render_from_template(
    'local_playergames/nav_header',
    (new \local_playergames\output\nav_header('achievements'))->export_for_template($output)
);
echo $output->render_from_template(
    'local_playergames/achievements',
    $achdata->export_for_template($output)
);
echo $output->footer();
