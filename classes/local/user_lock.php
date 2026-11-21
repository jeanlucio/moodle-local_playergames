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
 * Lock that serialises the rewards of one user.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\local;

use core\lock\lock;
use core\lock\lock_config;

/**
 * One lock per user, shared by every path that pays a reward or changes a counter of that user.
 *
 * The XP of the season, the progress of the missions, the streak, the freezes and the achievements are read,
 * changed in PHP and written back. Two requests of the same user (two games in two tabs, or a game and the
 * check-in of the first page of the day) would both read the old value: one of the two XP awards is lost,
 * and a mission is paid twice. Every one of those paths takes this lock first.
 *
 * The lock is reentrant within the request: the game takes it, and the XP, the missions and the streak it
 * reaches take it again without waiting for themselves. It must be taken before a transaction starts, and
 * the outermost holder keeps it until the transaction is committed: released earlier, the next request
 * would read the rows the transaction has not committed yet as they were.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class user_lock {
    /** @var int Seconds to wait for another request of the same user. */
    private const TIMEOUT = 10;

    /** @var array Locks this request holds, keyed by user id: ['lock' => lock, 'depth' => int]. */
    private static array $held = [];

    /** @var int[] Users whose lock is reported as taken by another request (PHPUnit only). */
    private static array $busyfortests = [];

    /** @var int The user the lock belongs to. */
    private int $userid;

    /** @var bool Whether this handle was already released. */
    private bool $released = false;

    /**
     * Builds a handle on a lock this request holds.
     *
     * @param int $userid The user the lock belongs to.
     */
    private function __construct(int $userid) {
        $this->userid = $userid;
    }

    /**
     * Takes the lock of a user, or another hold on it when this request already has it.
     *
     * @param int $userid The user.
     * @return self|null The handle, or null when another request kept the lock for the whole timeout.
     */
    public static function acquire(int $userid): ?self {
        if (isset(self::$held[$userid])) {
            self::$held[$userid]['depth']++;
            return new self($userid);
        }
        if (in_array($userid, self::$busyfortests, true)) {
            return null;
        }
        $lock = lock_config::get_lock_factory('local_playergames')->get_lock("user_{$userid}", self::TIMEOUT);
        if (!$lock) {
            return null;
        }
        self::$held[$userid] = ['lock' => $lock, 'depth' => 1];
        return new self($userid);
    }

    /**
     * Runs a piece of work while holding the lock of a user.
     *
     * @param int $userid The user.
     * @param callable $work The work; its return value is returned.
     * @return mixed What the work returned.
     * @throws \moodle_exception When another request kept the lock for the whole timeout.
     */
    public static function run(int $userid, callable $work): mixed {
        $lock = self::acquire($userid);
        if (!$lock) {
            throw new \moodle_exception('error_round_busy', 'local_playergames');
        }
        try {
            return $work();
        } finally {
            $lock->release();
        }
    }

    /**
     * Whether this request holds the lock of a user.
     *
     * @param int $userid The user.
     * @return bool
     */
    public static function is_held(int $userid): bool {
        return isset(self::$held[$userid]);
    }

    /**
     * Gives back this hold; the lock itself is released with the last hold of the request.
     *
     * @return void
     */
    public function release(): void {
        if ($this->released || !isset(self::$held[$this->userid])) {
            return;
        }
        $this->released = true;
        self::$held[$this->userid]['depth']--;
        if (self::$held[$this->userid]['depth'] > 0) {
            return;
        }
        /** @var lock $lock */
        $lock = self::$held[$this->userid]['lock'];
        unset(self::$held[$this->userid]);
        $lock->release();
    }

    /**
     * Makes the lock of a user look taken by another request, which PHPUnit cannot run in parallel.
     *
     * @param int $userid The user.
     * @return void
     * @throws \coding_exception Outside PHPUnit.
     */
    public static function force_busy_for_tests(int $userid): void {
        if (!defined('PHPUNIT_TEST') || !PHPUNIT_TEST) {
            throw new \coding_exception('user_lock::force_busy_for_tests() is for PHPUnit only.');
        }
        self::$busyfortests[] = $userid;
    }

    /**
     * Forgets the users made busy by force_busy_for_tests().
     *
     * @return void
     * @throws \coding_exception Outside PHPUnit.
     */
    public static function reset_for_tests(): void {
        if (!defined('PHPUNIT_TEST') || !PHPUNIT_TEST) {
            throw new \coding_exception('user_lock::reset_for_tests() is for PHPUnit only.');
        }
        self::$busyfortests = [];
    }
}
