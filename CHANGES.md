# Changelog — local_playergames

All notable changes to this plugin are documented here.
Versions align with `$plugin->release` in `version.php`.

---

## 0.1.6 — 2026-06-20

- Fixed the `streak_broken` event: it now receives the `previousstreak` value its
  description renders, instead of `currentstreak` (which left the description with
  an undefined key). Surfaced while adding event tests.
- Broadened automated test coverage to the remaining units: quiz loader,
  scheduled tasks, season game config, API key helper, engagement report,
  ecosystem registry/status, the concept-generator parser and all events.

---

## 0.1.5 — 2026-06-20

- Completed the privacy provider. It now declares all personal-data tables
  (player profile, streaks, daily and battle scores, mission progress, earned
  achievements, AI request log and uploaded cartridges) and fully implements
  export and deletion: `get_contexts_for_userid`, `get_users_in_context`,
  `export_user_data`, `delete_data_for_user`, `delete_data_for_users` and
  `delete_data_for_all_users_in_context`. Shared cartridges are preserved on
  deletion with the uploader link anonymised. Adds the matching PHPUnit tests.

---

## 0.1.4 — 2026-06-20

- Quiz cartridges now carry category and difficulty per question, mirroring
  concept cartridges. A schema upgrade adds `categoryid` and `difficulty` to the
  question table. These fields are foundation for upcoming player plugins that
  will select and weight quiz questions by category and difficulty.
- AI quiz generation now accepts the same inputs as concept generation:
  additional context, desired categories and a target difficulty. The generated
  questions are tagged with a category and difficulty in the review screen.
- The manual quiz editor gained category management and a category/difficulty
  selector per question, identical to the concept editor.
- Quiz export and import now round-trip category and difficulty.
- Fixed a latent bug where AI quiz generation called the provider with the wrong
  argument count and would fail; the call now matches the concept generator.

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
- Export logic was moved into a dedicated `cartridge\exporter` class (internal
  refactor, no behaviour change). Exports now also include the cartridge author,
  so all root metadata survives an export/import round-trip.
- Added the first PHPUnit suite: cartridge import/export (with round-trip),
  category management, quiz response parsing, and the hub XP, streak and season
  logic.

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
