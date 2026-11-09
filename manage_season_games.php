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
use local_playergames\local\question_bank_reuse;
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

// Games grouped by content family.
// Quiz family: quiz cartridges, or a shared Moodle question bank category, or both.
// Concept family: concept cartridges only.
$gametypes = [
    ['type' => 'quiz', 'family' => 'quiz'],
    ['type' => 'battle', 'family' => 'quiz'],
    ['type' => 'guess', 'family' => 'concept'],
    ['type' => 'fill', 'family' => 'concept'],
];

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
    $errors  = [];
    foreach ($gametypes as $game) {
        $gametype = $game['type'];
        $isquiz   = $game['family'] === 'quiz';

        // The concept games draw only from their cartridges. The quiz games may also draw from one shared
        // question bank category, which has to be one this user may choose: it publishes its questions to
        // every player.
        $source = season_game_config::SOURCE_CARTRIDGE;
        $auxid  = 0;
        if ($isquiz) {
            $source = optional_param('source_' . $gametype, season_game_config::SOURCE_CARTRIDGE, PARAM_ALPHA);
            $allowed = [
                season_game_config::SOURCE_CARTRIDGE,
                season_game_config::SOURCE_QUESTIONBANK,
                season_game_config::SOURCE_BOTH,
            ];
            if (!in_array($source, $allowed, true)) {
                $source = season_game_config::SOURCE_CARTRIDGE;
            }
            if ($source !== season_game_config::SOURCE_CARTRIDGE) {
                $auxid = (int) optional_param('auxid_' . $gametype, 0, PARAM_INT);
                if (!question_bank_reuse::can_choose($auxid, (int) $USER->id)) {
                    $errors[] = get_string(
                        'season_game_qbank_required',
                        'local_playergames',
                        get_string('hub_game_' . $gametype, 'local_playergames')
                    );
                }
            }
        }

        // Only cartridges of the kind this game reads count; none ticked means all of them.
        $family = $isquiz ? $quizcartridges : $conceptcartridges;
        $rawids = optional_param_array('cartridges_' . $gametype, [], PARAM_INT);
        $kept   = array_values(array_intersect(array_map('intval', $rawids), array_map('intval', array_keys($family))));

        $records[] = [
            'gametype'     => $gametype,
            'enabled'      => (int) optional_param('enabled_' . $gametype, 0, PARAM_INT),
            'source'       => $source,
            'cartridgeids' => empty($kept) ? null : json_encode($kept),
            'auxid'        => $auxid,
        ];
    }

    if (!empty($errors)) {
        redirect($pageurl, implode(' ', $errors), null, \core\output\notification::NOTIFY_ERROR);
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

    redirect(
        $pageurl,
        get_string('season_game_saved_ok', 'local_playergames'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// Load saved config, keyed by gametype.
$savedconfigs = season_game_config::get_all_for_season($seasonid);

// The shared question bank categories this user may choose for the quiz games.
$choosablecategories = question_bank_reuse::categories_for_user((int) $USER->id);

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

    $currentsource = $saved ? $saved->source : season_game_config::SOURCE_CARTRIDGE;

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

    // Source options and the question bank category exist only for the quiz games.
    $sourceoptions = [];
    $qbankoptions  = [];
    $unavailable   = false;
    if ($family === 'quiz') {
        foreach (
            [
                season_game_config::SOURCE_CARTRIDGE => 'season_game_source_cartridge',
                season_game_config::SOURCE_QUESTIONBANK => 'season_game_source_questionbank',
                season_game_config::SOURCE_BOTH => 'season_game_source_both',
            ] as $value => $labelkey
        ) {
            $sourceoptions[] = [
                'value' => $value,
                'label' => get_string($labelkey, 'local_playergames'),
                'selected' => $currentsource === $value,
            ];
        }

        $savedaux = $saved ? (int) $saved->auxid : 0;
        foreach ($choosablecategories as $category) {
            $label = text::plain($category->name);
            if ($category->bank !== '') {
                $label = text::plain($category->bank) . ': ' . $label;
            }
            $qbankoptions[] = [
                'id'       => (int) $category->id,
                'label'    => get_string('season_game_qbank_option', 'local_playergames', (object) [
                    'name' => $label,
                    'count' => $category->questions,
                ]),
                'selected' => (int) $category->id === $savedaux,
            ];
        }
        // A category saved earlier that can no longer be chosen is not read by the game either.
        $unavailable = $savedaux > 0 && !isset($choosablecategories[$savedaux]);
    }

    $gamerows[] = [
        'gametype'        => $gametype,
        'gamename'        => get_string('hub_game_' . $gametype, 'local_playergames'),
        'family'          => $family,
        'enabled'         => $saved ? (bool) $saved->enabled : true,
        'hassource'       => $family === 'quiz',
        'sourceoptions'   => $sourceoptions,
        'cartridges'      => $cartridgerows,
        'nocartridges'    => empty($cartridgerows),
        'hasqbank'        => $family === 'quiz',
        'qbankoptions'    => $qbankoptions,
        'qbankunavailable' => $unavailable,
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
    'str_source'   => get_string('season_game_source', 'local_playergames'),
    'str_cartridges' => get_string('season_game_cartridges', 'local_playergames'),
    'str_cartridges_all' => get_string('season_game_cartridges_all', 'local_playergames'),
    'str_nocartridges' => get_string('season_game_cartridges_none', 'local_playergames'),
    'str_cartridge_only' => get_string('season_game_cartridge_only', 'local_playergames'),
    'str_qbank'    => get_string('season_game_qbank', 'local_playergames'),
    'str_qbank_choose' => get_string('season_game_qbank_choose', 'local_playergames'),
    'str_qbank_none' => get_string('season_game_qbank_none', 'local_playergames'),
    'str_qbank_hint' => get_string('season_game_qbank_hint', 'local_playergames'),
    'str_qbank_unavailable' => get_string('season_game_qbank_unavailable', 'local_playergames'),
    'str_save'     => get_string('savechanges'),
    'str_heading'  => get_string('season_game_heading', 'local_playergames', text::plain($season->name)),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_playergames/manage_season_games', $templatedata);
echo $OUTPUT->footer();
