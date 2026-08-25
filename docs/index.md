---
layout: default
title: PlayerGames Documentation
lang: en
---

![Moodle](https://img.shields.io/badge/Moodle-4.5%2B-orange?style=flat&logo=moodle&logoColor=white)
![License](https://img.shields.io/badge/License-GPLv3-blue?style=flat)
![Status](https://img.shields.io/badge/Status-Alpha-red?style=flat)
[![Latest Release](https://img.shields.io/github/v/release/jeanlucio/moodle-local_playergames?style=flat)](https://github.com/jeanlucio/moodle-local_playergames/releases)
[![PlayerGames Ecosystem](https://img.shields.io/badge/PlayerGames-Ecosystem-6f42c1?style=flat&logo=gamepad&logoColor=white)](https://jeanlucio.github.io/playergames/)
![Core Component](https://img.shields.io/badge/Role-Central_Hub-198754?style=flat)
[![Author](https://img.shields.io/badge/by-Jean_Lucio-6f42c1?style=flat)](https://github.com/jeanlucio/)

[![Moodle Plugin CI](https://github.com/jeanlucio/moodle-local_playergames/actions/workflows/ci.yml/badge.svg)](https://github.com/jeanlucio/moodle-local_playergames/actions/workflows/ci.yml)
[![Last Commit](https://img.shields.io/github/last-commit/jeanlucio/moodle-local_playergames?style=flat)](https://github.com/jeanlucio/moodle-local_playergames/commits)
[![Open Issues](https://img.shields.io/github/issues/jeanlucio/moodle-local_playergames?style=flat)](https://github.com/jeanlucio/moodle-local_playergames/issues)

> ⚠️ **This plugin is under active development.** It is not yet published on the Moodle Plugin
> Directory. Some features described below are planned and not yet implemented.

**PlayerGames** (`local_playergames`) is the central hub of the PlayerGames gamification
ecosystem for Moodle. It serves four main purposes:

1. **Ecosystem Dashboard** — a visual overview of all Player plugins installed on the site, with
   status, dependencies, and quick-access links.
2. **Player Hub** — a site-wide gamification platform for students and/or other Moodle users
   (teachers, managers, administrators), with XP, levels, seasons, missions, achievements, avatars
   and daily streak. The administrator configures which groups participate.
3. **Daily Mini-games** — concept-reinforcement mini-games and a daily check-in powered by
   content cartridges (JSON, optionally AI-generated via the companion
   [local_aihub](https://github.com/jeanlucio/moodle-local_aihub) plugin), working as a
   Duolingo-style learning loop.
4. **Companion Sidebar Block** — [block_playergames](#ecosystem) mirrors a player's profile
   (avatar, level, XP, streak, today's games, ranking) on the site front page and Dashboard.

<p class="page-hint">👈 Use the sidebar to jump to any section on this page.</p>

---

<span id="screenshots"></span>
{% include_relative en/screenshots.md %}

<span id="features"></span>
{% include_relative en/features.md %}

<span id="ai-provider-chain"></span>
{% include_relative en/ai-provider-chain.md %}

<span id="cartridge-format"></span>
{% include_relative en/cartridge-format.md %}

<span id="playerquiz"></span>
{% include_relative en/playerquiz.md %}

<span id="playerguess"></span>
{% include_relative en/playerguess.md %}

<span id="playerfill"></span>
{% include_relative en/playerfill.md %}

<span id="avatars"></span>
{% include_relative en/avatars.md %}

<span id="learning-xp"></span>
{% include_relative en/learning-xp.md %}

<span id="ranking"></span>
{% include_relative en/ranking.md %}

<span id="educational-purpose"></span>
{% include_relative en/educational-purpose.md %}

<span id="ecosystem"></span>
{% include_relative en/ecosystem.md %}

<span id="requirements"></span>
{% include_relative en/requirements.md %}

<span id="installation"></span>
{% include_relative en/installation.md %}

<span id="usage"></span>
{% include_relative en/usage.md %}

<span id="testing"></span>
{% include_relative en/testing.md %}

<span id="security"></span>
{% include_relative en/security.md %}

<span id="license"></span>
{% include_relative en/license.md %}
