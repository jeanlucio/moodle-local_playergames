# 🤖 AI Provider Chain

PlayerGames resolves an AI provider **level-first**: an explicitly configured key always wins
over the institutional default. The first tier with a usable key is used, and a failing call
(network error, timeout, HTTP error) automatically falls through to the next available option.

**Resolution order:**

| Priority | Source | Notes |
|----------|--------|-------|
| 1 | **Personal keys** (*My AI Keys* page) | The teacher's own Gemini / Groq / OpenAI-compatible keys, including a personal OpenAI-compatible base URL and model. Tried first, so a personal key always wins. |
| 2 | **Site-wide keys** (PlayerGames settings) | Keys the admin configured for the whole site. |
| 3 | **Moodle `core_ai`** | Institutional default, tried **last**. Uses whichever AI providers the admin configured in *Site administration → AI → AI providers*; on Moodle 5.2+, `core_ai` has its own internal fallback between providers. No API key needed in PlayerGames. |

Within tiers 1 and 2, the direct providers are tried in a fixed order: **Gemini → Groq →
OpenAI-compatible**. Each direct call enforces JSON output mode where supported.

> **Origin beats provider.** If a personal Groq key exists (tier 1) and the admin also configured
> `core_ai` (tier 3), the personal Groq key is used because tier 1 is tried first — the explicit
> key always wins over the institutional default.

## Integration API for other plugins

Other plugins in the Player ecosystem can delegate AI calls to PlayerGames without managing keys
themselves:

```php
use local_playergames\cartridge\ai_generator;
use local_playergames\api_key_helper;

// Check availability before showing any AI UI
if (class_exists(ai_generator::class) && api_key_helper::has_any_key()) {
    $gen = new ai_generator();
    $result = $gen->send('Your custom prompt here');
    // $result['success'] (bool), $result['data'] (string), $result['provider'] (string)
}
```

- `api_key_helper::has_any_key()` — returns `true` if at least one provider is configured.
- `ai_generator::send(string $prompt): array` — sends a raw prompt through the full provider
  chain and returns the raw text response. Use this when the calling plugin has its own prompt
  format and response parser.
- `ai_generator::generate(...)` — use only when you need concept arrays in
  `{term, definition, category, difficulty}` format.
