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

use local_playergames\hub\season_manager;

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();

    if ($action === 'create') {
        $name      = required_param('name', PARAM_TEXT);
        $startstr  = required_param('startdate', PARAM_TEXT);
        $endstr    = required_param('enddate', PARAM_TEXT);
        $startdate = (int) strtotime($startstr);
        $enddate   = (int) strtotime($endstr);

        if (!$startdate || !$enddate || $enddate <= $startdate) {
            $notifications[] = $OUTPUT->notification(
                get_string('error_season_invalid_dates', 'local_playergames'),
                'error'
            );
        } else {
            season_manager::create($name, $startdate, $enddate);
            $notifications[] = $OUTPUT->notification(
                get_string('season_created_ok', 'local_playergames'),
                'success'
            );
        }
    } else if ($action === 'activate' && $seasonid > 0) {
        try {
            season_manager::activate($seasonid);
            $notifications[] = $OUTPUT->notification(
                get_string('season_activated_ok', 'local_playergames'),
                'success'
            );
        } catch (moodle_exception $e) {
            $notifications[] = $OUTPUT->notification(
                get_string('error_season_already_active', 'local_playergames'),
                'error'
            );
        }
    } else if ($action === 'close' && $seasonid > 0) {
        season_manager::close($seasonid);
        $notifications[] = $OUTPUT->notification(
            get_string('season_closed_ok', 'local_playergames'),
            'success'
        );
    }
}

$seasons    = $DB->get_records('local_playergames_seasons', null, 'timecreated DESC');
$seasondata = [];
foreach ($seasons as $season) {
    $playercount = $DB->count_records(
        'local_playergames_player_profile',
        ['seasonid' => $season->id]
    );
    if ($season->status === season_manager::STATUS_ACTIVE) {
        $statusbadge = 'bg-success text-white';
    } else if ($season->status === season_manager::STATUS_UPCOMING) {
        $statusbadge = 'bg-warning text-dark';
    } else {
        $statusbadge = 'bg-secondary text-white';
    }
    $seasondata[] = [
        'id'          => (int) $season->id,
        'name'        => format_string($season->name),
        'startdate'   => userdate($season->startdate, get_string('strftimedatefullshort', 'langconfig')),
        'enddate'     => userdate($season->enddate, get_string('strftimedatefullshort', 'langconfig')),
        'status'      => get_string('season_status_' . $season->status, 'local_playergames'),
        'statusbadge' => $statusbadge,
        'players'     => $playercount,
        'canactivate' => $season->status === season_manager::STATUS_UPCOMING,
        'canclose'    => $season->status === season_manager::STATUS_ACTIVE,
    ];
}

$months      = (int) get_config('local_playergames', 'season_duration_months') ?: 6;
$defaultstart = date('Y-m-d');
$defaultend   = date('Y-m-d', strtotime("+{$months} months"));
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
    'str_create'    => get_string('season_create_heading', 'local_playergames'),
    'str_name'      => get_string('season_name_label', 'local_playergames'),
    'str_startdate' => get_string('season_startdate', 'local_playergames'),
    'str_enddate'   => get_string('season_enddate', 'local_playergames'),
    'str_players'   => get_string('season_players', 'local_playergames'),
    'str_activate'  => get_string('season_activate', 'local_playergames'),
    'str_close'     => get_string('season_close', 'local_playergames'),
    'str_noseasons' => get_string('season_no_seasons', 'local_playergames'),
    'str_save'      => get_string('savechanges'),
    'str_status'    => get_string('season_col_status', 'local_playergames'),
    'str_actions'   => get_string('season_col_actions', 'local_playergames'),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_playergames/manage_seasons', $templatedata);
echo $OUTPUT->footer();
