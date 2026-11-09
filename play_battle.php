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
 * PlayerBattle: the daily match-3 battle against an opponent, with questions from the season's sources.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_playergames\games\battle_manager;
use local_playergames\hub\daily_play_manager;
use local_playergames\hub\season_manager;

require_login();
$context = context_system::instance();
require_capability('local/playergames:viewhub', $context);

$gamedate   = mktime(0, 0, 0, (int) date('n'), (int) date('j'), (int) date('Y'));
$formaction = (new moodle_url('/local/playergames/play_battle.php'))->out(false);
$manager    = new battle_manager();

// POST: the result of the battle, sent once when it ends.
if (data_submitted()) {
    require_sesskey();
    header('Content-Type: application/json');

    $season = season_manager::get_active();
    if ($season === null || optional_param('action', '', PARAM_ALPHAEXT) !== 'submit_result') {
        echo json_encode(['success' => false, 'xpawarded' => 0]);
        exit;
    }

    $seen = json_decode(optional_param('seen', '[]', PARAM_RAW), true);
    echo json_encode($manager->submit_result((int) $USER->id, $season, $context, [
        'victory'          => optional_param('victory', 0, PARAM_BOOL),
        'bosshpdealt'      => optional_param('bosshpdealt', 0, PARAM_INT),
        'questionscorrect' => optional_param('questionscorrect', 0, PARAM_INT),
        'questionstotal'   => optional_param('questionstotal', 0, PARAM_INT),
        'seen'             => is_array($seen) ? $seen : [],
    ]));
    exit;
}

// GET: render the game page.
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/playergames/play_battle.php'));
$PAGE->set_pagelayout('base');
$PAGE->set_title(get_string('battle_pagetitle', 'local_playergames'));
$PAGE->set_heading(get_string('battle_pagetitle', 'local_playergames'));

$activeseason = season_manager::get_active();
$remaining    = 0;
if ($activeseason !== null) {
    $snapshot  = season_manager::get_config_snapshot($activeseason);
    $remaining = daily_play_manager::remaining_plays((int) $USER->id, 'battle', $gamedate, $snapshot);
}
$alreadyplayed = $remaining <= 0;

$questions = $alreadyplayed ? [] : $manager->start_battle((int) $USER->id, $gamedate, $context);
$noquestions = !$alreadyplayed && empty($questions);

$config = [
    'posturl'   => $formaction,
    'sesskey'   => sesskey(),
    'questions' => $questions,
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_playergames/play_battle', [
    'already_played'     => $alreadyplayed,
    'no_questions'       => $noquestions,
    'playable'           => !$alreadyplayed && !$noquestions,
    'config'             => json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
    'huburl'             => (new moodle_url('/local/playergames/hub.php'))->out(false),
    'str_already_played' => get_string('battle_already_played', 'local_playergames'),
    'str_no_questions'   => get_string('battle_no_questions', 'local_playergames'),
    'str_back_to_hub'    => get_string('battle_back_to_hub', 'local_playergames'),
]);
echo $OUTPUT->footer();
