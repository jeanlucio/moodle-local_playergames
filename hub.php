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
 * Player Hub — ranking, profile, missions and today's games.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

require_login();

$context = context_system::instance();
require_capability('local/playergames:viewhub', $context);

$allowed  = get_config('local_playergames', 'allowed_participants') ?: 'students';
$isstaff  = has_capability('moodle/course:manageactivities', $context);
$isadmin  = has_capability('moodle/site:config', $context);

// Access control: enforce participant group restriction.
if ($allowed === 'students' && $isstaff && !$isadmin) {
    throw new moodle_exception('hub_access_restricted', 'local_playergames');
}
if ($allowed === 'staff' && !$isstaff) {
    throw new moodle_exception('hub_access_restricted', 'local_playergames');
}

// POST handler: toggle "appear in ranking".
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    $season = \local_playergames\hub\season_manager::get_active();
    if ($season) {
        $show    = optional_param('showinranking', 0, PARAM_INT);
        $profile = \local_playergames\hub\xp_manager::get_or_create_profile($USER->id, $season->id);
        $profile->showinranking  = $show ? 1 : 0;
        $profile->timemodified   = time();
        $DB->update_record('local_playergames_player_profile', $profile);

        $cache    = cache::make('local_playergames', 'ranking');
        $suffix   = $isstaff ? 'staff' : 'students';
        $cache->delete('season_' . $season->id . '_' . $suffix);
    }
    redirect(new moodle_url('/local/playergames/hub.php'));
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/playergames/hub.php'));
$PAGE->set_title(get_string('hub_pagetitle', 'local_playergames'));
$PAGE->set_heading(get_string('hub_pagetitle', 'local_playergames'));
$PAGE->set_pagelayout('standard');
$PAGE->requires->js_call_amd('local_playergames/hub', 'init');

$hubdata = new \local_playergames\output\hub($USER->id, $isstaff, $isadmin, $allowed);
$output  = $PAGE->get_renderer('core');

echo $output->header();
echo $output->render_from_template(
    'local_playergames/hub',
    $hubdata->export_for_template($output)
);
echo $output->footer();
