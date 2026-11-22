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
 * Deletes all the PlayerGames data, to start again from zero.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use core\output\notification;
use local_playergames\local\data_reset;

require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

$pageurl = new moodle_url('/local/playergames/reset_data.php');
$PAGE->set_context($context);
$PAGE->set_url($pageurl);
$PAGE->set_pagelayout('admin');
$pagetitle = get_string('reset_pagetitle', 'local_playergames');
$PAGE->set_title($pagetitle);
$PAGE->set_heading($pagetitle);

$confirmword = get_string('reset_confirm_word', 'local_playergames');

// A deletion that cannot be undone asks for a word typed by hand, not a click that could be a slip.
if (data_submitted()) {
    require_sesskey();
    $typed = core_text::strtoupper(trim(required_param('confirmword', PARAM_TEXT)));
    if ($typed !== core_text::strtoupper($confirmword)) {
        redirect($pageurl, get_string('reset_wrong_word', 'local_playergames'), null, notification::NOTIFY_ERROR);
    }
    $deleted = data_reset::run();
    redirect($pageurl, get_string('reset_done', 'local_playergames', $deleted), null, notification::NOTIFY_SUCCESS);
}

$counts = data_reset::counts();
$groups = [];
foreach ($counts as $group => $count) {
    $groups[] = [
        'name'  => get_string('reset_item_' . $group, 'local_playergames'),
        'count' => $count,
    ];
}
$total = array_sum($counts);

$output = $PAGE->get_renderer('core');
echo $output->header();
echo $output->render_from_template(
    'local_playergames/nav_header',
    (new \local_playergames\output\nav_header('admin'))->export_for_template($output)
);
echo $output->render_from_template('local_playergames/reset_data', [
    'formaction'  => $pageurl->out(false),
    'sesskey'     => sesskey(),
    'groups'      => $groups,
    'hasdata'     => $total > 0,
    'confirmword' => $confirmword,
    'backurl'     => (new moodle_url('/local/playergames/administration.php'))->out(false),
]);
echo $output->footer();
