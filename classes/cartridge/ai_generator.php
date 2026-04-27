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
 * Generates concept cartridge content by prompting configured AI providers.
 *
 * Provider priority: Gemini → Groq → OpenAI-compatible (first key found wins).
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
     * @return array Array of raw concept arrays with term, definition, category, difficulty.
     * @throws \moodle_exception If no AI key is available or the response cannot be parsed.
     */
    public function generate(
        string $topic,
        string $language,
        int $count,
        int $difficulty,
        array $categorynames = []
    ): array {
        $prompt = $this->build_prompt($topic, $language, $count, $difficulty, $categorynames);
        $result = $this->call_api($prompt);
        if (!$result['success']) {
            throw new \moodle_exception(
                'error_no_ai_key',
                'local_playergames',
                '',
                $result['message'] ?? ''
            );
        }
        return $this->parse_concepts($result['data']);
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
     * Builds the structured prompt for concept generation.
     *
     * @param string $topic Subject area.
     * @param string $language Target language name or code.
     * @param int $count Number of concepts to generate.
     * @param int $difficulty Average difficulty 1–5.
     * @param array $categorynames Optional list of category names to constrain the AI.
     * @return string The constructed prompt.
     */
    protected function build_prompt(
        string $topic,
        string $language,
        int $count,
        int $difficulty,
        array $categorynames = []
    ): string {
        $langname = $language !== '' ? $language : 'English';
        $jsonexample = '{"concepts":[{"term":"...","definition":"...","category":"...","difficulty":1}]}';

        if (!empty($categorynames)) {
            $catlist = '"' . implode('", "', $categorynames) . '"';
            $categoryrule = "- category: MUST be exactly one of these values (verbatim): {$catlist}";
        } else {
            $categoryrule = '- category: one or two words grouping similar terms';
        }

        $rules = "- term: short word or phrase (max 6 words)\n"
            . "- definition: one clear sentence explaining the term\n"
            . $categoryrule . "\n"
            . "- difficulty: integer 1–5\n"
            . '- No markdown, no code fences, only raw JSON';

        return implode("\n\n", [
            'You are a knowledgeable educator creating a vocabulary and concept study set.',
            "Generate exactly {$count} educational concepts about the topic: \"{$topic}\".",
            "Target average difficulty: {$difficulty} out of 5 (1 = very easy, 5 = very hard).",
            "Respond in language: {$langname}.",
            'IMPORTANT: Reply ONLY with a valid JSON object in this exact format, no extra text:',
            $jsonexample,
            "Rules:\n" . $rules,
        ]);
    }

    /**
     * Tries configured providers in priority order: Gemini → Groq → OpenAI-compatible.
     *
     * @param string $prompt The prompt text.
     * @return array Result with keys: success (bool), data (string), message (string), provider (string).
     */
    protected function call_api(string $prompt): array {
        $geminikey = api_key_helper::get_gemini_key();
        if ($geminikey !== '') {
            return $this->call_gemini($prompt, $geminikey);
        }

        $groqkey = api_key_helper::get_groq_key();
        if ($groqkey !== '') {
            return $this->call_groq($prompt, $groqkey);
        }

        $openaikey = api_key_helper::get_openai_key();
        if ($openaikey !== '') {
            $url = api_key_helper::get_openai_baseurl();
            $model = api_key_helper::get_openai_model();
            return $this->call_openai_compatible($prompt, $openaikey, $url, $model);
        }

        return ['success' => false, 'message' => ''];
    }

    /**
     * Calls the Gemini generative language API.
     *
     * @param string $prompt The prompt text.
     * @param string $key Gemini API key.
     * @return array HTTP result array.
     */
    protected function call_gemini(string $prompt, string $key): array {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
            . 'gemini-flash-latest:generateContent?key=' . urlencode($key);
        $data = ['contents' => [['parts' => [['text' => $prompt]]]]];
        return $this->http_post(
            $url,
            json_encode($data),
            ['Content-Type: application/json'],
            'Gemini'
        );
    }

    /**
     * Calls the Groq inference API.
     *
     * @param string $prompt The prompt text.
     * @param string $key Groq API key.
     * @return array HTTP result array.
     */
    protected function call_groq(string $prompt, string $key): array {
        $url = 'https://api.groq.com/openai/v1/chat/completions';
        $data = [
            'model' => 'llama-3.3-70b-versatile',
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'response_format' => ['type' => 'json_object'],
        ];
        return $this->http_post(
            $url,
            json_encode($data),
            ['Authorization: Bearer ' . $key, 'Content-Type: application/json'],
            'Groq'
        );
    }

    /**
     * Calls any OpenAI-compatible /chat/completions endpoint.
     *
     * @param string $prompt The prompt text.
     * @param string $key API key.
     * @param string $endpointurl Full URL to the chat completions endpoint.
     * @param string $model Model identifier (e.g. gpt-4o-mini).
     * @return array HTTP result array.
     */
    protected function call_openai_compatible(
        string $prompt,
        string $key,
        string $endpointurl,
        string $model
    ): array {
        $data = [
            'model' => $model !== '' ? $model : 'gpt-4o-mini',
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'response_format' => ['type' => 'json_object'],
        ];
        return $this->http_post(
            $endpointurl,
            json_encode($data),
            ['Authorization: Bearer ' . $key, 'Content-Type: application/json'],
            'OpenAI'
        );
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
