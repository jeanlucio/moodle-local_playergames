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
     * Returns where a user should land when they open the plugin without saying where.
     *
     * Staff go to the dashboard and everyone else who may use the hub goes there. Staff are
     * recognised by {@see self::is_staff()} and not by a capability checked at the system
     * context, which a teacher whose role comes from a course enrolment never holds.
     *
     * @param int $userid The user to inspect, or 0 for the current user.
     * @return \moodle_url
     */
    public static function landing_url(int $userid = 0): \moodle_url {
        if (self::is_staff($userid)) {
            return new \moodle_url('/local/playergames/dashboard.php');
        }

        if (has_capability('local/playergames:viewhub', context_system::instance(), $userid ?: null)) {
            return new \moodle_url('/local/playergames/hub.php');
        }

        return new \moodle_url('/');
    }

    /**
     * Returns the IDs of every user is_staff() would return true for.
     *
     * A bulk equivalent of is_staff(), for ranking/group-split queries that need the whole staff set at
     * once. The capability is not evaluated in SQL: whether a role holds it at a given course depends on
     * the role definition, the overrides on the way down (an override can grant or forbid it in one
     * course only) and on roles assigned at category level, none of which a join on role_capabilities
     * reproduces. So the query only narrows the users to those holding a role that is allowed one of the
     * capabilities anywhere, and each of them goes through is_staff(), the single definition of staff.
     * The set is cached for a few minutes because that check costs a few queries per candidate.
     *
     * @return int[]
     */
    public static function get_staff_ids(): array {
        $cache = \cache::make('local_playergames', 'staffids');
        $ids = $cache->get('ids');
        if (is_array($ids)) {
            return $ids;
        }

        $ids = self::compute_staff_ids();
        $cache->set('ids', $ids);
        return $ids;
    }

    /**
     * Works out the staff set, see get_staff_ids().
     *
     * @return int[]
     */
    private static function compute_staff_ids(): array {
        global $CFG, $DB;

        $sql = "SELECT DISTINCT ra.userid
                  FROM {role_assignments} ra
                  JOIN {role_capabilities} rc ON rc.roleid = ra.roleid
                       AND rc.capability IN (:updatecap, :configcap)
                       AND rc.permission = :allow
                  JOIN {user} u ON u.id = ra.userid AND u.deleted = 0";
        $candidates = $DB->get_fieldset_sql($sql, [
            'updatecap' => 'moodle/course:update',
            'configcap' => 'moodle/site:config',
            'allow'     => CAP_ALLOW,
        ]);

        $ids = [];
        foreach ($candidates as $candidate) {
            if (self::is_staff((int) $candidate)) {
                $ids[] = (int) $candidate;
            }
        }

        // Site admins have all capabilities implicitly and may hold no role at all.
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

    /**
     * Whether the user takes part in the gamification.
     *
     * The single rule behind every page and service that records game data: the per-user opt-out and
     * the allowed_participants site setting (students-only excludes non-admin staff, staff-only
     * excludes students). The Hub, the games, the daily check-in and the XP awarding all ask this,
     * so a user the Hub turns away cannot earn XP by opening a game's URL directly.
     *
     * @param int $userid The user to test.
     * @return bool
     */
    public static function is_participant(int $userid): bool {
        if (!preferences::is_gamification_enabled($userid)) {
            return false;
        }

        $allowed = get_config('local_playergames', 'allowed_participants') ?: 'students';
        $isstaff = self::is_staff($userid);
        $isadmin = has_capability('moodle/site:config', context_system::instance(), $userid);

        return self::can_view_hub($isstaff, $isadmin, $allowed);
    }

    /**
     * Stops the request unless the current user takes part in the gamification.
     *
     * Call it right after require_login() and require_capability() on every entry point that shows
     * or records game data, before any POST handling. A page sends a user who turned the gamification
     * off back to their dashboard with a notice; a service (which cannot redirect) gets an exception.
     *
     * @param bool $service True when called from a web service or AJAX handler, to throw instead of redirect.
     * @throws \moodle_exception When the user does not take part.
     */
    public static function require_participant(bool $service = false): void {
        global $USER;

        if (!preferences::is_gamification_enabled((int) $USER->id)) {
            if ($service) {
                throw new \moodle_exception('gamification_disabled_notice', 'local_playergames');
            }
            redirect(
                new \moodle_url('/my/'),
                get_string('gamification_disabled_notice', 'local_playergames'),
                null,
                \core\output\notification::NOTIFY_INFO
            );
        }

        if (!self::is_participant((int) $USER->id)) {
            throw new \moodle_exception('hub_access_restricted', 'local_playergames');
        }
    }
}
