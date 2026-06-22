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
 * PlayerQuiz daily game entry point.
 *
 * GET  — renders the quiz game page.
 * POST action=submit_correct — records the correct answer and awards XP.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_playergames\event\game_completed;
use local_playergames\games\quiz_loader;
use local_playergames\games\season_game_config;
use local_playergames\hub\season_manager;
use local_playergames\hub\xp_manager;

require_login();
$context = context_system::instance();
require_capability('local/playergames:viewhub', $context);

$gamedate   = mktime(0, 0, 0, (int) date('n'), (int) date('j'), (int) date('Y'));
$formaction = (new moodle_url('/local/playergames/play_quiz.php'))->out(false);

// POST: AJAX score submission.
if (data_submitted()) {
    require_sesskey();
    $submitaction = optional_param('action', '', PARAM_ALPHAEXT);

    if ($submitaction === 'submit_correct') {
        header('Content-Type: application/json');

        // Guard against double submission.
        $alreadysubmitted = $DB->record_exists('local_playergames_daily_scores', [
            'userid'   => $USER->id,
            'gamedate' => $gamedate,
            'gametype' => 'quiz',
        ]);
        if ($alreadysubmitted) {
            echo json_encode(['success' => false, 'xpawarded' => 0]);
            exit;
        }

        $xpcap     = (int) get_config('local_playergames', 'xp_cap_quiz');
        $xpcap     = $xpcap > 0 ? $xpcap : 25;
        $rawxp     = $xpcap;
        $xpawarded = 0;

        $season = season_manager::get_active();
        if ($season !== null) {
            $xpawarded = xp_manager::award($USER->id, $rawxp, 'quiz', $gamedate, (int) $season->id);
        }

        $record             = new stdClass();
        $record->userid     = (int) $USER->id;
        $record->gamedate   = $gamedate;
        $record->gametype   = 'quiz';
        $record->completed  = 1;
        $record->xpawarded  = $xpawarded;
        $record->attempts   = 1;
        $record->timeplayed = time();
        $scoreid = $DB->insert_record('local_playergames_daily_scores', $record);

        // Drive the streak, missions and achievements off the completion event.
        $event = game_completed::create([
            'objectid' => $scoreid,
            'context'  => $context,
            'userid'   => (int) $USER->id,
            'other'    => [
                'seasonid'  => $season !== null ? (int) $season->id : 0,
                'gametype'  => 'quiz',
                'gamedate'  => $gamedate,
                'xpawarded' => $xpawarded,
            ],
        ]);
        $event->trigger();

        echo json_encode(['success' => true, 'xpawarded' => $xpawarded]);
        exit;
    }
}

// GET: render game page.
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/playergames/play_quiz.php'));
$PAGE->set_pagelayout('base');
$PAGE->set_title(get_string('quiz_pagetitle', 'local_playergames'));
$PAGE->set_heading(get_string('quiz_pagetitle', 'local_playergames'));

$alreadyplayed = $DB->record_exists('local_playergames_daily_scores', [
    'userid'   => $USER->id,
    'gamedate' => $gamedate,
    'gametype' => 'quiz',
]);

$questions   = [];
$noquestions = false;

if (!$alreadyplayed) {
    $gameconfig = season_game_config::get_for_active_season('quiz');
    if ($gameconfig !== null) {
        $sources      = $gameconfig->source;
        $categoryid   = (int) $gameconfig->auxid;
        $cartridgeids = $gameconfig->cartridgeids;
    } else {
        $sources      = (string) (get_config('local_playergames', 'quiz_sources') ?: 'both');
        $categoryid   = (int) (get_config('local_playergames', 'quiz_qbank_categoryid') ?: 0);
        $cartridgeids = null;
    }

    $loader = new quiz_loader();
    $loaded = $loader->load_session(20, $sources, $categoryid, $cartridgeids);

    if (empty($loaded)) {
        $noquestions = true;
    } else {
        $letters = ['A', 'B', 'C', 'D', 'E'];
        foreach ($loaded as $i => $q) {
            $qtext = $q->source === 'questionbank'
                ? format_text($q->questiontext, FORMAT_HTML, ['context' => $context])
                : s($q->questiontext);

            $answers = [];
            foreach ($q->answers as $j => $a) {
                $atext = $q->source === 'questionbank'
                    ? format_text($a->text, FORMAT_HTML, ['context' => $context])
                    : s($a->text);
                $answers[] = [
                    'letter'  => $letters[$j] ?? (string) ($j + 1),
                    'text'    => $atext,
                    'correct' => $a->correct,
                ];
            }

            $questions[] = [
                'index'        => $i + 1,
                'questiontext' => $qtext,
                'answers'      => $answers,
            ];
        }
    }
}

$templatedata = [
    'sesskey'            => sesskey(),
    'formaction'         => $formaction,
    'questions'          => $questions,
    'already_played'     => $alreadyplayed,
    'no_questions'       => $noquestions,
    'huburl'             => (new moodle_url('/local/playergames/hub.php'))->out(false),
    'str_already_played' => get_string('quiz_already_played', 'local_playergames'),
    'str_no_questions'   => get_string('quiz_no_questions', 'local_playergames'),
    'str_completed'      => get_string('quiz_completed', 'local_playergames'),
    'str_attempt'        => get_string('quiz_attempt', 'local_playergames'),
    'str_result_xp'      => get_string('quiz_result_xp', 'local_playergames'),
    'str_back_to_hub'    => get_string('quiz_back_to_hub', 'local_playergames'),
];

if (!$alreadyplayed && !$noquestions) {
    $PAGE->requires->js_call_amd('local_playergames/play_quiz', 'init');
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_playergames/play_quiz', $templatedata);
echo $OUTPUT->footer();
