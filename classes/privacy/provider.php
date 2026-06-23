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
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\privacy;

use context;
use context_system;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider: gamification data lives in the system context.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\user_preference_provider {
    /** @var string[] Tables holding personal data keyed by a userid column. */
    private const USER_TABLES = [
        'local_playergames_player_profile',
        'local_playergames_streaks',
        'local_playergames_freeze_log',
        'local_playergames_daily_scores',
        'local_playergames_served_questions',
        'local_playergames_battle_scores',
        'local_playergames_mission_progress',
        'local_playergames_user_achievements',
        'local_playergames_ai_log',
    ];

    /**
     * Returns metadata describing the user data stored by this plugin.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection The updated collection.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_playergames_player_profile', [
            'userid'        => 'privacy:metadata:local_playergames_player_profile:userid',
            'seasonid'      => 'privacy:metadata:local_playergames_player_profile:seasonid',
            'xp'            => 'privacy:metadata:local_playergames_player_profile:xp',
            'level'         => 'privacy:metadata:local_playergames_player_profile:level',
            'showinranking' => 'privacy:metadata:local_playergames_player_profile:showinranking',
        ], 'privacy:metadata:local_playergames_player_profile');

        $collection->add_database_table('local_playergames_streaks', [
            'userid'           => 'privacy:metadata:local_playergames_streaks:userid',
            'currentstreak'    => 'privacy:metadata:local_playergames_streaks:currentstreak',
            'longeststreak'    => 'privacy:metadata:local_playergames_streaks:longeststreak',
            'freezesavailable' => 'privacy:metadata:local_playergames_streaks:freezesavailable',
            'lastactivedate'   => 'privacy:metadata:local_playergames_streaks:lastactivedate',
        ], 'privacy:metadata:local_playergames_streaks');

        $collection->add_database_table('local_playergames_freeze_log', [
            'userid'      => 'privacy:metadata:local_playergames_freeze_log:userid',
            'action'      => 'privacy:metadata:local_playergames_freeze_log:action',
            'amount'      => 'privacy:metadata:local_playergames_freeze_log:amount',
            'reason'      => 'privacy:metadata:local_playergames_freeze_log:reason',
            'timecreated' => 'privacy:metadata:local_playergames_freeze_log:timecreated',
        ], 'privacy:metadata:local_playergames_freeze_log');

        $collection->add_database_table('local_playergames_daily_scores', [
            'userid'    => 'privacy:metadata:local_playergames_daily_scores:userid',
            'gamedate'  => 'privacy:metadata:local_playergames_daily_scores:gamedate',
            'gametype'  => 'privacy:metadata:local_playergames_daily_scores:gametype',
            'completed' => 'privacy:metadata:local_playergames_daily_scores:completed',
            'xpawarded' => 'privacy:metadata:local_playergames_daily_scores:xpawarded',
            'attempts'  => 'privacy:metadata:local_playergames_daily_scores:attempts',
        ], 'privacy:metadata:local_playergames_daily_scores');

        $collection->add_database_table('local_playergames_served_questions', [
            'userid'      => 'privacy:metadata:local_playergames_served_questions:userid',
            'gamedate'    => 'privacy:metadata:local_playergames_served_questions:gamedate',
            'gametype'    => 'privacy:metadata:local_playergames_served_questions:gametype',
            'source'      => 'privacy:metadata:local_playergames_served_questions:source',
            'sourceid'    => 'privacy:metadata:local_playergames_served_questions:sourceid',
            'timecreated' => 'privacy:metadata:local_playergames_served_questions:timecreated',
        ], 'privacy:metadata:local_playergames_served_questions');

        $collection->add_database_table('local_playergames_battle_scores', [
            'userid'           => 'privacy:metadata:local_playergames_battle_scores:userid',
            'seasonid'         => 'privacy:metadata:local_playergames_battle_scores:seasonid',
            'gamedate'         => 'privacy:metadata:local_playergames_battle_scores:gamedate',
            'questionscorrect' => 'privacy:metadata:local_playergames_battle_scores:questionscorrect',
            'questionstotal'   => 'privacy:metadata:local_playergames_battle_scores:questionstotal',
            'victory'          => 'privacy:metadata:local_playergames_battle_scores:victory',
            'xpawarded'        => 'privacy:metadata:local_playergames_battle_scores:xpawarded',
        ], 'privacy:metadata:local_playergames_battle_scores');

        $collection->add_database_table('local_playergames_mission_progress', [
            'userid'        => 'privacy:metadata:local_playergames_mission_progress:userid',
            'missionid'     => 'privacy:metadata:local_playergames_mission_progress:missionid',
            'seasonid'      => 'privacy:metadata:local_playergames_mission_progress:seasonid',
            'currentvalue'  => 'privacy:metadata:local_playergames_mission_progress:currentvalue',
            'completed'     => 'privacy:metadata:local_playergames_mission_progress:completed',
            'timecompleted' => 'privacy:metadata:local_playergames_mission_progress:timecompleted',
        ], 'privacy:metadata:local_playergames_mission_progress');

        $collection->add_database_table('local_playergames_user_achievements', [
            'userid'        => 'privacy:metadata:local_playergames_user_achievements:userid',
            'achievementid' => 'privacy:metadata:local_playergames_user_achievements:achievementid',
            'timecreated'   => 'privacy:metadata:local_playergames_user_achievements:timecreated',
        ], 'privacy:metadata:local_playergames_user_achievements');

        $collection->add_database_table('local_playergames_ai_log', [
            'userid'       => 'privacy:metadata:local_playergames_ai_log:userid',
            'provider'     => 'privacy:metadata:local_playergames_ai_log:provider',
            'model'        => 'privacy:metadata:local_playergames_ai_log:model',
            'topic'        => 'privacy:metadata:local_playergames_ai_log:topic',
            'conceptcount' => 'privacy:metadata:local_playergames_ai_log:conceptcount',
            'timecreated'  => 'privacy:metadata:local_playergames_ai_log:timecreated',
        ], 'privacy:metadata:local_playergames_ai_log');

        $collection->add_database_table('local_playergames_cartridges', [
            'uploadedby' => 'privacy:metadata:local_playergames_cartridges:uploadedby',
        ], 'privacy:metadata:local_playergames_cartridges');

        $collection->add_user_preference(
            'local_playergames_gamification',
            'privacy:pref_gamification'
        );
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
        $collection->add_user_preference(
            'local_playergames_openai_url',
            'privacy:pref_openai_url'
        );
        $collection->add_user_preference(
            'local_playergames_openai_model',
            'privacy:pref_openai_model'
        );
        $collection->add_user_preference(
            'local_playergames_quizcooldown',
            'privacy:pref_quizcooldown'
        );

        $aifields = ['content' => 'privacy:external_ai_content'];
        $collection->add_external_location_link('google_gemini', $aifields, 'privacy:external_gemini');
        $collection->add_external_location_link('groq', $aifields, 'privacy:external_groq');
        $collection->add_external_location_link('openai', $aifields, 'privacy:external_openai');

        return $collection;
    }

    /**
     * Returns the system context if the user has any stored data.
     *
     * @param int $userid The user to search for.
     * @return contextlist The list of contexts containing the user's data.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $contextlist = new contextlist();

        $hasdata = false;
        foreach (self::USER_TABLES as $table) {
            if ($DB->record_exists($table, ['userid' => $userid])) {
                $hasdata = true;
                break;
            }
        }
        if (!$hasdata) {
            $hasdata = $DB->record_exists('local_playergames_cartridges', ['uploadedby' => $userid]);
        }

        if ($hasdata) {
            $contextlist->add_system_context();
        }
        return $contextlist;
    }

    /**
     * Returns the users who have data within the given context.
     *
     * @param userlist $userlist The userlist to populate.
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        if (!$userlist->get_context() instanceof context_system) {
            return;
        }
        foreach (self::USER_TABLES as $table) {
            $userlist->add_from_sql('userid', "SELECT userid FROM {{$table}}", []);
        }
        $userlist->add_from_sql(
            'uploadedby',
            "SELECT uploadedby FROM {local_playergames_cartridges}",
            []
        );
    }

    /**
     * Exports all stored personal data for the approved contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        if (count($contextlist) === 0) {
            return;
        }
        $userid = $contextlist->get_user()->id;

        $hassystem = false;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof context_system) {
                $hassystem = true;
                break;
            }
        }
        if (!$hassystem) {
            return;
        }

        $context = context_system::instance();
        $writer  = writer::with_context($context);
        $root    = [get_string('pluginname', 'local_playergames')];

        foreach (self::USER_TABLES as $table) {
            $rows = $DB->get_records($table, ['userid' => $userid], 'id ASC');
            if (empty($rows)) {
                continue;
            }
            $data = array_values(array_map(fn($r): array => (array) $r, $rows));
            $writer->export_data(
                array_merge($root, [get_string('privacy:metadata:' . $table, 'local_playergames')]),
                (object) ['rows' => $data]
            );
        }

        $cartridges = $DB->get_records(
            'local_playergames_cartridges',
            ['uploadedby' => $userid],
            'id ASC',
            'id, name, version, language, timecreated'
        );
        if (!empty($cartridges)) {
            $data = array_values(array_map(fn($r): array => (array) $r, $cartridges));
            $writer->export_data(
                array_merge($root, [
                    get_string('privacy:metadata:local_playergames_cartridges', 'local_playergames'),
                ]),
                (object) ['rows' => $data]
            );
        }
    }

    /**
     * Deletes all personal data for all users in the given context.
     *
     * @param context $context The context to delete data within.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;
        if (!$context instanceof context_system) {
            return;
        }
        foreach (self::USER_TABLES as $table) {
            $DB->delete_records($table);
        }
        // Cartridges are shared content: keep them but drop the uploader link.
        $DB->set_field('local_playergames_cartridges', 'uploadedby', 0, []);
    }

    /**
     * Deletes all personal data for the user in the approved contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $hassystem = false;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof context_system) {
                $hassystem = true;
                break;
            }
        }
        if (!$hassystem) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        foreach (self::USER_TABLES as $table) {
            $DB->delete_records($table, ['userid' => $userid]);
        }
        $DB->set_field('local_playergames_cartridges', 'uploadedby', 0, ['uploadedby' => $userid]);
    }

    /**
     * Deletes personal data for the approved users within the given context.
     *
     * @param approved_userlist $userlist The approved users and context.
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        if (!$userlist->get_context() instanceof context_system) {
            return;
        }
        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        foreach (self::USER_TABLES as $table) {
            $DB->delete_records_select($table, "userid {$insql}", $params);
        }
        $DB->set_field_select(
            'local_playergames_cartridges',
            'uploadedby',
            0,
            "uploadedby {$insql}",
            $params
        );
    }

    /**
     * Exports all user preferences for the given user.
     *
     * @param int $userid The user whose preferences are exported.
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
                writer::with_context(
                    context_system::instance()
                )->export_user_preference(
                    'local_playergames',
                    $prefname,
                    // Export only the presence of a key, never its value.
                    get_string('privacy:pref_key_set', 'local_playergames'),
                    get_string($stringkey, 'local_playergames')
                );
            }
        }

        // The endpoint URL and model are not sensitive, so export their values.
        $plainprefs = [
            'local_playergames_openai_url'   => 'privacy:pref_openai_url',
            'local_playergames_openai_model' => 'privacy:pref_openai_model',
            'local_playergames_gamification' => 'privacy:pref_gamification',
            'local_playergames_quizcooldown' => 'privacy:pref_quizcooldown',
        ];
        foreach ($plainprefs as $prefname => $stringkey) {
            $value = get_user_preferences($prefname, null, $userid);
            if ($value !== null) {
                writer::with_context(
                    context_system::instance()
                )->export_user_preference(
                    'local_playergames',
                    $prefname,
                    $value,
                    get_string($stringkey, 'local_playergames')
                );
            }
        }
    }
}
