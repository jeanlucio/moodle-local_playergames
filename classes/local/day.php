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
 * The day of PlayerGames, the same for every player.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\local;

/**
 * Midnights of the server, which date every play, every daily word and the bounds of every season.
 *
 * A day of play is one day for the whole site, so it starts at midnight in the timezone of the server, which the
 * core sets as the default timezone of PHP. The dates an administrator types for a season are read in that
 * timezone too, and shown back in it: read in the timezone of whoever typed them, a season would start and end
 * hours away from the midnight that dates the plays, and its first and last days would count for the wrong season.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class day {
    /**
     * Returns the midnight that starts the day of a moment.
     *
     * @param int|null $time The moment, or null for now.
     * @return int The timestamp of that midnight.
     */
    public static function midnight(?int $time = null): int {
        $time = $time ?? time();
        return mktime(0, 0, 0, (int) date('n', $time), (int) date('j', $time), (int) date('Y', $time));
    }

    /**
     * Returns the midnight that starts today.
     *
     * @return int
     */
    public static function today(): int {
        return self::midnight();
    }

    /**
     * Returns the midnight that starts the day before a moment.
     *
     * Counted on the calendar, not by subtracting DAYSECS: on the two days a year the clocks change, the day has
     * 23 or 25 hours and "today minus 86400 seconds" is no longer the midnight that dates were stored with.
     *
     * @param int|null $time The moment, or null for now.
     * @return int
     */
    public static function yesterday(?int $time = null): int {
        $time = $time ?? time();
        return mktime(0, 0, 0, (int) date('n', $time), (int) date('j', $time) - 1, (int) date('Y', $time));
    }

    /**
     * Reads a date typed as YYYY-MM-DD as the start or the last second of that day.
     *
     * @param string $date The date as typed.
     * @param bool $endofday True for 23:59:59 of that day (the end of a season), false for its midnight.
     * @return int The timestamp, or 0 when the text is not a real date.
     */
    public static function from_date(string $date, bool $endofday): int {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', trim($date), $matches)) {
            return 0;
        }
        [, $year, $month, $day] = array_map('intval', $matches);
        if (!checkdate($month, $day, $year)) {
            return 0;
        }
        return $endofday ? mktime(23, 59, 59, $month, $day, $year) : mktime(0, 0, 0, $month, $day, $year);
    }

    /**
     * Writes the day of a moment as YYYY-MM-DD, as a date field takes it.
     *
     * @param int $time The moment.
     * @return string
     */
    public static function to_date(int $time): string {
        return date('Y-m-d', $time);
    }

    /**
     * Formats the day of a moment for display, as the day it is on the server.
     *
     * For the bounds of a season and the dates of plays, which are days of the site: shown in the timezone of the
     * viewer, the midnight of a day would read as the day before to whoever is west of the server.
     *
     * @param int $time The moment.
     * @param string $format A strftime format, usually a langconfig string.
     * @return string
     */
    public static function format(int $time, string $format): string {
        return userdate($time, $format, \core_date::get_server_timezone());
    }
}
