# 🧪 Automated Tests

PlayerGames ships with a PHPUnit suite covering the gamification engine, the cartridge pipeline,
scheduled tasks, privacy and events, and with Node tests for the rules of PlayerBattle. Every CI push runs the full matrix (Moodle 4.5 → 5.3,
PostgreSQL & MariaDB).

## PHPUnit — Unit & Integration Tests

| Test file | Cases | What is covered |
|-----------|------:|-----------------|
| `cartridge/importer_test.php` | 14 | Concept and quiz import; type inference; difficulty clamping; category dedup; schema-error paths; fields of the wrong type; the whole import saved in one transaction |
| `cartridge/exporter_test.php` | 5 | Concept/quiz export structure and full import→export round-trip including root metadata |
| `cartridge/category_manager_test.php` | 7 | Category CRUD, incrementing sortorder, idempotent `ensure`, ownership guard, concept null-on-delete |
| `cartridge/quiz_generator_test.php` | 12 | Quiz response parsing, category/difficulty defaults, `save_standalone` persistence; responses with fields of the wrong type |
| `cartridge/ai_generator_test.php` | 7 | Concept response parser: wrapped/bare/fenced JSON, invalid and missing-concepts errors; delegation to the AI Hub facade with a `core_ai` fallback; responses with fields of the wrong type |
| `cartridge/payload_test.php` | 3 | Cleaning of imported and AI-returned fields: a scalar becomes text and anything else counts as missing; a single value is wrapped as a list; numbers are clamped with a fallback |
| `hub/xp_manager_test.php` | 17 | Level thresholds, per-game cap enforcement, uncapped mission award, level-up event, season ranking position/tie-break |
| `hub/avatar_manager_test.php` | 10 | Default catalog seeding, tier thresholds, lifetime best-level tracking, unlock/equip rules, locked-equip rejection |
| `hub/learning_xp_manager_test.php` | 22 | Windowed monthly buckets, visibility gating (independent of staff status), ranking opt-in and position, recompute correctness |
| `hub/daily_play_manager_test.php` | 6 | Multiple plays split XP evenly; play beyond the daily quota rejected; last play trimmed to the exact cap; the extra payload (victory) reaches the event and completes the battle mission only when flagged; every pending game links to its page and a played one does not |
| `hub/level_manager_test.php` | 8 | Ladder seeding, XP/title lookups, save renumber + zero floor, restore defaults, linear generation and bounds |
| `hub/checkin_manager_test.php` | 11 | Check-in insert + XP award, idempotency, season cap, optional streak advance, participant eligibility |
| `hub/served_questions_test.php` | 3 | Per-day "already shown" set grouped by source, idempotent scoping, invalid items ignored |
| `hub/streak_manager_test.php` | 12 | Streak start/continue/reset, freeze consumption, overnight break processing |
| `hub/season_manager_test.php` | 15 | Season lifecycle, active/upcoming resolution, exclusive activation, snapshot, `create_next`; whether any season exists; editing (a closed season keeps its dates), deleting only a season's own data, the active season refused |
| `hub/mission_manager_test.php` | 7 | Mission sync, progress/completion with XP reward, daily and missed check-in resets |
| `hub/achievement_manager_test.php` | 6 | Achievement sync, granting (first game/level/all-games-day), idempotency |
| `hub/title_manager_test.php` | 2 | Level→title key clamping and translation |
| `observer_test.php` | 5 | `game_completed` drives streak/mission/achievement; records streak even without a season; mirrors `block_playerhud` XP changes into Learning XP |
| `hook_callbacks_test.php` | 5 | Primary navigation link: the hub for a student, none after opting out, the dashboard for a teacher who opted out, none for a guest, and the staff lookup skipped (counted in queries) when gamification is on |
| `games/quiz_loader_test.php` | 13 | Cartridge source: completeness filter, session size, active-only, id filter, metadata, random draw, fresh-question exclusion and pool reuse; question bank source reads only a category kept for reuse |
| `games/quiz_settings_test.php` | 8 | Timer, max-attempts and cooldown configuration and their interaction |
| `games/guess_manager_test.php` | 7 | Daily concept resolution, term normalisation, Wordle-style letter feedback (including duplicate letters), guess validation |
| `games/fill_manager_test.php` | 10 | Word count/max-attempts config clamping, shared letter→slot assignment (including disjoint terms), tile reveal state, cross-reveal cascade, per-day term ordering and filtering, POST response payload reveal rules |
| `games/battle_manager_test.php` | 12 | PlayerBattle on the server: questions delivered ready for the browser with the text escaped once, no battle without questions, a defeat saved without XP and without spending the play, a victory with the season XP (also past the quota, with none), the best result of the day, a battle accepted once, counters kept in range, only the questions delivered counted as seen |
| `games/season_game_config_test.php` | 7 | Source helpers; enabled-record lookup; per-season listing; default seeding and preservation |
| `external/set_avatar_test.php` | 3 | Equip/unequip via the AJAX endpoint, rejecting a locked avatar |
| `external/set_ranking_visibility_test.php` | 2 | Season ranking opt-in/opt-out toggle |
| `external/set_learning_ranking_visibility_test.php` | 2 | Learning XP ranking opt-in/opt-out toggle |
| `task/assign_daily_games_test.php` | 8 | Per-game concept assignment, PlayerFill's multi-concept assignment and pool-too-small skip, idempotency, no-cartridge and empty-cartridge cases, PlayerGuess eligibility filter, task name |
| `task/reset_daily_missions_test.php` | 1 | Daily mission reset + streak break orchestration |
| `task/close_expired_seasons_test.php` | 2 | Closes expired season; auto-renew creates and activates next |
| `task/purge_old_scores_test.php` | 3 | Retention-window purge; keep-within-window no-op; battle results of seasons beyond the window are purged too |
| `task/recompute_learning_xp_test.php` | 2 | Nightly window recompute prunes aged-out buckets and corrects cache drift |
| `privacy/provider_test.php` | 9 | Metadata, contexts, userlist, export, and the three deletion paths; every declared table lists exactly its real columns |
| `output/hub_test.php` | 2 | Who is offered the seasons page while there is no season: the administrator, and no one else |
| `local/access_test.php` | 16 | Staff detection (site admin, course-editing capability), bulk staff-id resolution, hub visibility rules; where each role lands |
| `local/admin_cards_test.php` | 8 | Administration tab: cards and groups by permission (administrator, manager, student), the tab in the navigation, the state of each card, a season name escaped once, and the documentation link opening in a new tab |
| `local/game_page_test.php` | 2 | The notice of the game pages when no season is active, and that it never says the game was already played |
| `local/question_bank_reuse_test.php` | 4 | Which question bank categories are kept for reuse (shared banks on Moodle 5.x, system context on 4.5), question counts, what a user may choose, and the check of a given category |
| `cartridge/glossary_importer_test.php` | 4 | Global glossaries listed with their approved entries, import into a concept cartridge, refusal of a course glossary and of an empty one |
| `local/text_test.php` | 4 | Plain-text names: text as typed, the level title, cartridge pages escaping names once, and edit forms filling inputs with the stored text |
| `local/engagement_report_test.php` | 4 | Empty metrics, course counting, player-course detection, scope split |
| `ecosystem/plugin_registry_test.php` | 2 | Catalog structure and unique components |
| `ecosystem/plugin_status_test.php` | 1 | Installed status keyed by component; hub reported installed |
| `event/events_test.php` | 1 | All nine events trigger, are captured and render a description |
| `db_upgrade_test.php` | 4 | Upgrade step that removes an index duplicating a key: two identical indexes become one, a column with no index gets the key's index, a single index is left alone; concept games lose the glossary source and the category |
| `uninstall_test.php` | 2 | Uninstall removes only the plugin's own prefixed user preferences, and does nothing when there are none |
| **Total** | **320** | |

