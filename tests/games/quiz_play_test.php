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
 * Tests for the server side of a PlayerQuiz play.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\games;

use local_playergames\hub\served_questions;

/**
 * Unit tests for {@see quiz_play}.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_playergames\games\quiz_play
 */
final class quiz_play_test extends \advanced_testcase {
    /** @var \stdClass The active season of the test. */
    private \stdClass $season;

    /** @var int Midnight of today. */
    private int $today;

    /** @var \stdClass The player. */
    private \stdClass $user;

    /**
     * Creates an active season paying 10 XP for one quiz play a day, and a player.
     *
     * @return void
     */
    private function prepare(): void {
        global $DB;
        $this->resetAfterTest();
        $this->redirectEvents();

        $now = time();
        $id  = (int) $DB->insert_record('local_playergames_seasons', (object) [
            'name' => 'Season',
            'startdate' => $now - DAYSECS,
            'enddate' => $now + (DAYSECS * 30),
            'status' => 'active',
            'config_snapshot' => json_encode(['xp_per_game_quiz' => 10, 'xp_games_quiz' => 1]),
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $this->season = $DB->get_record('local_playergames_seasons', ['id' => $id], '*', MUST_EXIST);
        $this->today  = mktime(0, 0, 0);
        $this->user   = $this->getDataGenerator()->create_user();
        $this->setUser($this->user);
        set_config('quiz_question_seconds', 0, 'local_playergames');
        set_config('quiz_max_attempts', 0, 'local_playergames');
        set_config('quiz_cooldown_minutes', 0, 'local_playergames');
    }

    /**
     * Builds questions whose right option is the first one before the loader shuffles; here, as given.
     *
     * @param int $count How many questions.
     * @param int $correct Position of the right option in every question.
     * @return quiz_question[]
     */
    private function questions(int $count = 3, int $correct = 2): array {
        $questions = [];
        for ($i = 1; $i <= $count; $i++) {
            $question = new quiz_question();
            $question->source = 'cartridge';
            $question->sourceid = 100 + $i;
            $question->questiontext = "Question {$i}?";
            $question->generalfeedback = "Feedback {$i}";
            for ($j = 0; $j < 5; $j++) {
                $answer = new quiz_answer();
                $answer->text = "Q{$i} option {$j}";
                $answer->correct = $j === $correct;
                $question->answers[] = $answer;
            }
            $questions[] = $question;
        }
        return $questions;
    }

    /**
     * Opens a play for the current user.
     *
     * @param int $count How many questions.
     * @return array The state.
     */
    private function start(int $count = 3): array {
        return quiz_play::start($this->today, (int) $this->season->id, $this->questions($count), \context_system::instance());
    }

    /**
     * Sends an answer for the current user.
     *
     * @param int $question Index of the question the browser believes is current.
     * @param int $choice Option picked, or -1 for a timeout.
     * @return array The server's verdict.
     */
    private function answer(int $question, int $choice): array {
        return quiz_play::answer((int) $this->user->id, $question, $choice, $this->season, \context_system::instance());
    }

    /**
     * Rewrites the stored play, to move its clock without waiting.
     *
     * @param callable $change Receives the state and returns the changed one.
     * @return void
     */
    private function change_state(callable $change): void {
        $store = \cache::make_from_params(\cache_store::MODE_SESSION, 'local_playergames', 'quizplay');
        $store->set('run', $change($store->get('run')));
    }

    /**
     * What the browser receives names no right option, and the verdict comes from the server.
     *
     * Regression test: the page carried data-correct on every option and posted submit_correct on the
     * browser's word, so a request made without playing earned the day's XP.
     */
    public function test_the_page_data_does_not_say_which_option_is_right(): void {
        $this->prepare();
        $state = $this->start();

        $view = quiz_play::view($state);

        $this->assertCount(3, $view);
        foreach ($view as $question) {
            $this->assertSame(['index', 'letter', 'text'], array_keys($question['answers'][0]));
        }
        $this->assertStringNotContainsString('correct', strtolower(json_encode($view)));
        $this->assertStringNotContainsString('Feedback', json_encode($view));
    }

    /**
     * A correct answer ends the play, pays the XP once and records the day's play.
     */
    public function test_a_correct_answer_wins_and_pays_the_xp(): void {
        global $DB;
        $this->prepare();
        $this->start();

        $result = $this->answer(0, 2);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['correct']);
        $this->assertTrue($result['finished']);
        $this->assertTrue($result['won']);
        $this->assertSame(10, $result['xpawarded']);
        $this->assertSame(
            1,
            (int) $DB->get_field('local_playergames_daily_scores', 'attempts', ['userid' => $this->user->id, 'gametype' => 'quiz'])
        );
    }

    /**
     * A wrong answer reveals the right option and the feedback, and waits for the player to continue.
     */
    public function test_a_wrong_answer_reveals_the_right_option_and_waits(): void {
        $this->prepare();
        $this->start();

        $result = $this->answer(0, 0);

        $this->assertTrue($result['success']);
        $this->assertFalse($result['correct']);
        $this->assertFalse($result['finished']);
        $this->assertSame(2, $result['correctindex']);
        $this->assertSame('Feedback 1', $result['feedback']);
        $this->assertSame(2, $result['attempt']);

        // Nothing else is accepted until the player continues.
        $this->assertSame('outofsync', $this->answer(0, 2)['reason']);

        $next = quiz_play::next();
        $this->assertTrue($next['success']);
        $this->assertSame(1, $next['index']);
        $this->assertTrue($this->answer(1, 2)['won']);
    }

    /**
     * Questions repeat in turn when the player keeps answering wrong.
     */
    public function test_the_questions_come_round_again(): void {
        $this->prepare();
        $this->start(2);

        $this->answer(0, 0);
        $this->assertSame(1, quiz_play::next()['index']);
        $this->answer(1, 0);
        $this->assertSame(0, quiz_play::next()['index']);
    }

    /**
     * The wrong-answer limit ends the play in failure, with no XP, and starts the cooldown.
     *
     * Regression test: the cooldown only started when the browser chose to report the failure, so
     * a player who never reported it had no waiting time.
     */
    public function test_running_out_of_attempts_fails_and_starts_the_cooldown(): void {
        global $DB;
        $this->prepare();
        set_config('quiz_max_attempts', 2, 'local_playergames');
        set_config('quiz_cooldown_minutes', 10, 'local_playergames');
        $this->start();

        $this->answer(0, 0);
        quiz_play::next();
        $result = $this->answer(1, 1);

        $this->assertTrue($result['finished']);
        $this->assertFalse($result['won']);
        $this->assertSame(0, $result['xpawarded']);
        $this->assertGreaterThan(0, (new quiz_settings())->cooldown_remaining((int) $this->user->id));
        $this->assertFalse($DB->record_exists('local_playergames_daily_scores', ['userid' => $this->user->id]));
        $this->assertSame('noplay', $this->answer(1, 2)['reason']);
    }

    /**
     * An answer for no play, for another question or for an option that does not exist changes nothing.
     */
    public function test_answers_the_play_does_not_expect_are_refused(): void {
        $this->prepare();

        $this->assertSame('noplay', $this->answer(0, 2)['reason']);
        $this->assertSame('outofsync', quiz_play::next()['reason']);

        $this->start();
        $this->assertSame('outofsync', $this->answer(1, 2)['reason']);
        $this->assertSame('invalid', $this->answer(0, 5)['reason']);
        $this->assertSame('invalid', $this->answer(0, -5)['reason']);
        // The play is still open and can be won.
        $this->assertTrue($this->answer(0, 2)['won']);
    }

    /**
     * Winning twice with the same play is not possible.
     */
    public function test_a_won_play_cannot_be_won_again(): void {
        $this->prepare();
        $this->start();

        $this->assertTrue($this->answer(0, 2)['won']);
        $this->assertSame('noplay', $this->answer(0, 2)['reason']);
        $this->assertSame('outofsync', quiz_play::next()['reason']);
    }

    /**
     * A timeout is believed only when the clock agrees, so it cannot be used to skip the limit either way.
     */
    public function test_a_timeout_counts_only_when_the_time_really_ran_out(): void {
        $this->prepare();
        set_config('quiz_question_seconds', 60, 'local_playergames');
        $this->start();

        $this->assertSame('early', $this->answer(0, -1)['reason']);

        $this->change_state(function (array $state): array {
            $state['shownat'] = time() - 61;
            return $state;
        });
        $result = $this->answer(0, -1);
        $this->assertTrue($result['success']);
        $this->assertTrue($result['timedout']);
        $this->assertFalse($result['correct']);
        $this->assertSame(2, $result['correctindex']);
    }

    /**
     * Answering after the time is up is a wrong answer even when the option was the right one.
     */
    public function test_a_late_answer_is_wrong(): void {
        $this->prepare();
        set_config('quiz_question_seconds', 60, 'local_playergames');
        $this->start();
        $this->change_state(function (array $state): array {
            $state['shownat'] = time() - 600;
            return $state;
        });

        $result = $this->answer(0, 2);

        $this->assertTrue($result['timedout']);
        $this->assertFalse($result['correct']);
        $this->assertFalse($result['won']);
    }

    /**
     * A timer cannot be dodged by never reporting it: the play only goes on with the same wrong count.
     */
    public function test_a_reload_resumes_the_same_questions_and_keeps_the_wrong_answers(): void {
        $this->prepare();
        set_config('quiz_max_attempts', 3, 'local_playergames');
        $state = $this->start();
        $this->answer(0, 0);

        $resumed = quiz_play::resume((int) $this->user->id, $this->today, (int) $this->season->id);

        $this->assertNotNull($resumed);
        $this->assertSame(array_column($state['questions'], 'key'), array_column($resumed['questions'], 'key'));
        $this->assertSame(1, $resumed['wrong']);
        // The question answered wrong was left behind; the player picks up at the next one.
        $this->assertSame(1, $resumed['current']);
        $this->assertFalse($resumed['awaitingnext']);
    }

    /**
     * Time that ran out while the player was away counts as a wrong answer when the page comes back.
     */
    public function test_time_lost_during_an_absence_counts_as_a_wrong_answer(): void {
        $this->prepare();
        set_config('quiz_question_seconds', 60, 'local_playergames');
        set_config('quiz_max_attempts', 1, 'local_playergames');
        set_config('quiz_cooldown_minutes', 10, 'local_playergames');
        $this->start();
        $this->change_state(function (array $state): array {
            $state['shownat'] = time() - 600;
            return $state;
        });

        $resumed = quiz_play::resume((int) $this->user->id, $this->today, (int) $this->season->id);

        $this->assertNull($resumed);
        $this->assertGreaterThan(0, (new quiz_settings())->cooldown_remaining((int) $this->user->id));
    }

    /**
     * A play that ended, or belongs to another day or season, is not resumed.
     */
    public function test_only_an_open_play_of_today_is_resumed(): void {
        $this->prepare();
        $userid = (int) $this->user->id;
        $this->assertNull(quiz_play::resume($userid, $this->today, (int) $this->season->id));

        $this->start();
        $this->assertNull(quiz_play::resume($userid, $this->today - DAYSECS, (int) $this->season->id));
        $this->assertNull(quiz_play::resume($userid, $this->today, (int) $this->season->id + 1));
        $this->assertNotNull(quiz_play::resume($userid, $this->today, (int) $this->season->id));

        $this->answer(0, 2);
        $this->assertNull(quiz_play::resume($userid, $this->today, (int) $this->season->id));
    }

    /**
     * Winning marks the questions the player went through as seen, and only those.
     */
    public function test_the_questions_gone_through_are_marked_as_seen_on_a_win(): void {
        $this->prepare();
        $this->start(3);

        $this->answer(0, 0);
        quiz_play::next();
        $this->answer(1, 2);

        $seen = served_questions::get_excluded((int) $this->user->id, 'quiz', $this->today);
        $this->assertEqualsCanonicalizing([101, 102], $seen['cartridge']);
    }

    /**
     * A user the participant rules exclude earns nothing, even with a play the server accepted.
     */
    public function test_a_user_outside_the_participants_earns_no_xp(): void {
        $this->prepare();
        set_config('allowed_participants', 'staff', 'local_playergames');
        $this->start();

        $result = $this->answer(0, 2);

        $this->assertTrue($result['correct']);
        $this->assertSame(0, $result['xpawarded']);
    }
}
