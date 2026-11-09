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
 * Tests for the PlayerBattle manager.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\games;

use local_playergames\hub\served_questions;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Unit tests for {@see battle_manager}.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(battle_manager::class)]
final class battle_manager_test extends \advanced_testcase {
    /** @var \stdClass The active season of the test. */
    private \stdClass $season;

    /** @var int Midnight of today. */
    private int $today;

    /**
     * Creates an active season and a player, and silences the events the plays fire.
     *
     * @param int $games Battle plays that earn XP per day.
     * @return \stdClass The player.
     */
    private function prepare(int $games = 1): \stdClass {
        global $DB;
        $this->resetAfterTest();
        $this->redirectEvents();

        $now = time();
        $id  = (int) $DB->insert_record('local_playergames_seasons', (object) [
            'name' => 'Season',
            'startdate' => $now - DAYSECS,
            'enddate' => $now + (DAYSECS * 30),
            'status' => 'active',
            'config_snapshot' => json_encode(['xp_per_game_battle' => 10, 'xp_games_battle' => $games]),
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $this->season = $DB->get_record('local_playergames_seasons', ['id' => $id], '*', MUST_EXIST);
        $this->today  = mktime(0, 0, 0);

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        return $user;
    }

    /**
     * Adds a cartridge question with a correct answer and four distractors.
     *
     * @param string $text Question text.
     * @return int Question id.
     */
    private function add_question(string $text = 'Q?'): int {
        global $DB;
        $now = time();
        $cartridgeid = (int) $DB->get_field('local_playergames_cartridges', 'id', ['name' => 'Battle']);
        if (!$cartridgeid) {
            $cartridgeid = (int) $DB->insert_record('local_playergames_cartridges', (object) [
                'name' => 'Battle', 'version' => '1.0', 'language' => 'en', 'type' => 'quiz',
                'timecreated' => $now, 'timemodified' => $now, 'uploadedby' => 0, 'active' => 1,
            ]);
        }
        $qid = (int) $DB->insert_record('local_playergames_concept_questions', (object) [
            'conceptid' => null, 'cartridgeid' => $cartridgeid, 'questiontext' => $text,
            'source' => 'manual', 'difficulty' => 3, 'categoryid' => null,
            'generalfeedback' => null, 'timecreated' => $now,
        ]);
        $DB->insert_record('local_playergames_concept_answers', (object) [
            'questionid' => $qid, 'answertext' => 'Correct', 'iscorrect' => 1, 'sortorder' => 0,
        ], false);
        for ($i = 1; $i <= 4; $i++) {
            $DB->insert_record('local_playergames_concept_answers', (object) [
                'questionid' => $qid, 'answertext' => "Wrong {$i}", 'iscorrect' => 0, 'sortorder' => $i,
            ], false);
        }
        return $qid;
    }

    /**
     * Opens a battle for the current user.
     *
     * @return array The questions delivered.
     */
    private function start(): array {
        return (new battle_manager())->start_battle(
            (int) $GLOBALS['USER']->id,
            $this->today,
            \context_system::instance()
        );
    }

    /**
     * Reports how the open battle ended.
     *
     * @param bool $victory Whether the player won.
     * @param int $damage Damage dealt to the opponent.
     * @param array $extra Other result values, overriding the defaults.
     * @return array The manager's answer.
     */
    private function finish(bool $victory, int $damage = 50, array $extra = []): array {
        return (new battle_manager())->submit_result(
            (int) $GLOBALS['USER']->id,
            $this->season,
            \context_system::instance(),
            $extra + [
                'victory' => $victory,
                'bosshpdealt' => $damage,
                'questionscorrect' => 2,
                'questionstotal' => 3,
                'seen' => [],
            ]
        );
    }

    /**
     * Returns the day's battle_scores row of a user.
     *
     * @param \stdClass $user The player.
     * @return \stdClass|false
     */
    private function score_row(\stdClass $user) {
        global $DB;
        return $DB->get_record('local_playergames_battle_scores', ['userid' => $user->id, 'gamedate' => $this->today]);
    }

    public function test_start_returns_questions_ready_for_the_browser(): void {
        $this->prepare();
        $first  = $this->add_question();
        $second = $this->add_question();

        $questions = $this->start();

        $this->assertCount(2, $questions);
        $keys = array_column($questions, 'key');
        $this->assertEqualsCanonicalizing(["cartridge:{$first}", "cartridge:{$second}"], $keys);
        foreach ($questions as $question) {
            $this->assertCount(5, $question['answers']);
            $this->assertCount(1, array_filter($question['answers'], static fn($a): bool => $a['correct']));
        }
    }

    public function test_cartridge_text_is_escaped_once(): void {
        $this->prepare();
        $this->add_question('Is 1 < 2 & 3 > 2?');

        $questions = $this->start();

        $this->assertSame('Is 1 &lt; 2 &amp; 3 &gt; 2?', $questions[0]['questiontext']);
    }

    public function test_no_questions_opens_no_battle(): void {
        $user = $this->prepare();

        $this->assertSame([], $this->start());
        $result = $this->finish(true);

        $this->assertFalse($result['success']);
        $this->assertFalse($this->score_row($user));
    }

    public function test_submit_without_a_battle_earns_nothing(): void {
        global $DB;
        $user = $this->prepare();

        $result = $this->finish(true);

        $this->assertFalse($result['success']);
        $this->assertSame(0, $result['xpawarded']);
        $this->assertFalse($this->score_row($user));
        $this->assertSame(0, $DB->count_records('local_playergames_daily_scores', ['userid' => $user->id]));
    }

    public function test_defeat_is_saved_without_xp_and_keeps_the_play(): void {
        global $DB;
        $user = $this->prepare();
        $this->add_question();
        $this->start();

        $result = $this->finish(false, 30);

        $this->assertTrue($result['success']);
        $this->assertFalse($result['victory']);
        $this->assertSame(0, $result['xpawarded']);
        $this->assertSame(1, $result['remaining']);
        $row = $this->score_row($user);
        $this->assertSame(0, (int) $row->victory);
        $this->assertSame(30, (int) $row->bosshpdealt);
        $this->assertSame(0, $DB->count_records('local_playergames_daily_scores', ['userid' => $user->id]));
    }

    public function test_victory_earns_the_season_xp_and_spends_the_play(): void {
        global $DB;
        $user = $this->prepare();
        $this->add_question();
        $this->start();

        $result = $this->finish(true, 100);

        $this->assertTrue($result['victory']);
        $this->assertSame(10, $result['xpawarded']);
        $this->assertSame(0, $result['remaining']);
        $row = $this->score_row($user);
        $this->assertSame(1, (int) $row->victory);
        $this->assertSame(10, (int) $row->xpawarded);
        $this->assertSame(2, (int) $row->questionscorrect);
        $this->assertSame(3, (int) $row->questionstotal);
        $daily = $DB->get_record('local_playergames_daily_scores', ['userid' => $user->id, 'gametype' => 'battle']);
        $this->assertSame(1, (int) $daily->attempts);
    }

    public function test_victory_past_the_quota_is_saved_with_no_xp(): void {
        $user = $this->prepare(1);
        $this->add_question();
        $this->start();
        $this->finish(true, 40);

        $this->start();
        $result = $this->finish(true, 90);

        $this->assertTrue($result['success']);
        $this->assertSame(0, $result['xpawarded']);
        $row = $this->score_row($user);
        $this->assertSame(90, (int) $row->bosshpdealt);
        $this->assertSame(10, (int) $row->xpawarded);
    }

    public function test_the_day_keeps_the_best_result_and_adds_up_the_xp(): void {
        $user = $this->prepare(2);
        $this->add_question();

        $this->start();
        $this->finish(false, 90);
        $this->assertSame(90, (int) $this->score_row($user)->bosshpdealt);

        // A victory beats any defeat, however much damage the defeat dealt.
        $this->start();
        $this->finish(true, 50);
        $row = $this->score_row($user);
        $this->assertSame(1, (int) $row->victory);
        $this->assertSame(50, (int) $row->bosshpdealt);

        // Between victories, the one with more damage wins.
        $this->start();
        $this->finish(true, 80);
        $this->assertSame(80, (int) $this->score_row($user)->bosshpdealt);

        // A later defeat never replaces a victory.
        $this->start();
        $this->finish(false, 100);
        $row = $this->score_row($user);
        $this->assertSame(1, (int) $row->victory);
        $this->assertSame(80, (int) $row->bosshpdealt);
        $this->assertSame(20, (int) $row->xpawarded);
    }

    /**
     * Regression test: a result sent twice for the same battle earns once.
     */
    public function test_a_battle_is_accepted_only_once(): void {
        $user = $this->prepare(3);
        $this->add_question();
        $this->start();

        $first  = $this->finish(true);
        $second = $this->finish(true);

        $this->assertTrue($first['success']);
        $this->assertFalse($second['success']);
        $this->assertSame(10, (int) $this->score_row($user)->xpawarded);
    }

    public function test_reported_counters_are_kept_within_range(): void {
        $user = $this->prepare();
        $this->add_question();

        $this->start();
        $this->finish(false, -5, ['questionscorrect' => 9, 'questionstotal' => 4]);
        $row = $this->score_row($user);
        $this->assertSame(0, (int) $row->bosshpdealt);
        $this->assertSame(4, (int) $row->questionscorrect);

        $this->start();
        $this->finish(false, 999999999, ['questionscorrect' => -3, 'questionstotal' => 999999]);
        $row = $this->score_row($user);
        $this->assertSame(1000000, (int) $row->bosshpdealt);
        $this->assertSame(0, (int) $row->questionscorrect);
        $this->assertSame(200, (int) $row->questionstotal);
    }

    public function test_only_questions_the_battle_delivered_count_as_seen(): void {
        $user = $this->prepare();
        $delivered = $this->add_question();
        $this->start();

        $this->finish(false, 10, ['seen' => ["cartridge:{$delivered}", 'cartridge:99999', 'questionbank:5', 'junk', 7]]);

        $this->assertSame(
            ['cartridge' => [$delivered]],
            served_questions::get_excluded((int) $user->id, 'battle', $this->today)
        );
    }

    /**
     * A defeat does not spend the play, so the questions it showed are not offered again.
     */
    public function test_questions_seen_in_a_defeat_are_not_offered_next_time(): void {
        $this->prepare();
        $seen  = $this->add_question();
        $other = $this->add_question();
        $this->start();
        $this->finish(false, 10, ['seen' => ["cartridge:{$seen}"]]);

        $next = $this->start();

        $this->assertSame(["cartridge:{$other}"], array_column($next, 'key'));
    }
}
