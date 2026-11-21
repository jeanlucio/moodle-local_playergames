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
 * Tests of the day of PlayerGames.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\local;

/**
 * The midnights of the server date every play and bound every season, whoever is logged in.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_playergames\local\day
 */
final class day_test extends \advanced_testcase {
    /**
     * A season typed by an administrator in another timezone starts and ends on the midnights of the server.
     *
     * Regression test: the dates were read in the timezone of the administrator, so with the server in UTC and
     * the administrator in São Paulo the season started at 03:00 of its first day, after the plays of that day,
     * and ended at 02:59 of the day after its last, taking in the plays of that day too.
     */
    public function test_a_season_typed_in_another_timezone_follows_the_midnights_of_the_server(): void {
        $this->resetAfterTest();
        $this->setTimezone('UTC', 'UTC');
        $admin = $this->getDataGenerator()->create_user(['timezone' => 'America/Sao_Paulo']);
        $this->setUser($admin);

        $start = day::from_date('2026-01-01', false);
        $end = day::from_date('2026-06-30', true);

        $this->assertSame(gmmktime(0, 0, 0, 1, 1, 2026), $start);
        $this->assertSame(gmmktime(23, 59, 59, 6, 30, 2026), $end);
        // The plays of the first and of the last day are dated inside the season, the day after is not.
        $this->assertSame($start, day::midnight(gmmktime(10, 0, 0, 1, 1, 2026)));
        $this->assertLessThanOrEqual($end, day::midnight(gmmktime(10, 0, 0, 6, 30, 2026)));
        $this->assertGreaterThan($end, day::midnight(gmmktime(10, 0, 0, 7, 1, 2026)));
        // And the form shows the administrator the days that were typed.
        $this->assertSame('2026-01-01', day::to_date($start));
        $this->assertSame('2026-06-30', day::to_date($end));
        // Shown in the timezone of the administrator, that midnight would read as 31 December.
        $format = get_string('strftimedatefullshort', 'langconfig');
        $this->assertSame(userdate($start, $format, 'UTC'), day::format($start, $format));
        $this->assertNotSame(userdate($start, $format), day::format($start, $format));
    }

    /**
     * The midnight of a moment is the start of its day on the server, and today is the midnight of now.
     */
    public function test_midnight_is_the_start_of_the_day_on_the_server(): void {
        $this->resetAfterTest();
        $this->setTimezone('Europe/Lisbon', 'Europe/Lisbon');

        $this->assertSame(mktime(0, 0, 0, 7, 15, 2026), day::midnight(mktime(23, 30, 0, 7, 15, 2026)));
        $this->assertSame(day::midnight(time()), day::today());
    }

    /**
     * A typed date becomes nothing when it is not a real day.
     */
    public function test_a_typed_date_that_is_not_a_real_day_gives_nothing(): void {
        foreach (['', 'tomorrow', '2026-13-01', '2026-02-30', '31/12/2026', '2026-1-1'] as $text) {
            $this->assertSame(0, day::from_date($text, true), $text);
        }
    }

    /**
     * "Yesterday" is the midnight of the previous calendar day even on the two days a year the clocks
     * change, when the day has 25 or 23 hours.
     */
    public function test_yesterday_is_the_previous_calendar_midnight_on_the_days_the_clocks_change(): void {
        $this->resetAfterTest();
        $this->setTimezone('Europe/Lisbon', 'Europe/Lisbon');

        // The clocks went back on 25 October 2026 (a 25-hour day) and forward on 29 March 2026 (23 hours).
        foreach ([[2026, 10, 26], [2026, 10, 25], [2026, 3, 30], [2026, 3, 29], [2026, 6, 15]] as [$year, $month, $day]) {
            $now = mktime(12, 0, 0, $month, $day, $year);

            $expected = mktime(0, 0, 0, $month, $day - 1, $year);
            $this->assertSame($expected, day::yesterday($now), "{$year}-{$month}-{$day}");
            $this->assertSame('000000', date('His', $expected));
        }
        // The old arithmetic missed on the day after the clocks went back.
        $monday = mktime(0, 0, 0, 10, 26, 2026);
        $this->assertNotSame($monday - DAYSECS, day::yesterday(mktime(12, 0, 0, 10, 26, 2026)));
    }
}
