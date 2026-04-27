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
 * Privacy provider for local_playergames.
 *
 * Phase 2: declares user preferences local_playergames_*_key (personal API keys).
 * Full export/deletion for staff_profile, streaks, daily_scores, bounce_scores,
 * mission_progress, user_achievements will be added in Phase 8.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\user_preference_provider;

/**
 * Privacy provider implementing user_preference_provider for API key preferences.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements user_preference_provider {
    /**
     * Returns metadata describing the user data stored by this plugin.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_user_preference(
            'local_playergames_gemini_key',
            'privacy:pref_gemini_key'
        );
        $collection->add_user_preference(
            'local_playergames_groq_key',
            'privacy:pref_groq_key'
        );
        $collection->add_user_preference(
            'local_playergames_openai_key',
            'privacy:pref_openai_key'
        );
        return $collection;
    }

    /**
     * Exports all user preferences for the given user.
     *
     * @param int $userid
     * @return void
     */
    public static function export_user_preferences(int $userid): void {
        $prefs = [
            'local_playergames_gemini_key' => 'privacy:pref_gemini_key',
            'local_playergames_groq_key'   => 'privacy:pref_groq_key',
            'local_playergames_openai_key' => 'privacy:pref_openai_key',
        ];

        foreach ($prefs as $prefname => $stringkey) {
            $value = get_user_preferences($prefname, null, $userid);
            if ($value !== null) {
                \core_privacy\local\request\writer::with_context(
                    \context_system::instance()
                )->export_user_preference(
                    'local_playergames',
                    $prefname,
                    // Export only the presence of a key, never its value.
                    get_string('privacy:pref_key_set', 'local_playergames'),
                    get_string($stringkey, 'local_playergames')
                );
            }
        }
    }
}
