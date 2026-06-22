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
 * Level ladder configuration page.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use core\output\notification;
use local_playergames\hub\level_manager;

require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

$pageurl = new moodle_url('/local/playergames/manage_levels.php');
$PAGE->set_context($context);
$PAGE->set_url($pageurl);
$PAGE->set_pagelayout('admin');

$pagetitle = get_string('levels_pagetitle', 'local_playergames');
$PAGE->set_title($pagetitle);
$PAGE->set_heading($pagetitle);

// POST: restore defaults.
if (data_submitted() && optional_param('action', '', PARAM_ALPHA) === 'restore') {
    require_sesskey();
    level_manager::restore_defaults();
    redirect($pageurl, get_string('levels_restored', 'local_playergames'), null, notification::NOTIFY_SUCCESS);
}

// POST: generate a linear ladder (fixed XP per level up to a maximum).
if (data_submitted() && optional_param('action', '', PARAM_ALPHA) === 'generate') {
    require_sesskey();
    $xpperlevel = optional_param('xpperlevel', 0, PARAM_INT);
    $maxlevel   = optional_param('maxlevel', 0, PARAM_INT);
    if ($xpperlevel < 1 || $maxlevel < 2) {
        redirect($pageurl, get_string('levels_generate_error', 'local_playergames'), null, notification::NOTIFY_ERROR);
    }
    level_manager::generate_linear($xpperlevel, $maxlevel);
    redirect($pageurl, get_string('levels_generated', 'local_playergames'), null, notification::NOTIFY_SUCCESS);
}

// POST: save the edited ladder.
if (data_submitted()) {
    require_sesskey();
    $minxps = optional_param_array('minxp', [], PARAM_INT);
    $titles = optional_param_array('title', [], PARAM_TEXT);

    $rows = [];
    foreach ($titles as $i => $title) {
        $title = trim($title);
        if ($title === '') {
            continue;
        }
        $rows[] = ['minxp' => (int) ($minxps[$i] ?? 0), 'title' => $title];
    }

    if (empty($rows)) {
        redirect($pageurl, get_string('levels_error_empty', 'local_playergames'), null, notification::NOTIFY_ERROR);
    }

    $sorted = $rows;
    usort($sorted, fn(array $a, array $b): int => $a['minxp'] <=> $b['minxp']);
    for ($k = 1, $n = count($sorted); $k < $n; $k++) {
        if ($sorted[$k]['minxp'] <= $sorted[$k - 1]['minxp']) {
            redirect(
                $pageurl,
                get_string('levels_error_duplicate_xp', 'local_playergames'),
                null,
                notification::NOTIFY_ERROR
            );
        }
    }

    level_manager::save_ladder($rows);
    redirect($pageurl, get_string('levels_saved', 'local_playergames'), null, notification::NOTIFY_SUCCESS);
}

// GET: build the editable table with explicit row indices so the parallel
// minxp[]/title[] arrays stay aligned on submission.
$rows  = [];
$index = 0;
foreach (level_manager::get_levels() as $row) {
    $rows[] = [
        'index'      => $index,
        'level'      => (int) $row->level,
        'minxp'      => (int) $row->minxp,
        'title'      => format_string($row->title),
        'islevelone' => (int) $row->level === 1,
        'isblank'    => false,
    ];
    $index++;
}
for ($b = 0; $b < 3; $b++) {
    $rows[] = ['index' => $index, 'isblank' => true];
    $index++;
}

$maxleveloptions = [];
foreach ([5, 10, 15, 20, 50, 100] as $option) {
    $maxleveloptions[] = ['value' => $option, 'selected' => $option === 20];
}

$templatedata = [
    'formaction'     => $pageurl->out(false),
    'sesskey'        => sesskey(),
    'rows'           => $rows,
    'str_intro'      => get_string('levels_intro', 'local_playergames'),
    'str_col_level'  => get_string('levels_col_level', 'local_playergames'),
    'str_col_minxp'  => get_string('levels_col_minxp', 'local_playergames'),
    'str_col_title'  => get_string('levels_col_title', 'local_playergames'),
    'str_add_heading' => get_string('levels_add_heading', 'local_playergames'),
    'str_add_hint'   => get_string('levels_add_hint', 'local_playergames'),
    'str_save'       => get_string('savechanges'),
    'str_restore'    => get_string('levels_restore', 'local_playergames'),
    'str_gen_heading' => get_string('levels_generate_heading', 'local_playergames'),
    'str_gen_hint'   => get_string('levels_generate_hint', 'local_playergames'),
    'str_xpperlevel' => get_string('levels_xpperlevel', 'local_playergames'),
    'str_maxlevel'   => get_string('levels_maxlevel', 'local_playergames'),
    'str_generate'   => get_string('levels_generate', 'local_playergames'),
    'gen_default_xp' => 100,
    'maxlevel_options' => $maxleveloptions,
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_playergames/manage_levels', $templatedata);
echo $OUTPUT->footer();
