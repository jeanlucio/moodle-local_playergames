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
 * AI-powered concept cartridge generator.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\cartridge;

use local_playergames\api_key_helper;

/**
 * Generates AI content by prompting configured providers, level-first.
 *
 * Resolution: personal keys (any provider) → site keys (any provider) → Moodle
 * core_ai. Within a tier the provider order is Gemini → Groq → OpenAI-compatible.
 */
class ai_generator {
    /** @var int HTTP timeout in seconds for AI API calls. */
    const HTTP_TIMEOUT = 30;

    /**
     * Generates an array of raw concept arrays via the configured AI provider.
     *
     * @param string $topic Subject area or theme for the concepts.
     * @param string $language Target language for the generated content.
     * @param int $count Number of concepts to request (10–100).
     * @param int $difficulty Average difficulty target (1–5).
     * @param array $categorynames Optional list of category names the AI must use.
     * @param string $context Optional reference text or summary to focus the AI.
     * @return array Array of raw concept arrays with term, definition, category, difficulty.
     * @throws \moodle_exception If no AI key is available or the response cannot be parsed.
     */
    public function generate(
        string $topic,
        string $language,
        int $count,
        int $difficulty,
        array $categorynames = [],
        string $context = ''
    ): array {
        $prompt = $this->build_prompt($topic, $language, $count, $difficulty, $categorynames, $context);
        $result = $this->call_api('', $prompt, true);
        if (!$result['success']) {
            if (empty($result['message'])) {
                throw new \moodle_exception('error_no_ai_key', 'local_playergames');
            }
            throw new \moodle_exception(
                'error_ai_request_failed',
                'local_playergames',
                '',
                $result['message']
            );
        }
        $concepts = $this->parse_concepts($result['data']);
        $this->log_usage($result['provider'], $result['model'] ?? '', $topic, count($concepts));
        return $concepts;
    }

    /**
     * Sends a raw prompt to the configured AI provider and returns the result array.
     *
     * Allows external plugins to use the provider chain without coupling to the
     * concept-generation prompt format of {@see generate()}.
     *
     * @param string $prompt The full prompt text.
     * @return array Result with keys: success (bool), data (string), message (string), provider (string).
     */
    public function send(string $prompt): array {
        return $this->call_api('', $prompt, true);
    }

    /**
     * Generates free text from a system + user prompt pair via the provider chain.
     *
     * Stable generic entry point for plugins that build their own prompts (course
     * generation, etc.). Returns the raw provider text; the caller decodes it.
     *
     * @param string $system System instruction (role/rules); may be empty.
     * @param string $user User prompt text.
     * @param bool $jsonmode Request structured JSON output (default false for free text).
     * @return string The generated text.
     * @throws \moodle_exception If no AI source is available or the request fails.
     */
    public function generate_text(string $system, string $user, bool $jsonmode = false): string {
        $result = $this->call_api($system, $user, $jsonmode);
        if (!$result['success']) {
            if (empty($result['message'])) {
                throw new \moodle_exception('error_no_ai_key', 'local_playergames');
            }
            throw new \moodle_exception(
                'error_ai_request_failed',
                'local_playergames',
                '',
                $result['message']
            );
        }
        return (string) $result['data'];
    }

    /**
     * Returns whether at least one AI provider key is currently available.
     *
     * @return bool
     */
    public function has_key(): bool {
        return api_key_helper::has_any_key();
    }

    /**
     * Inserts a usage log entry after a successful generation.
     *
     * @param string $provider Provider display name (Gemini, Groq, OpenAI).
     * @param string $model Model identifier used.
     * @param string $topic Topic that was generated.
     * @param int $conceptcount Number of concepts returned.
     * @return void
     */
    protected function log_usage(string $provider, string $model, string $topic, int $conceptcount): void {
        global $DB, $USER;
        $record = new \stdClass();
        $record->userid = (int) $USER->id;
        $record->provider = $provider;
        $record->model = $model;
        $record->topic = $topic;
        $record->conceptcount = $conceptcount;
        $record->timecreated = time();
        $DB->insert_record('local_playergames_ai_log', $record, false);
    }

