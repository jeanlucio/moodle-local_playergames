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
 * Phase 6 CLI verification script for local_playergames.
 *
 * Verifies the Player Hub UI layer: output renderables, ranking cache,
 * showinranking toggle, achievement grid and lang strings.
 * Creates an isolated test season and cleans up on exit.
 *
 * Usage:
 *   php local/playergames/cli/test_phase6.php
 *   php local/playergames/cli/test_phase6.php --userid=2
 *   php local/playergames/cli/test_phase6.php --keep      (skip cleanup)
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use cache;
use context_system;
use local_playergames\hub\achievement_manager;
use local_playergames\hub\mission_manager;
use local_playergames\hub\season_manager;
use local_playergames\hub\streak_manager;
use local_playergames\hub\title_manager;
use local_playergames\hub\xp_manager;
use local_playergames\output\achievements as achievements_output;
use local_playergames\output\hub as hub_output;

$params = cli_get_params(
    ['userid' => 0, 'keep' => false, 'help' => false],
    ['u' => 'userid', 'k' => 'keep', 'h' => 'help']
);

if (!empty($params['help'])) {
    cli_writeln('Phase 6 verification — local_playergames (Player Hub UI)');
    cli_writeln('');
    cli_writeln('Options:');
    cli_writeln('  --userid=N   User ID to run tests against (default: admin)');
    cli_writeln('  --keep       Skip cleanup so you can inspect DB rows after the run');
    cli_writeln('  --help       Show this help');
    exit(0);
}

$userid   = !empty($params['userid']) ? (int) $params['userid'] : (int) get_admin()->id;
$keepdb   = !empty($params['keep']);
$passed   = 0;
$failed   = 0;
$seasonid = 0;
$prevactiveid = null;

// ---------------------------------------------------------------------------
// Helpers.

/**
 * Prints a pass/fail check line and updates global counters.
 *
 * @param string $label     Short description of the check.
 * @param bool   $condition True if the check passed.
 * @param string $detail    Optional detail appended in parentheses.
 * @return void
 */
function ok(string $label, bool $condition, string $detail = ''): void {
    global $passed, $failed;
    $mark   = $condition ? "\033[32m✔\033[0m" : "\033[31m✘\033[0m";
    $suffix = $detail ? " ({$detail})" : '';
    cli_writeln("  {$mark}  {$label}{$suffix}");
    if ($condition) {
        $passed++;
    } else {
        $failed++;
    }
}

/**
 * Prints a bold section heading to the CLI output.
 *
 * @param string $title Section title.
 * @return void
 */
function section(string $title): void {
    cli_writeln('');
    cli_writeln("\033[1m── {$title}\033[0m");
}

/**
 * Deletes all test data created during the verification run.
 *
 * @param int      $seasonid     ID of the test season to remove.
 * @param int      $userid       User ID whose test records are deleted.
 * @param bool     $keepdb       When true, skip cleanup and leave data in DB.
 * @param int|null $prevactiveid Season to restore to active status, if any.
 * @return void
 */
function cleanup(int $seasonid, int $userid, bool $keepdb, ?int $prevactiveid): void {
    global $DB;
    if ($keepdb) {
        cli_writeln('');
        cli_writeln("Skipping cleanup (--keep). Season ID: {$seasonid}");
        return;
    }
    if ($seasonid <= 0) {
        return;
    }
    $DB->delete_records('local_playergames_mission_progress', ['seasonid' => $seasonid]);
    $DB->delete_records('local_playergames_player_profile', ['seasonid' => $seasonid]);
    $DB->delete_records('local_playergames_daily_scores', ['userid' => $userid]);
    $DB->delete_records('local_playergames_user_achievements', ['userid' => $userid]);
    $DB->delete_records('local_playergames_streaks', ['userid' => $userid]);
    $DB->delete_records('local_playergames_seasons', ['id' => $seasonid]);
    if ($prevactiveid) {
        $DB->set_field('local_playergames_seasons', 'status', 'active', ['id' => $prevactiveid]);
    }
    cli_writeln('  Cleanup done.');
}

// ---------------------------------------------------------------------------
// Minimal renderer stub for export_for_template().
// The real Moodle page renderer is not available in CLI context.
$stubrenderer = new class extends \renderer_base {
    /** @var \moodle_page */
    public $page;
    public function __construct() {
        global $PAGE;
        $this->page = $PAGE;
    }
    public function render(\renderable $widget): string {
        return '';
    }
};

// ---------------------------------------------------------------------------

cli_writeln('');
cli_writeln("\033[1mPhase 6 verification — Player Hub UI\033[0m");
cli_writeln("User ID: {$userid}");

