# 🧭 Administration

Everything an administrator sets up is gathered in the **Administration** tab of PlayerGames, as cards,
in the order the work is usually done. Each card says what the function does and, where there is one,
where things stand: which season is active, how many cartridges are on, and so on. A card is a link to
the page of the function.

The tab shows up for whoever can open at least one card, and each card is shown only if the user can
open where it leads.

## The cards

| Group | Card | What it is for | Needs |
|---|---|---|---|
| **Get started** | Cartridges | The packs of questions and concepts the games draw from. Import a file, generate one with AI or write it by hand. | Manage cartridges |
| | Seasons | The period in which students earn XP and compete in the ranking. Create, activate and close seasons. | Site administrator |
| | Season games | Which games run in the season and where the questions of each one come from (PlayerGuess and PlayerFill always use concept cartridges). | Site administrator |
| **Rules** | Participation and seasons | Who plays (students, teaching staff or both), whether seasons renew by themselves and how long they last. | Site administrator |
| | Game XP | How much XP each game gives and how many times a day it can be played for XP. | Site administrator |
| | Game rules | Time per question, attempts and the other rules of each game. | Site administrator |
| | Levels and titles | How much XP each level takes and the title it gives. | Site administrator |
| | Avatars | From which level each group of avatars is unlocked. | Site administrator |
| | Streak and freeze | How the daily streak works and how a freeze is earned. | Site administrator |
| | Learning XP | The XP courses give through PlayerHUD, shown apart from the season XP. | Site administrator |
| **Help and integrations** | AI keys | Keys used to generate questions with AI. Shown only when the AI Hub plugin is installed. | Site administrator |
| | Documentation | This guide, in English and Portuguese. | Anyone who sees the tab |

The Engagement Meter and the Plugin ecosystem are not cards: they already have a tab of their own.

## Who sees what

* **Site administrators** see every card.
* **Managers** (the Moodle *Manager* role) see **Cartridges** and **Documentation**: the permission to
  manage cartridges is given to managers by default, while seasons, levels and rules need the permission
  to change the site configuration, which only site administrators have.
* **Everyone else** has no Administration tab.

When there is no active season, the Player Hub tells the administrator so and offers a button to the
seasons page.

## The same order in the Moodle administration

The plugin's pages in **Site administration → Plugins → Local plugins → PlayerGames** follow the same
groups: **Get started** (Cartridges, Seasons, Season games), **Rules** (Participation and seasons, Game XP,
Game rules, Levels and titles, Avatars, Streak and freeze, Learning XP) and **Monitoring** (Engagement
Meter). The Administration tab is a shortcut to them, not a replacement.

Season games opens the active season, or the next one if none is active. With no season at all it leads
to the seasons page.

## First setup

1. Create a cartridge (**Cartridges**): the games need something to ask.
2. Create a season and activate it (**Seasons**).
3. Choose the games the season runs and their sources (**Season games**).
4. Review the rules (**Rules**): the defaults work, but the XP, the levels and who plays are yours to set.
