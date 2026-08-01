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
 * PlayerFill daily game entry point.
 *
 * GET  — renders the crossword-style game page.
 * POST action=submit_word_guess — validates one word guess server-side and returns
 * the full puzzle panel (a correct guess can cross-reveal letters in other pending
 * words too); awards XP through daily_play_manager once every word is resolved.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_playergames\games\fill_manager;
use local_playergames\games\fill_state;
use local_playergames\games\guess_manager;
use local_playergames\games\season_game_config;
use local_playergames\hub\daily_play_manager;
use local_playergames\hub\season_manager;

require_login();
$context = context_system::instance();
require_capability('local/playergames:viewhub', $context);

$gamedate    = mktime(0, 0, 0, (int) date('n'), (int) date('j'), (int) date('Y'));
$formaction  = (new moodle_url('/local/playergames/play_fill.php'))->out(false);
$maxattempts = fill_manager::max_attempts();

// POST: AJAX word guess submission.
if (data_submitted()) {
    require_sesskey();
    $submitaction = optional_param('action', '', PARAM_ALPHAEXT);

    if ($submitaction === 'submit_word_guess') {
        header('Content-Type: application/json');

        $season     = season_manager::get_active();
        $gameconfig = $season !== null ? season_game_config::get_for_active_season('fill') : null;
        $concepts   = $gameconfig !== null ? fill_manager::get_daily_concepts($gamedate) : [];

        if ($season === null || $gameconfig === null || count($concepts) < 2) {
            echo json_encode(['success' => false, 'reason' => 'unavailable']);
            exit;
        }

        $state = fill_state::load($gamedate, $concepts);
        if (!empty($state['finished'])) {
            echo json_encode(['success' => false, 'reason' => 'finished']);
            exit;
        }

        $conceptid = optional_param('conceptid', 0, PARAM_INT);
        $index = null;
        foreach ($state['words'] as $key => $word) {
            if ($word['conceptid'] === $conceptid) {
                $index = $key;
                break;
            }
        }

        if ($index === null || $state['words'][$index]['resolved'] || $state['words'][$index]['exhausted']) {
            echo json_encode(['success' => false, 'reason' => 'invalid']);
            exit;
        }

        $targetword = $state['words'][$index]['word'];
        $targetlength = core_text::strlen($targetword);
        $normalizedguess = guess_manager::normalize(optional_param('guess', '', PARAM_TEXT));

        if (!guess_manager::is_valid_guess($normalizedguess, $targetlength)) {
            echo json_encode(['success' => false, 'reason' => 'invalid']);
            exit;
        }

        $state['words'][$index]['attemptsused']++;
        $resolved = false;
        $xpawarded = 0;

        if ($normalizedguess === $targetword) {
            $resolved = true;
            $state['words'][$index]['resolved'] = true;
            $state['revealedslots'] = array_values(array_unique(array_merge(
                $state['revealedslots'],
                $state['words'][$index]['slots']
            )));
            $state['words'] = fill_manager::apply_cascade($state['words'], $state['revealedslots']);

            $allresolved = true;
            foreach ($state['words'] as $word) {
                if (!$word['resolved']) {
                    $allresolved = false;
                    break;
                }
            }

            if ($allresolved) {
                $state['finished'] = true;
                $state['won'] = true;
                $result = daily_play_manager::register_play((int) $USER->id, 'fill', $gamedate, $season, $context);
                $xpawarded = (int) ($result['xpawarded'] ?? 0);
            }
        } else if ($state['words'][$index]['attemptsused'] >= $maxattempts) {
            $state['words'][$index]['exhausted'] = true;
            $state['finished'] = true;
            $state['won'] = false;
        }

        fill_state::save($state);

        echo json_encode([
            'success'   => true,
            'resolved'  => $resolved,
            'finished'  => (bool) $state['finished'],
            'won'       => (bool) $state['won'],
            'xpawarded' => $xpawarded,
            'words'     => fill_manager::build_words_payload(
                $state,
                !empty($state['finished']) && empty($state['won'])
            ),
        ]);
        exit;
    }
}

// GET: render game page.
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/playergames/play_fill.php'));
$PAGE->set_pagelayout('base');
$PAGE->set_title(get_string('fill_pagetitle', 'local_playergames'));
$PAGE->set_heading(get_string('fill_pagetitle', 'local_playergames'));

$activeseason = season_manager::get_active();
$remaining    = 0;
if ($activeseason !== null) {
    $snapshot  = season_manager::get_config_snapshot($activeseason);
    $remaining = daily_play_manager::remaining_plays((int) $USER->id, 'fill', $gamedate, $snapshot);
}
$alreadyplayed = $remaining <= 0;

$gameconfig = $activeseason !== null ? season_game_config::get_for_active_season('fill') : null;
$concepts   = $gameconfig !== null ? fill_manager::get_daily_concepts($gamedate) : [];
$noconcept  = count($concepts) < 2;

$revealed = false;
$rows = [];

if (!$noconcept && !$alreadyplayed) {
    $state = fill_state::load($gamedate, $concepts);
    $revealed = !empty($state['finished']) && empty($state['won']);

    foreach ($state['words'] as $word) {
        $tiles = fill_manager::build_tiles($word['word'], $word['slots'], $state['revealedslots']);
        $rows[] = [
            'conceptid'    => $word['conceptid'],
            // Not pre-escaped: s() output rendered via the template's triple-mustache
            // avoids double-encoding, the same pattern used for PlayerGuess's definition.
            'definition'   => s($word['definition']),
            'tiles'        => array_values($tiles),
            'resolved'     => $word['resolved'],
            'exhausted'    => $word['exhausted'],
            'revealword'   => ($word['resolved'] || $revealed) ? s($word['originalterm']) : '',
            'canguess'     => !$word['resolved'] && !$word['exhausted'] && !$revealed,
            'attemptsused' => $word['attemptsused'],
        ];
    }
}

$templatedata = [
    'sesskey'              => sesskey(),
    'formaction'           => $formaction,
    'already_played'       => $alreadyplayed,
    'no_concept'           => $noconcept,
    'revealed'             => $revealed,
    'rows'                 => $rows,
    'max_attempts'         => $maxattempts,
    'huburl'               => (new moodle_url('/local/playergames/hub.php'))->out(false),
    'str_already_played'   => get_string('fill_already_played', 'local_playergames'),
    'str_no_concept'       => get_string('fill_no_concept', 'local_playergames'),
    'str_revealed_title'   => get_string('fill_revealed_title', 'local_playergames'),
    'str_revealed_msg'     => get_string('fill_revealed_msg', 'local_playergames'),
    'str_definition_label' => get_string('fill_definition_label', 'local_playergames'),
    'str_guess_label'      => get_string('fill_guess_label', 'local_playergames'),
    'str_submit'           => get_string('fill_submit', 'local_playergames'),
    'str_invalid'          => get_string('fill_invalid', 'local_playergames'),
    'str_solved'           => get_string('fill_word_solved', 'local_playergames'),
    'str_result_title'     => get_string('fill_result_title', 'local_playergames'),
    'str_result_xp'        => get_string('fill_result_xp', 'local_playergames'),
    'str_view_result'      => get_string('fill_view_result', 'local_playergames'),
    'str_back_to_hub'      => get_string('fill_back_to_hub', 'local_playergames'),
];

if (!$alreadyplayed && !$noconcept && !$revealed) {
    $PAGE->requires->js_call_amd('local_playergames/play_fill', 'init');
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_playergames/play_fill', $templatedata);
echo $OUTPUT->footer();