    /**
     * Builds the structured prompt for concept generation.
     *
     * @param string $topic Subject area.
     * @param string $language Target language name or code.
     * @param int $count Number of concepts to generate.
     * @param int $difficulty Average difficulty 1–5.
     * @param array $categorynames Optional list of category names to constrain the AI.
     * @param string $context Optional reference text or summary about the topic.
     * @return string The constructed prompt.
     */
    protected function build_prompt(
        string $topic,
        string $language,
        int $count,
        int $difficulty,
        array $categorynames = [],
        string $context = ''
    ): string {
        $langname = $language !== '' ? $language : 'English';
        $jsonexample = '{"concepts":[{"term":"...","definition":"...","category":"...","difficulty":1}]}';

        if (!empty($categorynames)) {
            $catlist = '"' . implode('", "', $categorynames) . '"';
            $categoryrule = "- category: MUST be exactly one of these values (verbatim): {$catlist}";
        } else {
            $categoryrule = '- category: broad subject-area label in ' . $langname
                . ' that identifies the field of knowledge.'
                . ' Use at most 3 distinct categories for the whole concept set.'
                . ' Do NOT use specific sub-topics of the current theme as category names.'
                . ' The category value MUST be written in ' . $langname . '.';
        }

        $rules = "- term: short word or phrase (max 6 words)\n"
            . "- definition: one clear sentence explaining the term\n"
            . $categoryrule . "\n"
            . "- difficulty: integer 1–5\n"
            . '- No markdown, no code fences, only raw JSON';

        $parts = [
            'You are a knowledgeable educator creating a vocabulary and concept study set.',
            "Generate exactly {$count} educational concepts about the topic: \"{$topic}\".",
            "Target average difficulty: {$difficulty} out of 5 (1 = very easy, 5 = very hard).",
            "Respond in language: {$langname}.",
        ];

        if ($context !== '') {
            $parts[] = 'The following reference text provides specific details about this topic.'
                . ' Use it to generate targeted, specific concepts rather than generic ones.'
                . ' Focus on the concrete terms, rules, and ideas mentioned in this text:';
            $parts[] = '---' . "\n" . $context . "\n" . '---';
        }

        $parts[] = 'IMPORTANT: Reply ONLY with a valid JSON object in this exact format, no extra text:';
        $parts[] = $jsonexample;
        $parts[] = "Rules:\n" . $rules;

        return implode("\n\n", $parts);
    }

    /**
     * Resolves a provider and generates content, level-first.
     *
     * Order: personal keys (any provider) → site keys (any provider) → Moodle
     * core_ai. Within a tier the provider order is Gemini → Groq → OpenAI. If a
     * provider call fails (network error, timeout, HTTP error), the next available
     * one is tried. The error from the last attempt is returned only when all
     * options are exhausted.
     *
     * @param string $system System instruction (may be empty).
     * @param string $user User prompt text.
     * @param bool $jsonmode Whether to request structured JSON output from providers.
     * @return array Result with keys: success (bool), data (string), message (string), provider (string).
     */
    protected function call_api(string $system, string $user, bool $jsonmode): array {
        $lasterror = ['success' => false, 'message' => ''];

        // Tier 1: personal keys (the user's own, opt-in).
        $result = $this->try_key_tier($system, $user, $jsonmode, true, $lasterror);
        if ($result !== null) {
            return $result;
        }

        // Tier 2: site keys (admin-wide).
        $result = $this->try_key_tier($system, $user, $jsonmode, false, $lasterror);
        if ($result !== null) {
            return $result;
        }

        // Tier 3: Moodle core_ai — institutional default at the bottom.
        if (api_key_helper::has_core_ai_provider()) {
            $result = $this->call_core_ai($system, $user);
            if ($result['success']) {
                return $result;
            }
            if ($result['message'] !== '') {
                $lasterror = $result;
            }
        }

        return $lasterror;
    }

