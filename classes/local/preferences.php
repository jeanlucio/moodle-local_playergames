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
 * Gamification opt-in preference helper.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\local;

/**
 * Single source of truth for whether gamification is enabled for a user.
 *
 * Resolution order: the user's own preference, then the site default
 * (admin setting), then enabled. Sibling Player plugins (block_playerhud,
 * filter_playerhud) can read the same preference to honour the opt-out.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class preferences {
    /** @var string User preference name storing the gamification opt-in flag. */
    public const PREF_GAMIFICATION = 'local_playergames_gamification';

    /**
     * Whether gamification surfaces should be shown to the given user.
     *
     * @param int $userid The user to check.
     * @return bool True when gamification is enabled for the user.
     */
    public static function is_gamification_enabled(int $userid): bool {
        $default = get_config('local_playergames', 'gamificationdefault');
        // The setting is unset on a fresh install; treat that as enabled.
        $default = ($default === false) ? 1 : (int) $default;

        return (bool) get_user_preferences(self::PREF_GAMIFICATION, $default, $userid);
    }

    /**
     * Store the gamification opt-in choice for the given user.
     *
     * Opting out also takes the user off both rankings: the button that would do it later is behind the
     * participant rule, which turns away exactly this user, so a name left on the list could never be
     * removed. Opting back in starts invisible, and the user chooses again whether to show up.
     *
     * @param int $userid The user whose preference is being set.
     * @param bool $enabled Whether gamification should be enabled.
     */
    public static function set(int $userid, bool $enabled): void {
        global $DB;

        set_user_preference(self::PREF_GAMIFICATION, $enabled ? 1 : 0, $userid);

        if (!$enabled) {
            $DB->set_field('local_playergames_player_profile', 'showinranking', 0, ['userid' => $userid]);
            $DB->set_field('local_playergames_student_xp_cache', 'showinranking', 0, ['userid' => $userid]);
            \cache::make('local_playergames', 'ranking')->purge();
        }
    }

    /**
     * SQL condition that leaves out the users who chose to opt out of the gamification.
     *
     * The ranking queries add it next to showinranking, so a name does not stay on a list because of a
     * flag that was set before the opt-out, or by a path that did not go through set().
     *
     * @param string $useralias Alias of the table with the userid column the condition is about.
     * @param string $prefix Prefix of the condition's parameter names and table alias, unique in the query.
     * @return array [string SQL condition, array parameters]
     */
    public static function sql_not_opted_out(string $useralias, string $prefix = 'optout'): array {
        global $DB;

        $sql = "NOT EXISTS (SELECT 1
                              FROM {user_preferences} {$prefix}up
                             WHERE {$prefix}up.userid = {$useralias}.userid
                               AND {$prefix}up.name = :{$prefix}name
                               AND " . $DB->sql_compare_text("{$prefix}up.value") . " = :{$prefix}value)";

        return [$sql, [$prefix . 'name' => self::PREF_GAMIFICATION, $prefix . 'value' => '0']];
    }
}