```bash
vendor/bin/phpunit --testsuite local_playergames
```

## Node — PlayerBattle rules

The board and combat rules of PlayerBattle are plain modules (`amd/src/battle/`) that also load in
Node, so they are tested without a browser or Phaser. The CI runs them with `node --test`.

| Test file | Cases | What is covered |
|-----------|------:|-----------------|
| `js/battle/prng.test.js` | 5 | The seeded generator: same seed, same sequence; different seeds differ; values in [0, 1); a fixed known sequence; seeds coerced to unsigned 32 bits |
| `js/battle/board_rules.test.js` | 14 | A new grid has only the types in play and no match; the number of types is the one asked for; matches found with their size; a piece in two matches is destroyed once; type 0 is a piece, not an empty cell; swaps; which type a swap would match; the opponent's move; shuffling; gravity and refill |
| `js/battle/combat_rules.test.js` | 23 | Starting state and the pinned balance; combo bonus; Sword damage and the multiplier; Potion; the Shield and Grimoire meters; the Orb filling the mana bar; the critical hit and the cost of a wrong answer; poison ticks; victory and defeat; the rules never change the state they are given |
| **Total** | **42** | |

```bash
node --test tests/js/battle/*.test.js
```

## Coverage

Line/method coverage is measured locally with Xdebug (`moodle-coverage`, a bench tool — not part
of CI) rather than published as a single plugin-wide percentage: this codebase deliberately does
not unit-test Moodle's own output renderers (`classes/output/*`) beyond the two that decide who
sees what (the hub's seasons button and the Administration tab), the cartridge management UI
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
