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
 * English language strings for local_playergames.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
// phpcs:disable moodle.Files.LineLength
defined('MOODLE_INTERNAL') || die();

$string['apikey_clear'] = 'Clear saved key';
$string['apikey_cleared'] = 'API key cleared.';
$string['apikey_placeholder'] = 'Paste your API key here';
$string['apikey_saved'] = 'API key saved.';
$string['autorenew_seasons'] = 'Automatically open next season';
$string['autorenew_seasons_desc'] = 'When a season closes, the system automatically creates and activates the next one using the duration configured below. The new season inherits the XP caps from the closed season.';
$string['defaultseasonname'] = 'Season 1';
$string['event_achievement_earned'] = 'Achievement earned';
$string['event_cartridge_deleted'] = 'Concept cartridge deleted';
$string['event_cartridge_imported'] = 'Concept cartridge imported';
$string['event_game_completed'] = 'Daily game completed';
$string['event_level_reached'] = 'Staff level reached';
$string['event_season_closed'] = 'Gamification season closed';
$string['event_season_created'] = 'Gamification season created';
$string['event_streak_broken'] = 'Activity streak broken';
$string['event_streak_updated'] = 'Activity streak updated';
$string['local/playergames:managecartridges'] = 'Manage concept cartridges';
$string['local/playergames:manageownkeys'] = 'Manage own AI API keys';
$string['local/playergames:playgames'] = 'Play daily games';
$string['local/playergames:viewdashboard'] = 'View ecosystem dashboard';
$string['local/playergames:viewstaffhud'] = 'View staff gamification HUD';
$string['mykeys_heading'] = 'My personal AI API keys';
$string['mykeys_intro'] = 'Your personal keys take priority over the shared hub key. Leave a field blank to use the shared key.';
$string['mykeys_pagetitle'] = 'My AI API keys';
$string['pluginname'] = 'Player Games';
$string['privacy:metadata'] = 'The Player Games plugin stores personal AI API keys as user preferences and will store gamification data in a later phase.';
$string['privacy:pref_gemini_key'] = 'Personal Gemini API key stored as a user preference.';
$string['privacy:pref_groq_key'] = 'Personal Groq API key stored as a user preference.';
$string['privacy:pref_key_set'] = 'Key is set (value not exported for security).';
$string['privacy:pref_openai_key'] = 'Personal OpenAI API key stored as a user preference.';
$string['season_duration_months'] = 'Default season duration (months)';
$string['season_duration_months_desc'] = 'Number of months for automatically created or pre-filled seasons. Used when auto-renewal creates the next season and as the default end date in the season creation form.';
$string['season_setup_heading'] = 'Season settings';
$string['settings_apikeys_heading'] = 'AI API keys';
$string['settings_apikeys_heading_desc'] = 'Shared keys used by all Player plugins. Personal keys configured by each user always take priority.';
$string['settings_gemini_key'] = 'Gemini API key';
$string['settings_gemini_key_desc'] = 'Google Gemini API key shared across all Player plugins.';
$string['settings_groq_key'] = 'Groq API key';
$string['settings_groq_key_desc'] = 'Groq API key shared across all Player plugins.';
$string['settings_openai_baseurl'] = 'OpenAI-compatible base URL';
$string['settings_openai_baseurl_desc'] = 'Base URL for any OpenAI-compatible endpoint (e.g. local Ollama, Azure OpenAI).';
$string['settings_openai_key'] = 'OpenAI API key';
$string['settings_openai_key_desc'] = 'OpenAI (or compatible) API key shared across all Player plugins.';
$string['settings_openai_model'] = 'Model name';
$string['settings_openai_model_desc'] = 'Model used for AI-assisted features (e.g. gpt-4o-mini, gemma3, llama3).';
$string['task_assign_daily_games'] = 'Assign daily game concepts';
$string['task_close_expired_seasons'] = 'Close expired seasons';
$string['task_purge_old_scores'] = 'Purge old game scores';
$string['task_reset_daily_missions'] = 'Reset daily missions';
