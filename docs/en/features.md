# ✨ Features

## ✅ Implemented

* 🗺️ **Ecosystem Dashboard:** SVG overview of all Player plugins — installed, missing,
  dependencies, status, and quick-action links for admins.
* 🎮 **Player Hub — XP & Levels:** Site-wide XP across configurable seasons. Each mini-game
  awards a fixed amount of XP, and admins set how many scoring plays per day each game allows —
  XP is earned exclusively through gameplay, so teachers with different course loads and students
  enrolled in different numbers of courses all compete on equal footing.
* 🪜 **Player Hub — Configurable Level Ladder:** Admins edit the per-level XP thresholds and
  titles from the Level Ladder page — restore the default 5-tier ladder or generate a longer
  linear progression with one click.
* 📅 **Player Hub — Daily Check-in:** Earn XP just for visiting the hub once a day, capped per
  season. Optionally counts toward the daily streak.
* 🏆 **Player Hub — Season Ranking:** Leaderboard with privacy controls (opt-in), tie-breaks and
  a staff/student split — see [Ranking Behavior](#ranking).
* 📘 **Player Hub — Learning XP:** An optional, separate XP pool mirrored from a student's
  per-course activity in `block_playerhud`, with its own opt-in ranking — see
  [Learning XP](#learning-xp).
* 🔥 **Player Hub — Streak & Freeze:** Daily streak tracking. Freeze consumables prevent streak
  loss and are earned via missions; the daily check-in can keep the streak alive when configured.
* 🎯 **Player Hub — Missions:** Daily, streak-based, cumulative XP, and victory-based missions
  with configurable XP rewards.
* 🏅 **Player Hub — Achievements:** Permanent achievements that persist across seasons.
* 🏷️ **Player Hub — Titles:** Level-based titles visible in Moodle profiles, forums, and courses.
* 🦊 **Player Hub — Avatar Collection:** Emoji avatars unlocked permanently by the highest level a
  player has ever reached, grouped into 4 configurable tiers — see [Avatars](#avatars).
* 🕐 **Unified Activity Log:** A single, chronological log of every XP, streak and freeze event
  (season XP, learning XP, freeze earned/used, streak broken), with a shared "how it works" help
  modal explaining season XP, learning XP, avatars and rankings.
* 📦 **Cartridge System:** Content source for the mini-games. Supports multiple active cartridges
  simultaneously.
  * **All games:** manual creation (inline editor), JSON upload, or AI generation (see
    [AI Provider Chain](#ai-provider-chain)).
  * **PlayerQuiz:** also accepts the **Moodle Question Bank** (multiple-choice questions only).
* 🧠 **PlayerQuiz:** Daily multiple-choice mini-game using concepts from the active cartridge —
  see [How PlayerQuiz Works](#playerquiz).
* 🔡 **PlayerGuess:** Daily Wordle-style mini-game — guess the term letter by letter — see
  [How PlayerGuess Works](#playerguess).
* 📝 **PlayerFill:** Daily crossword-style mini-game — numbered positions; the same number shares
  the same letter across multiple terms; solving one reveals letters in others (cascade effect).
  Grid generated in PHP without external libraries — see [How PlayerFill Works](#playerfill).
* 🧩 **PlayerGames Block:** Companion sidebar block (`block_playergames`) showing the user's
  equipped avatar, level, XP, streak, today's games, and ranking position on the site front page
  and Dashboard, linking to the full Player Hub — see [PlayerGames Ecosystem](#ecosystem).
* 📅 **Season Management:** Create, close, and auto-renew seasons with configuration snapshots.
  Historical data is preserved when a season closes.
* 🔐 **Privacy (GDPR):** Complete Privacy Provider — metadata declaration, export and deletion of
  all stored personal data; shared cartridges are preserved with the uploader anonymised.
* 🧪 **Automated Tests:** 246-case PHPUnit suite, green across the full CI matrix (see the
  [Automated Tests](#testing) section).

## ⏳ In Development / Planned

* ⚔️ **PlayerBattle:** Match-3 RPG mini-game (8×8 grid) with turn-based combat against a boss
  powered by Phaser 3. Combining mana pieces charges a question; correct answer → triple damage;
  wrong answer → player takes damage.
* 📦 **Phaser Centralized:** `local_playergames` will serve `phaser.min.js` to all Player plugins
  via `local_playergames_get_phaser_url()`, removing duplicated copies from each plugin.
* 🛡️ **Publication Polish:** Full accessibility audit and broader Behat acceptance coverage
  (PHPUnit suite and PHPCS compliance are already in place).
