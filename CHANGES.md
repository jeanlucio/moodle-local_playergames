# Changelog — local_playergames

All notable changes to this plugin are documented here.
Versions align with `$plugin->release` in `version.php`.

---

## 0.1.1 — 2026-06-15

- AI provider resolution is now **level-first** and consistent with the rest of
  the ecosystem: personal keys (any provider) → site keys (any provider) →
  Moodle `core_ai`. `core_ai` moved from first to last, so an explicitly set
  personal or site key always wins over the institutional default.
- `api_key_helper` is now pg-pure: added `get_personal_key()`, `get_site_key()`
  and `has_personal_key()`, and removed the legacy `block_playerhud` key reads
  (each plugin reads its own store directly; the hub holds the shared keys).
- Added `cartridge\ai_generator::generate_text(string $system, string $user,
  bool $jsonmode = false): string` — a stable generic entry point for plugins
  that build their own prompts (e.g. course generation). The provider chain now
  threads a system instruction to Gemini (`systemInstruction`), Groq/OpenAI
  (`system` role) and `core_ai` (prepended). The structured `generate()` for
  concept cartridges is unchanged.

---

## 0.1.0 — 2026-06-01

- Initial alpha: PlayerGames hub (seasons, XP, achievements, missions, quiz
  cartridges) with AI concept generation and centralized API key management.
