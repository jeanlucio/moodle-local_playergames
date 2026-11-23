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
 * The missions: see them, change them, switch them off and add more of the same kinds.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use core\output\notification;
use local_playergames\form\mission_form;
use local_playergames\hub\mission_manager;
use local_playergames\hub\season_manager;

require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

$action = optional_param('action', '', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);

$pageurl = new moodle_url('/local/playergames/manage_missions.php');
$PAGE->set_context($context);
$PAGE->set_url($pageurl);
$PAGE->set_pagelayout('admin');
$pagetitle = get_string('missions_pagetitle', 'local_playergames');
$PAGE->set_title($pagetitle);
$PAGE->set_heading($pagetitle);

$missions = mission_manager::get_all();
if ($id > 0 && !isset($missions[$id])) {
    throw new moodle_exception('invalidrecord', 'error', $pageurl, 'local_playergames_missions');
}

if (in_array($action, ['enable', 'disable'], true) && $id > 0) {
    require_sesskey();
    $changed = mission_manager::set_enabled($id, $action === 'enable');
    redirect(
        $pageurl,
        get_string($changed ? 'mission_' . $action . 'd_ok' : 'mission_locked_help', 'local_playergames'),
        null,
        $changed ? notification::NOTIFY_SUCCESS : notification::NOTIFY_WARNING
    );
}

if ($action === 'delete' && $id > 0) {
    if (optional_param('confirm', 0, PARAM_BOOL) && confirm_sesskey()) {
        $deleted = mission_manager::delete($id);
        redirect(
            $pageurl,
            get_string($deleted ? 'mission_deleted' : 'mission_locked_help', 'local_playergames'),
            null,
            $deleted ? notification::NOTIFY_SUCCESS : notification::NOTIFY_WARNING
        );
    }
    echo $OUTPUT->header();
    echo $OUTPUT->confirm(
        get_string('mission_confirm_delete', 'local_playergames'),
        new moodle_url($pageurl, ['action' => 'delete', 'id' => $id, 'confirm' => 1, 'sesskey' => sesskey()]),
        $pageurl
    );
    echo $OUTPUT->footer();
    exit;
}

if ($action === 'edit' || $action === 'add') {
    $adding = $action === 'add';
    $formurl = new moodle_url($pageurl, ['action' => $action, 'id' => $adding ? 0 : $id]);
    $form = new mission_form($formurl, ['adding' => $adding, 'locked' => !$adding && mission_manager::is_locked($id)]);
    if ($form->is_cancelled()) {
        redirect($pageurl);
    }
    if ($data = $form->get_data()) {
        if ($adding) {
            $data->id = 0;
        }
        mission_manager::save($data);
        redirect($pageurl, get_string('mission_saved', 'local_playergames'), null, notification::NOTIFY_SUCCESS);
    }
    if (!$adding) {
        $mission = clone $missions[$id];
        $form->set_data($mission);
    }
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string($adding ? 'mission_add' : 'mission_edit', 'local_playergames'), 3);
    $form->display();
    echo $OUTPUT->footer();
    exit;
}

$season = season_manager::get_active();
$locked = $season !== null ? mission_manager::locked_ids((int) $season->id) : [];
$rows = [];
foreach ($missions as $mission) {
    $missionid = (int) $mission->id;
    $islocked = isset($locked[$missionid]);
    $reward = [];
    if ((int) $mission->xpreward > 0) {
        $reward[] = (int) $mission->xpreward . ' XP';
    }
    if ((int) $mission->freezereward > 0) {
        $freezes = (int) $mission->freezereward;
        $reward[] = get_string($freezes === 1 ? 'mission_reward_freeze' : 'mission_reward_freezes', 'local_playergames', $freezes);
    }
    $toggle = $mission->enabled ? 'disable' : 'enable';
    $rows[] = [
        'icon' => $mission->icon ?: mission_manager::TYPES[$mission->type]['icon'],
        'name' => mission_manager::display_name($mission),
        'goal' => mission_manager::description($mission),
        'reward' => implode(' + ', $reward),
        'enabled' => (bool) $mission->enabled,
        'locked' => $islocked,
        'editurl' => (new moodle_url($pageurl, ['action' => 'edit', 'id' => $missionid]))->out(false),
        'toggleurl' => $islocked ? '' : (new moodle_url(
            $pageurl,
            ['action' => $toggle, 'id' => $missionid, 'sesskey' => sesskey()]
        ))->out(false),
        'togglelabel' => get_string('mission_' . $toggle, 'local_playergames'),
        'deleteurl' => mission_manager::has_history($missionid) ? '' : (new moodle_url(
            $pageurl,
            ['action' => 'delete', 'id' => $missionid]
        ))->out(false),
    ];
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_playergames/manage_missions', [
    'missions' => $rows,
    'addurl' => (new moodle_url($pageurl, ['action' => 'add']))->out(false),
]);
echo $OUTPUT->footer();
