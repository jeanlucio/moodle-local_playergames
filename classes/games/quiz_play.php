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
 * The PlayerQuiz play in progress, kept and judged on the server.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\games;

use local_playergames\hub\daily_play_manager;
use local_playergames\hub\served_questions;

/**
 * Holds the questions delivered to a player and decides every answer.
 *
 * The browser never receives which option is right. It sends the option the player chose, and the
 * server says whether it was correct, counts the wrong answers and the time, ends the play, starts
 * the cooldown and pays the XP. The play lives in the user's session, so reloading the page resumes
 * it with the same questions and the same wrong-answer count instead of drawing a fresh set. Like
 * the PlayerGuess round, it does not survive a logout.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_play {
    /** @var int Seconds a late answer is still taken as given in time, for the round trip and the clock skew. */
    private const GRACE_SECONDS = 3;

    /** @var string Key of the single play a session keeps. */
    private const CACHE_KEY = 'run';

    /**
     * Opens a play for the questions just loaded, replacing any play left open.
     *
     * @param int $gamedate Midnight timestamp of the day.
     * @param int $seasonid The active season.
     * @param quiz_question[] $loaded The questions to deliver.
     * @param \context $context Context the question text is filtered in.
     * @return array The new state.
     */
    public static function start(int $gamedate, int $seasonid, array $loaded, \context $context): array {
        $questions = [];
        foreach ($loaded as $question) {
            $questions[] = self::describe($question, $context);
        }

        $state = [
            'gamedate'     => $gamedate,
            'seasonid'     => $seasonid,
            'questions'    => $questions,
            'current'      => 0,
            'wrong'        => 0,
            'awaitingnext' => false,
            'shownat'      => time(),
            'visited'      => empty($questions) ? [] : [$questions[0]['key']],
            'finished'     => false,
            'won'          => false,
        ];
        self::save($state);
        return $state;
    }

    /**
     * Returns the play the session has open for today, or null.
     *
     * Time that ran out while the player was away counts as a wrong answer, and a question already
     * answered wrong moves on, so the player picks up at a question that is waiting for an answer.
     * A play that ended, or belongs to another day or season, is not resumable.
     *
     * @param int $userid The player.
     * @param int $gamedate Midnight timestamp of the day.
     * @param int $seasonid The active season.
     * @return array|null The state to continue, or null when a new play must start.
     */
    public static function resume(int $userid, int $gamedate, int $seasonid): ?array {
        $state = self::load();
        if (
            $state === null
            || !empty($state['finished'])
            || (int) $state['gamedate'] !== $gamedate
            || (int) $state['seasonid'] !== $seasonid
            || empty($state['questions'])
        ) {
            return null;
        }

        if (!$state['awaitingnext'] && self::timed_out($state)) {
            $state['awaitingnext'] = true;
            $state['wrong']++;
            if (self::out_of_attempts($state)) {
                $state['finished'] = true;
                (new quiz_settings())->start_cooldown($userid);
                self::save($state);
                return null;
            }
        }
        if ($state['awaitingnext']) {
            self::advance($state);
        }
        self::save($state);
        return $state;
    }

    /**
     * Judges the player's answer to the current question.
     *
     * @param int $userid The player.
     * @param int $questionindex The question the browser believes is current.
     * @param int $choice Zero-based option the player picked, or -1 when the time ran out.
     * @param \stdClass $season The active season.
     * @param \context $context Context of the event the play fires.
     * @return array {success, reason?, correct, timedout, correctindex, feedback, finished, won, xpawarded,
     *               attempt}. success is false, with a reason, when the answer is not one the play expects.
     */
    public static function answer(int $userid, int $questionindex, int $choice, \stdClass $season, \context $context): array {
        $state = self::load();
        if ($state === null || !empty($state['finished']) || (int) $state['seasonid'] !== (int) $season->id) {
            return ['success' => false, 'reason' => 'noplay'];
        }
        if ($state['awaitingnext'] || (int) $state['current'] !== $questionindex) {
            return ['success' => false, 'reason' => 'outofsync'];
        }

        $question = $state['questions'][$state['current']];
        $seconds  = (new quiz_settings())->question_seconds();
        $elapsed  = time() - (int) $state['shownat'];

        if ($choice === -1) {
            // The browser says the time ran out. Only believe it when the clock agrees, or the
            // wrong-answer limit could be dodged by never answering.
            if ($seconds <= 0 || $elapsed < $seconds - self::GRACE_SECONDS) {
                return ['success' => false, 'reason' => 'early'];
            }
            $timedout = true;
        } else if ($choice < 0 || $choice >= count($question['answers'])) {
            return ['success' => false, 'reason' => 'invalid'];
        } else {
            $timedout = $seconds > 0 && $elapsed > $seconds + self::GRACE_SECONDS;
        }

        $correct   = !$timedout && $choice === (int) $question['correct'];
        $xpawarded = 0;

        if ($correct) {
            $state['finished'] = true;
            $state['won']      = true;
            $play = daily_play_manager::register_play($userid, 'quiz', (int) $state['gamedate'], $season, $context);
            $xpawarded = (int) ($play['xpawarded'] ?? 0);
            self::record_served($userid, $state);
        } else {
            $state['wrong']++;
            $state['awaitingnext'] = true;
            if (self::out_of_attempts($state)) {
                $state['finished'] = true;
                (new quiz_settings())->start_cooldown($userid);
            }
        }
        self::save($state);

        return [
            'success'      => true,
            'correct'      => $correct,
            'timedout'     => $timedout,
            'correctindex' => (int) $question['correct'],
            'feedback'     => $question['feedback'],
            'finished'     => (bool) $state['finished'],
            'won'          => (bool) $state['won'],
            'xpawarded'    => $xpawarded,
            'attempt'      => (int) $state['wrong'] + 1,
        ];
    }

    /**
     * Moves on to the next question after a wrong answer, once the player asks for it.
     *
     * @return array {success, reason?, index, attempt}
     */
    public static function next(): array {
        $state = self::load();
        if ($state === null || !empty($state['finished']) || !$state['awaitingnext']) {
            return ['success' => false, 'reason' => 'outofsync'];
        }

        self::advance($state);
        self::save($state);
        return ['success' => true, 'index' => (int) $state['current'], 'attempt' => (int) $state['wrong'] + 1];
    }

    /**
     * What the page renders for a play: the text of every question and option, never the answer.
     *
     * @param array $state A state returned by start() or resume().
     * @return array[] Questions as the template expects them.
     */
    public static function view(array $state): array {
        $letters = ['A', 'B', 'C', 'D', 'E'];
        $view    = [];
        foreach ($state['questions'] as $i => $question) {
            $answers = [];
            foreach ($question['answers'] as $j => $text) {
                $answers[] = [
                    'index'  => $j,
                    'letter' => $letters[$j] ?? (string) ($j + 1),
                    'text'   => $text,
                ];
            }
            $view[] = [
                'index'        => $i + 1,
                'questiontext' => $question['questiontext'],
                'answers'      => $answers,
            ];
        }
        return $view;
    }

    /**
     * Seconds left on the current question, for the page that resumes a play.
     *
     * @param array $state The play.
     * @return int Whole seconds, or 0 when the question has no timer.
     */
    public static function seconds_left(array $state): int {
        $seconds = (new quiz_settings())->question_seconds();
        if ($seconds <= 0) {
            return 0;
        }
        return max(1, $seconds - (time() - (int) $state['shownat']));
    }

    /**
     * Whether the question on screen has used up its time.
     *
     * @param array $state The play.
     * @return bool
     */
    private static function timed_out(array $state): bool {
        $seconds = (new quiz_settings())->question_seconds();
        return $seconds > 0 && time() - (int) $state['shownat'] > $seconds + self::GRACE_SECONDS;
    }

    /**
     * Whether the wrong answers reached the limit that ends the play.
     *
     * @param array $state The play.
     * @return bool
     */
    private static function out_of_attempts(array $state): bool {
        $max = (new quiz_settings())->max_attempts();
        return $max > 0 && (int) $state['wrong'] >= $max;
    }

    /**
     * Makes the next question current and restarts its clock.
     *
     * @param array $state The play, changed in place.
     */
    private static function advance(array &$state): void {
        $state['current']      = ((int) $state['current'] + 1) % count($state['questions']);
        $state['awaitingnext'] = false;
        $state['shownat']      = time();
        $key = $state['questions'][$state['current']]['key'];
        if (!in_array($key, $state['visited'], true)) {
            $state['visited'][] = $key;
        }
    }

    /**
     * Marks the questions the player went through as seen today, so the next play brings new ones.
     *
     * @param int $userid The player.
     * @param array $state The finished play.
     */
    private static function record_served(int $userid, array $state): void {
        $served = [];
        foreach ($state['questions'] as $question) {
            if (in_array($question['key'], $state['visited'], true)) {
                $served[] = ['source' => $question['source'], 'sourceid' => $question['sourceid']];
            }
        }
        served_questions::record($userid, 'quiz', (int) $state['gamedate'], $served);
    }

    /**
     * Turns a loaded question into what the play keeps: the display text and the right option.
     *
     * Cartridge text is plain and is escaped; question bank text is HTML and goes through the filters.
     *
     * @param quiz_question $question The loaded question.
     * @param \context $context Context the text is filtered in.
     * @return array
     */
    private static function describe(quiz_question $question, \context $context): array {
        $isbank = $question->source === 'questionbank';
        $format = static fn(string $text): string => $isbank
            ? format_text($text, FORMAT_HTML, ['context' => $context])
            : s($text);

        $answers = [];
        $correct = -1;
        foreach ($question->answers as $j => $answer) {
            $answers[] = $format($answer->text);
            if ($answer->correct && $correct < 0) {
                $correct = (int) $j;
            }
        }

        $feedbackraw = (string) ($question->generalfeedback ?? '');
        return [
            'key'          => "{$question->source}:{$question->sourceid}",
            'source'       => $question->source,
            'sourceid'     => (int) $question->sourceid,
            'questiontext' => $format($question->questiontext),
            'answers'      => $answers,
            'correct'      => $correct,
            'feedback'     => $feedbackraw === ''
                ? ''
                : format_text($feedbackraw, $question->generalfeedbackformat, ['context' => $context]),
        ];
    }

    /**
     * Reads the session's play.
     *
     * @return array|null
     */
    private static function load(): ?array {
        $state = self::store()->get(self::CACHE_KEY);
        return is_array($state) ? $state : null;
    }

    /**
     * Writes the session's play.
     *
     * @param array $state The play.
     */
    private static function save(array $state): void {
        self::store()->set(self::CACHE_KEY, $state);
    }

    /**
     * The per-session store of the play the user has open.
     *
     * @return \cache_session
     */
    private static function store(): \cache_session {
        return \cache::make_from_params(\cache_store::MODE_SESSION, 'local_playergames', 'quizplay');
    }
}