    /**
     * Tries Gemini → Groq → OpenAI for a single key tier (personal or site).
     *
     * @param string $system System instruction (may be empty).
     * @param string $user User prompt text.
     * @param bool $jsonmode Whether to request structured JSON output.
     * @param bool $personal True for the personal-key tier, false for the site-key tier.
     * @param array $lasterror Updated in place with the last failing provider result.
     * @return array|null A successful result, or null when no provider in this tier succeeded.
     */
    private function try_key_tier(
        string $system,
        string $user,
        bool $jsonmode,
        bool $personal,
        array &$lasterror
    ): ?array {
        $key = function (string $provider) use ($personal): string {
            return $personal
                ? api_key_helper::get_personal_key($provider)
                : api_key_helper::get_site_key($provider);
        };

        $geminikey = $key(api_key_helper::PROVIDER_GEMINI);
        if ($geminikey !== '') {
            $result = $this->call_gemini($system, $user, $geminikey, $jsonmode);
            if ($result['success']) {
                return $result;
            }
            $lasterror = $result;
        }

        $groqkey = $key(api_key_helper::PROVIDER_GROQ);
        if ($groqkey !== '') {
            $result = $this->call_groq($system, $user, $groqkey, $jsonmode);
            if ($result['success']) {
                return $result;
            }
            $lasterror = $result;
        }

        $openaikey = $key(api_key_helper::PROVIDER_OPENAI);
        $openaiurl = api_key_helper::get_openai_baseurl();
        if ($openaikey !== '' && $this->is_safe_url($openaiurl)) {
            $model = api_key_helper::get_openai_model();
            $result = $this->call_openai_compatible($system, $user, $openaikey, $openaiurl, $model, $jsonmode);
            if ($result['success']) {
                return $result;
            }
            $lasterror = $result;
        }

        return null;
    }

    /**
     * Instantiates core_ai manager for the current Moodle version.
     *
     * Reflects on get_providers_for_actions — the method used in call_core_ai — so
     * the staticness check is consistent between availability detection and actual calls.
     *
     * @return \core_ai\manager
     */
    private function make_core_ai_manager(): \core_ai\manager {
        global $DB;
        $reflection = new \ReflectionMethod(\core_ai\manager::class, 'get_providers_for_actions');
        if ($reflection->isStatic()) {
            return new \core_ai\manager();
        }
        return new \core_ai\manager($DB);
    }