// Pause any currently active season so we work on an isolated one.
$existing = season_manager::get_active();
if ($existing) {
    $prevactiveid = (int) $existing->id;
    $DB->set_field('local_playergames_seasons', 'status', 'upcoming', ['id' => $prevactiveid]);
}

// Create isolated test season.
$now = time();
$season = season_manager::create('Test Season Phase 6', $now - DAYSECS, $now + 30 * DAYSECS);
season_manager::activate($season->id);
$seasonid = $season->id;

// ---------------------------------------------------------------------------
section('1. Lang strings');

$strkeys = [
    'hub_pagetitle', 'hub_no_season', 'hub_profile_section', 'hub_ranking_section',
    'hub_missions_section', 'hub_games_section', 'hub_showinranking', 'hub_streak',
    'hub_freezes', 'hub_xp_next_level', 'hub_ranking_tab_students', 'hub_ranking_tab_staff',
    'hub_game_quiz', 'hub_game_guess', 'hub_game_fill', 'hub_game_battle',
    'hub_game_done', 'hub_game_pending', 'hub_game_locked',
    'hub_show_all', 'hub_show_less', 'hub_invite_noprofile', 'hub_join_ranking',
    'hub_season_label', 'hub_access_restricted',
    'achievements_pagetitle',
];

foreach ($strkeys as $key) {
    $val = get_string($key, 'local_playergames');
    ok($key, $val !== "[[{$key}]]" && $val !== '', $val);
}

// ---------------------------------------------------------------------------
section('2. Hub output — no season fallback');

// Temporarily deactivate season to test no-season path.
$DB->set_field('local_playergames_seasons', 'status', 'upcoming', ['id' => $seasonid]);
$hub = new hub_output($userid, false, true, 'both');
$data = $hub->export_for_template($stubrenderer);
ok('noseason flag is true when no active season', !empty($data['noseason']));
ok('str_no_season string present', !empty($data['str_no_season']));
// Reactivate.
$DB->set_field('local_playergames_seasons', 'status', 'active', ['id' => $seasonid]);

// ---------------------------------------------------------------------------
section('3. Hub output — active season, admin view');

// Award XP bypassing the daily cap so the profile has meaningful values.
$profile = xp_manager::get_or_create_profile($userid, $seasonid);
$profile->xp           = 350;
$profile->level        = xp_manager::get_level(350);
$profile->timemodified = time();
$DB->update_record('local_playergames_player_profile', $profile);
streak_manager::record_activity($userid);
mission_manager::sync();

$hub = new hub_output($userid, true, true, 'both');
$data = $hub->export_for_template($stubrenderer);

ok('noseason is false', empty($data['noseason']));
ok('season_name present', !empty($data['season_name']));
ok('XP >= 350', isset($data['xp']) && $data['xp'] >= 350, "xp={$data['xp']}");
ok('level >= 1', isset($data['level']) && $data['level'] >= 1, "level={$data['level']}");
ok('title string not empty', !empty($data['title']), $data['title'] ?? '');
ok('streak = 1', isset($data['streak']) && $data['streak'] === 1, "streak={$data['streak']}");
ok('showbothtabs is true for admin+both', !empty($data['showbothtabs']));
ok('achievementsurl present', !empty($data['achievementsurl']));
ok('sesskey present', !empty($data['sesskey']));
ok('formaction present', !empty($data['formaction']));
ok('missions array present', isset($data['missions']));
ok('games array has 4 items', isset($data['games']) && count($data['games']) === 4, 'quiz/guess/fill/battle');

$leveldata = ['xp_next', 'xp_in_level', 'level_range', 'progress_pct'];
foreach ($leveldata as $k) {
    ok("level data key '{$k}' present", array_key_exists($k, $data), (string) ($data[$k] ?? 'missing'));
}

// ---------------------------------------------------------------------------
section('4. Ranking and cache');

// showinranking is 0 by default — ranking should be empty.
$ranking = $data['studentranking'] ?? $data['staffranking'] ?? [];
ok('Ranking empty when showinranking=0', empty($ranking));

// Enable showinranking.
$profile = xp_manager::get_or_create_profile($userid, $seasonid);
$profile->showinranking = 1;
$profile->timemodified  = time();
$DB->update_record('local_playergames_player_profile', $profile);

// Invalidate cache and re-fetch.
$cache = cache::make('local_playergames', 'ranking');
$cache->delete('season_' . $seasonid . '_staff');
$cache->delete('season_' . $seasonid . '_students');

$hub2  = new hub_output($userid, true, true, 'both');
$data2 = $hub2->export_for_template($stubrenderer);
$staffrank = $data2['staffranking'] ?? [];
ok('Staff ranking not empty after showinranking=1', !empty($staffrank), count($staffrank) . ' entries');
ok('First entry has pos=1', !empty($staffrank) && $staffrank[0]['pos'] === 1);
ok('XP in entry >= 350', !empty($staffrank) && $staffrank[0]['xp'] >= 350, "xp=" . ($staffrank[0]['xp'] ?? 'n/a'));

