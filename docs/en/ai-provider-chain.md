# 🤖 AI Provider Chain

PlayerGames no longer stores AI API keys or talks to providers directly. All key storage and
provider transport (Gemini, Groq, OpenAI-compatible) now live in a dedicated companion plugin,
**[local_aihub](https://github.com/jeanlucio/moodle-local_aihub)**. PlayerGames keeps only the
cartridge-specific parts: building the generation prompt (topic, language, concept count,
difficulty, categories) and parsing the AI's JSON response back into concepts. This keeps
PlayerGames free of key management while still supporting AI-assisted cartridge generation
wherever the Hub is installed.

## Resolution order

| Priority | Source | Notes |
|----------|--------|-------|
| 1 | **[local_aihub](https://github.com/jeanlucio/moodle-local_aihub)** | Tried first when installed. Resolves personal keys, then site-wide keys, across Gemini / Groq / OpenAI-compatible providers. |
| 2 | **Moodle `core_ai`** | Institutional fallback, used only if the Hub is not installed or returns no usable source. Uses whichever providers the admin configured in *Site administration → AI → AI providers*. |

A real provider failure (e.g. an invalid key configured in the Hub) is preserved and surfaced to
the user — it is never silently masked as "no AI source available".

> **Installing `local_aihub` is optional.** Without it, cartridge AI generation still works if
> the site has `core_ai` configured; PlayerGames only loses the BYOK personal-key option (each
> teacher bringing their own Gemini/Groq/OpenAI key) that the Hub provides.

## Integration API for other plugins

`cartridge\ai_generator` exposes a small, stable API that other plugins in the ecosystem can
call directly:

```php
use local_playergames\cartridge\ai_generator;

$gen = new ai_generator();
if ($gen->has_key()) {
    // Free-form prompt, routed through the same chain as cartridge generation.
    $text = $gen->generate_text('Optional system instruction', 'Your prompt here');
}
```

- `has_key(): bool` — `true` if either `local_aihub` has a usable key or `core_ai` has a
  configured provider.
- `generate_text(string $system, string $user, bool $jsonmode = false): string` — sends a free
  system+user prompt through the chain and returns the raw text response.
- `send(string $prompt): array` — lower-level variant returning the full result array
  (`success`, `data`, `message`, `provider`).
- `generate(string $topic, string $language, int $count, int $difficulty, array $categorynames = [], string $context = ''): array`
  — the structured cartridge-concept generator; use only when you need concept arrays in
  `{term, definition, category, difficulty}` format.

Every AI generation triggered from PlayerGames is tagged in the Hub's own usage log with the
component (`local_playergames`) and a short description of what was generated (e.g. the cartridge
topic), so site administrators can see AI usage per consuming plugin from the Hub's admin report.
