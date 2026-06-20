# Changelog — local_playergames

All notable changes to this plugin are documented here.
Versions align with `$plugin->release` in `version.php`.

---

## 0.1.3 — 2026-06-19

- Reorganised the cartridge management page into two levels. The top tab bar now
  lists only ways to obtain a cartridge — Library, Import JSON, Generate with AI
  and the renamed **Create cartridge** tab. Editing a specific cartridge is no
  longer a tab: it opens a dedicated editor screen with a breadcrumb and the tab
  bar hidden, so the active tab no longer mislabels AI-generated or imported
  cartridges as "manual".
- The Create cartridge tab now offers a **type selector** (Concepts or Quiz).
  Manually created quiz cartridges open straight into the question editor, which
  was previously unreachable except for AI-generated quizzes.
- The concept and quiz editors now share a consistent header (breadcrumb, type
  badge and Export action).
- Exporting a quiz cartridge now serialises its questions, correct answers and
  distractors (with a `"type"` field), instead of producing an empty concept
  payload. Concept exports also carry the `"type"` field now.
- The JSON importer now accepts quiz cartridges as well as concept cartridges,
  detected by the `"type"` field (or inferred from a `questions` array). Quiz and
  concept exports are therefore round-trippable.

---

## 0.1.2 — 2026-06-15

- The Moodle `core_ai` manager is now retrieved through the dependency container
  (`\core\di::get(\core_ai\manager::class)`), the documented retrieval pattern,
  instead of a reflection-based constructor shim. Behaviour is unchanged; the
  code now matches the official AI usage example.

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
