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
 * PlayerQuiz gameplay settings and failure cooldown resolver.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\games;

/**
 * Reads the admin-configured PlayerQuiz rules, applying defaults and bounds,
 * and manages the cooldown that locks the quiz after a failed play.
 *
 * The cooldown is stored as a user preference holding the unix time until
 * which the quiz stays locked. The reference time is injectable so the logic
 * can be exercised deterministically by tests.
 *
 * @package    local_playergames
 */
class quiz_settings {
    /** @var string User preference holding the cooldown expiry timestamp. */
    public const PREF_COOLDOWN = 'local_playergames_quizcooldown';

    /** @var int Default per-question time limit in seconds (two minutes). */
    public const DEFAULT_SECONDS = 120;

    /** @var int Default number of questions drawn per play. */
    public const DEFAULT_SESSION = 20;

    /**
     * Returns the per-question time limit in seconds; 0 disables the timer.
     *
     * @return int
     */
    public function question_seconds(): int {
        $value = get_config('local_playergames', 'quiz_question_seconds');
        if ($value === false || $value === '') {
            return self::DEFAULT_SECONDS;
        }
        return max(0, (int) $value);
    }

    /**
     * Returns the maximum wrong attempts before a play fails; 0 is unlimited.
     *
     * @return int
     */
    public function max_attempts(): int {
        return max(0, (int) get_config('local_playergames', 'quiz_max_attempts'));
    }

    /**
     * Returns the cooldown applied after a failed play, in minutes; 0 disables.
     *
     * @return int
     */
    public function cooldown_minutes(): int {
        return max(0, (int) get_config('local_playergames', 'quiz_cooldown_minutes'));
    }

    /**
     * Returns the number of questions to draw per play; at least 1.
     *
     * @return int
     */
    public function session_size(): int {
        $value = (int) get_config('local_playergames', 'quiz_session_size');
        return $value > 0 ? $value : self::DEFAULT_SESSION;
    }

    /**
     * Returns the seconds remaining on the user's cooldown, or 0 when none.
     *
     * @param int $userid User to check.
     * @param int|null $now Reference time; defaults to the current time.
     * @return int
     */
    public function cooldown_remaining(int $userid, ?int $now = null): int {
        $now = $now ?? time();
        $until = (int) get_user_preferences(self::PREF_COOLDOWN, 0, $userid);
        return max(0, $until - $now);
    }

    /**
     * Returns whether the quiz is currently locked for the user by a cooldown.
     *
     * @param int $userid User to check.
     * @param int|null $now Reference time; defaults to the current time.
     * @return bool
     */
    public function is_on_cooldown(int $userid, ?int $now = null): bool {
        return $this->cooldown_remaining($userid, $now) > 0;
    }

    /**
     * Starts the failure cooldown for the user. Does nothing when the cooldown
     * is disabled (zero minutes).
     *
     * @param int $userid User to lock out.
     * @param int|null $now Reference time; defaults to the current time.
     * @return void
     */
    public function start_cooldown(int $userid, ?int $now = null): void {
        $minutes = $this->cooldown_minutes();
        if ($minutes <= 0) {
            return;
        }
        $now = $now ?? time();
        set_user_preference(self::PREF_COOLDOWN, $now + $minutes * 60, $userid);
    }
}
