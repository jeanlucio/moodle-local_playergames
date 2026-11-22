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
 * PlayerQuiz gameplay settings.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\games;

/**
 * Reads the admin-configured PlayerQuiz rules, applying defaults and bounds.
 *
 * @package    local_playergames
 */
class quiz_settings {
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
     * Returns the wrong answers that lose the play, and the day; 0 is unlimited.
     *
     * @return int
     */
    public function max_attempts(): int {
        return max(0, (int) get_config('local_playergames', 'quiz_max_attempts'));
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
}
