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
 * Per-season game configuration page.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_playergames\games\season_game_config;
use local_playergames\hub\season_manager;
use local_playergames\local\text;

require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

// Opened without a season (from the admin menu or the Administration tab), it shows the active one, or the next.
$seasonid = optional_param('seasonid', 0, PARAM_INT);
if ($seasonid === 0) {
    $default = season_manager::get_active_or_upcoming();
    if ($default === null) {
        redirect(
            new moodle_url('/local/playergames/manage_seasons.php'),
            get_string('admin_state_games_none', 'local_playergames'),
            null,
            \core\output\notification::NOTIFY_INFO
        );
    }
    $seasonid = (int) $default->id;
}
$season = $DB->get_record('local_playergames_seasons', ['id' => $seasonid], '*', MUST_EXIST);

$pageurl = new moodle_url('/local/playergames/manage_season_games.php', ['seasonid' => $seasonid]);
$PAGE->set_context($context);
$PAGE->set_url($pageurl);
$PAGE->set_pagelayout('admin');

$pagetitle = get_string('season_game_pagetitle', 'local_playergames', format_string($season->name));
$PAGE->set_title($pagetitle);
$PAGE->set_heading($pagetitle);

// Games grouped by content family: the quiz games read quiz cartridges, the concept games read concept cartridges.
$gametypes = [];
foreach (season_game_config::FAMILIES as $type => $family) {
    $gametypes[] = ['type' => $type, 'family' => $family];
}

// Load cartridges by family type.
$quizcartridges = $DB->get_records('local_playergames_cartridges', ['type' => 'quiz'], 'name ASC', 'id, name');
$conceptcartridges = $DB->get_records(
    'local_playergames_cartridges',
    ['type' => 'concept'],
    'name ASC',
    'id, name'
);

// POST: save configuration.
if (data_submitted()) {
    require_sesskey();

    $existingconfigs = season_game_config::get_all_for_season($seasonid);
    $records = [];
    foreach ($gametypes as $game) {
        $gametype = $game['type'];
        $isquiz   = $game['family'] === 'quiz';

        // Only cartridges of the kind this game reads count; none ticked means all of them.
        $family = $isquiz ? $quizcartridges : $conceptcartridges;
        $rawids = optional_param_array('cartridges_' . $gametype, [], PARAM_INT);
        $kept   = array_values(array_intersect(array_map('intval', $rawids), array_map('intval', array_keys($family))));

        $records[] = [
            'gametype'     => $gametype,
            'enabled'      => (int) optional_param('enabled_' . $gametype, 0, PARAM_INT),
            'cartridgeids' => empty($kept) ? null : json_encode($kept),
        ];
    }

    foreach ($records as $record) {
        $row           = (object) $record;
        $row->seasonid = $seasonid;
        if (isset($existingconfigs[$row->gametype])) {
            $row->id = (int) $existingconfigs[$row->gametype]->id;
            $DB->update_record('local_playergames_season_games', $row);
        } else {
            $DB->insert_record('local_playergames_season_games', $row);
        }
    }

    // Today's words were drawn from the cartridges the season kept until now: release those that came from a
    // cartridge it no longer keeps for that game, then draw for every game left without words, which also covers
    // a game that had nothing to draw from before this change.
    if ($season->status === season_manager::STATUS_ACTIVE) {
        season_game_config::release_todays_words_outside($seasonid);
        \local_playergames\task\assign_daily_games::draw_now();
    }

    redirect(
        $pageurl,
        get_string('season_game_saved_ok', 'local_playergames'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// Load saved config, keyed by gametype.
$savedconfigs = season_game_config::get_all_for_season($seasonid);

// Build template data for each game row.
$gamerows = [];
foreach ($gametypes as $game) {
    $gametype = $game['type'];
    $family   = $game['family'];
    $saved    = $savedconfigs[$gametype] ?? null;

    $savedids = [];
    if ($saved && $saved->cartridgeids !== null) {
        $decoded = json_decode($saved->cartridgeids, true);
        $savedids = is_array($decoded) ? array_map('intval', $decoded) : [];
    }

    // Build cartridge checkbox list.
    $cartridgelist = $family === 'quiz' ? $quizcartridges : $conceptcartridges;
    $cartridgerows = [];
    foreach ($cartridgelist as $c) {
        $cartridgerows[] = [
            'id'      => (int) $c->id,
            'name'    => \local_playergames\local\text::plain($c->name),
            'checked' => empty($savedids) || in_array((int) $c->id, $savedids, true),
        ];
    }

    $gamerows[] = [
        'gametype'        => $gametype,
        'gamename'        => get_string('hub_game_' . $gametype, 'local_playergames'),
        'family'          => $family,
        'enabled'         => $saved ? (bool) $saved->enabled : true,
        'cartridges'      => $cartridgerows,
        'nocartridges'    => empty($cartridgerows),
    ];
}

$templatedata = [
    'seasonname'   => text::plain($season->name),
    'formaction'   => $pageurl->out(false),
    'sesskey'      => sesskey(),
    'gamerows'     => $gamerows,
    'backurl'      => (new moodle_url('/local/playergames/manage_seasons.php'))->out(false),
    'str_back'     => get_string('season_game_back', 'local_playergames'),
    'str_enabled'  => get_string('season_game_enabled', 'local_playergames'),
    'str_cartridges' => get_string('season_game_cartridges', 'local_playergames'),
    'str_cartridges_all' => get_string('season_game_cartridges_all', 'local_playergames'),
    'str_nocartridges' => get_string('season_game_cartridges_none', 'local_playergames'),
    'str_save'     => get_string('savechanges'),
    'str_heading'  => get_string('season_game_heading', 'local_playergames', text::plain($season->name)),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_playergames/manage_season_games', $templatedata);
echo $OUTPUT->footer();
