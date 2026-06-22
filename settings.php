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
 * Plugin settings for local_playergames.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add(
        'localplugins',
        new admin_category(
            'playergames',
            get_string('pluginname', 'local_playergames')
        )
    );

    $ADMIN->add(
        'playergames',
        new admin_category(
            'playergames_settings',
            get_string('admin_category_settings', 'local_playergames')
        )
    );

    $ADMIN->add(
        'playergames',
        new admin_category(
            'playergames_management',
            get_string('admin_category_management', 'local_playergames')
        )
    );

    // API Keys.
    $page = new admin_settingpage(
        'local_playergames_apikeys',
        get_string('settings_apikeys_heading', 'local_playergames')
    );

    $page->add(new admin_setting_heading(
        'local_playergames/apikeys_heading',
        get_string('settings_apikeys_heading', 'local_playergames'),
        get_string('settings_apikeys_heading_desc', 'local_playergames')
    ));

    $page->add(new admin_setting_configpasswordunmask(
        'local_playergames/gemini_key',
        get_string('settings_gemini_key', 'local_playergames'),
        get_string('settings_gemini_key_desc', 'local_playergames'),
        ''
    ));

    $page->add(new admin_setting_configpasswordunmask(
        'local_playergames/groq_key',
        get_string('settings_groq_key', 'local_playergames'),
        get_string('settings_groq_key_desc', 'local_playergames'),
        ''
    ));

    $page->add(new admin_setting_configpasswordunmask(
        'local_playergames/openai_key',
        get_string('settings_openai_key', 'local_playergames'),
        get_string('settings_openai_key_desc', 'local_playergames'),
        ''
    ));

    $page->add(new admin_setting_configtext(
        'local_playergames/openai_baseurl',
        get_string('settings_openai_baseurl', 'local_playergames'),
        get_string('settings_openai_baseurl_desc', 'local_playergames'),
        'https://api.openai.com/v1',
        PARAM_URL
    ));

    $page->add(new admin_setting_configtext(
        'local_playergames/openai_model',
        get_string('settings_openai_model', 'local_playergames'),
        get_string('settings_openai_model_desc', 'local_playergames'),
        'gpt-4o-mini',
        PARAM_TEXT
    ));

    $ADMIN->add('playergames_settings', $page);

    // Seasons (lifecycle only).
    $page2 = new admin_settingpage(
        'local_playergames_seasons',
        get_string('season_setup_heading', 'local_playergames')
    );

    $page2->add(new admin_setting_heading(
        'local_playergames/seasons_heading',
        get_string('season_setup_heading', 'local_playergames'),
        ''
    ));

    $page2->add(new admin_setting_configcheckbox(
        'local_playergames/autorenew_seasons',
        get_string('autorenew_seasons', 'local_playergames'),
        get_string('autorenew_seasons_desc', 'local_playergames'),
        0
    ));

    $page2->add(new admin_setting_configtext(
        'local_playergames/season_duration_months',
        get_string('season_duration_months', 'local_playergames'),
        get_string('season_duration_months_desc', 'local_playergames'),
        '6',
        PARAM_INT
    ));

    $participantoptions = [
        'students' => get_string('settings_participants_students', 'local_playergames'),
        'staff'    => get_string('settings_participants_staff', 'local_playergames'),
        'both'     => get_string('settings_participants_both', 'local_playergames'),
    ];
    $page2->add(new admin_setting_configselect(
        'local_playergames/allowed_participants',
        get_string('settings_allowed_participants', 'local_playergames'),
        get_string('settings_allowed_participants_desc', 'local_playergames'),
        'students',
        $participantoptions
    ));

    $page2->add(new admin_setting_configcheckbox(
        'local_playergames/gamificationdefault',
        get_string('settings_gamificationdefault', 'local_playergames'),
        get_string('settings_gamificationdefault_desc', 'local_playergames'),
        1
    ));

    $page2->add(new admin_setting_configtext(
        'local_playergames/seasons_keep',
        get_string('settings_seasons_keep', 'local_playergames'),
        get_string('settings_seasons_keep_desc', 'local_playergames'),
        '2',
        PARAM_INT
    ));

    $ADMIN->add('playergames_settings', $page2);

    // XP and rewards.
    $pagexp = new admin_settingpage(
        'local_playergames_xp',
        get_string('settings_xp_heading', 'local_playergames')
    );

    $pagexp->add(new admin_setting_heading(
        'local_playergames/xp_heading',
        get_string('settings_xp_heading', 'local_playergames'),
        get_string('settings_xp_heading_desc', 'local_playergames')
    ));

    foreach (['quiz', 'guess', 'fill', 'battle'] as $gametype) {
        $pagexp->add(new admin_setting_configtext(
            'local_playergames/xp_per_game_' . $gametype,
            get_string('settings_xp_per_game_' . $gametype, 'local_playergames'),
            get_string('settings_xp_per_game_' . $gametype . '_desc', 'local_playergames'),
            '25',
            PARAM_INT
        ));
        $pagexp->add(new admin_setting_configtext(
            'local_playergames/xp_games_' . $gametype,
            get_string('settings_xp_games_' . $gametype, 'local_playergames'),
            get_string('settings_xp_games_' . $gametype . '_desc', 'local_playergames'),
            '1',
            PARAM_INT
        ));
    }

    $pagexp->add(new admin_setting_configtext(
        'local_playergames/xp_checkin_daily',
        get_string('settings_xp_checkin_daily', 'local_playergames'),
        get_string('settings_xp_checkin_daily_desc', 'local_playergames'),
        '5',
        PARAM_INT
    ));

    $pagexp->add(new admin_setting_configtext(
        'local_playergames/xp_cap_checkin_season',
        get_string('settings_xp_cap_checkin_season', 'local_playergames'),
        get_string('settings_xp_cap_checkin_season_desc', 'local_playergames'),
        '150',
        PARAM_INT
    ));

    $ADMIN->add('playergames_settings', $pagexp);

    // Streak and freeze.
    $pagestreak = new admin_settingpage(
        'local_playergames_streak',
        get_string('settings_streak_heading', 'local_playergames')
    );

    $pagestreak->add(new admin_setting_heading(
        'local_playergames/streak_heading',
        get_string('settings_streak_heading', 'local_playergames'),
        get_string('settings_streak_heading_desc', 'local_playergames')
    ));

    $streaksources = [
        'games'         => get_string('settings_streak_source_games', 'local_playergames'),
        'games_checkin' => get_string('settings_streak_source_games_checkin', 'local_playergames'),
    ];
    $pagestreak->add(new admin_setting_configselect(
        'local_playergames/streak_activity_source',
        get_string('settings_streak_source', 'local_playergames'),
        get_string('settings_streak_source_desc', 'local_playergames'),
        'games',
        $streaksources
    ));

    $pagestreak->add(new admin_setting_configtext(
        'local_playergames/freeze_max',
        get_string('settings_freeze_max', 'local_playergames'),
        get_string('settings_freeze_max_desc', 'local_playergames'),
        '3',
        PARAM_INT
    ));

    $ADMIN->add('playergames_settings', $pagestreak);

    // Game rules (global gameplay rules; per-season content is set in season management).
    $page3 = new admin_settingpage(
        'local_playergames_games',
        get_string('settings_games_heading', 'local_playergames')
    );

    $page3->add(new admin_setting_heading(
        'local_playergames/games_heading',
        get_string('settings_games_heading', 'local_playergames'),
        get_string('settings_games_heading_desc', 'local_playergames')
    ));

    $page3->add(new admin_setting_configtext(
        'local_playergames/wordle_minlen',
        get_string('settings_wordle_minlen', 'local_playergames'),
        get_string('settings_wordle_minlen_desc', 'local_playergames'),
        '4',
        PARAM_INT
    ));

    $page3->add(new admin_setting_configtext(
        'local_playergames/wordle_maxlen',
        get_string('settings_wordle_maxlen', 'local_playergames'),
        get_string('settings_wordle_maxlen_desc', 'local_playergames'),
        '8',
        PARAM_INT
    ));

    $conceptdayoptions = [
        'random' => get_string('cartridge_concept_day_random', 'local_playergames'),
        'bycategory' => get_string('cartridge_concept_day_bycategory', 'local_playergames'),
    ];
    $page3->add(new admin_setting_configselect(
        'local_playergames/concept_day_mode',
        get_string('cartridge_concept_day_mode', 'local_playergames'),
        get_string('cartridge_concept_day_mode_desc', 'local_playergames'),
        'random',
        $conceptdayoptions
    ));

    $ADMIN->add('playergames_settings', $page3);

    // Season management.
    $ADMIN->add(
        'playergames_management',
        new admin_externalpage(
            'local_playergames_manage_seasons',
            get_string('season_manage_pagetitle', 'local_playergames'),
            new moodle_url('/local/playergames/manage_seasons.php'),
            'moodle/site:config'
        )
    );

    // Level ladder.
    $ADMIN->add(
        'playergames_management',
        new admin_externalpage(
            'local_playergames_manage_levels',
            get_string('levels_pagetitle', 'local_playergames'),
            new moodle_url('/local/playergames/manage_levels.php'),
            'moodle/site:config'
        )
    );

    // Concept cartridges.
    $ADMIN->add(
        'playergames_management',
        new admin_externalpage(
            'local_playergames_cartridge',
            get_string('cartridge_pagetitle', 'local_playergames'),
            new moodle_url('/local/playergames/cartridge.php'),
            'local/playergames:managecartridges'
        )
    );

    // Engagement meter, site-wide scope (every course).
    // This admin entry is intentionally distinct from the dashboard's "Engagement"
    // button: the page scope is driven by the entry point, not by role. This admin
    // link passes scope=all (all courses on the site, guarded by moodle/site:config),
    // while the dashboard/nav button uses the default scope=own (only the courses
    // the user teaches). Both views are deliberate, so — unlike the dashboard — this
    // entry must NOT be removed from the admin tree. See engagement_meter.php.
    $ADMIN->add(
        'playergames_management',
        new admin_externalpage(
            'local_playergames_engagementmeter',
            get_string('engmeter_pagetitle', 'local_playergames'),
            new moodle_url('/local/playergames/engagement_meter.php', ['scope' => 'all']),
            'moodle/site:config'
        )
    );

    // The ecosystem dashboard is reached from the plugin's own navigation
    // (the "Ecosystem" header button), so it is intentionally not added to
    // the admin tree to avoid a redundant entry.
}
