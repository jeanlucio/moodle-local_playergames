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
 * This hub owns the canonical key store. Resolution is level-first:
 * 1. Personal key  local_playergames_{provider}_key   (opt-in, per user)
 * 2. Site key      local_playergames / {provider}_key  (admin-wide)
 *
 * core_ai is consulted by the generator after both, as the institutional default.
 * Keys are pg-only here: each plugin reads its own store directly and this hub
 * separately, so there is no cross-plugin key bridging.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames;

/**
 * Resolves AI API keys for the hub, personal-first then site.
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
     * Returns the personal (per-user, opt-in) API key for a provider, or '' if unset.
     *
     * @param string $provider One of the PROVIDER_* constants.
     * @param int|null $userid Defaults to $USER->id.
     * @return string
     */
    public static function get_personal_key(string $provider, ?int $userid = null): string {
        global $USER;

        $userid = $userid ?? (int) $USER->id;
        return (string) get_user_preferences('local_playergames_' . $provider . '_key', '', $userid);
    }

    /**
     * Returns the site-wide (admin-configured) API key for a provider, or '' if unset.
     *
     * @param string $provider One of the PROVIDER_* constants.
     * @return string
     */
    public static function get_site_key(string $provider): string {
        return (string) get_config('local_playergames', $provider . '_key');
    }

    /**
     * Returns true when the user has at least one personal key set (any provider).
     *
     * @param int|null $userid Defaults to $USER->id.
     * @return bool
     */
    public static function has_personal_key(?int $userid = null): bool {
        $providers = [self::PROVIDER_GEMINI, self::PROVIDER_GROQ, self::PROVIDER_OPENAI];
        foreach ($providers as $provider) {
            if (self::get_personal_key($provider, $userid) !== '') {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns the resolved API key for a provider: personal first, then site.
     *
     * Returns an empty string when no key is configured. core_ai is handled
     * separately by the generator.
     *
     * @param string $provider One of the PROVIDER_* constants.
     * @param int|null $userid User whose personal key is checked first. Defaults to $USER->id.
     * @return string
     */
    public static function get_key(string $provider, ?int $userid = null): string {
        $personal = self::get_personal_key($provider, $userid);
        if ($personal !== '') {
            return $personal;
        }
        return self::get_site_key($provider);
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
     * Compatible with Moodle 4.5+ — the manager is retrieved via the dependency
     * container, which injects the dependencies for the running version.
     *
     * @return bool
     */
    public static function has_core_ai_provider(): bool {
        if (
            !class_exists(\core_ai\manager::class)
            || !class_exists(\core_ai\aiactions\generate_text::class)
        ) {
            return false;
        }

        try {
            $actionclass = \core_ai\aiactions\generate_text::class;
            $manager = \core\di::get(\core_ai\manager::class);
            $providers = $manager->get_providers_for_actions([$actionclass], true);
            return !empty($providers[$actionclass]);
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
