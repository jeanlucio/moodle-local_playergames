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
 * Site-wide access helpers for local_playergames.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\local;

use context_system;

/**
 * Resolves a user's role in the site-wide PlayerGames features.
 *
 * The plugin is site-wide (global seasons), but most users get their student
 * or teacher role from course enrolments, whose capabilities never reach the
 * system context. These helpers therefore aggregate course-level roles instead
 * of relying on system-context capability checks.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class access {
    /**
     * Returns the IDs of courses where the user can update the course (teaches).
     *
     * @param int $userid The user to inspect.
     * @return int[]
     */
    public static function teacher_courseids(int $userid): array {
        $courses = get_user_capability_course('moodle/course:update', $userid, true, 'id');
        if (!$courses) {
            return [];
        }
        return array_map('intval', array_column($courses, 'id'));
    }

    /**
     * Whether the user is staff: a site manager/admin, or a teacher in any course.
     *
     * @param int $userid The user to inspect, or 0 for the current user.
     * @return bool
     */
    public static function is_staff(int $userid = 0): bool {
        global $USER;

        if ($userid === 0) {
            $userid = (int) $USER->id;
        }

        static $cache = [];
        if (array_key_exists($userid, $cache)) {
            return $cache[$userid];
        }

        $cache[$userid] = has_capability('moodle/site:config', context_system::instance(), $userid)
            || !empty(self::teacher_courseids($userid));

        return $cache[$userid];
    }
}
