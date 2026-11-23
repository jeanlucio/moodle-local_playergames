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
| | Seasons | The period in which students earn XP and compete in the ranking. Create, edit, activate, close and delete seasons. | Site administrator |
| | Season games | Which games run in the season and which cartridges the questions or words of each one come from (PlayerQuiz and PlayerBattle use question cartridges; PlayerGuess and PlayerFill, concept cartridges). | Site administrator |
| **Rules** | Participation and seasons | Who plays (students, staff or both), whether seasons renew by themselves and how long they last. | Site administrator |
| | Game XP | How much XP each game gives and how many times a day it can be played for XP. | Site administrator |
| | Game rules | Time per question, attempts and the other rules of each game. | Site administrator |
| | Levels and titles | How much XP each level takes and the title it gives. The avatars unlock along this ladder. | Site administrator |
| | Streak and freeze | How the daily streak works and how a freeze is earned. | Site administrator |
| | Course XP | The XP courses give through PlayerHUD, shown apart from the season XP. | Site administrator |
| **Data** | Reset data | Deletes the seasons and everything the players earned, to start again from zero. The cartridges and the settings stay. | Site administrator |
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

### Editing and deleting a season

On the seasons page each season has **Edit** and, unless it is the active one, **Delete**.

* **Edit** changes the name and the dates. A closed season keeps its dates, as they are history; only its
  name changes. The status is never changed here — use **Activate** and **Close season** for that.
* **Delete** asks for confirmation and removes the season with its game settings and the XP, missions and
  results of its players. The daily scores of those days go too, unless another season covers the same
  days. The active season cannot be deleted: close it first. Deleting cannot be undone.
* **Close season** asks for confirmation too, as a closed season cannot be reopened. Closed before its planned
  end, the season ends today: its end date changes to today's, so the days it never used are free for the
  next season.

Seasons never share a day. Creating or editing a season whose dates cross another one is refused, with the
name and dates of the other season, because the retention task and **Delete** remove the scores of a season by
its dates. The form suggests a start date after the last season when that one still runs: after closing a
season today, the next one can start tomorrow.

### Resetting all the data

Deleting the seasons and the cartridges one by one leaves what belongs to no season: streaks, freezes,
achievements, avatar progress, the activity history and the course XP mirrored from PlayerHUD. **Reset data**
deletes all of it, for every user, to start again from zero, after a pilot for instance.

* **Deleted:** every season, the active one included, with its game settings; the season results and the
  hall of fame; the daily scores, battles and rounds; the season profiles and mission progress; the streaks,
  freezes, achievements and avatar progress; the activity and freeze history; the course XP mirrored from
  PlayerHUD (the XP in PlayerHUD itself is not touched).
* **Kept:** the cartridges with their questions and concepts, the levels and titles, the avatar catalogue,
  the missions and achievements themselves, every setting, and each user's choice to take part.

The page lists how many records each group holds, and nothing is deleted until the administrator types the
confirmation word (*RESET*; *ZERAR* in Portuguese). It cannot be undone. The site log records who did it,
when and how many records went (event *PlayerGames data reset*).

## The same order in the Moodle administration

The plugin's pages in **Site administration → Plugins → Local plugins → PlayerGames** follow the same
groups: **Get started** (Cartridges, Seasons, Season games), **Rules** (Participation and seasons, Game XP,
Game rules, Levels and titles, Streak and freeze, Course XP), **Monitoring** (Engagement
Meter) and **Data** (Reset data). The Administration tab is a shortcut to them, not a replacement.

Season games opens the active season, or the next one if none is active. With no season at all it leads
to the seasons page.

For PlayerGuess and PlayerFill, each cartridge shows how many of its words serve the game, and the page warns when
the chosen cartridges are not enough. In the hub, the card of the game says why there is nothing to play: no active
cartridge, no words that meet the requirements, or words of the day not drawn yet.

## First setup

1. Create a cartridge (**Cartridges**): the games need something to ask.
2. Create a season and activate it (**Seasons**).
3. Choose the games the season runs and their cartridges (**Season games**).
4. Review the rules (**Rules**): the defaults work, but the XP, the levels and who plays are yours to set.
