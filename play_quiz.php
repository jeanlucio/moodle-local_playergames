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
use local_playergames\games\quiz_play;
use local_playergames\games\quiz_settings;
use local_playergames\games\season_game_config;
use local_playergames\hub\daily_play_manager;
use local_playergames\hub\season_manager;
use local_playergames\hub\served_questions;
use local_playergames\local\game_page;

require_login();
$context = context_system::instance();
require_capability('local/playergames:viewhub', $context);
\local_playergames\local\access::require_participant();

$gamedate   = mktime(0, 0, 0, (int) date('n'), (int) date('j'), (int) date('Y'));
$formaction = (new moodle_url('/local/playergames/play_quiz.php'))->out(false);

// POST: AJAX answers. The server judges each one, so the page never receives which option is right.
if (data_submitted()) {
    require_sesskey();
    header('Content-Type: application/json');

    $season = season_manager::get_active();
    $remaining = $season === null ? 0 : daily_play_manager::remaining_plays(
        (int) $USER->id,
        'quiz',
        $gamedate,
        season_manager::get_config_snapshot($season)
    );
    if (
        $season === null
        || $remaining <= 0
        || season_game_config::is_disabled((int) $season->id, 'quiz')
        || (new quiz_settings())->is_on_cooldown((int) $USER->id)
    ) {
        echo json_encode(['success' => false, 'reason' => 'unavailable']);
        exit;
    }

    $submitaction = optional_param('action', '', PARAM_ALPHAEXT);
    if ($submitaction === 'answer') {
        echo json_encode(quiz_play::answer(
            (int) $USER->id,
            optional_param('question', -1, PARAM_INT),
            optional_param('choice', -2, PARAM_INT),
            $season,
            $context
        ));
        exit;
    }
    if ($submitaction === 'next') {
        echo json_encode(quiz_play::next());
        exit;
    }

    echo json_encode(['success' => false, 'reason' => 'unknownaction']);
    exit;
}

// GET: render game page.
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/playergames/play_quiz.php'));
$PAGE->set_pagelayout('base');
$PAGE->set_title(get_string('quiz_pagetitle', 'local_playergames'));
$PAGE->set_heading(get_string('quiz_pagetitle', 'local_playergames'));

$activeseason = season_manager::get_active();
if ($activeseason === null) {
    echo $OUTPUT->header();
    echo game_page::no_season_html();
    echo $OUTPUT->footer();
    exit;
}
if (season_game_config::is_disabled((int) $activeseason->id, 'quiz')) {
    echo $OUTPUT->header();
    echo game_page::disabled_html();
    echo $OUTPUT->footer();
    exit;
}
$snapshot      = season_manager::get_config_snapshot($activeseason);
$remaining     = daily_play_manager::remaining_plays((int) $USER->id, 'quiz', $gamedate, $snapshot);
$alreadyplayed = $remaining <= 0;

// Gameplay rules configured by the admin (read live so changes apply at once).
$settings        = new quiz_settings();
$questionseconds = $settings->question_seconds();
$cooldownminutes = $settings->cooldown_minutes();
$sessionsize     = $settings->session_size();

// Cooldown lock: set when the player previously ran out of attempts.
$cooldownremaining = $settings->cooldown_remaining((int) $USER->id);
$cooldownactive    = !$alreadyplayed && $cooldownremaining > 0;

$questions    = [];
$noquestions  = false;
$currentindex = 0;
$attempt      = 1;
$firstseconds = $questionseconds;

if (!$alreadyplayed && !$cooldownactive) {
    // A play left open by a reload continues with the same questions and wrong-answer count.
    $play = quiz_play::resume((int) $USER->id, $gamedate, (int) $activeseason->id);

    if ($play === null) {
        // A cooldown can start while resuming, when time ran out during an absence.
        $cooldownremaining = $settings->cooldown_remaining((int) $USER->id);
        $cooldownactive    = $cooldownremaining > 0;
    }

    if ($play === null && !$cooldownactive) {
        // A season with no row for the game predates per-season config: fall back to the cartridges.
        $gameconfig = season_game_config::get_for_active_season('quiz');
        if ($gameconfig !== null) {
            $sources      = $gameconfig->source;
            $categoryid   = (int) $gameconfig->auxid;
            $cartridgeids = $gameconfig->cartridgeids;
        } else {
            $sources      = season_game_config::SOURCE_CARTRIDGE;
            $categoryid   = 0;
            $cartridgeids = null;
        }

        $exclude = served_questions::get_excluded((int) $USER->id, 'quiz', $gamedate);
        $loaded  = (new quiz_loader())->load_session($sessionsize, $sources, $categoryid, $cartridgeids, $exclude);
        if (!empty($loaded)) {
            $play = quiz_play::start($gamedate, (int) $activeseason->id, $loaded, $context);
        }
    }

    if ($play !== null) {
        $questions    = quiz_play::view($play);
        $currentindex = (int) $play['current'];
        $attempt      = (int) $play['wrong'] + 1;
        $firstseconds = quiz_play::seconds_left($play);
    } else if (!$cooldownactive) {
        $noquestions = true;
    }
}

$failedmsg = $cooldownminutes > 0
    ? get_string('quiz_failed_cooldown', 'local_playergames', $cooldownminutes)
    : get_string('quiz_failed_retry', 'local_playergames');

$templatedata = [
    'sesskey'            => sesskey(),
    'formaction'         => $formaction,
    'questions'          => $questions,
    'current_index'      => $currentindex,
    'attempt'            => $attempt,
    'first_seconds'      => $firstseconds,
    'already_played'     => $alreadyplayed,
    'cooldown_active'    => $cooldownactive,
    'no_questions'       => $noquestions,
    'question_seconds'   => $questionseconds,
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
