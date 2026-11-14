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
 * Tests for the season lifecycle manager.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\hub;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Unit tests for {@see season_manager}.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(season_manager::class)]
final class season_manager_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->redirectEvents();
    }

    public function test_create_sets_upcoming_with_frozen_snapshot(): void {
        $start = mktime(0, 0, 0);
        $end = $start + (DAYSECS * 30);

        $season = season_manager::create('Spring', $start, $end);

        $this->assertSame(season_manager::STATUS_UPCOMING, $season->status);
        $snapshot = season_manager::get_config_snapshot($season);
        $this->assertArrayHasKey('xp_cap_quiz', $snapshot);
        $this->assertArrayHasKey('allowed_participants', $snapshot);
    }

    public function test_get_active_returns_only_active(): void {
        $start = mktime(0, 0, 0);
        $season = season_manager::create('S', $start, $start + DAYSECS);

        // Upcoming season is not "active".
        $this->assertNull(season_manager::get_active());

        season_manager::activate((int) $season->id);
        $active = season_manager::get_active();
        $this->assertNotNull($active);
        $this->assertSame((int) $season->id, (int) $active->id);
    }

    public function test_get_active_or_upcoming_prefers_active(): void {
        $start = mktime(0, 0, 0);
        $upcoming = season_manager::create('Upcoming', $start, $start + DAYSECS);
        $active = season_manager::create('Active', $start, $start + DAYSECS);
        season_manager::activate((int) $active->id);

        $result = season_manager::get_active_or_upcoming();
        $this->assertSame((int) $active->id, (int) $result->id);
    }

    public function test_get_active_or_upcoming_falls_back_to_upcoming(): void {
        $start = mktime(0, 0, 0);
        $upcoming = season_manager::create('Upcoming', $start, $start + DAYSECS);

        $result = season_manager::get_active_or_upcoming();
        $this->assertNotNull($result);
        $this->assertSame((int) $upcoming->id, (int) $result->id);
    }

    public function test_activate_throws_when_another_active(): void {
        $start = mktime(0, 0, 0);
        $first = season_manager::create('First', $start, $start + DAYSECS);
        $second = season_manager::create('Second', $start, $start + DAYSECS);
        season_manager::activate((int) $first->id);

        $this->expectException(\moodle_exception::class);
        season_manager::activate((int) $second->id);
    }

    public function test_close_sets_closed_status(): void {
        global $DB;
        $start = mktime(0, 0, 0);
        $season = season_manager::create('S', $start, $start + DAYSECS);
        season_manager::activate((int) $season->id);

        season_manager::close((int) $season->id);

        $status = $DB->get_field('local_playergames_seasons', 'status', ['id' => $season->id]);
        $this->assertSame(season_manager::STATUS_CLOSED, $status);
    }

    public function test_create_next_inherits_snapshot_and_starts_after_previous(): void {
        $start = mktime(0, 0, 0);
        $end = $start + (DAYSECS * 30);
        $previous = season_manager::create('Old', $start, $end);

        $next = season_manager::create_next($previous);

        $this->assertSame(season_manager::STATUS_UPCOMING, $next->status);
        // The new season begins the second after the previous one ended.
        $this->assertSame($end + 1, (int) $next->startdate);
        $this->assertGreaterThan((int) $next->startdate, (int) $next->enddate);
        $this->assertNotEmpty($next->config_snapshot);
    }

    /**
     * Regression test: a negative length saved before the setting was validated made the next season end
     * before it began, so the close task opened another one every run.
     */
    public function test_create_next_ignores_a_negative_season_length(): void {
        $this->resetAfterTest();
        set_config('season_duration_months', -6, 'local_playergames');
        $start = mktime(0, 0, 0);
        $previous = season_manager::create('Old', $start, $start + (DAYSECS * 30));

        $next = season_manager::create_next($previous);

        $this->assertGreaterThan((int) $next->startdate, (int) $next->enddate);
    }

    public function test_the_snapshot_never_holds_a_negative_amount_of_xp(): void {
        $this->resetAfterTest();
        set_config('xp_per_game_quiz', -25, 'local_playergames');
        set_config('xp_checkin_daily', -5, 'local_playergames');
        set_config('xp_cap_checkin_season', -150, 'local_playergames');

        $snapshot = season_manager::build_snapshot();

        $this->assertSame(1, $snapshot['xp_per_game_quiz']);
        $this->assertSame(1, $snapshot['xp_checkin_daily']);
        $this->assertSame(1, $snapshot['xp_cap_checkin_season']);
    }

    public function test_get_config_snapshot_falls_back_when_empty(): void {
        $season = (object) ['config_snapshot' => null];
        $snapshot = season_manager::get_config_snapshot($season);
        // A missing snapshot yields current settings, not an empty array.
        $this->assertArrayHasKey('xp_cap_quiz', $snapshot);
    }

    public function test_has_any_counts_a_season_of_any_status(): void {
        $this->resetAfterTest();
        $this->assertFalse(season_manager::has_any());

        $season = season_manager::create('First', time() - DAYSECS, time() + DAYSECS);
        $this->assertTrue(season_manager::has_any());

        season_manager::activate((int) $season->id);
        season_manager::close((int) $season->id);
        $this->assertNull(season_manager::get_active());
        $this->assertTrue(season_manager::has_any());
    }

    public function test_update_changes_name_and_dates_of_an_upcoming_season(): void {
        $start = mktime(0, 0, 0);
        $season = season_manager::create('Old', $start, $start + DAYSECS);

        season_manager::update((int) $season->id, 'New', $start + DAYSECS, $start + (DAYSECS * 9));

        global $DB;
        $record = $DB->get_record('local_playergames_seasons', ['id' => $season->id], '*', MUST_EXIST);
        $this->assertSame('New', $record->name);
        $this->assertEquals($start + DAYSECS, $record->startdate);
        $this->assertEquals($start + (DAYSECS * 9), $record->enddate);
        $this->assertSame(season_manager::STATUS_UPCOMING, $record->status);
    }

    public function test_update_rejects_an_end_before_the_start(): void {
        $start = mktime(0, 0, 0);
        $season = season_manager::create('Old', $start, $start + DAYSECS);

        $this->expectException(\moodle_exception::class);
        season_manager::update((int) $season->id, 'Old', $start + DAYSECS, $start);
    }

    public function test_update_of_a_closed_season_changes_only_the_name(): void {
        global $DB;
        $start = mktime(0, 0, 0);
        $season = season_manager::create('Done', $start, $start + DAYSECS);
        season_manager::activate((int) $season->id);
        season_manager::close((int) $season->id);

        season_manager::update((int) $season->id, 'Renamed', $start + (DAYSECS * 5), $start + (DAYSECS * 6));

        $record = $DB->get_record('local_playergames_seasons', ['id' => $season->id], '*', MUST_EXIST);
        $this->assertSame('Renamed', $record->name);
        $this->assertEquals($start, $record->startdate);
        $this->assertEquals($start + DAYSECS, $record->enddate);
        $this->assertSame(season_manager::STATUS_CLOSED, $record->status);
    }

    public function test_delete_removes_the_season_and_only_its_own_data(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $start = mktime(0, 0, 0);
        $gone = season_manager::create('Gone', $start, $start + DAYSECS);
        $kept = season_manager::create('Kept', $start + (DAYSECS * 10), $start + (DAYSECS * 11));
        foreach ([$gone, $kept] as $season) {
            $this->add_season_rows((int) $user->id, (int) $season->id, (int) $season->startdate);
        }

        season_manager::delete((int) $gone->id);

        $this->assertFalse($DB->record_exists('local_playergames_seasons', ['id' => $gone->id]));
        foreach (
            [
                'local_playergames_player_profile',
                'local_playergames_mission_progress',
                'local_playergames_battle_scores',
                'local_playergames_season_games',
            ] as $table
        ) {
            $this->assertSame(0, $DB->count_records($table, ['seasonid' => $gone->id]), $table);
        }
        $this->assertSame(1, $DB->count_records('local_playergames_player_profile', ['seasonid' => $kept->id]));
        $this->assertSame(1, $DB->count_records('local_playergames_mission_progress', ['seasonid' => $kept->id]));
        $this->assertSame(1, $DB->count_records('local_playergames_battle_scores', ['seasonid' => $kept->id]));
        $this->assertGreaterThan(0, $DB->count_records('local_playergames_season_games', ['seasonid' => $kept->id]));
        $this->assertSame(1, $DB->count_records('local_playergames_daily_scores', ['gamedate' => $kept->startdate]));
        $this->assertSame(0, $DB->count_records('local_playergames_daily_scores', ['gamedate' => $gone->startdate]));
    }

    public function test_delete_keeps_daily_scores_that_another_season_also_covers(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $start = mktime(0, 0, 0);
        $gone = season_manager::create('Gone', $start, $start + (DAYSECS * 5));
        season_manager::create('Overlaps', $start + DAYSECS, $start + (DAYSECS * 9));
        $this->add_season_rows((int) $user->id, (int) $gone->id, $start + (DAYSECS * 2));

        season_manager::delete((int) $gone->id);

        $this->assertSame(1, $DB->count_records('local_playergames_daily_scores'));
    }

    public function test_delete_refuses_the_active_season(): void {
        global $DB;
        $start = mktime(0, 0, 0);
        $season = season_manager::create('Live', $start - DAYSECS, $start + DAYSECS);
        season_manager::activate((int) $season->id);

        try {
            season_manager::delete((int) $season->id);
            $this->fail('The active season was deleted.');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_season_delete_active', $e->errorcode);
        }
        $this->assertTrue($DB->record_exists('local_playergames_seasons', ['id' => $season->id]));
    }

    /**
     * Stores one row of every table that belongs to a season.
     *
     * @param int $userid
     * @param int $seasonid
     * @param int $gamedate Day of the daily score and of the battle result.
     * @return void
     */
    private function add_season_rows(int $userid, int $seasonid, int $gamedate): void {
        global $DB;
        $now = time();
        $DB->insert_record('local_playergames_player_profile', (object) [
            'userid' => $userid, 'seasonid' => $seasonid, 'xp' => 10, 'level' => 1,
            'showinranking' => 0, 'timecreated' => $now, 'timemodified' => $now,
        ]);
        $DB->insert_record('local_playergames_mission_progress', (object) [
            'userid' => $userid, 'missionid' => 1, 'seasonid' => $seasonid, 'currentvalue' => 1, 'completed' => 0,
        ]);
        $DB->insert_record('local_playergames_battle_scores', (object) [
            'userid' => $userid, 'seasonid' => $seasonid, 'gamedate' => $gamedate, 'bosshpdealt' => 1,
            'questionscorrect' => 1, 'questionstotal' => 1, 'victory' => 0, 'xpawarded' => 0, 'duration' => 1,
            'timeplayed' => $now,
        ]);
        $DB->insert_record('local_playergames_daily_scores', (object) [
            'userid' => $userid, 'gamedate' => $gamedate, 'gametype' => 'quiz', 'completed' => 1,
            'xpawarded' => 0, 'attempts' => 1, 'timeplayed' => $now,
        ]);
    }

    /**
     * A typed date becomes the first or the last second of that day.
     *
     * Regression test: the end date was stored as 00:00 of the last day, and the task that closes expired
     * seasons shut the season that night, so the announced last day could not be played.
     */
    public function test_date_to_timestamp_gives_the_first_and_the_last_second_of_the_day(): void {
        $start = season_manager::date_to_timestamp('2026-12-31', false);
        $end   = season_manager::date_to_timestamp('2026-12-31', true);

        $this->assertSame('2026-12-31 00:00:00', userdate($start, '%Y-%m-%d %H:%M:%S'));
        $this->assertSame('2026-12-31 23:59:59', userdate($end, '%Y-%m-%d %H:%M:%S'));
        $this->assertSame(DAYSECS - 1, $end - $start);
    }

    public function test_date_to_timestamp_refuses_what_is_not_a_real_date(): void {
        foreach (['', 'tomorrow', '2026-13-01', '2026-02-30', '31/12/2026', '2026-1-1'] as $text) {
            $this->assertSame(0, season_manager::date_to_timestamp($text, true), $text);
        }
    }

    /**
     * A season that ends on the typed day is still open during that whole day.
     */
    public function test_a_season_typed_to_end_today_is_not_closed_until_tomorrow(): void {
        global $DB;

        $start = season_manager::date_to_timestamp(userdate(time() - DAYSECS, '%Y-%m-%d'), false);
        $end   = season_manager::date_to_timestamp(userdate(time(), '%Y-%m-%d'), true);
        $season = season_manager::create('Ends today', $start, $end);
        season_manager::activate((int) $season->id);

        $expired = $DB->get_records_select(
            'local_playergames_seasons',
            "status = 'active' AND enddate < :now",
            ['now' => time()]
        );

        $this->assertSame([], $expired);
    }
}
