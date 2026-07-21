# 🧪 Automated Tests

PlayerGames ships with a PHPUnit suite covering the gamification engine, the cartridge pipeline,
scheduled tasks, privacy and events. Every CI push runs the full matrix (Moodle 4.5 → 5.2,
PostgreSQL & MariaDB).

## PHPUnit — Unit & Integration Tests

| Test file | Cases | What is covered |
|-----------|------:|-----------------|
| `cartridge/importer_test.php` | 9 | Concept and quiz import; type inference; difficulty clamping; category dedup; schema-error paths |
| `cartridge/exporter_test.php` | 4 | Concept/quiz export structure and full import→export round-trip including root metadata |
| `cartridge/category_manager_test.php` | 7 | Category CRUD, incrementing sortorder, idempotent `ensure`, ownership guard, concept null-on-delete |
| `cartridge/quiz_generator_test.php` | 7 | Quiz response parsing, category/difficulty defaults, `save_standalone` persistence |
| `cartridge/ai_generator_test.php` | 5 | Concept response parser: wrapped/bare/fenced JSON, invalid and missing-concepts errors |
| `hub/xp_manager_test.php` | 7 | Level thresholds, per-game cap enforcement, uncapped mission award, level-up event |
| `hub/daily_play_manager_test.php` | 3 | Multiple plays split XP evenly; play beyond the daily quota rejected; last play trimmed to the exact cap |
| `hub/level_manager_test.php` | 8 | Ladder seeding, XP/title lookups, save renumber + zero floor, restore defaults, linear generation and bounds |
| `hub/checkin_manager_test.php` | 9 | Check-in insert + XP award, idempotency, season cap, optional streak advance, participant eligibility |
| `hub/served_questions_test.php` | 3 | Per-day "already shown" set grouped by source, idempotent scoping, invalid items ignored |
| `hub/streak_manager_test.php` | 9 | Streak start/continue/reset, freeze consumption, overnight break processing |
| `hub/season_manager_test.php` | 8 | Season lifecycle, active/upcoming resolution, exclusive activation, snapshot, `create_next` |
| `hub/mission_manager_test.php` | 7 | Mission sync, progress/completion with XP reward, daily and missed check-in resets |
| `hub/achievement_manager_test.php` | 6 | Achievement sync, granting (first game/level/all-games-day), idempotency |
| `hub/title_manager_test.php` | 2 | Level→title key clamping and translation |
| `observer_test.php` | 2 | `game_completed` drives streak/mission/achievement; records streak even without a season |
| `games/quiz_loader_test.php` | 10 | Cartridge source: completeness filter, session size, active-only, id filter, metadata, random draw, fresh-question exclusion and pool reuse |
| `games/season_game_config_test.php` | 7 | Source helpers; enabled-record lookup; per-season listing; default seeding and preservation |
| `task/assign_daily_games_test.php` | 3 | Per-game concept assignment, idempotency, no-cartridge case |
| `task/reset_daily_missions_test.php` | 1 | Daily mission reset + streak break orchestration |
| `task/close_expired_seasons_test.php` | 2 | Closes expired season; auto-renew creates and activates next |
| `task/purge_old_scores_test.php` | 2 | Retention-window purge; keep-within-window no-op |
| `privacy/provider_test.php` | 8 | Metadata, contexts, userlist, export, and the three deletion paths |
| `api_key_helper_test.php` | 5 | Personal/site key resolution, OpenAI defaults, `has_any_key` |
| `local/engagement_report_test.php` | 4 | Empty metrics, course counting, player-course detection, scope split |
| `ecosystem/plugin_registry_test.php` | 2 | Catalog structure and unique components |
| `ecosystem/plugin_status_test.php` | 1 | Installed status keyed by component; hub reported installed |
| `event/events_test.php` | 1 | All nine events trigger, are captured and render a description |
| **Total** | **142** | |

```bash
vendor/bin/phpunit --testsuite local_playergames
```
