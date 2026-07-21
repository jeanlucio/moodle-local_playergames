# ✨ Features

## ✅ Implemented

* 🗺️ **Ecosystem Dashboard:** SVG overview of all Player plugins — installed, missing,
  dependencies, status, and quick-action links for admins.
* 🔑 **Shared AI Key Hub:** Configure Gemini, Groq, and OpenAI-compatible API keys once — all
  Player plugins consume them automatically via a 4-level priority chain.
* 📊 **Engagement Meter:** Compare engagement metrics (events per student, completion rate,
  average grade) between courses that use Player plugins and those that don't — available to
  admins (all courses) and teachers (their own courses).
* 🎮 **Player Hub — XP & Levels:** Site-wide XP across configurable seasons. Each mini-game
  awards a fixed amount of XP, and admins set how many scoring plays per day each game allows —
  XP is earned exclusively through gameplay, so teachers with different course loads and students
  enrolled in different numbers of courses all compete on equal footing.
* 🪜 **Player Hub — Configurable Level Ladder:** Admins edit the per-level XP thresholds and
  titles from the Level Ladder page — restore the default 5-tier ladder or generate a longer
  linear progression with one click.
* 📅 **Player Hub — Daily Check-in:** Earn XP just for visiting the hub once a day, capped per
  season. Optionally counts toward the daily streak.
* 🏆 **Player Hub — Ranking:** Season ranking with privacy controls (opt-in), separated by
  participant group (students vs. non-students — teachers, managers, admins). Admins and managers
  see both groups via tabs. Players who rank outside the top 50 still see their own position, and
  ties are broken by who reached the XP first.
* 🔥 **Player Hub — Streak & Freeze:** Daily streak tracking. Freeze consumables prevent streak
  loss and are earned via missions; the daily check-in can keep the streak alive when configured.
* 🎯 **Player Hub — Missions:** Daily, streak-based, cumulative XP, and victory-based missions
  with configurable XP rewards.
* 🏅 **Player Hub — Achievements:** Permanent achievements that persist across seasons.
* 🏷️ **Player Hub — Titles:** Level-based titles visible in Moodle profiles, forums, and courses.
* 📦 **Cartridge System:** Content source for the mini-games. Supports multiple active cartridges
  simultaneously.
  * **All games:** manual creation (inline editor), JSON upload, or AI generation
    (Gemini/Groq/OpenAI).
  * **PlayerQuiz and PlayerBattle:** also accept the **Moodle Question Bank** (multiple-choice
    questions only).
  * **PlayerGuess and PlayerFill:** also accept the **Moodle Glossary** (terms and definitions
    reused as-is).
* 🧠 **PlayerQuiz:** Daily multiple-choice mini-game using concepts from the active cartridge.
  Wrong answer → new concept; correct answer → XP. Replaying within the same day serves fresh
  questions instead of repeating ones already seen.
* 📅 **Season Management:** Create, close, and auto-renew seasons with configuration snapshots.
  Historical data is preserved when a season closes.
* 🔐 **Privacy (GDPR):** Complete Privacy Provider — metadata declaration, export and deletion of
  all stored personal data; shared cartridges are preserved with the uploader anonymised.
* 🧪 **Automated Tests:** 142-case PHPUnit suite, green across the full CI matrix (see the
  [Automated Tests](#testing) section).

## ⏳ In Development / Planned

* 🔡 **PlayerGuess:** Wordle-style mini-game — guess the term letter by letter (5–8 letters,
  configurable). Six attempts before the answer is revealed.
* 📝 **PlayerFill:** Crossword-style mini-game — numbered positions; the same number shares the
  same letter across multiple words; solving one word reveals letters in others (cascade effect).
  Grid generated in PHP without external libraries.
* ⚔️ **PlayerBattle:** Match-3 RPG mini-game (8×8 grid) with turn-based combat against a boss
  powered by Phaser 3. Combining mana pieces charges a question; correct answer → triple damage;
  wrong answer → player takes damage.
* 📦 **Phaser Centralized:** `local_playergames` will serve `phaser.min.js` to all Player plugins
  via `local_playergames_get_phaser_url()`, removing duplicated copies from each plugin.
* 🧩 **block_playergames:** Companion sidebar block showing the user's current XP, level, streak,
  and daily game status on any Moodle page, linking to the full Player Hub.
* 🛡️ **Publication Polish:** Full accessibility audit and Behat acceptance tests (PHPUnit suite
  and PHPCS compliance are already in place).
