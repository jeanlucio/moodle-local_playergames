# 🧪 Automated Tests

PlayerGames ships with a PHPUnit suite covering the gamification engine, the cartridge pipeline,
scheduled tasks, privacy and events. Every CI push runs the full matrix (Moodle 4.5 → 5.2,
PostgreSQL & MariaDB).

## PHPUnit — Unit & Integration Tests

| Test file | Cases | What is covered |
|-----------|------:|-----------------|
| `cartridge/importer_test.php` | 10 | Concept and quiz import; type inference; difficulty clamping; category dedup; schema-error paths |
| `cartridge/exporter_test.php` | 5 | Concept/quiz export structure and full import→export round-trip including root metadata |
| `cartridge/category_manager_test.php` | 7 | Category CRUD, incrementing sortorder, idempotent `ensure`, ownership guard, concept null-on-delete |
| `cartridge/quiz_generator_test.php` | 10 | Quiz response parsing, category/difficulty defaults, `save_standalone` persistence |
| `cartridge/ai_generator_test.php` | 5 | Concept response parser: wrapped/bare/fenced JSON, invalid and missing-concepts errors; delegation to the AI Hub facade with a `core_ai` fallback |
| `hub/xp_manager_test.php` | 17 | Level thresholds, per-game cap enforcement, uncapped mission award, level-up event, season ranking position/tie-break |
| `hub/avatar_manager_test.php` | 10 | Default catalog seeding, tier thresholds, lifetime best-level tracking, unlock/equip rules, locked-equip rejection |
| `hub/learning_xp_manager_test.php` | 22 | Windowed monthly buckets, visibility gating (independent of staff status), ranking opt-in and position, recompute correctness |
| `hub/daily_play_manager_test.php` | 3 | Multiple plays split XP evenly; play beyond the daily quota rejected; last play trimmed to the exact cap |
| `hub/level_manager_test.php` | 8 | Ladder seeding, XP/title lookups, save renumber + zero floor, restore defaults, linear generation and bounds |
| `hub/checkin_manager_test.php` | 11 | Check-in insert + XP award, idempotency, season cap, optional streak advance, participant eligibility |
| `hub/served_questions_test.php` | 3 | Per-day "already shown" set grouped by source, idempotent scoping, invalid items ignored |
| `hub/streak_manager_test.php` | 12 | Streak start/continue/reset, freeze consumption, overnight break processing |
| `hub/season_manager_test.php` | 8 | Season lifecycle, active/upcoming resolution, exclusive activation, snapshot, `create_next` |
| `hub/mission_manager_test.php` | 7 | Mission sync, progress/completion with XP reward, daily and missed check-in resets |
| `hub/achievement_manager_test.php` | 6 | Achievement sync, granting (first game/level/all-games-day), idempotency |
| `hub/title_manager_test.php` | 2 | Level→title key clamping and translation |
| `observer_test.php` | 5 | `game_completed` drives streak/mission/achievement; records streak even without a season; mirrors `block_playerhud` XP changes into Learning XP |
| `games/quiz_loader_test.php` | 12 | Cartridge source: completeness filter, session size, active-only, id filter, metadata, random draw, fresh-question exclusion and pool reuse |
| `games/quiz_settings_test.php` | 8 | Timer, max-attempts and cooldown configuration and their interaction |
| `games/guess_manager_test.php` | 7 | Daily concept resolution, term normalisation, Wordle-style letter feedback (including duplicate letters), guess validation |
| `games/fill_manager_test.php` | 10 | Word count/max-attempts config clamping, shared letter→slot assignment (including disjoint terms), tile reveal state, cross-reveal cascade, per-day term ordering and filtering, POST response payload reveal rules |
| `games/season_game_config_test.php` | 7 | Source helpers; enabled-record lookup; per-season listing; default seeding and preservation |
| `external/set_avatar_test.php` | 3 | Equip/unequip via the AJAX endpoint, rejecting a locked avatar |
| `external/set_ranking_visibility_test.php` | 2 | Season ranking opt-in/opt-out toggle |
| `external/set_learning_ranking_visibility_test.php` | 2 | Learning XP ranking opt-in/opt-out toggle |
| `task/assign_daily_games_test.php` | 8 | Per-game concept assignment, PlayerFill's multi-concept assignment and pool-too-small skip, idempotency, no-cartridge and empty-cartridge cases, PlayerGuess eligibility filter, task name |
| `task/reset_daily_missions_test.php` | 1 | Daily mission reset + streak break orchestration |
| `task/close_expired_seasons_test.php` | 2 | Closes expired season; auto-renew creates and activates next |
| `task/purge_old_scores_test.php` | 2 | Retention-window purge; keep-within-window no-op |
| `task/recompute_learning_xp_test.php` | 2 | Nightly window recompute prunes aged-out buckets and corrects cache drift |
| `privacy/provider_test.php` | 8 | Metadata, contexts, userlist, export, and the three deletion paths |
| `local/access_test.php` | 13 | Staff detection (site admin, course-editing capability), bulk staff-id resolution, hub visibility rules |
| `local/engagement_report_test.php` | 4 | Empty metrics, course counting, player-course detection, scope split |
| `ecosystem/plugin_registry_test.php` | 2 | Catalog structure and unique components |
| `ecosystem/plugin_status_test.php` | 1 | Installed status keyed by component; hub reported installed |
| `event/events_test.php` | 1 | All nine events trigger, are captured and render a description |
| **Total** | **246** | |

```bash
vendor/bin/phpunit --testsuite local_playergames
```

## Coverage

Line/method coverage is measured locally with Xdebug (`moodle-coverage`, a bench tool — not part
of CI) rather than published as a single plugin-wide percentage: this codebase deliberately does
not unit-test Moodle's own output renderers (`classes/output/*`), the cartridge management UI
controller, or `ai_generator`'s real HTTP calls (only its response-parsing logic is tested, since
mocking the network round-trip has low ROI) — none of that is a gap the PHPUnit suite is meant to
close, so a raw plugin-wide number would just measure that policy rather than test quality. The
classes it is meant to cover — game managers, hub services, scheduled tasks, events, external
functions and the Privacy Provider — are what the case counts in the table above exercise, and
`games/fill_manager` and `task/assign_daily_games` (the classes PlayerFill added or changed) are
both at 100% line and method coverage.

## Behat — Acceptance Tests

| Feature file | Scenarios | What is covered |
|--------------|----------:|-----------------|
| `play_quiz.feature` | 2 | The PlayerQuiz play flow end to end |

```bash
php admin/tool/behat/cli/init.php
vendor/bin/behat --tags=@local_playergames --profile=chrome
```
