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
 * Phase 4 CLI verification script for local_playergames.
 *
 * Tests hub managers, task logic and observer logic without any game UI.
 * Creates an isolated test season and cleans up on exit.
 *
 * Usage:
 *   php local/playergames/cli/test_phase4.php
 *   php local/playergames/cli/test_phase4.php --userid=2
 *   php local/playergames/cli/test_phase4.php --keep      (skip cleanup)
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use local_playergames\hub\achievement_manager;
use local_playergames\hub\mission_manager;
use local_playergames\hub\season_manager;
use local_playergames\hub\streak_manager;
use local_playergames\hub\xp_manager;
use local_playergames\hub\title_manager;

$params = cli_get_params(
    ['userid' => 0, 'keep' => false, 'help' => false],
    ['u' => 'userid', 'k' => 'keep', 'h' => 'help']
);

if (!empty($params['help'])) {
    cli_writeln('Phase 4 verification — local_playergames');
    cli_writeln('');
    cli_writeln('Options:');
    cli_writeln('  --userid=N   User ID to run tests against (default: admin)');
    cli_writeln('  --keep       Skip cleanup so you can inspect DB rows after the run');
    cli_writeln('  --help       Show this help');
    exit(0);
}

$userid  = !empty($params['userid']) ? (int) $params['userid'] : (int) get_admin()->id;
$keepdb  = !empty($params['keep']);
$passed  = 0;
$failed  = 0;
$seasonid = 0;

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
    $DB->delete_records('local_playergames_daily_assignments', []);
    $DB->delete_records('local_playergames_user_achievements', ['userid' => $userid]);
    $DB->delete_records('local_playergames_streaks', ['userid' => $userid]);
    $DB->delete_records('local_playergames_seasons', ['id' => $seasonid]);
    if ($prevactiveid) {
        $DB->set_field('local_playergames_seasons', 'status', 'active', ['id' => $prevactiveid]);
    }
    cli_writeln('  Cleanup complete.');
}

// ---------------------------------------------------------------------------
// Main.

cli_writeln('');
cli_writeln("\033[1mPhase 4 — Player Hub Logic Verification\033[0m");
cli_writeln("Running as user ID {$userid}");

// ---------------------------------------------------------------------------
// 1. Sync hardcoded data
// ---------------------------------------------------------------------------
section('1. Sync missions and achievements');

mission_manager::sync();
achievement_manager::sync();

$missioncount     = $DB->count_records('local_playergames_missions');
$achievementcount = $DB->count_records('local_playergames_achievements');

ok('5 missions in DB', $missioncount >= 5, "{$missioncount} found");
ok('6 achievements in DB', $achievementcount >= 6, "{$achievementcount} found");

$missiontypes = $DB->get_fieldset_select('local_playergames_missions', 'type', '1=1');
ok('daily mission exists', in_array('daily', $missiontypes, true));
ok('streak mission exists', in_array('streak', $missiontypes, true));
ok('cumulative mission exists', in_array('cumulative', $missiontypes, true));
ok('battle_win mission exists', in_array('battle_win', $missiontypes, true));
ok('checkin_streak mission exists', in_array('checkin_streak', $missiontypes, true));

// ---------------------------------------------------------------------------
// 2. Season lifecycle
// ---------------------------------------------------------------------------
section('2. Season lifecycle');

$startdate = mktime(0, 0, 0) - (DAYSECS * 30);
$enddate   = mktime(0, 0, 0) + (DAYSECS * 150);
$season    = season_manager::create('CLI Test ' . time(), $startdate, $enddate);
$seasonid  = (int) $season->id;

ok('Season created', $seasonid > 0, "ID {$seasonid}");
ok('Status is upcoming', $season->status === 'upcoming');

$snapshot = season_manager::get_config_snapshot($season);
ok('Config snapshot has xp_cap_quiz', isset($snapshot['xp_cap_quiz']), "cap={$snapshot['xp_cap_quiz']}");
ok('Config snapshot has allowed_participants', isset($snapshot['allowed_participants']));

// Temporarily suspend any existing active season to avoid conflict.
$prevactive = season_manager::get_active();
if ($prevactive) {
    $DB->set_field('local_playergames_seasons', 'status', 'upcoming', ['id' => $prevactive->id]);
}
season_manager::activate($seasonid);
$active = season_manager::get_active();
ok('Season activated', $active !== null && (int) $active->id === $seasonid);

