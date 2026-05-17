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
 * AI API key helper for local_playergames.
 *
 * Resolution chain (highest priority first):
 * 1. User preference  local_playergames_{provider}_key  (opt-in personal key)
 * 2. Hub site config  local_playergames / {provider}_key
 * 3. Legacy block config  block_playerhud / {provider}_key  (backward compat)
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames;

/**
 * Resolves AI API keys following the three-level fallback chain.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api_key_helper {
    /** @var string Gemini provider identifier. */
    const PROVIDER_GEMINI = 'gemini';

    /** @var string Groq provider identifier. */
    const PROVIDER_GROQ = 'groq';

    /** @var string OpenAI-compatible provider identifier. */
    const PROVIDER_OPENAI = 'openai';

    /**
     * Returns the resolved API key for the given provider.
     *
     * Returns an empty string when no key is configured at any level.
     *
     * @param string $provider One of the PROVIDER_* constants.
     * @param int|null $userid User whose personal key is checked first. Defaults to $USER->id.
     * @return string
     */
    public static function get_key(string $provider, ?int $userid = null): string {
        global $USER;

        $userid = $userid ?? (int) $USER->id;

        // Level 1: user personal preference (opt-in via mykeys.php).
        $prefname = 'local_playergames_' . $provider . '_key';
        $personal = get_user_preferences($prefname, '', $userid);
        if ($personal !== '') {
            return $personal;
        }

        // Level 2: hub site config.
        $hub = (string) get_config('local_playergames', $provider . '_key');
        if ($hub !== '') {
            return $hub;
        }

        // Level 3: legacy block_playerhud site config.
        // block_playerhud uses 'apikey_{provider}' (e.g. apikey_gemini), not '{provider}_key'.
        $legacy = (string) get_config('block_playerhud', 'apikey_' . $provider);
        return $legacy;
    }

    /**
     * Convenience wrapper for the Gemini key.
     *
     * @param int|null $userid
     * @return string
     */
    public static function get_gemini_key(?int $userid = null): string {
        return self::get_key(self::PROVIDER_GEMINI, $userid);
    }

    /**
     * Convenience wrapper for the Groq key.
     *
     * @param int|null $userid
     * @return string
     */
    public static function get_groq_key(?int $userid = null): string {
        return self::get_key(self::PROVIDER_GROQ, $userid);
    }

    /**
     * Convenience wrapper for the OpenAI key.
     *
     * @param int|null $userid
     * @return string
     */
    public static function get_openai_key(?int $userid = null): string {
        return self::get_key(self::PROVIDER_OPENAI, $userid);
    }

    /**
     * Returns the configured OpenAI-compatible base URL.
     *
     * @return string
     */
    public static function get_openai_baseurl(): string {
        $url = (string) get_config('local_playergames', 'openai_baseurl');
        if ($url === '') {
            return 'https://api.openai.com/v1/chat/completions';
        }
        return $url;
    }

    /**
     * Returns the configured OpenAI-compatible model name.
     *
     * @return string
     */
    public static function get_openai_model(): string {
        $model = (string) get_config('local_playergames', 'openai_model');
        if ($model === '') {
            return 'gpt-4o-mini';
        }
        return $model;
    }

    /**
     * Saves a personal API key as a user preference.
     *
     * Pass an empty string to remove a previously saved key.
     *
     * @param string $provider One of the PROVIDER_* constants.
     * @param string $key The API key value (empty to clear).
     * @param int|null $userid Defaults to $USER->id.
     * @return void
     */
    public static function save_user_key(string $provider, string $key, ?int $userid = null): void {
        global $USER;

        $userid = $userid ?? (int) $USER->id;
        $prefname = 'local_playergames_' . $provider . '_key';

        if ($key === '') {
            unset_user_preference($prefname, $userid);
        } else {
            set_user_preference($prefname, $key, $userid);
        }
    }

    /**
     * Returns true when the Moodle core_ai subsystem has at least one provider
     * configured and enabled for text generation.
     *
     * Compatible with Moodle 4.5 (static API) and 5.x (instance API with DB injection).
     *
     * @return bool
     */
    public static function has_core_ai_provider(): bool {
        global $DB;

        if (
            !class_exists(\core_ai\manager::class)
            || !class_exists(\core_ai\aiactions\generate_text::class)
        ) {
            return false;
        }

        try {
            $reflection = new \ReflectionMethod(\core_ai\manager::class, 'is_action_available');
            $actionclass = \core_ai\aiactions\generate_text::class;
            if ($reflection->isStatic()) {
                return \core_ai\manager::is_action_available($actionclass);
            }
            return (new \core_ai\manager($DB))->is_action_available($actionclass);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Returns true when at least one AI source is available: the Moodle core_ai
     * subsystem or a personal/site API key for any configured provider.
     *
     * @param int|null $userid
     * @return bool
     */
    public static function has_any_key(?int $userid = null): bool {
        if (self::has_core_ai_provider()) {
            return true;
        }
        $providers = [self::PROVIDER_GEMINI, self::PROVIDER_GROQ, self::PROVIDER_OPENAI];
        foreach ($providers as $provider) {
            if (self::get_key($provider, $userid) !== '') {
                return true;
            }
        }
        return false;
    }
}
