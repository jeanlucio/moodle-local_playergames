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
 * Server side of PlayerBattle: loads the questions and records the result.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\games;

use local_playergames\hub\daily_play_manager;
use local_playergames\hub\season_manager;
use local_playergames\hub\served_questions;

/**
 * Starts a battle and records how it ended.
 *
 * Like the other hub games, the board is played in the browser and the server only enforces the
 * daily quota. What it adds is a started battle per submission: loading the page opens one, and
 * the result is accepted once, for the questions that page delivered, so a request made without
 * playing, or sent twice, earns nothing.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class battle_manager {
    /** @var int Questions loaded for a battle. The mana orb keeps asking from them, repeating when they run out. */
    public const QUESTIONS_PER_BATTLE = 10;

    /** @var int Upper bound for the answered-questions counter a client can report. */
    private const MAX_QUESTIONS_ANSWERED = 200;

    /** @var int Upper bound for the damage dealt a client can report. */
    private const MAX_DAMAGE_DEALT = 1000000;

    /**
     * Opens a battle for the user and returns its questions, ready to send to the browser.
     *
     * @param int $userid The player.
     * @param int $gamedate Midnight timestamp of the day.
     * @param \context $context Context the question text is filtered in.
     * @return array[] Questions, each {key, questiontext, feedback, answers: {text, correct}[]}. Empty when
     *                 the season has no questions to offer, in which case no battle is opened.
     */
    public function start_battle(int $userid, int $gamedate, \context $context): array {
        $season = season_manager::get_active();
        if ($season === null || season_game_config::is_disabled((int) $season->id, 'battle')) {
            self::run_cache()->delete($userid);
            return [];
        }

        // A season with no row for the game predates per-season config: fall back to the cartridges.
        $config = season_game_config::get_for_active_season('battle');
        if ($config !== null) {
            $sources      = $config->source;
            $categoryid   = (int) $config->auxid;
            $cartridgeids = $config->cartridgeids;
        } else {
            $sources      = season_game_config::SOURCE_CARTRIDGE;
            $categoryid   = 0;
            $cartridgeids = null;
        }

        $exclude = served_questions::get_excluded($userid, 'battle', $gamedate);
        $loaded  = (new quiz_loader())->load_session(
            self::QUESTIONS_PER_BATTLE,
            $sources,
            $categoryid,
            $cartridgeids,
            $exclude
        );

        $questions = [];
        $keys      = [];
        foreach ($loaded as $question) {
            $key         = "{$question->source}:{$question->sourceid}";
            $keys[]      = $key;
            $questions[] = $this->format_question($question, $key, $context);
        }

        if (empty($questions)) {
            self::run_cache()->delete($userid);
            return [];
        }

        self::run_cache()->set($userid, [
            'gamedate' => $gamedate,
            'started'  => time(),
            'keys'     => $keys,
        ]);
        return $questions;
    }

    /**
     * Records how the battle ended, once, for the battle the user opened last.
     *
     * A defeat is saved but earns nothing and does not spend the day's play. A victory spends one
     * play and earns the XP the season grants; past the day's quota it is still saved, with no XP.
     * The day keeps the best result: a victory over a defeat, then the most damage dealt.
     *
     * @param int $userid The player.
     * @param \stdClass $season The active season.
     * @param \context $context Context of the event the play fires.
     * @param array $result Reported result: victory (bool), bosshpdealt (int), questionscorrect (int),
     *                      questionstotal (int) and seen (string[] of "source:sourceid" shown).
     * @return array {success: bool, victory: bool, xpawarded: int, remaining: int}. success is false
     *               when the user has no battle open, and then nothing was recorded.
     */
    public function submit_result(int $userid, \stdClass $season, \context $context, array $result): array {
        $run = self::run_cache()->get($userid);
        if (!is_array($run)) {
            return ['success' => false, 'victory' => false, 'xpawarded' => 0, 'remaining' => 0];
        }
        // One result per battle: a repeated request finds nothing open.
        self::run_cache()->delete($userid);

        $gamedate = (int) $run['gamedate'];
        $victory  = !empty($result['victory']);
        $total    = max(0, min((int) ($result['questionstotal'] ?? 0), self::MAX_QUESTIONS_ANSWERED));
        $correct  = max(0, min((int) ($result['questionscorrect'] ?? 0), $total));
        $damage   = max(0, min((int) ($result['bosshpdealt'] ?? 0), self::MAX_DAMAGE_DEALT));

        $xpawarded = 0;
        if ($victory) {
            $play = daily_play_manager::register_play($userid, 'battle', $gamedate, $season, $context, ['victory' => 1]);
            $xpawarded = (int) $play['xpawarded'];
        }

        $this->save_score($userid, (int) $season->id, $gamedate, [
            'victory'          => $victory ? 1 : 0,
            'bosshpdealt'      => $damage,
            'questionscorrect' => $correct,
            'questionstotal'   => $total,
            'xpawarded'        => $xpawarded,
            // Measured here: the browser's own clock proves nothing.
            'duration'         => max(0, time() - (int) $run['started']),
        ]);

        // The questions shown count as seen whether or not the battle was won: a defeat does not
        // spend the play, so the next try should bring new ones.
        $this->record_seen($userid, $gamedate, (array) ($result['seen'] ?? []), (array) $run['keys']);

        $snapshot = season_manager::get_config_snapshot($season);
        return [
            'success'   => true,
            'victory'   => $victory,
            'xpawarded' => $xpawarded,
            'remaining' => daily_play_manager::remaining_plays($userid, 'battle', $gamedate, $snapshot),
        ];
    }

    /**
     * Saves a result, keeping the day's best.
     *
     * @param int $userid The player.
     * @param int $seasonid The season.
     * @param int $gamedate Midnight timestamp of the day.
     * @param array $score victory, bosshpdealt, questionscorrect, questionstotal, xpawarded, duration.
     */
    private function save_score(int $userid, int $seasonid, int $gamedate, array $score): void {
        global $DB;

        $now      = time();
        $existing = $DB->get_record('local_playergames_battle_scores', ['userid' => $userid, 'gamedate' => $gamedate]);
        if (!$existing) {
            $DB->insert_record('local_playergames_battle_scores', (object) ($score + [
                'userid'     => $userid,
                'seasonid'   => $seasonid,
                'gamedate'   => $gamedate,
                'timeplayed' => $now,
            ]));
            return;
        }

        $better = $score['victory'] > (int) $existing->victory
            || ($score['victory'] === (int) $existing->victory && $score['bosshpdealt'] > (int) $existing->bosshpdealt);
        if ($better) {
            $existing->victory          = $score['victory'];
            $existing->bosshpdealt      = $score['bosshpdealt'];
            $existing->questionscorrect = $score['questionscorrect'];
            $existing->questionstotal   = $score['questionstotal'];
            $existing->duration         = $score['duration'];
            $existing->timeplayed       = $now;
        }
        // The XP of the day adds up whichever result is kept.
        $existing->xpawarded = (int) $existing->xpawarded + $score['xpawarded'];
        $DB->update_record('local_playergames_battle_scores', $existing);
    }

    /**
     * Marks the questions the player was shown as seen today.
     *
     * Only questions this battle delivered are accepted, so the report cannot name others.
     *
     * @param int $userid The player.
     * @param int $gamedate Midnight timestamp of the day.
     * @param array $seen Reported "source:sourceid" strings.
     * @param string[] $delivered The keys the battle delivered.
     */
    private function record_seen(int $userid, int $gamedate, array $seen, array $delivered): void {
        $items = [];
        foreach ($seen as $key) {
            if (!is_string($key) || !in_array($key, $delivered, true)) {
                continue;
            }
            [$source, $sourceid] = explode(':', $key, 2);
            $items[$key] = ['source' => $source, 'sourceid' => (int) $sourceid];
        }
        served_questions::record($userid, 'battle', $gamedate, array_values($items));
    }

    /**
     * Turns a loaded question into the structure the browser receives.
     *
     * Cartridge text is plain and is escaped; question bank text is HTML and goes through the filters.
     *
     * @param quiz_question $question The loaded question.
     * @param string $key Its "source:sourceid" key.
     * @param \context $context Context the text is filtered in.
     * @return array {key, questiontext, feedback, answers: {text, correct}[]}
     */
    private function format_question(quiz_question $question, string $key, \context $context): array {
        $isbank = $question->source === 'questionbank';
        $format = static fn(string $text, int $textformat): string => $isbank
            ? format_text($text, $textformat, ['context' => $context])
            : s($text);

        $answers = [];
        foreach ($question->answers as $answer) {
            $answers[] = ['text' => $format($answer->text, $answer->format), 'correct' => $answer->correct];
        }

        $feedback = (string) ($question->generalfeedback ?? '');
        return [
            'key'          => $key,
            'questiontext' => $format($question->questiontext, $question->questiontextformat),
            'feedback'     => $feedback === ''
                ? ''
                : format_text($feedback, $question->generalfeedbackformat, ['context' => $context]),
            'answers'      => $answers,
        ];
    }

    /**
     * The per-session store of the battle each user has open.
     *
     * @return \cache_session
     */
    private static function run_cache(): \cache_session {
        return \cache::make_from_params(\cache_store::MODE_SESSION, 'local_playergames', 'battle');
    }
}
