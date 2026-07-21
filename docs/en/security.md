# 🔎 Third-party Service Disclosure

PlayerGames includes optional AI-assisted cartridge generation. This is entirely optional — all
content can be created manually.

## Architecture

AI generation is routed through the companion
**[local_aihub](https://github.com/jeanlucio/moodle-local_aihub)** plugin when installed, which
owns the key storage and the provider transport; if the Hub is not installed, PlayerGames falls
back to Moodle's own `core_ai` subsystem, whichever providers the site administrator configured
there.

## Supported Providers (via local_aihub)

- **Google Gemini** — https://ai.google.dev/
- **Groq** — https://console.groq.com/
- **DeepSeek** — https://platform.deepseek.com/
- **OpenAI-compatible APIs** — Any provider following the OpenAI API format (e.g. OpenRouter,
  self-hosted models via LM Studio, Ollama proxy, etc.)

These services operate under their own terms of service and privacy policies. Full disclosure of
exact model IDs and data destinations is documented in
[local_aihub's own Third-party Service Disclosure](https://jeanlucio.github.io/moodle-local_aihub/#third-party-disclosure).

## Data Transmission

When AI generation is used, the prompt built by PlayerGames (topic, language, difficulty,
categories, and any optional reference text the teacher provides) is passed to whichever source
resolved the request (the Hub or `core_ai`) for processing. PlayerGames:
- Does not store prompts or AI responses beyond the cartridge concepts created inside Moodle
- Never includes student data in an AI prompt

No external communication occurs unless an AI feature is explicitly used.

## Cost

None required by PlayerGames itself. If `local_aihub` is installed, any cost is whatever the
underlying provider charges through that plugin's own BYOK keys; without it, PlayerGames falls
back to Moodle's `core_ai`, which may be free if the site admin has configured a no-cost
institutional provider.

## Demo Credentials

Not applicable — no credentials are required to install or use PlayerGames; AI-assisted
cartridge generation is entirely opt-in.
