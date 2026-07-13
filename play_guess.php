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
 * PlayerGuess daily game entry point.
 *
 * GET  — renders the Wordle-style game page.
 * POST action=submit_guess — validates one guess server-side and returns
 * per-letter feedback; awards XP through daily_play_manager on a win.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_playergames\games\guess_manager;
use local_playergames\games\guess_state;
use local_playergames\games\season_game_config;
use local_playergames\hub\daily_play_manager;
use local_playergames\hub\season_manager;

require_login();
$context = context_system::instance();
require_capability('local/playergames:viewhub', $context);

$gamedate   = mktime(0, 0, 0, (int) date('n'), (int) date('j'), (int) date('Y'));
$formaction = (new moodle_url('/local/playergames/play_guess.php'))->out(false);
$maxattempts = guess_manager::max_attempts();

// POST: AJAX guess submission.
if (data_submitted()) {
    require_sesskey();
    $submitaction = optional_param('action', '', PARAM_ALPHAEXT);

    if ($submitaction === 'submit_guess') {
        header('Content-Type: application/json');

        $season = season_manager::get_active();
        $gameconfig = $season !== null ? season_game_config::get_for_active_season('guess') : null;
        $concept = $gameconfig !== null ? guess_manager::get_daily_concept($gamedate) : null;

        if ($season === null || $gameconfig === null || $concept === null) {
            echo json_encode(['success' => false, 'reason' => 'unavailable']);
            exit;
        }

        $state = guess_state::load($gamedate, (int) $concept->id);
        if (!empty($state['finished'])) {
            echo json_encode(['success' => false, 'reason' => 'finished']);
            exit;
        }

        $normalizedtarget = guess_manager::normalize($concept->term);
        $targetlength = core_text::strlen($normalizedtarget);
        $normalizedguess = guess_manager::normalize(optional_param('guess', '', PARAM_TEXT));

        if (!guess_manager::is_valid_guess($normalizedguess, $targetlength)) {
            echo json_encode(['success' => false, 'reason' => 'invalid']);
            exit;
        }

        $feedback = guess_manager::build_letter_feedback($normalizedguess, $normalizedtarget);
        $state['rows'][] = ['guess' => $normalizedguess, 'feedback' => $feedback];

        $won = ($normalizedguess === $normalizedtarget);
        $outofattempts = count($state['rows']) >= $maxattempts;
        $xpawarded = 0;

        if ($won) {
            $state['finished'] = true;
            $state['won'] = true;
            $result = daily_play_manager::register_play((int) $USER->id, 'guess', $gamedate, $season, $context);
            $xpawarded = (int) ($result['xpawarded'] ?? 0);
        } else if ($outofattempts) {
            $state['finished'] = true;
            $state['won'] = false;
        }

        guess_state::save($state);

        echo json_encode([
            'success'      => true,
            'feedback'     => array_values($feedback),
            'finished'     => (bool) $state['finished'],
            'won'          => $won,
            'xpawarded'    => $xpawarded,
            'target'       => $state['finished'] ? $concept->term : null,
            'attemptsleft' => max(0, $maxattempts - count($state['rows'])),
        ]);
        exit;
    }
}

// GET: render game page.
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/playergames/play_guess.php'));
$PAGE->set_pagelayout('base');
$PAGE->set_title(get_string('guess_pagetitle', 'local_playergames'));
$PAGE->set_heading(get_string('guess_pagetitle', 'local_playergames'));

$activeseason = season_manager::get_active();
$remaining    = 0;
if ($activeseason !== null) {
    $snapshot  = season_manager::get_config_snapshot($activeseason);
    $remaining = daily_play_manager::remaining_plays((int) $USER->id, 'guess', $gamedate, $snapshot);
}
$alreadyplayed = $remaining <= 0;

$gameconfig = $activeseason !== null ? season_game_config::get_for_active_season('guess') : null;
$concept    = $gameconfig !== null ? guess_manager::get_daily_concept($gamedate) : null;
$noconcept  = $concept === null;

$revealed     = false;
$revealedterm = '';
$targetlength = 0;
$rowsjson     = '[]';

if (!$noconcept && !$alreadyplayed) {
    $state = guess_state::load($gamedate, (int) $concept->id);
    $revealed = !empty($state['finished']) && empty($state['won']);
    if ($revealed) {
        // Not pre-escaped: get_string() interpolates it raw and the whole
        // resulting string is escaped once by the template's double-mustache
        // output, avoiding double-encoding of the term.
        $revealedterm = $concept->term;
    }
    $targetlength = core_text::strlen(guess_manager::normalize($concept->term));
    $rowsjson = json_encode($state['rows'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}

$templatedata = [
    'sesskey'             => sesskey(),
    'formaction'          => $formaction,
    'already_played'      => $alreadyplayed,
    'no_concept'          => $noconcept,
    'revealed'            => $revealed,
    'definition'          => $noconcept ? '' : s($concept->definition),
    'target_length'       => $targetlength,
    'max_attempts'        => $maxattempts,
    'rows_json'           => $rowsjson,
    'huburl'              => (new moodle_url('/local/playergames/hub.php'))->out(false),
    'str_already_played'  => get_string('guess_already_played', 'local_playergames'),
    'str_no_concept'      => get_string('guess_no_concept', 'local_playergames'),
    'str_revealed_title'  => get_string('guess_failed_title', 'local_playergames'),
    'str_revealed_msg'    => get_string('guess_failed_msg', 'local_playergames', $revealedterm),
    'str_definition_label' => get_string('guess_definition_label', 'local_playergames'),
    'str_enter'           => get_string('guess_enter', 'local_playergames'),
    'str_backspace'       => get_string('guess_backspace', 'local_playergames'),
    'str_invalid'         => get_string('guess_invalid', 'local_playergames'),
    'str_result_title'    => get_string('guess_result_title', 'local_playergames'),
    'str_result_xp'       => get_string('guess_result_xp', 'local_playergames'),
    'str_view_result'     => get_string('guess_view_result', 'local_playergames'),
    'str_back_to_hub'     => get_string('guess_back_to_hub', 'local_playergames'),
];

if (!$alreadyplayed && !$noconcept && !$revealed) {
    $PAGE->requires->js_call_amd('local_playergames/play_guess', 'init');
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_playergames/play_guess', $templatedata);
echo $OUTPUT->footer();