// Second call should come from cache.
$hub3   = new hub_output($userid, true, true, 'both');
$data3  = $hub3->export_for_template($stubrenderer);
$staffrank3 = $data3['staffranking'] ?? [];
ok('Second call returns same result (cache)', count($staffrank3) === count($staffrank));

// ---------------------------------------------------------------------------
section('5. showinranking = 0 removes from ranking');

$profile->showinranking = 0;
$DB->update_record('local_playergames_player_profile', $profile);
$cache->delete('season_' . $seasonid . '_staff');
$cache->delete('season_' . $seasonid . '_students');

$hub4    = new hub_output($userid, true, true, 'both');
$data4   = $hub4->export_for_template($stubrenderer);
$staffr4 = $data4['staffranking'] ?? [];
ok('User absent from ranking after showinranking=0', empty($staffr4));

// ---------------------------------------------------------------------------
section('6. Achievements output');

achievement_manager::sync();
$achout = new achievements_output($userid);
$achdata = $achout->export_for_template($stubrenderer);

ok('str_pagetitle present', !empty($achdata['str_pagetitle']));
ok('items array present', isset($achdata['items']));
ok('total = 6 (V1 achievements)', isset($achdata['total']) && $achdata['total'] === 6, "total={$achdata['total']}");
ok('totalearned = 0 (fresh user)', isset($achdata['totalearned']) && $achdata['totalearned'] === 0);

// Earn the first-game achievement manually.
achievement_manager::check($userid, $seasonid, 'game_played', ['gamedate' => time()]);
$achout2  = new achievements_output($userid);
$achdata2 = $achout2->export_for_template($stubrenderer);

// Check if first_game achievement is now earned (requires at least 1 completed daily_score).
$gamesplayed = $DB->count_records('local_playergames_daily_scores', ['userid' => $userid, 'completed' => 1]);
if ($gamesplayed >= 1) {
    ok('First Steps earned after game_played check', $achdata2['totalearned'] >= 1);
} else {
    // No completed game in DB yet; just verify items have expected keys.
    $item = $achdata2['items'][0] ?? [];
    ok('Achievement item has expected keys', isset($item['icon']) && isset($item['name']) && isset($item['earned']));
}

// ---------------------------------------------------------------------------
section('7. Missions in hub output');

$missions = $data2['missions'] ?? [];
ok('5 missions loaded', count($missions) === 5, count($missions) . ' found');
$missionkeys = ['icon', 'name', 'desc', 'current', 'target', 'completed', 'xpreward', 'progress_pct'];
if (!empty($missions)) {
    foreach ($missionkeys as $k) {
        ok("Mission has key '{$k}'", array_key_exists($k, $missions[0]));
    }
}

// ---------------------------------------------------------------------------
section('8. Games today in hub output');

$games = $data2['games'] ?? [];
ok('4 game types in output', count($games) === 4);
$gametypes = array_column($games, 'gametype');
foreach (['quiz', 'guess', 'fill', 'battle'] as $gt) {
    ok("Game type '{$gt}' present", in_array($gt, $gametypes, true));
}
$gamekeys = ['gametype', 'name', 'icon', 'done', 'locked', 'pending', 'str_done', 'str_pending', 'str_locked'];
if (!empty($games)) {
    foreach ($gamekeys as $k) {
        ok("Game has key '{$k}'", array_key_exists($k, $games[0]));
    }
}

// ---------------------------------------------------------------------------
section('9. Level bar data');

// Set XP directly to cross the level-2 threshold (100 XP).
$profile = xp_manager::get_or_create_profile($userid, $seasonid);
$profile->xp           = 550;
$profile->level        = xp_manager::get_level(550);
$profile->showinranking = 0;
$profile->timemodified  = time();
$DB->update_record('local_playergames_player_profile', $profile);

$hub5  = new hub_output($userid, true, true, 'both');
$data5 = $hub5->export_for_template($stubrenderer);
ok('Level >= 2 after 550 XP', isset($data5['level']) && $data5['level'] >= 2, "level={$data5['level']}");
ok('progress_pct 0–100', isset($data5['progress_pct']) && $data5['progress_pct'] >= 0 && $data5['progress_pct'] <= 100);

// ---------------------------------------------------------------------------

cleanup($seasonid, $userid, $keepdb, $prevactiveid);

cli_writeln('');
$total = $passed + $failed;
if ($failed === 0) {
    cli_writeln("\033[32mAll {$passed}/{$total} checks passed.\033[0m");
    exit(0);
} else {
    cli_writeln("\033[31m{$failed} of {$total} checks FAILED.\033[0m");
    exit(1);
}