// ---------------------------------------------------------------------------
// 3. XP award and level progression
// ---------------------------------------------------------------------------
section('3. XP award and level progression');

$today = mktime(0, 0, 0, (int) date('n'), (int) date('j'), (int) date('Y'));

// Award uncapped (mission type) to test level progression without game flow.
$a1 = xp_manager::award_uncapped($userid, 100, $seasonid);
ok('Award 100 XP (uncapped)', $a1 === 100, "returned {$a1}");

$profile = xp_manager::get_or_create_profile($userid, $seasonid);
ok('player_profile.xp = 100', (int) $profile->xp === 100, "xp={$profile->xp}");
ok('Level is 2 at 100 XP', (int) $profile->level === 2, "level={$profile->level}");

$a2 = xp_manager::award_uncapped($userid, 200, $seasonid);
$profile = $DB->get_record('local_playergames_player_profile', ['userid' => $userid, 'seasonid' => $seasonid]);
ok('Award 200 more XP → 300 total', (int) $profile->xp === 300, "xp={$profile->xp}");
ok('Level is 3 at 300 XP', (int) $profile->level === 3, "level={$profile->level}");

ok('get_level(0) = 1', xp_manager::get_level(0) === 1);
ok('get_level(99) = 1', xp_manager::get_level(99) === 1);
ok('get_level(100) = 2', xp_manager::get_level(100) === 2);
ok('get_level(19000) = 20', xp_manager::get_level(19000) === 20);
ok('get_xp_for_level(5) = 1000', xp_manager::get_xp_for_level(5) === 1000);

// ---------------------------------------------------------------------------
// 4. Daily XP cap enforcement
// ---------------------------------------------------------------------------
section('4. Daily XP cap (gametype=quiz)');

$cap = $snapshot['xp_cap_quiz'] ?? 25;

// No daily_scores yet for quiz today → full cap available.
$capped1 = xp_manager::award($userid, 50, 'quiz', $today, $seasonid);
ok("Award 50 XP capped to {$cap}", $capped1 === $cap, "returned {$capped1}");

// Insert daily_scores row to simulate what game handler writes.
$DB->insert_record('local_playergames_daily_scores', (object) [
    'userid'     => $userid,
    'gamedate'   => $today,
    'gametype'   => 'quiz',
    'completed'  => 1,
    'xpawarded'  => $capped1,
    'attempts'   => 1,
    'timeplayed' => time(),
]);

// Now daily cap is exhausted.
$capped2 = xp_manager::award($userid, 25, 'quiz', $today, $seasonid);
ok('Second award returns 0 (cap exhausted)', $capped2 === 0, "returned {$capped2}");

// ---------------------------------------------------------------------------
// 5. Missions
// ---------------------------------------------------------------------------
section('5. Mission progress');

mission_manager::update($userid, $seasonid, 'game_played');
$dailymissionid = $DB->get_field('local_playergames_missions', 'id', ['type' => 'daily']);
$progress       = $DB->get_record(
    'local_playergames_mission_progress',
    ['userid' => $userid, 'missionid' => $dailymissionid, 'seasonid' => $seasonid]
);
ok('Daily mission completed (game_played trigger)', $progress && $progress->completed == 1);

// Cumulative: current XP is 300 (from uncapped awards above) + cap = 300+25=325.
$profile = $DB->get_record('local_playergames_player_profile', ['userid' => $userid, 'seasonid' => $seasonid]);
mission_manager::update($userid, $seasonid, 'xp_earned', ['total_xp' => (int) $profile->xp]);
$cummissionid = $DB->get_field('local_playergames_missions', 'id', ['type' => 'cumulative']);
$cumprogress  = $DB->get_record(
    'local_playergames_mission_progress',
    ['userid' => $userid, 'missionid' => $cummissionid, 'seasonid' => $seasonid]
);
ok('Cumulative mission completed (100 XP threshold)', $cumprogress && $cumprogress->completed == 1);

