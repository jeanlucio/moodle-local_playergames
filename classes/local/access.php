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
    /** @var int Seconds after which a request works the staff set out again, if the task has not. */
    private const STAFF_SET_MAX_AGE = HOURSECS;

    /**
     * Whether the user can update at least one course (teaches).
     *
     * Asks for a single course: the answer is a yes or a no, and for a manager the complete list is
     * every course of the site.
     *
     * @param int $userid The user to inspect.
     * @return bool
     */
    public static function teaches_any(int $userid): bool {
        return !empty(get_user_capability_course('moodle/course:update', $userid, true, 'id', '', 1));
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
            || self::teaches_any($userid);
    }

    /**
     * Whether the user is staff, for deciding what to show, from a copy kept for a few minutes.
     *
     * The primary navigation asks on every page of the site, and the answer rarely changes. Never use
     * this to allow something: the pages and services check is_staff() themselves, live, so a link
     * that lingers after a role was removed leads to a refusal and nothing more.
     *
     * @param int $userid The user to inspect.
     * @return bool
     */
    public static function is_staff_for_display(int $userid): bool {
        $cache = \cache::make('local_playergames', 'staffids');
        $key = 'user' . $userid;
        $kept = $cache->get($key);
        if ($kept !== false) {
            return (int) $kept === 1;
        }

        $staff = self::is_staff($userid);
        $cache->set($key, $staff ? 1 : 0);
        return $staff;
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
     *
     * That check costs about eight queries per candidate, so a request never waits for it when a copy
     * exists: the refresh_staff_ids task keeps the copy, and only a site that has none yet, or whose copy
     * is older than an hour because cron is not running, works it out on the spot.
     *
     * @return int[]
     */
    public static function get_staff_ids(): array {
        $kept = \cache::make('local_playergames', 'staffset')->get('set');
        if (is_array($kept) && time() - (int) $kept['time'] < self::STAFF_SET_MAX_AGE) {
            return $kept['ids'];
        }

        return self::refresh_staff_ids();
    }

    /**
     * Works out the staff set and keeps it for get_staff_ids().
     *
     * @return int[]
     */
    public static function refresh_staff_ids(): array {
        $ids = self::compute_staff_ids();
        \cache::make('local_playergames', 'staffset')->set('set', ['ids' => $ids, 'time' => time()]);
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
     * Whether the rankings are shown at all.
     *
     * An admin can turn them off so players only see their own score. A value that was never saved
     * counts as on, as the setting is on by default.
     *
     * @return bool
     */
    public static function ranking_enabled(): bool {
        $setting = get_config('local_playergames', 'enable_ranking');
        return $setting === false ? true : (bool) $setting;
    }

    /**
     * Which rankings a viewer may see: the one of the students, the one of the staff, or both.
     *
     * The single rule of the hub and of the hall of fame. An admin sees both; staff sees both when the
     * participants setting is 'both' and only the staff one otherwise; everyone else sees the students'
     * one. A ranking that is off shows nothing to anyone.
     *
     * @param bool $isstaff Whether the viewer is staff (see is_staff()).
     * @param bool $isadmin Whether the viewer has moodle/site:config.
     * @param string $allowed Value of the allowed_participants setting.
     * @return bool[] Whether the 'students' and the 'staff' rankings may be seen.
     */
    public static function visible_ranking_groups(bool $isstaff, bool $isadmin, string $allowed): array {
        if (!self::ranking_enabled()) {
            return ['students' => false, 'staff' => false];
        }
        if ($isadmin || ($allowed === 'both' && $isstaff)) {
            return ['students' => true, 'staff' => true];
        }
        return ['students' => !$isstaff, 'staff' => $isstaff];
    }

    /**
     * Whether the user takes part in the gamification.
     *
     * The single rule behind every page and service that records game data: the viewhub capability
     * (an administrator takes a group out by prohibiting it), the per-user opt-out and the
     * allowed_participants site setting (students-only excludes non-admin staff, staff-only
     * excludes students). The Hub, the games, the daily check-in and the XP awarding all ask this,
     * so a user the Hub turns away cannot earn XP by opening a game's URL directly, or through the
     * check-in that runs on every page.
     *
     * @param int $userid The user to test.
     * @param bool|null $isstaff Whether the user is staff, when the caller already knows (for display only);
     *     null looks it up with is_staff().
     * @param bool $checkoptout Whether to read the user's opt-out. A caller that already left the users who
     *     opted out out of its query passes false, and saves one preference read per user.
     * @return bool
     */
    public static function is_participant(int $userid, ?bool $isstaff = null, bool $checkoptout = true): bool {
        if (!has_capability('local/playergames:viewhub', context_system::instance(), $userid)) {
            return false;
        }

        if ($checkoptout && !preferences::is_gamification_enabled($userid)) {
            return false;
        }

        $allowed = get_config('local_playergames', 'allowed_participants') ?: 'students';
        $isstaff = $isstaff ?? self::is_staff($userid);
        $isadmin = has_capability('moodle/site:config', context_system::instance(), $userid);

        return self::can_view_hub($isstaff, $isadmin, $allowed);
    }

    /**
     * The rule of is_participant() as an SQL condition on a user id column, for the rankings.
     *
     * A ranking list and the position of a player outside it must leave out the same users: someone who lost
     * the viewhub capability, or a group the allowed_participants setting no longer lets in, keeps the
     * showinranking flag of the profile. Asked in the query, the rule needs no capability check per listed
     * user. The opt-out is not part of it: the queries have preferences::sql_not_opted_out() already.
     *
     * @param string $useridcolumn The column holding the user id, e.g. 'p.userid'.
     * @param bool|null $wantstaff True for the staff list, false for the students list, null for a list that
     *     is not split between them (the learning XP), where only the capability counts.
     * @return array [string $sql, array $params] The condition and its named parameters.
     */
    public static function participant_sql(string $useridcolumn, ?bool $wantstaff = null): array {
        $context = context_system::instance();

        // A site administrator passes has_capability() whatever the roles say, so is let in by id as well.
        [$adminsql, $params] = self::site_admins_sql($useridcolumn);
        [$viewsql, $viewparams] = get_with_capability_sql($context, 'local/playergames:viewhub');
        $conditions = ["({$useridcolumn} IN ({$viewsql}) OR {$adminsql})"];
        $params += $viewparams;

        $allowed = get_config('local_playergames', 'allowed_participants') ?: 'students';
        if ($wantstaff === true && $allowed === 'students') {
            // On a students-only site the staff who still take part are the administrators (see can_view_hub()).
            [$adminsql, $adminparams] = self::site_admins_sql($useridcolumn);
            [$configsql, $configparams] = get_with_capability_sql($context, 'moodle/site:config');
            $conditions[] = "({$useridcolumn} IN ({$configsql}) OR {$adminsql})";
            $params += $adminparams + $configparams;
        } else if ($wantstaff === false && $allowed === 'staff') {
            $conditions[] = '1 = 0';
        }

        return [implode(' AND ', $conditions), $params];
    }

    /**
     * An SQL condition matching the site administrators, with parameter names of its own.
     *
     * @param string $useridcolumn The column holding the user id.
     * @return array [string $sql, array $params]
     */
    private static function site_admins_sql(string $useridcolumn): array {
        global $CFG, $DB;
        $admins = array_values(array_filter(array_map('intval', explode(',', (string) $CFG->siteadmins))));
        if ($admins === []) {
            return ['1 = 0', []];
        }
        [$insql, $params] = $DB->get_in_or_equal($admins, SQL_PARAMS_NAMED, 'pgadmin');
        return ["{$useridcolumn} {$insql}", $params];
    }

    /**
     * Where a page sends a user the participant rules turned away.
     *
     * Staff the setting turns away from the hub have the dashboard; anyone else goes to their own page. Never
     * the hub itself, which would send them straight back to the check that turned them away.
     *
     * @param int $userid The user.
     * @return \moodle_url
     */
    public static function turned_away_url(int $userid): \moodle_url {
        return self::is_staff($userid)
            ? new \moodle_url('/local/playergames/dashboard.php')
            : new \moodle_url('/my/');
    }

    /**
     * Stops the request unless the current user takes part in the gamification.
     *
     * Call it right after require_login() and require_capability() on every entry point that shows
     * or records game data, before any POST handling. A page sends a user the participant rules turn away
     * (the gamification turned off, or a group the site does not allow) somewhere that opens for them
     * with a notice; a service or a file (which cannot redirect) gets an exception.
     *
     * @param bool $service True when called from a web service, AJAX handler or file, to throw instead of redirect.
     * @throws \moodle_exception When the user does not take part and the caller cannot redirect.
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
            if ($service) {
                throw new \moodle_exception('hub_access_restricted', 'local_playergames');
            }
            redirect(
                self::turned_away_url((int) $USER->id),
                get_string('hub_access_restricted', 'local_playergames'),
                null,
                \core\output\notification::NOTIFY_INFO
            );
        }
    }
}