    /**
     * Generates text via the Moodle core_ai subsystem.
     *
     * core_ai's generate_text action has no separate system field, so the system
     * instruction is prepended to the user prompt. Falls back silently (empty
     * message) when no providers are configured.
     *
     * @param string $system System instruction (may be empty).
     * @param string $user User prompt text.
     * @return array Result with keys: success (bool), data (string), message (string), provider (string).
     */
    protected function call_core_ai(string $system, string $user): array {
        global $USER;

        try {
            $actionclass = \core_ai\aiactions\generate_text::class;
            $manager = $this->make_core_ai_manager();
            $providers = $manager->get_providers_for_actions([$actionclass], true);

            if (empty($providers[$actionclass])) {
                return ['success' => false, 'message' => ''];
            }

            $prompttext = $system !== '' ? ($system . "\n\n" . $user) : $user;
            $action = new \core_ai\aiactions\generate_text(
                contextid: \context_system::instance()->id,
                userid: (int) $USER->id,
                prompttext: $prompttext,
            );

            $response = $manager->process_action($action);

            if (!$response->get_success()) {
                return ['success' => false, 'message' => 'core_ai: provider returned failure'];
            }

            $data = $response->get_response_data();
            $content = (string) ($data['generatedcontent'] ?? '');

            if ($content === '') {
                return ['success' => false, 'message' => 'core_ai: empty response'];
            }

            return ['success' => true, 'data' => $content, 'provider' => 'Moodle AI', 'model' => ''];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'core_ai: ' . $e->getMessage()];
        }
    }

    /**
     * Calls the Gemini generative language API with JSON mode enabled.
     *
     * Uses responseMimeType=application/json to force structured output and
     * avoid truncated or wrapped responses for large concept counts.
     *
     * @param string $system System instruction (may be empty).
     * @param string $user User prompt text.
     * @param string $key Gemini API key.
     * @param bool $jsonmode Whether to force JSON output.
     * @return array HTTP result array.
     */
    protected function call_gemini(string $system, string $user, string $key, bool $jsonmode): array {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
            . 'gemini-flash-latest:generateContent';
        $data = ['contents' => [['parts' => [['text' => $user]]]]];
        if ($system !== '') {
            $data['systemInstruction'] = ['parts' => [['text' => $system]]];
        }
        if ($jsonmode) {
            $data['generationConfig'] = ['responseMimeType' => 'application/json'];
        }
        return $this->http_post(
            $url,
            json_encode($data),
            ['Content-Type: application/json', 'x-goog-api-key: ' . $key],
            'Gemini'
        ) + ['model' => 'gemini-flash-latest'];
    }

    /**
     * Calls the Groq inference API.
     *
     * @param string $system System instruction (may be empty).
     * @param string $user User prompt text.
     * @param string $key Groq API key.
     * @param bool $jsonmode Whether to force JSON output.
     * @return array HTTP result array.
     */
    protected function call_groq(string $system, string $user, string $key, bool $jsonmode): array {
        $url = 'https://api.groq.com/openai/v1/chat/completions';
        $data = [
            'model' => 'llama-3.3-70b-versatile',
            'messages' => $this->build_chat_messages($system, $user),
        ];
        if ($jsonmode) {
            $data['response_format'] = ['type' => 'json_object'];
        }
        return $this->http_post(
            $url,
            json_encode($data),
            ['Authorization: Bearer ' . $key, 'Content-Type: application/json'],
            'Groq'
        ) + ['model' => 'llama-3.3-70b-versatile'];
    }

    /**
     * Calls any OpenAI-compatible /chat/completions endpoint.
     *
     * @param string $system System instruction (may be empty).
     * @param string $user User prompt text.
     * @param string $key API key.
     * @param string $endpointurl Full URL to the chat completions endpoint.
     * @param string $model Model identifier (e.g. gpt-4o-mini).
     * @param bool $jsonmode Whether to force JSON output.
     * @return array HTTP result array.
     */
    protected function call_openai_compatible(
        string $system,
        string $user,
        string $key,
        string $endpointurl,
        string $model,
        bool $jsonmode
    ): array {
        $modelname = $model !== '' ? $model : 'gpt-4o-mini';
        $data = [
            'model' => $modelname,
            'messages' => $this->build_chat_messages($system, $user),
        ];
        if ($jsonmode) {
            $data['response_format'] = ['type' => 'json_object'];
        }
        return $this->http_post(
            $endpointurl,
            json_encode($data),
            ['Authorization: Bearer ' . $key, 'Content-Type: application/json'],
            'OpenAI'
        ) + ['model' => $modelname];
    }

    /**
     * Builds an OpenAI-style messages array with an optional system message.
     *
     * @param string $system System instruction (omitted when empty).
     * @param string $user User prompt text.
     * @return array The messages array.
     */
    private function build_chat_messages(string $system, string $user): array {
        $messages = [];
        if ($system !== '') {
            $messages[] = ['role' => 'system', 'content' => $system];
        }
        $messages[] = ['role' => 'user', 'content' => $user];
        return $messages;
    }

    /**
     * Returns true when the URL is safe to use as an AI endpoint.
     *
     * Enforces HTTPS and blocks loopback, link-local, and RFC-1918 private
     * addresses to prevent SSRF via admin-configured endpoints. Also resolves
     * A/AAAA DNS records to block DNS-rebinding attacks where a public domain
     * resolves to an internal IP.
     *
     * @param string $url The URL to validate.
     * @return bool True if safe; false otherwise.
     */
    private function is_safe_url(string $url): bool {
        $parsed = parse_url($url);
        if (!$parsed || ($parsed['scheme'] ?? '') !== 'https') {
            return false;
        }
        $host = $parsed['host'] ?? '';
        if (empty($host)) {
            return false;
        }
        if (in_array(strtolower($host), ['localhost', '127.0.0.1', '::1'], true)) {
            return false;
        }
        $ip = filter_var($host, FILTER_VALIDATE_IP);
        if ($ip !== false) {
            $ispublic = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
            if ($ispublic === false) {
                return false;
            }
        } else {
            $resolvedips = [];
            $arecords = dns_get_record($host, DNS_A);
            if (is_array($arecords)) {
                foreach ($arecords as $r) {
                    if (!empty($r['ip'])) {
                        $resolvedips[] = $r['ip'];
                    }
                }
            }
            $aaaarecords = dns_get_record($host, DNS_AAAA);
            if (is_array($aaaarecords)) {
                foreach ($aaaarecords as $r) {
                    if (!empty($r['ipv6'])) {
                        $resolvedips[] = $r['ipv6'];
                    }
                }
            }
            foreach ($resolvedips as $resolvedip) {
                $ispublic = filter_var(
                    $resolvedip,
                    FILTER_VALIDATE_IP,
                    FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
                );
                if ($ispublic === false) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Executes an HTTP POST using Moodle's curl wrapper.
     *
     * @param string $url Target URL.
     * @param string $payload JSON-encoded POST body.
     * @param array $headers Array of header strings.
     * @param string $source Display name of the AI provider (for error messages).
     * @return array Result with keys: success, data (on success), message (on failure), provider.
     */
    protected function http_post(
        string $url,
        string $payload,
        array $headers,
        string $source
    ): array {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $curl = new \curl();
        $curl->setHeader($headers);
        $response = $curl->post($url, $payload, ['timeout' => self::HTTP_TIMEOUT]);
        $info = $curl->get_info();
        $code = isset($info['http_code']) ? (int) $info['http_code'] : 0;

        if ($curl->get_errno()) {
            return ['success' => false, 'message' => $source . ': ' . $curl->error];
        }

        if ($code !== 200) {
            $decoded = json_decode($response, true);
            $extra = isset($decoded['error']['message'])
                ? $decoded['error']['message']
                : 'HTTP ' . $code;
            return ['success' => false, 'message' => $source . ': ' . $extra];
        }

        $decoded = json_decode($response, true);
        $content = $source === 'Gemini'
            ? ($decoded['candidates'][0]['content']['parts'][0]['text'] ?? '')
            : ($decoded['choices'][0]['message']['content'] ?? '');

        return ['success' => true, 'data' => $content, 'provider' => $source];
    }

    /**
     * Extracts and returns the concepts array from an AI text response.
     *
     * Strips markdown code fences if present, then decodes the JSON.
     *
     * @param string $responsetext Raw text returned by the AI provider.
     * @return array Array of raw concept arrays.
     * @throws \moodle_exception If the response cannot be parsed or contains no concepts.
     */
    protected function parse_concepts(string $responsetext): array {
        // Strip optional markdown code fences (triple backtick with optional json tag).
        $cleaned = preg_replace('/^\x60\x60\x60(?:json)?\s*/im', '', $responsetext);
        $cleaned = preg_replace('/\x60\x60\x60\s*$/m', '', $cleaned);
        $cleaned = trim($cleaned);

        $decoded = json_decode($cleaned, true);
        if ($decoded === null) {
            throw new \moodle_exception('error_cartridge_invalid_json', 'local_playergames');
        }

        if (isset($decoded['concepts']) && is_array($decoded['concepts'])) {
            return $decoded['concepts'];
        }

        // Handle providers that return a bare array instead of a wrapped object.
        if (is_array($decoded) && isset($decoded[0]['term'])) {
            return $decoded;
        }

        throw new \moodle_exception('error_cartridge_no_concepts', 'local_playergames');
    }
}
