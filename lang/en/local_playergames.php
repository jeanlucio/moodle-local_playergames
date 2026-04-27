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
$string['cartridge_activate'] = 'Activate';
$string['cartridge_active'] = 'Active';
$string['cartridge_add_concept'] = 'Add concept';
$string['cartridge_ai_categories'] = 'Desired categories';
$string['cartridge_ai_categories_desc'] = 'e.g. Anatomy, Physiology, Pharmacology';
$string['cartridge_ai_categories_help'] = 'Comma-separated. Leave blank to let the AI decide.';
$string['cartridge_ai_difficulty'] = 'Average difficulty';
$string['cartridge_ai_difficulty_desc'] = '1 (very easy) to 5 (very hard). Used as the target average for generated concepts.';
$string['cartridge_ai_generate'] = 'Generate';
$string['cartridge_ai_heading'] = 'Generate concepts with AI';
$string['cartridge_ai_language'] = 'Language';
$string['cartridge_ai_nokey'] = 'No AI key is configured. Add a key in AI API Keys settings or in My AI keys.';
$string['cartridge_ai_quantity'] = 'Number of concepts';
$string['cartridge_ai_quantity_desc'] = 'How many concepts to generate (10–100).';
$string['cartridge_ai_topic'] = 'Topic';
$string['cartridge_ai_topic_desc'] = 'Describe the subject area or theme for the generated concepts.';
$string['cartridge_author'] = 'Author';
$string['cartridge_categories'] = 'Categories';
$string['cartridge_concept_day_bycategory'] = 'By category (one per day of week)';
$string['cartridge_concept_day_mode'] = 'Concept of the day selection';
$string['cartridge_concept_day_mode_desc'] = 'Random picks from all active cartridges, or one fixed category per day of the week.';
$string['cartridge_concept_day_random'] = 'Random';
$string['cartridge_concepts_count'] = 'Concepts';
$string['cartridge_confirm_delete'] = 'Delete this cartridge and all its concepts? This cannot be undone.';
$string['cartridge_created'] = 'Cartridge saved.';
$string['cartridge_deactivate'] = 'Deactivate';
$string['cartridge_delete'] = 'Delete';
$string['cartridge_deleted'] = 'Cartridge deleted.';
$string['cartridge_edit_concepts'] = 'Edit concepts';
$string['cartridge_export'] = 'Export JSON';
$string['cartridge_heading'] = 'Concept cartridges';
$string['cartridge_import_file'] = 'JSON file';
$string['cartridge_import_heading'] = 'Import cartridge from JSON';
$string['cartridge_import_success'] = 'Imported {$a->count} concepts. Categories: {$a->categories}. Language: {$a->language}.';
$string['cartridge_inactive'] = 'Inactive';
$string['cartridge_language'] = 'Language';
$string['cartridge_list_empty'] = 'No cartridges uploaded yet.';
$string['cartridge_name'] = 'Cartridge name';
$string['cartridge_new_create'] = 'Create cartridge';
$string['cartridge_new_heading'] = 'Or create a new empty cartridge';
$string['cartridge_pagetitle'] = 'Manage concept cartridges';
$string['cartridge_preview_heading'] = 'Review generated concepts';
$string['cartridge_preview_save'] = 'Save as new cartridge';
$string['cartridge_select_to_edit'] = 'Select a cartridge from the list above and click "Edit concepts" to manage its concepts.';
$string['cartridge_tab_ai'] = 'Generate with AI';
$string['cartridge_tab_editor'] = 'Manual editor';
$string['cartridge_tab_import'] = 'Import JSON';
$string['cartridge_timemodified'] = 'Modified';
$string['cartridge_uploaded'] = 'Created';
$string['cartridge_uploadedby'] = 'Uploaded by';
$string['category_add'] = 'Add category';
$string['category_confirm_delete'] = 'Delete this category? Concepts will become uncategorised.';
$string['category_delete'] = 'Delete category';
$string['category_deleted'] = 'Category deleted.';
$string['category_heading'] = 'Categories';
$string['category_list_empty'] = 'No categories yet. Add one below.';
$string['category_name'] = 'Category name';
$string['category_rename'] = 'Rename';
$string['category_saved'] = 'Category saved.';
$string['concept_category'] = 'Category';
$string['concept_definition'] = 'Definition';
$string['concept_delete'] = 'Delete concept';
$string['concept_deleted'] = 'Concept deleted.';
$string['concept_difficulty'] = 'Difficulty';
$string['concept_edit'] = 'Edit';
$string['concept_list_empty'] = 'No concepts in this cartridge yet. Add the first one below.';
$string['concept_save'] = 'Save';
$string['concept_saved'] = 'Concept saved.';
$string['concept_term'] = 'Term';
$string['defaultseasonname'] = 'Season 1';
$string['error_ai_request_failed'] = 'AI request failed: {$a}';
$string['error_cartridge_invalid_json'] = 'The uploaded file does not contain valid JSON.';
$string['error_cartridge_missing_field'] = 'Required field missing in cartridge: {$a}';
$string['error_cartridge_no_concepts'] = 'The cartridge contains no concepts.';
$string['error_cartridge_nofile'] = 'No file was uploaded.';
$string['error_cartridge_notfound'] = 'Cartridge not found.';
$string['error_cartridge_tooconcepts'] = 'The cartridge exceeds the maximum number of concepts ({$a}).';
$string['error_category_duplicate'] = 'A category with this name already exists in this cartridge.';
$string['error_category_empty_name'] = 'Category name cannot be empty.';
$string['error_category_notfound'] = 'Category not found.';
$string['error_concept_empty_definition'] = 'Concept definition cannot be empty.';
$string['error_concept_empty_term'] = 'Concept term cannot be empty.';
$string['error_concept_invalid_difficulty'] = 'Difficulty must be a number between 1 and 5.';
$string['error_no_ai_key'] = 'No AI key is configured. Add a key in the AI API Keys settings page.';
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
$string['settings_games_heading'] = 'Game settings';
$string['settings_games_heading_desc'] = 'Configure the daily games available to staff members.';
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
$string['settings_wordle_maxlen'] = 'Maximum term length for PlayerGuess';
$string['settings_wordle_maxlen_desc'] = 'Terms longer than this value are excluded from PlayerGuess draws. Default: 8.';
$string['settings_wordle_minlen'] = 'Minimum term length for PlayerGuess';
$string['settings_wordle_minlen_desc'] = 'Terms shorter than this value are excluded from PlayerGuess draws. Default: 4.';
$string['task_assign_daily_games'] = 'Assign daily game concepts';
$string['task_close_expired_seasons'] = 'Close expired seasons';
$string['task_purge_old_scores'] = 'Purge old game scores';
$string['task_reset_daily_missions'] = 'Reset daily missions';