mission_manager::update($userid, $seasonid, 'battle_won');
$battlemissionid = $DB->get_field('local_playergames_missions', 'id', ['type' => 'battle_win']);
$battleprogress  = $DB->get_record(
    'local_playergames_mission_progress',
    ['userid' => $userid, 'missionid' => $battlemissionid, 'seasonid' => $seasonid]
);
ok('Battle win mission completed (battle_won trigger)', $battleprogress && $battleprogress->completed == 1);

// Daily mission reset.
mission_manager::reset_daily($seasonid);
$progressafter = $DB->get_record(
    'local_playergames_mission_progress',
    ['userid' => $userid, 'missionid' => $dailymissionid, 'seasonid' => $seasonid]
);
ok('Daily mission reset (cron)', $progressafter && $progressafter->completed == 0);

// ---------------------------------------------------------------------------
// 6. Streak record_activity and process_breaks
// ---------------------------------------------------------------------------
section('6. Streak and freeze');

streak_manager::record_activity($userid);
$streak = streak_manager::get_or_create($userid);
ok('Streak started at 1', (int) $streak->currentstreak === 1, "streak={$streak->currentstreak}");

// Simulate having played yesterday: set lastactivedate back by one day.
$streak->lastactivedate = $today - DAYSECS;
$DB->update_record('local_playergames_streaks', $streak);

streak_manager::record_activity($userid);
$streak = $DB->get_record('local_playergames_streaks', ['userid' => $userid]);
ok('Streak advances to 2 after activity yesterday', (int) $streak->currentstreak === 2, "streak={$streak->currentstreak}");

// Simulate missing a day (set lastactivedate to 3 days ago).
$streak->lastactivedate    = $today - (DAYSECS * 3);
$streak->freezesavailable  = 1;
$DB->update_record('local_playergames_streaks', $streak);

streak_manager::process_breaks();
$streak = $DB->get_record('local_playergames_streaks', ['userid' => $userid]);
ok('Freeze consumed after missed day', (int) $streak->freezesavailable === 0, "freezes={$streak->freezesavailable}");
ok('Streak preserved when freeze consumed', (int) $streak->currentstreak === 2, "streak={$streak->currentstreak}");

// Simulate missing again with no freeze.
$streak->lastactivedate = $today - (DAYSECS * 3);
$DB->update_record('local_playergames_streaks', $streak);

streak_manager::process_breaks();
$streak = $DB->get_record('local_playergames_streaks', ['userid' => $userid]);
ok('Streak broken when no freeze available', (int) $streak->currentstreak === 0, "streak={$streak->currentstreak}");
ok('Longest streak preserved after break', (int) $streak->longeststreak >= 2);

// Streak mission: simulate streak=7 scenario.
mission_manager::update($userid, $seasonid, 'streak_updated', ['streak' => 7]);
$streakmissionid = $DB->get_field('local_playergames_missions', 'id', ['type' => 'streak']);
$streakprogress  = $DB->get_record(
    'local_playergames_mission_progress',
    ['userid' => $userid, 'missionid' => $streakmissionid, 'seasonid' => $seasonid]
);
ok('Streak mission completed at 7 days', $streakprogress && $streakprogress->completed == 1);

// ---------------------------------------------------------------------------
// 7. Achievements
// ---------------------------------------------------------------------------
section('7. Achievements');

achievement_manager::check($userid, $seasonid, 'game_played', [
    'gamedate' => $today,
    'level'    => 4,
    'streak'   => 0,
]);

$earnedids = $DB->get_fieldset_select(
    'local_playergames_user_achievements',
    'achievementid',
    'userid = :uid',
    ['uid' => $userid]
);
ok('At least one achievement earned', count($earnedids) >= 1, count($earnedids) . ' earned');

$firstgameid = $DB->get_field(
    'local_playergames_achievements',
    'id',
    ['namestring' => 'achievement_first_game_name']
);
ok('First game achievement earned', in_array((string) $firstgameid, $earnedids, true));

achievement_manager::check($userid, $seasonid, 'level_reached', ['level' => 5, 'streak' => 0]);
$level5id = $DB->get_field(
    'local_playergames_achievements',
    'id',
    ['namestring' => 'achievement_level5_name']
);
$earnedafter = $DB->get_fieldset_select(
    'local_playergames_user_achievements',
    'achievementid',
    'userid = :uid',
    ['uid' => $userid]
);
ok('Level 5 achievement earned', in_array((string) $level5id, $earnedafter, true));

