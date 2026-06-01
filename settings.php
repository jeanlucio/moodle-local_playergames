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
        'root',
        new admin_category(
            'playergames',
            get_string('pluginname', 'local_playergames')
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
        'https://api.openai.com/v1/chat/completions',
        PARAM_URL
    ));

    $page->add(new admin_setting_configtext(
        'local_playergames/openai_model',
        get_string('settings_openai_model', 'local_playergames'),
        get_string('settings_openai_model_desc', 'local_playergames'),
        'gpt-4o-mini',
        PARAM_TEXT
    ));

    $ADMIN->add('playergames', $page);

    // Seasons.
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

    $ADMIN->add('playergames', $page2);

    // Games.
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

    $ADMIN->add('playergames', $page3);

    // Engagement meter.
    $ADMIN->add(
        'playergames',
        new admin_externalpage(
            'local_playergames_engagementmeter',
            get_string('engmeter_pagetitle', 'local_playergames'),
            new moodle_url('/local/playergames/engagement_meter.php'),
            'local/playergames:viewengagementmeter'
        )
    );
}
