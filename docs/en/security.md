# 🔎 Third-party Service Disclosure

PlayerGames includes optional AI-powered features for content generation (cartridges) and
cartridge AI generation. These are entirely optional — all content can be created manually.

## Supported Providers

- **Google Gemini** — https://ai.google.dev/
- **Groq** — https://console.groq.com/
- **OpenAI-compatible APIs** — Any provider following the OpenAI API format (e.g. OpenRouter,
  self-hosted models via LM Studio, Ollama proxy, etc.)

These services operate under their own terms of service and privacy policies.

## Data Transmission

When the AI feature is used, user-entered prompts are transmitted to the selected provider for
processing. The plugin:
- Does not store prompts or AI responses
- Only stores the cartridge concepts created inside Moodle

No external communication occurs unless an AI feature is explicitly used.
