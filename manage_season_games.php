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

require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

$seasonid = required_param('seasonid', PARAM_INT);
$season = $DB->get_record('local_playergames_seasons', ['id' => $seasonid], '*', MUST_EXIST);

$pageurl = new moodle_url('/local/playergames/manage_season_games.php', ['seasonid' => $seasonid]);
$PAGE->set_context($context);
$PAGE->set_url($pageurl);
$PAGE->set_pagelayout('admin');

$pagetitle = get_string('season_game_pagetitle', 'local_playergames', format_string($season->name));
$PAGE->set_title($pagetitle);
$PAGE->set_heading($pagetitle);

// Games grouped by content family.
// Quiz family: use quiz cartridges or Moodle question bank.
// Concept family: use concept cartridges or Moodle glossary.
$gametypes = [
    ['type' => 'quiz', 'family' => 'quiz'],
    ['type' => 'battle', 'family' => 'quiz'],
    ['type' => 'guess', 'family' => 'concept'],
    ['type' => 'fill', 'family' => 'concept'],
];

// POST: save configuration.
if (data_submitted()) {
    require_sesskey();

    foreach ($gametypes as $game) {
        $gametype = $game['type'];
        $enabled  = (int) optional_param('enabled_' . $gametype, 0, PARAM_INT);
        $source   = optional_param('source_' . $gametype, season_game_config::SOURCE_CARTRIDGE, PARAM_ALPHA);
        $rawids   = optional_param_array('cartridges_' . $gametype, [], PARAM_INT);
        $auxid    = (int) optional_param('auxid_' . $gametype, 0, PARAM_INT);

        $cartridgeids = empty($rawids) ? null : json_encode(array_values(array_map('intval', $rawids)));

        $existing = $DB->get_record('local_playergames_season_games', [
            'seasonid' => $seasonid,
            'gametype' => $gametype,
        ]);

        $record           = new stdClass();
        $record->seasonid = $seasonid;
        $record->gametype = $gametype;
        $record->enabled  = $enabled;
        $record->source   = $source;
        $record->cartridgeids = $cartridgeids;
        $record->auxid    = $auxid;

        if ($existing) {
            $record->id = (int) $existing->id;
            $DB->update_record('local_playergames_season_games', $record);
        } else {
            $DB->insert_record('local_playergames_season_games', $record);
        }
    }

    redirect(
        $pageurl,
        get_string('season_game_saved_ok', 'local_playergames'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// Load cartridges by family type.
$quizcartridges = $DB->get_records('local_playergames_cartridges', ['type' => 'quiz'], 'name ASC', 'id, name');
$conceptcartridges = $DB->get_records(
    'local_playergames_cartridges',
    ['type' => 'concept'],
    'name ASC',
    'id, name'
);

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

    $currentsource = $saved ? $saved->source : season_game_config::SOURCE_CARTRIDGE;

    // Build cartridge checkbox list.
    $cartridgelist = $family === 'quiz' ? $quizcartridges : $conceptcartridges;
    $cartridgerows = [];
    foreach ($cartridgelist as $c) {
        $cartridgerows[] = [
            'id'      => (int) $c->id,
            'name'    => format_string($c->name),
            'checked' => empty($savedids) || in_array((int) $c->id, $savedids, true),
        ];
    }

    // Source options depend on family.
    if ($family === 'quiz') {
        $sourceoptions = [
            [
                'value' => season_game_config::SOURCE_CARTRIDGE,
                'label' => get_string('season_game_source_cartridge', 'local_playergames'),
                'selected' => $currentsource === season_game_config::SOURCE_CARTRIDGE,
            ],
            [
                'value' => season_game_config::SOURCE_QUESTIONBANK,
                'label' => get_string('season_game_source_questionbank', 'local_playergames'),
                'selected' => $currentsource === season_game_config::SOURCE_QUESTIONBANK,
            ],
            [
                'value' => season_game_config::SOURCE_BOTH,
                'label' => get_string('season_game_source_both', 'local_playergames'),
                'selected' => $currentsource === season_game_config::SOURCE_BOTH,
            ],
        ];
        $auxlabel = get_string('season_game_auxid_qbank', 'local_playergames');
    } else {
        $sourceoptions = [
            [
                'value' => season_game_config::SOURCE_CARTRIDGE,
                'label' => get_string('season_game_source_cartridge', 'local_playergames'),
                'selected' => $currentsource === season_game_config::SOURCE_CARTRIDGE,
            ],
            [
                'value' => season_game_config::SOURCE_GLOSSARY,
                'label' => get_string('season_game_source_glossary', 'local_playergames'),
                'selected' => $currentsource === season_game_config::SOURCE_GLOSSARY,
            ],
            [
                'value' => season_game_config::SOURCE_BOTH,
                'label' => get_string('season_game_source_both', 'local_playergames'),
                'selected' => $currentsource === season_game_config::SOURCE_BOTH,
            ],
        ];
        $auxlabel = get_string('season_game_auxid_glossary', 'local_playergames');
    }

    $gamerows[] = [
        'gametype'        => $gametype,
        'gamename'        => get_string('hub_game_' . $gametype, 'local_playergames'),
        'family'          => $family,
        'enabled'         => $saved ? (bool) $saved->enabled : true,
        'sourceoptions'   => $sourceoptions,
        'cartridges'      => $cartridgerows,
        'nocartridges'    => empty($cartridgerows),
        'auxid'           => $saved ? (int) $saved->auxid : 0,
        'auxlabel'        => $auxlabel,
        'showsecondary'   => $currentsource !== season_game_config::SOURCE_CARTRIDGE,
        'showcartridges'  => $currentsource !== ($family === 'quiz'
                                ? season_game_config::SOURCE_QUESTIONBANK
                                : season_game_config::SOURCE_GLOSSARY),
    ];
}

$templatedata = [
    'seasonname'   => format_string($season->name),
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
    'str_auxhint'  => get_string('season_game_auxid_hint', 'local_playergames'),
    'str_save'     => get_string('savechanges'),
    'str_heading'  => get_string('season_game_heading', 'local_playergames', format_string($season->name)),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_playergames/manage_season_games', $templatedata);
echo $OUTPUT->footer();