// ---------------------------------------------------------------------------
// 8. Title manager
// ---------------------------------------------------------------------------
section('8. Title manager');

ok("Level 1 title key is 'level_title_1'", title_manager::get_string_key(1) === 'level_title_1');
ok("Level 20 title key is 'level_title_20'", title_manager::get_string_key(20) === 'level_title_20');
ok('Level 0 clamps to title 1', title_manager::get_string_key(0) === 'level_title_1');
ok('Level 99 clamps to title 20', title_manager::get_string_key(99) === 'level_title_20');

// ---------------------------------------------------------------------------
// 9. Purge task simulation
// ---------------------------------------------------------------------------
section('9. Purge old scores (seasons_keep=1)');

$old1 = $DB->insert_record('local_playergames_seasons', (object) [
    'name'            => 'Old Season 1',
    'startdate'       => $startdate - (DAYSECS * 400),
    'enddate'         => $startdate - (DAYSECS * 210),
    'status'          => 'closed',
    'config_snapshot' => '{}',
    'timecreated'     => time(),
    'timemodified'    => time(),
]);
$old2 = $DB->insert_record('local_playergames_seasons', (object) [
    'name'            => 'Old Season 2',
    'startdate'       => $startdate - (DAYSECS * 200),
    'enddate'         => $startdate - (DAYSECS * 10),
    'status'          => 'closed',
    'config_snapshot' => '{}',
    'timecreated'     => time(),
    'timemodified'    => time(),
]);

// Insert one daily_scores row per old season.
$old1season = $DB->get_record('local_playergames_seasons', ['id' => $old1]);
$old2season = $DB->get_record('local_playergames_seasons', ['id' => $old2]);
$DB->insert_record('local_playergames_daily_scores', (object) [
    'userid'     => $userid,
    'gamedate'   => $old1season->startdate + 86400,
    'gametype'   => 'quiz',
    'completed'  => 1,
    'xpawarded'  => 10,
    'attempts'   => 1,
    'timeplayed' => time(),
]);
$DB->insert_record('local_playergames_daily_scores', (object) [
    'userid'     => $userid,
    'gamedate'   => $old2season->startdate + 86400,
    'gametype'   => 'quiz',
    'completed'  => 1,
    'xpawarded'  => 10,
    'attempts'   => 1,
    'timeplayed' => time(),
]);

set_config('seasons_keep', '1', 'local_playergames');

$task = new \local_playergames\task\purge_old_scores();
ob_start();
$task->execute();
ob_end_clean();

$old1scores = $DB->count_records_select(
    'local_playergames_daily_scores',
    'gamedate BETWEEN :start AND :end',
    ['start' => $old1season->startdate, 'end' => $old1season->enddate]
);
$old2scores = $DB->count_records_select(
    'local_playergames_daily_scores',
    'gamedate BETWEEN :start AND :end',
    ['start' => $old2season->startdate, 'end' => $old2season->enddate]
);

ok('Oldest season scores purged', $old1scores === 0, "{$old1scores} rows remaining");
ok('Most recent closed season scores preserved', $old2scores > 0, "{$old2scores} rows remaining");

$DB->delete_records('local_playergames_daily_scores', [
    'gamedate' => $old2season->startdate + 86400,
]);
$DB->delete_records('local_playergames_seasons', ['id' => $old1]);
$DB->delete_records('local_playergames_seasons', ['id' => $old2]);

set_config('seasons_keep', '2', 'local_playergames');

// ---------------------------------------------------------------------------
// Summary.
section('Summary');

cleanup($seasonid, $userid, $keepdb, $prevactive ? (int) $prevactive->id : null);
cli_writeln('');

$total  = $passed + $failed;
$colour = $failed === 0 ? "\033[32m" : "\033[31m";
cli_writeln("{$colour}{$passed}/{$total} checks passed\033[0m");

if ($failed > 0) {
    cli_writeln("\033[31mPhase 4 verification FAILED — {$failed} check(s) did not pass.\033[0m");
    exit(1);
}

cli_writeln("\033[32mPhase 4 verification PASSED.\033[0m");
exit(0);
