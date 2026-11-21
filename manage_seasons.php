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
 * Season management page for local_playergames.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use core\output\notification;
use local_playergames\hub\season_manager;
use local_playergames\hub\season_summary;
use local_playergames\local\day;

require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

$action   = optional_param('action', '', PARAM_ALPHA);
$seasonid = optional_param('seasonid', 0, PARAM_INT);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/playergames/manage_seasons.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('season_manage_pagetitle', 'local_playergames'));
$PAGE->set_heading(get_string('season_manage_pagetitle', 'local_playergames'));

$notifications = [];
$editing = null;

if (!data_submitted() && $seasonid > 0) {
    $target = $DB->get_record('local_playergames_seasons', ['id' => $seasonid], '*', MUST_EXIST);
    if ($action === 'delete' && $target->status !== season_manager::STATUS_ACTIVE) {
        $message = get_string('season_delete_confirm', 'local_playergames', (object) [
            // The confirmation prints its message as HTML, so the plain text is escaped.
            'name' => s(\local_playergames\local\text::plain($target->name)),
            'players' => season_summary::count_players($seasonid),
        ]);
        echo $OUTPUT->header();
        echo $OUTPUT->confirm(
            $message,
            new single_button(
                new moodle_url($PAGE->url, ['action' => 'delete', 'seasonid' => $seasonid]),
                get_string('season_delete', 'local_playergames'),
                'post',
                single_button::BUTTON_DANGER
            ),
            $PAGE->url
        );
        echo $OUTPUT->footer();
        exit;
    }
    if ($action === 'edit') {
        $editing = $target;
    }
}

if (data_submitted()) {
    require_sesskey();

    // A done action goes back to the page with its message (Post/Redirect/Get): shown in the same response,
    // reloading the page sent the action again, and a second activation said another season was active.
    $done = null;
    if ($action === 'create') {
        $name      = required_param('name', PARAM_TEXT);
        $startstr  = required_param('startdate', PARAM_TEXT);
        $endstr    = required_param('enddate', PARAM_TEXT);
        $startdate = day::from_date($startstr, false);
        $enddate   = day::from_date($endstr, true);

        if (!$startdate || !$enddate || $enddate <= $startdate) {
            $notifications[] = $OUTPUT->notification(
                get_string('error_season_invalid_dates', 'local_playergames'),
                'error'
            );
        } else {
            season_manager::create($name, $startdate, $enddate);
            $done = ['season_created_ok', notification::NOTIFY_SUCCESS];
        }
    } else if ($action === 'update' && $seasonid > 0) {
        $name      = required_param('name', PARAM_TEXT);
        $startdate = day::from_date(optional_param('startdate', '', PARAM_TEXT), false);
        $enddate   = day::from_date(optional_param('enddate', '', PARAM_TEXT), true);
        $existing  = $DB->get_record('local_playergames_seasons', ['id' => $seasonid], '*', MUST_EXIST);
        $closed    = $existing->status === season_manager::STATUS_CLOSED;
        if (!$closed && (!$startdate || !$enddate || $enddate <= $startdate)) {
            $notifications[] = $OUTPUT->notification(
                get_string('error_season_invalid_dates', 'local_playergames'),
                'error'
            );
            $editing = $existing;
        } else {
            season_manager::update($seasonid, $name, $startdate, $enddate);
            $done = ['season_updated_ok', notification::NOTIFY_SUCCESS];
        }
    } else if ($action === 'delete' && $seasonid > 0) {
        try {
            season_manager::delete($seasonid);
            $done = ['season_deleted_ok', notification::NOTIFY_SUCCESS];
        } catch (moodle_exception $e) {
            $done = ['error_season_delete_active', notification::NOTIFY_ERROR];
        }
    } else if ($action === 'activate' && $seasonid > 0) {
        try {
            season_manager::activate($seasonid);
            $done = ['season_activated_ok', notification::NOTIFY_SUCCESS];
        } catch (moodle_exception $e) {
            $done = ['error_season_already_active', notification::NOTIFY_ERROR];
        }
    } else if ($action === 'close' && $seasonid > 0) {
        season_manager::close($seasonid);
        $done = ['season_closed_ok', notification::NOTIFY_SUCCESS];
    }

    if ($done !== null) {
        redirect($PAGE->url, get_string($done[0], 'local_playergames'), null, $done[1]);
    }
}

