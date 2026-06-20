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

    public function test_get_config_snapshot_falls_back_when_empty(): void {
        $season = (object) ['config_snapshot' => null];
        $snapshot = season_manager::get_config_snapshot($season);
        // A missing snapshot yields current settings, not an empty array.
        $this->assertArrayHasKey('xp_cap_quiz', $snapshot);
    }
}
