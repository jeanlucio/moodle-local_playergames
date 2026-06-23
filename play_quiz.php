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

use local_playergames\games\quiz_loader;
use local_playergames\games\season_game_config;
use local_playergames\hub\daily_play_manager;
use local_playergames\hub\season_manager;
use local_playergames\hub\served_questions;

require_login();
$context = context_system::instance();
require_capability('local/playergames:viewhub', $context);

$gamedate   = mktime(0, 0, 0, (int) date('n'), (int) date('j'), (int) date('Y'));
$formaction = (new moodle_url('/local/playergames/play_quiz.php'))->out(false);

// POST: AJAX score submission.
if (data_submitted()) {
    require_sesskey();
    $submitaction = optional_param('action', '', PARAM_ALPHAEXT);

    if ($submitaction === 'submit_failed') {
        // The player ran out of attempts. Start the configured cooldown so the
        // quiz stays locked until it elapses. No XP and no daily play is spent.
        header('Content-Type: application/json');

        $cooldownminutes = (int) get_config('local_playergames', 'quiz_cooldown_minutes');
        if ($cooldownminutes > 0) {
            set_user_preference(
                'local_playergames_quizcooldown',
                time() + $cooldownminutes * 60,
                $USER->id
            );
        }

        echo json_encode(['success' => true]);
        exit;
    }

    if ($submitaction === 'submit_correct') {
        header('Content-Type: application/json');

        $season = season_manager::get_active();
        if ($season === null) {
            echo json_encode(['success' => false, 'xpawarded' => 0]);
            exit;
        }

        $result = daily_play_manager::register_play((int) $USER->id, 'quiz', $gamedate, $season, $context);

        // Record the questions the client actually showed this play so the next
        // play today excludes them. Entries arrive as "source:sourceid" strings.
        if (!empty($result['success'])) {
            $items = json_decode(optional_param('seen', '', PARAM_RAW), true);
            if (is_array($items)) {
                $served = [];
                foreach ($items as $entry) {
                    if (!is_string($entry) || strpos($entry, ':') === false) {
                        continue;
                    }
                    [$source, $sourceid] = explode(':', $entry, 2);
                    if (in_array($source, ['cartridge', 'questionbank'], true) && (int) $sourceid > 0) {
                        $served[] = ['source' => $source, 'sourceid' => (int) $sourceid];
                    }
                }
                served_questions::record((int) $USER->id, 'quiz', $gamedate, $served);
            }
        }

        echo json_encode($result);
        exit;
    }
}

// GET: render game page.
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/playergames/play_quiz.php'));
$PAGE->set_pagelayout('base');
$PAGE->set_title(get_string('quiz_pagetitle', 'local_playergames'));
$PAGE->set_heading(get_string('quiz_pagetitle', 'local_playergames'));

$activeseason = season_manager::get_active();
$remaining    = 0;
if ($activeseason !== null) {
    $snapshot  = season_manager::get_config_snapshot($activeseason);
    $remaining = daily_play_manager::remaining_plays((int) $USER->id, 'quiz', $gamedate, $snapshot);
}
$alreadyplayed = $remaining <= 0;

// Gameplay rules configured by the admin (read live so changes apply at once).
$secondscfg      = get_config('local_playergames', 'quiz_question_seconds');
$questionseconds = ($secondscfg === false || $secondscfg === '') ? 120 : max(0, (int) $secondscfg);
$maxattempts     = max(0, (int) get_config('local_playergames', 'quiz_max_attempts'));
$cooldownminutes = max(0, (int) get_config('local_playergames', 'quiz_cooldown_minutes'));
$sizecfg         = get_config('local_playergames', 'quiz_session_size');
$sessionsize     = ((int) $sizecfg) > 0 ? (int) $sizecfg : 20;

// Cooldown lock: set when the player previously ran out of attempts.
$cooldownuntil     = (int) get_user_preferences('local_playergames_quizcooldown', 0, $USER->id);
$cooldownremaining = $cooldownuntil - time();
$cooldownactive    = !$alreadyplayed && $cooldownremaining > 0;

$questions   = [];
$noquestions = false;

if (!$alreadyplayed && !$cooldownactive) {
    $gameconfig = season_game_config::get_for_active_season('quiz');
    if ($gameconfig !== null) {
        $sources      = $gameconfig->source;
        $categoryid   = (int) $gameconfig->auxid;
        $cartridgeids = $gameconfig->cartridgeids;
    } else {
        // Defensive fallback for a season created before per-season config existed.
        $sources      = season_game_config::SOURCE_CARTRIDGE;
        $categoryid   = 0;
        $cartridgeids = null;
    }

    $exclude = served_questions::get_excluded((int) $USER->id, 'quiz', $gamedate);
    $loader  = new quiz_loader();
    $loaded  = $loader->load_session($sessionsize, $sources, $categoryid, $cartridgeids, $exclude);

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

            $feedbackraw = (string) ($q->generalfeedback ?? '');
            $feedback    = $feedbackraw === ''
                ? ''
                : format_text($feedbackraw, $q->generalfeedbackformat, ['context' => $context]);

            $questions[] = [
                'index'        => $i + 1,
                'questiontext' => $qtext,
                'answers'      => $answers,
                'source'       => $q->source,
                'sourceid'     => (int) $q->sourceid,
                'feedback'     => $feedback,
                'has_feedback' => $feedback !== '',
            ];
        }
    }
}

$failedmsg = $cooldownminutes > 0
    ? get_string('quiz_failed_cooldown', 'local_playergames', $cooldownminutes)
    : get_string('quiz_failed_retry', 'local_playergames');

$templatedata = [
    'sesskey'            => sesskey(),
    'formaction'         => $formaction,
    'questions'          => $questions,
    'already_played'     => $alreadyplayed,
    'cooldown_active'    => $cooldownactive,
    'no_questions'       => $noquestions,
    'question_seconds'   => $questionseconds,
    'max_attempts'       => $maxattempts,
    'huburl'             => (new moodle_url('/local/playergames/hub.php'))->out(false),
    'str_already_played' => get_string('quiz_already_played', 'local_playergames'),
    'str_locked'         => get_string('quiz_locked', 'local_playergames'),
    'str_locked_msg'     => get_string(
        'quiz_locked_msg',
        'local_playergames',
        format_time(max(0, $cooldownremaining))
    ),
    'str_no_questions'   => get_string('quiz_no_questions', 'local_playergames'),
    'str_completed'      => get_string('quiz_completed', 'local_playergames'),
    'str_attempt'        => get_string('quiz_attempt', 'local_playergames'),
    'str_time_left'      => get_string('quiz_time_left', 'local_playergames'),
    'str_continue'       => get_string('quiz_continue', 'local_playergames'),
    'str_view_result'    => get_string('quiz_view_result', 'local_playergames'),
    'str_failed'         => get_string('quiz_failed', 'local_playergames'),
    'str_failed_msg'     => $failedmsg,
    'str_result_xp'      => get_string('quiz_result_xp', 'local_playergames'),
    'str_back_to_hub'    => get_string('quiz_back_to_hub', 'local_playergames'),
];

if (!$alreadyplayed && !$cooldownactive && !$noquestions) {
    $PAGE->requires->js_call_amd('local_playergames/play_quiz', 'init');
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_playergames/play_quiz', $templatedata);
echo $OUTPUT->footer();