$seasons    = $DB->get_records('local_playergames_seasons', null, 'timecreated DESC');
$seasondata = [];
foreach ($seasons as $season) {
    $playercount = season_summary::count_players((int) $season->id);
    if ($season->status === season_manager::STATUS_ACTIVE) {
        $statusbadge = 'pg-badge-success';
    } else if ($season->status === season_manager::STATUS_UPCOMING) {
        $statusbadge = 'bg-warning text-dark';
    } else {
        $statusbadge = 'bg-secondary pg-season-closed';
    }
    $name = \local_playergames\local\text::plain($season->name);
    $seasondata[] = [
        'id'             => (int) $season->id,
        'name'           => $name,
        'startdate'      => day::format((int) $season->startdate, get_string('strftimedatefullshort', 'langconfig')),
        'enddate'        => day::format((int) $season->enddate, get_string('strftimedatefullshort', 'langconfig')),
        'status'         => get_string('season_status_' . $season->status, 'local_playergames'),
        'statusbadge'    => $statusbadge,
        'players'        => $playercount,
        'canactivate'    => $season->status === season_manager::STATUS_UPCOMING,
        'canclose'       => $season->status === season_manager::STATUS_ACTIVE,
        'candelete'      => $season->status !== season_manager::STATUS_ACTIVE,
        'editurl'        => (new moodle_url(
            '/local/playergames/manage_seasons.php',
            ['action' => 'edit', 'seasonid' => (int) $season->id]
        ))->out(false),
        'deleteurl'      => (new moodle_url(
            '/local/playergames/manage_seasons.php',
            ['action' => 'delete', 'seasonid' => (int) $season->id]
        ))->out(false),
        'editlabel'      => get_string('season_edit_aria', 'local_playergames', $name),
        'deletelabel'    => get_string('season_delete_aria', 'local_playergames', $name),
        'gameconfigurl'  => (new moodle_url(
            '/local/playergames/manage_season_games.php',
            ['seasonid' => (int) $season->id]
        ))->out(false),
    ];
}

$months      = max(1, (int) get_config('local_playergames', 'season_duration_months') ?: 6);
$defaultstart = day::to_date(time());
$defaultend   = day::to_date(season_manager::add_months(time(), $months));
$nextnum      = $DB->count_records('local_playergames_seasons') + 1;
$defaultname  = get_string('defaultseasonname', 'local_playergames');
$defaultname  = preg_replace('/\d+$/', (string) $nextnum, $defaultname) ?? $defaultname;

$templatedata = [
    'notifications' => implode('', $notifications),
    'seasons'       => array_values($seasondata),
    'noseasons'     => empty($seasondata),
    'formaction'    => (new moodle_url('/local/playergames/manage_seasons.php'))->out(false),
    'sesskey'       => sesskey(),
    'defaultname'   => $defaultname,
    'defaultstart'  => $defaultstart,
    'defaultend'    => $defaultend,
    'str_name'      => get_string('season_name_label', 'local_playergames'),
    'str_startdate' => get_string('season_startdate', 'local_playergames'),
    'str_enddate'   => get_string('season_enddate', 'local_playergames'),
    'str_players'   => get_string('season_players', 'local_playergames'),
    'str_activate'  => get_string('season_activate', 'local_playergames'),
    'str_close'     => get_string('season_close', 'local_playergames'),
    'str_noseasons' => get_string('season_no_seasons', 'local_playergames'),
    'str_save'      => get_string('savechanges'),
    'str_status'    => get_string('season_col_status', 'local_playergames'),
    'str_actions'         => get_string('season_col_actions', 'local_playergames'),
    'str_configure_games' => get_string('season_game_configure', 'local_playergames'),
    'str_edit'            => get_string('season_edit', 'local_playergames'),
    'str_delete'          => get_string('season_delete', 'local_playergames'),
    'str_cancel'          => get_string('cancel'),
    'formtitle'           => get_string('season_create_heading', 'local_playergames'),
    'formaction_name'     => 'create',
    'formseasonid'        => 0,
    'editing'             => false,
    'lockdates'           => false,
    'cancelurl'           => (new moodle_url('/local/playergames/manage_seasons.php'))->out(false),
];

if ($editing) {
    $templatedata['formtitle']       = get_string('season_edit_heading', 'local_playergames');
    $templatedata['formaction_name'] = 'update';
    $templatedata['formseasonid']    = (int) $editing->id;
    $templatedata['editing']         = true;
    $templatedata['lockdates']       = $editing->status === season_manager::STATUS_CLOSED;
    $templatedata['defaultname']     = (string) $editing->name;
    $templatedata['defaultstart']    = day::to_date((int) $editing->startdate);
    $templatedata['defaultend']      = day::to_date((int) $editing->enddate);
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_playergames/manage_seasons', $templatedata);
echo $OUTPUT->footer();
