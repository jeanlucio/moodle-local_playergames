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

/**
 * Builds cartridge concept prompts and generates content through the AI Hub.
 *
 * Key storage and provider transport live in local_aihub. This class keeps only
 * the cartridge domain logic (prompt building and concept parsing) and routes
 * generation through \local_aihub\ai when present, falling back to core_ai so a
 * site with core_ai configured works without the hub installed.
 */
class ai_generator {
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
        $result = $this->call_api('', $prompt, true, $topic);
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
        return $this->parse_concepts($result['data']);
    }

    /**
     * Sends a raw prompt to the AI provider chain and returns the result array.
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
     * Returns whether an AI source is currently available (hub keys or core_ai).
     *
     * @return bool
     */
    public function has_key(): bool {
        if (class_exists(\local_aihub\ai::class) && \local_aihub\ai::is_available()) {
            return true;
        }
        return $this->has_core_ai();
    }

    /**
     * Builds the structured prompt for concept generation.
     *
     * @param string $topic Subject area or theme.
     * @param string $language Target language name.
     * @param int $count Number of concepts to generate.
     * @param int $difficulty Average difficulty 1–5.
     * @param array $categorynames Optional category names the AI must use.
     * @param string $context Optional reference text to focus the AI.
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
     * Resolves an AI source and generates content.
     *
     * Routes to the AI Hub (local_aihub) first, which resolves personal then site
     * BYOK keys. Falls back to the Moodle core_ai subsystem when the hub returns no
     * result or is not installed.
     *
     * @param string $system System instruction (may be empty).
     * @param string $user User prompt text.
     * @param bool $jsonmode Whether to request structured JSON output.
     * @param string $description Short label of what is being generated, for the hub usage log.
     * @return array Result with keys: success (bool), data (string), message (string), provider (string).
     */
    protected function call_api(string $system, string $user, bool $jsonmode, string $description = ''): array {
        $lasterror = ['success' => false, 'message' => '', 'data' => '', 'provider' => ''];

        if (class_exists(\local_aihub\ai::class)) {
            $result = \local_aihub\ai::generate_text($system, $user, $jsonmode, 'local_playergames', $description);
            if (!empty($result['success'])) {
                return $result;
            }
            // Preserve a real failure (e.g. an invalid key) so it is not masked as "no source".
            if (!empty($result['message'])) {
                $lasterror = $result;
            }
        }

        if ($this->has_core_ai()) {
            $result = $this->call_core_ai($system, $user);
            if ($result['success'] || !empty($result['message'])) {
                return $result;
            }
        }

        return $lasterror;
    }

    /**
     * Returns true when the Moodle core_ai subsystem has a text-generation provider.
     *
     * @return bool
     */
    protected function has_core_ai(): bool {
        if (
            !class_exists(\core_ai\manager::class)
            || !class_exists(\core_ai\aiactions\generate_text::class)
        ) {
            return false;
        }

        try {
            $actionclass = \core_ai\aiactions\generate_text::class;
            $manager = $this->make_core_ai_manager();
            $providers = $manager->get_providers_for_actions([$actionclass], true);
            return !empty($providers[$actionclass]);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Instantiates core_ai manager through the Moodle dependency container.
     *
     * @return \core_ai\manager
     */
    private function make_core_ai_manager(): \core_ai\manager {
        return \core\di::get(\core_ai\manager::class);
    }

    /**
     * Generates text via the Moodle core_ai subsystem (institutional fallback).
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
