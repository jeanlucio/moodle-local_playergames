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

        return has_capability('moodle/site:config', context_system::instance(), $userid)
            || !empty(self::teacher_courseids($userid));
    }

    /**
     * Returns the IDs of every user is_staff() would return true for.
     *
     * A bulk equivalent of is_staff(), for ranking/group-split queries that need
     * the whole staff set at once. Uses a single query against role_assignments
     * joined to course contexts and role_capabilities — get_users_by_capability()
     * cannot be used here because, called against the system context, it only
     * finds capabilities granted at system level, not via course-level role
     * assignments (the common case for teachers) — the same pitfall is_staff()
     * avoids by checking teacher_courseids() instead.
     *
     * @return int[]
     */
    public static function get_staff_ids(): array {
        global $CFG, $DB;

        $sql = "SELECT DISTINCT ra.userid
                  FROM {role_assignments} ra
                  JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = :coursecontextlevel
                  JOIN {role_capabilities} rc ON rc.roleid = ra.roleid
                       AND rc.capability = :capability
                       AND rc.permission = :allow";
        $ids = $DB->get_fieldset_sql($sql, [
            'coursecontextlevel' => CONTEXT_COURSE,
            'capability'         => 'moodle/course:update',
            'allow'              => CAP_ALLOW,
        ]);
        $ids = array_map('intval', $ids);

        // Site admins have all capabilities implicitly but are not returned by
        // the query above. Merge them into the staff set.
        if (!empty($CFG->siteadmins)) {
            $adminids = array_map('intval', explode(',', $CFG->siteadmins));
            $ids      = array_values(array_unique(array_merge($ids, $adminids)));
        }

        return $ids;
    }

    /**
     * Whether a participant is allowed to view the Player Hub under the
     * allowed_participants site setting.
     *
     * Site admins always pass regardless of the setting. Shared by hub.php
     * (which throws on false) and block_playergames (which just hides its
     * content on false) so the rule only lives in one place.
     *
     * @param bool $isstaff Whether the user is staff (see is_staff()).
     * @param bool $isadmin Whether the user has moodle/site:config.
     * @param string $allowed Value of the allowed_participants setting.
     * @return bool
     */
    public static function can_view_hub(bool $isstaff, bool $isadmin, string $allowed): bool {
        if ($allowed === 'students' && $isstaff && !$isadmin) {
            return false;
        }
        if ($allowed === 'staff' && !$isstaff) {
            return false;
        }
        return true;
    }
}
