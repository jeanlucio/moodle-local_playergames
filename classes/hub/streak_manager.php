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
 * Daily activity streak manager for local_playergames.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\hub;

use local_playergames\event\streak_broken;
use local_playergames\event\streak_updated;
use local_playergames\local\unique_row;
use local_playergames\local\user_lock;
use stdClass;

/**
 * Records daily activity streaks and processes streak breaks at midnight.
 *
 * record_activity() is called by the observer whenever a game is completed.
 * process_breaks() is called by the reset_daily_missions cron task at midnight
 * to handle users who did not play the previous day.
 *
 * A freeze consumable shields a single missed day. Freezes are awarded by missions.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class streak_manager {
    /**
     * Records game activity for a user, advancing their streak if warranted.
     *
     * - If already recorded today: no-op.
     * - If last activity was yesterday: increment streak.
     * - Otherwise: start a new streak of 1 (freeze consumption handled by cron).
     *
     * @param int $userid
     * @return void
     * @throws \moodle_exception When another request of the user kept the lock for the whole timeout.
     */
    public static function record_activity(int $userid): void {
        // The streak is read, advanced and written back: under the lock of the user, so the check-in and a game
        // of the same moment do not both start from the old row.
        user_lock::run($userid, fn() => self::record_activity_locked($userid));
    }

    /**
     * Does the work of record_activity() while the request holds the lock of the user.
     *
     * @param int $userid
     * @return void
     */
    private static function record_activity_locked(int $userid): void {
        global $DB;
        $today     = self::midnight_today();
        $yesterday = self::midnight_yesterday();

        $streak = self::get_or_create($userid);

        if ($streak->lastactivedate !== null && (int) $streak->lastactivedate >= $today) {
            return;
        }

        if ($streak->lastactivedate !== null && (int) $streak->lastactivedate >= $yesterday) {
            $streak->currentstreak++;
        } else {
            $streak->currentstreak = 1;
        }

        if ($streak->currentstreak > $streak->longeststreak) {
            $streak->longeststreak = $streak->currentstreak;
        }
        $streak->lastactivedate = $today;
        $DB->update_record('local_playergames_streaks', $streak);

        $event = streak_updated::create([
            'objectid' => $streak->id,
            'context'  => \context_system::instance(),
            'userid'   => $userid,
            'other'    => ['currentstreak' => $streak->currentstreak],
        ]);
        $event->trigger();
    }

    /**
     * Processes overnight streak breaks for all users.
     *
     * Called by reset_daily_missions at midnight. For every user whose last
     * activity was before yesterday: if they have a freeze, consume it and
     * mark yesterday as covered; otherwise reset the streak to 0 and fire
     * streak_broken.
     *
     * @return int Number of streaks broken (not counting freeze consumptions).
     */
    public static function process_breaks(): int {
        global $DB;
        $yesterday = self::midnight_yesterday();
        $broken    = 0;

        $records = $DB->get_records_select(
            'local_playergames_streaks',
            'currentstreak > 0 AND lastactivedate < :yesterday',
            ['yesterday' => $yesterday]
        );

        foreach ($records as $candidate) {
            // A user who plays right after midnight advances the same row: the break is decided again on the row
            // as it is now, under the lock of the user, or it would undo the play. A user whose lock stays taken
            // is left for the next run, which still finds the missed day.
            $lock = user_lock::acquire((int) $candidate->userid);
            if (!$lock) {
                continue;
            }
            try {
                $broken += self::process_break_locked((int) $candidate->id, $yesterday);
            } finally {
                $lock->release();
            }
        }

        return $broken;
    }

    /**
     * Breaks a streak, or spends a freeze on it, while the request holds the lock of its user.
     *
     * @param int $streakid The streak row.
     * @param int $yesterday Midnight of yesterday.
     * @return int 1 when the streak was broken, 0 when a freeze covered the day or nothing was due any more.
     */
    private static function process_break_locked(int $streakid, int $yesterday): int {
        global $DB;
        $streak = $DB->get_record('local_playergames_streaks', ['id' => $streakid]);
        $due = $streak && (int) $streak->currentstreak > 0
            && $streak->lastactivedate !== null && (int) $streak->lastactivedate < $yesterday;
        if (!$due) {
            return 0;
        }
        if ($streak->freezesavailable > 0) {
            $streak->freezesavailable--;
            // The freeze covers the day that was missed: count it as an active day, so that the next
            // play continues the streak (record_activity() looks for activity yesterday) and the same
            // missed day is not charged again tomorrow night.
            $streak->lastactivedate = $yesterday;
            $DB->update_record('local_playergames_streaks', $streak);
            self::log_freeze((int) $streak->userid, 'used', 1, 'streak_break');

            $event = streak_updated::create([
                'objectid' => $streak->id,
                'context'  => \context_system::instance(),
                'userid'   => (int) $streak->userid,
                'other'    => ['currentstreak' => $streak->currentstreak, 'freeze_used' => true],
            ]);
            $event->trigger();
        } else {
            $previousstreak = (int) $streak->currentstreak;
            $streak->currentstreak = 0;
            $DB->update_record('local_playergames_streaks', $streak);

            $event = streak_broken::create([
                'objectid' => $streak->id,
                'context'  => \context_system::instance(),
                'userid'   => (int) $streak->userid,
                'other'    => ['previousstreak' => $previousstreak],
            ]);
            $event->trigger();
            activity_log::record((int) $streak->userid, activity_log::TYPE_STREAK_BROKEN, 0, 'cron');
            return 1;
        }
        return 0;
    }

    /** @var int Default cap on banked freezes when the setting is unset. */
    const DEFAULT_MAX_FREEZES = 3;

    /**
     * Adds freeze consumables to a user's streak record, up to the global cap.
     *
     * Called by mission_manager when a mission that awards a freeze is completed.
     * Never reduces an existing balance: if the user is already at or above the
     * cap, nothing is added.
     *
     * @param int $userid
     * @param int $count Number of freezes to add (default 1).
     * @return int The number of freezes actually added (0 if at the cap).
     * @throws \moodle_exception When another request of the user kept the lock for the whole timeout.
     */
    public static function add_freezes(int $userid, int $count = 1): int {
        if ($count <= 0) {
            return 0;
        }
        return user_lock::run($userid, fn(): int => self::add_freezes_locked($userid, $count));
    }

    /**
     * Does the work of add_freezes() while the request holds the lock of the user.
     *
     * @param int $userid
     * @param int $count Number of freezes to add, above zero.
     * @return int The number of freezes actually added.
     */
    private static function add_freezes_locked(int $userid, int $count): int {
        global $DB;
        $max    = self::max_freezes();
        $streak = self::get_or_create($userid);
        if ((int) $streak->freezesavailable >= $max) {
            return 0;
        }
        $before = (int) $streak->freezesavailable;
        $streak->freezesavailable = min($before + $count, $max);
        $DB->update_record('local_playergames_streaks', $streak);
        $granted = (int) $streak->freezesavailable - $before;
        if ($granted > 0) {
            self::log_freeze($userid, 'earned', $granted, 'mission');
        }
        return $granted;
    }

    /**
     * Returns the configured cap on banked freezes.
     *
     * @return int
     */
    public static function max_freezes(): int {
        $max = get_config('local_playergames', 'freeze_max');
        return ($max === false || $max === null) ? self::DEFAULT_MAX_FREEZES : max(0, (int) $max);
    }

    /**
     * Returns the last $limit freeze log entries for a user, most recent first.
     *
     * @param int $userid
     * @param int $limit Maximum number of entries to return.
     * @return stdClass[]
     */
    public static function get_freeze_log(int $userid, int $limit = 5): array {
        global $DB;
        return array_values($DB->get_records(
            'local_playergames_freeze_log',
            ['userid' => $userid],
            'timecreated DESC',
            '*',
            0,
            $limit
        ));
    }

    /**
     * Writes a freeze event to the log table.
     *
     * @param int    $userid
     * @param string $action  'earned' or 'used'.
     * @param int    $amount  Number of freezes involved.
     * @param string $reason  Short reason key ('mission', 'streak_break').
     * @return void
     */
    private static function log_freeze(int $userid, string $action, int $amount, string $reason): void {
        global $DB;
        $record              = new stdClass();
        $record->userid      = $userid;
        $record->action      = $action;
        $record->amount      = $amount;
        $record->reason      = $reason;
        $record->timecreated = time();
        $DB->insert_record('local_playergames_freeze_log', $record, false);

        $eventtype = $action === 'earned' ? activity_log::TYPE_FREEZE_EARNED : activity_log::TYPE_FREEZE_USED;
        activity_log::record($userid, $eventtype, 0, $reason);
    }

    /**
     * Returns the streak record for a user, creating it with zero values if absent.
     *
     * @param int $userid
     * @return stdClass
     */
    public static function get_or_create(int $userid): stdClass {
        return unique_row::get_or_create(
            'local_playergames_streaks',
            ['userid' => $userid],
            ['currentstreak' => 0, 'longeststreak' => 0, 'freezesavailable' => 0, 'lastactivedate' => null]
        );
    }

    /**
     * Returns the Unix timestamp for midnight at the start of today (server time).
     *
     * @return int
     */
    private static function midnight_today(): int {
        return mktime(0, 0, 0, (int) date('n'), (int) date('j'), (int) date('Y'));
    }

    /**
     * Returns the Unix timestamp for midnight at the start of yesterday (server time).
     *
     * Counted on the calendar, not by subtracting DAYSECS: on the two days a year the clocks change, the
     * day has 23 or 25 hours and "today minus 86400 seconds" is no longer the midnight that dates were
     * stored with.
     *
     * @param int|null $now Reference time, or null for the current time.
     * @return int
     */
    public static function midnight_yesterday(?int $now = null): int {
        $now = $now ?? time();
        return mktime(0, 0, 0, (int) date('n', $now), (int) date('j', $now) - 1, (int) date('Y', $now));
    }
}
