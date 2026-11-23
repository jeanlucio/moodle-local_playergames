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
 * Rules that span several classes, checked over the whole plugin.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames;

use local_playergames\games\season_game_config;
use local_playergames\hub\season_manager;

/**
 * Contracts a new method, action or game must keep, which no test of a single class would notice it breaking.
 *
 * Each test lists what exists today and fails on anything new that is not in the list: whoever adds it has to
 * decide which side of the rule it is on, instead of the rule being forgotten silently.
 *
 * @package    local_playergames
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class contracts_test extends \advanced_testcase {
    /**
     * @var array Public methods of the reward classes that change the XP, streak, missions, achievements or
     *     scores of a user, by class, with what in their own body takes the lock of the user. The behaviour of
     *     each one under a taken lock is tested in local/user_lock_test.php.
     */
    private const LOCKED = [
        'hub\xp_manager' => ['award' => 'user_lock::run', 'award_uncapped' => 'self::award('],
        'hub\streak_manager' => [
            'record_activity' => 'user_lock::run',
            'add_freezes' => 'user_lock::run',
            'process_breaks' => 'user_lock::acquire',
        ],
        'hub\mission_manager' => ['update' => 'user_lock::run'],
        'hub\achievement_manager' => ['check' => 'user_lock::run'],
        'hub\daily_play_manager' => ['register_play' => 'user_lock::acquire'],
        'hub\checkin_manager' => ['record' => 'user_lock::acquire'],
        'games\battle_manager' => ['submit_result' => 'user_lock::acquire'],
    ];

    /** @var array Public methods of the same classes that need no lock, by class, with the reason. */
    private const EXEMPT = [
        'hub\xp_manager' => [
            'get_level' => 'reads',
            'get_xp_for_level' => 'reads',
            'build_level_data' => 'computes',
            'get_or_create_profile' => 'creates an empty row through unique_row, which settles a race on the unique key',
            'get_season_position' => 'reads',
            'set_ranking_visibility' => 'one flag the user sets, where the last request wins',
        ],
        'hub\streak_manager' => [
            'max_freezes' => 'reads a setting',
            'get_freeze_log' => 'reads',
            'get_or_create' => 'creates an empty row through unique_row, which settles a race on the unique key',
        ],
        'hub\mission_manager' => [
            'sync' => 'writes the mission definitions, which belong to nobody',
            'get_all' => 'reads',
            'reset_daily' => 'cron reset of the missions completed before today; a play does it under the lock too',
            'reset_missed_checkin_streaks' => 'cron reset of streaks with no check-in yesterday nor today; a check-in does it too',
        ],
        'hub\achievement_manager' => [
            'sync' => 'writes the achievement definitions, which belong to nobody',
            'get_definitions' => 'reads',
        ],
        'hub\daily_play_manager' => [
            'games_per_day' => 'computes',
            'xp_per_game' => 'computes',
            'attempts_today' => 'reads',
            'remaining_plays' => 'reads',
            'get_games_today' => 'reads',
        ],
        'hub\checkin_manager' => [
            'is_participant' => 'reads',
            'count_this_season' => 'reads',
        ],
        'games\battle_manager' => [
            'max_attempts' => 'reads a setting',
            'begin_battle' => 'marks the match under the lock of the round; nothing is paid before submit_result',
            'settle_abandoned' => 'counts a defeat under the lock of the round, and pays nothing',
            'start_battle' => 'keeps the battle in the session of the request; nothing is paid before submit_result',
            'answer_question' => 'judges one answer in the session of the request; nothing is paid before submit_result',
        ],
        'hub\learning_xp_manager' => [
            'is_visible_to' => 'reads a setting',
            'window_months' => 'reads a setting',
            'period_for' => 'computes',
            'oldest_period_in_window' => 'computes',
            'record_change' => 'adds in one UPDATE ... SET xp = xp + :delta, atomic without a lock',
            'get_or_create_cache' => 'creates an empty row through unique_row, which settles a race on the unique key',
            'get_windowedxp' => 'reads',
            'set_ranking_visibility' => 'one flag the user sets, where the last request wins',
            'get_position' => 'reads',
            'recompute_all' => 'cron rebuild of the cache from the monthly rows',
        ],
    ];

    /** @var string[] Actions of the cartridge page that can give a game a word it can draw, and so draw at once. */
    private const DRAWING_ACTIONS = [
        'action_import_json' => 'a concept cartridge brings concepts (a quiz one does not, and skips the draw)',
        'action_import_glossary' => 'brings concepts',
        'action_save_ai_cartridge' => 'brings concepts',
        'action_add_concept' => 'adds a concept',
        'action_edit_concept' => 'a new term may be the first one a game can use, or replace today\'s word',
        'action_delete_concept' => 'may remove today\'s word',
        'action_toggle_active' => 'switching on brings concepts back; switching off may remove today\'s word',
        'action_delete_cartridge' => 'may remove today\'s word',
    ];

    /** @var string[] Actions of the cartridge page that change no concept a game draws from, with the reason. */
    private const NON_DRAWING_ACTIONS = [
        'action_import_questionbank' => 'makes a quiz cartridge',
        'action_generate_ai' => 'only shows a preview',
        'action_create_cartridge' => 'makes an empty cartridge',
        'action_add_category' => 'categories are not drawn from',
        'action_rename_category' => 'categories are not drawn from',
        'action_generate_ai_quiz' => 'only shows a preview',
        'action_save_ai_quiz_cartridge' => 'makes a quiz cartridge',
        'action_delete_category' => 'the concepts of the category stay',
        'action_add_quiz_question' => 'quiz questions are not drawn from',
        'action_edit_quiz_question' => 'quiz questions are not drawn from',
        'action_delete_quiz_question' => 'quiz questions are not drawn from',
    ];

    /**
     * Returns the source of a method, from its signature to its closing brace.
     *
     * @param \ReflectionMethod $method The method.
     * @return string Its source.
     */
    private function source_of(\ReflectionMethod $method): string {
        $lines = file($method->getFileName());
        $length = $method->getEndLine() - $method->getStartLine() + 1;
        return implode('', array_slice($lines, $method->getStartLine() - 1, $length));
    }

    /**
     * Lists the PHP files of the plugin that run on a site, leaving out the tests and the language packs.
     *
     * @return string[] Paths relative to the plugin.
     */
    private function plugin_php_files(): array {
        global $CFG;
        $root = $CFG->dirroot . '/local/playergames/';
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            $relative = substr($file->getPathname(), strlen($root));
            if ($file->getExtension() === 'php' && !preg_match('~^(tests|lang|vendor|node_modules|\.plans)/~', $relative)) {
                $files[] = $relative;
            }
        }
        sort($files);
        return $files;
    }

    /**
     * Every public method of the classes that write the rewards of a user is either one that takes the lock of
     * the user, or one listed with the reason it needs none.
     *
     * The lock is what stops two requests of the same user, a game and a check-in for instance, from both
     * reading the same XP, mission or streak and both writing it back.
     */
    public function test_every_public_method_of_the_reward_classes_takes_the_user_lock_or_says_why_not(): void {
        $classes = array_unique(array_merge(array_keys(self::LOCKED), array_keys(self::EXEMPT)));
        foreach ($classes as $short) {
            $class = new \ReflectionClass('local_playergames\\' . $short);
            $locked = self::LOCKED[$short] ?? [];
            $exempt = self::EXEMPT[$short] ?? [];
            $this->assertSame([], array_intersect_key($locked, $exempt), "{$short}: a method is both locked and exempt.");

            $public = [];
            foreach ($class->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->getDeclaringClass()->getName() === $class->getName() && $method->getName() !== '__construct') {
                    $public[] = $method->getName();
                }
            }
            sort($public);
            $listed = array_keys($locked + $exempt);
            sort($listed);
            $this->assertSame(
                $listed,
                $public,
                "{$short}: classify each public method in LOCKED (with what takes the lock) or EXEMPT (with why)."
            );

            foreach ($locked as $name => $takesthelock) {
                $this->assertStringContainsString(
                    $takesthelock,
                    $this->source_of($class->getMethod($name)),
                    "{$short}::{$name}() no longer reaches the lock of the user as listed."
                );
            }
        }
    }

    /**
     * Every action of the cartridge page that can change what PlayerGuess and PlayerFill draw from draws today's
     * words again, and every other action is listed with the reason it does not.
     *
     * The task of midnight only draws once a day: an action that adds concepts without drawing leaves the games
     * locked until the next midnight, one that removes today's word leaves them without one.
     */
    public function test_every_cartridge_action_that_changes_the_concepts_draws_todays_words(): void {
        $class = new \ReflectionClass(\local_playergames\cartridge\controller::class);
        $actions = [];
        foreach ($class->getMethods() as $method) {
            if (str_starts_with($method->getName(), 'action_')) {
                $actions[] = $method->getName();
            }
        }
        sort($actions);
        $listed = array_keys(self::DRAWING_ACTIONS + self::NON_DRAWING_ACTIONS);
        sort($listed);
        $this->assertSame($listed, $actions, 'Classify each action in DRAWING_ACTIONS or NON_DRAWING_ACTIONS.');

        foreach (array_keys(self::DRAWING_ACTIONS) as $name) {
            $this->assertStringContainsString(
                '$this->draw_todays_words()',
                $this->source_of($class->getMethod($name)),
                "{$name}() changes the concepts a game draws from but no longer draws today's words."
            );
        }
        foreach (array_keys(self::NON_DRAWING_ACTIONS) as $name) {
            $this->assertDoesNotMatchRegularExpression(
                "/(insert_record|insert_records|update_record|delete_records)\(\s*'local_playergames_concepts'/",
                $this->source_of($class->getMethod($name)),
                "{$name}() writes concepts: it belongs in DRAWING_ACTIONS."
            );
        }
    }

    /**
     * Only the shared play service and the check-in record a score of the day.
     *
     * The service is what fires game_completed, which moves the streak, the missions and the achievements: a
     * game that wrote its score itself left all three untouched (PlayerQuiz did, before the service existed).
     */
    public function test_only_the_play_service_and_the_checkin_record_a_score_of_the_day(): void {
        global $CFG;
        $writers = [];
        $write = "/(insert_record|insert_records|update_record|set_field|set_field_select)\(\s*'local_playergames_daily_scores'"
            . '|(INSERT INTO|UPDATE)\s*\{local_playergames_daily_scores\}/i';
        foreach ($this->plugin_php_files() as $file) {
            if (preg_match($write, file_get_contents($CFG->dirroot . '/local/playergames/' . $file))) {
                $writers[] = $file;
            }
        }

        $this->assertSame(['classes/hub/checkin_manager.php', 'classes/hub/daily_play_manager.php'], $writers);
    }

    /**
     * The midnight of the day is worked out in local\day only, and no date is read in the timezone of the user.
     *
     * Every play, daily word and season bound is compared with the others, so they must all come from the same
     * midnight: a season read with make_timestamp(), in the timezone of the administrator, started and ended
     * hours away from the midnight that dated the plays.
     */
    public function test_the_day_is_worked_out_in_one_place(): void {
        global $CFG;
        $own = "/mktime\\(0, 0, 0(\\)|, \\(int\\) date\\('n'(, \\\$[a-z]+)?\\), \\(int\\) date\\('j')"
            . "|make_timestamp\\(|usergetmidnight\\(/";
        $found = [];
        foreach ($this->plugin_php_files() as $file) {
            $source = file_get_contents($CFG->dirroot . '/local/playergames/' . $file);
            if ($file !== 'classes/local/day.php' && preg_match($own, $source)) {
                $found[] = $file;
            }
        }

        $this->assertSame([], $found, 'Use local\\day for the midnight of a day and for typed dates.');
    }

    /**
     * The games are listed once, in season_game_config, and everything each game needs exists for all of them.
     *
     * A game missing from one of several hand-written lists got no XP setting, no place in the hub or no part
     * in the achievement for playing every game, with no error anywhere.
     */
    public function test_every_game_has_what_a_game_needs_and_is_listed_only_once(): void {
        global $CFG;
        $root = $CFG->dirroot . '/local/playergames/';
        $games = season_game_config::GAMETYPES;
        $families = array_keys(season_game_config::FAMILIES);
        sort($families);
        $sorted = $games;
        sort($sorted);
        $this->assertSame($sorted, $families, 'Each game needs a cartridge family in season_game_config::FAMILIES.');
        $icons = array_keys(season_game_config::ICONS);
        sort($icons);
        $this->assertSame($sorted, $icons, 'Each game needs an icon in season_game_config::ICONS.');

        $strings = [];
        foreach (['en', 'pt_br'] as $lang) {
            $string = [];
            require($root . 'lang/' . $lang . '/local_playergames.php');
            $strings[$lang] = $string;
        }
        $snapshot = season_manager::build_snapshot();
        foreach ($games as $game) {
            $this->assertFileExists($root . 'play_' . $game . '.php');
            $this->assertArrayHasKey('xp_per_game_' . $game, $snapshot);
            foreach (['hub_game_', 'settings_xp_per_game_'] as $prefix) {
                foreach ($strings as $lang => $defined) {
                    $this->assertArrayHasKey($prefix . $game, $defined, "{$lang}: {$prefix}{$game}");
                }
            }
        }

        // A list that names every game by hand would not follow when a game is added.
        $handwritten = [];
        foreach ($this->plugin_php_files() as $file) {
            if ($file === 'classes/games/season_game_config.php') {
                continue;
            }
            preg_match_all('/\[[^\[\]]*\]/', file_get_contents($root . $file), $lists);
            foreach ($lists[0] as $list) {
                $named = array_filter($games, fn(string $game): bool => str_contains($list, "'{$game}'"));
                if (count($named) === count($games)) {
                    $handwritten[] = $file;
                }
            }
        }
        $this->assertSame([], $handwritten, 'Use season_game_config::GAMETYPES instead of listing the games.');
    }
}
