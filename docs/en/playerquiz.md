# 🧠 How PlayerQuiz Works

PlayerQuiz is the first daily mini-game. It draws multiple-choice questions from the active
cartridges (and, optionally, the Moodle Question Bank) and shows them **one at a time**.

## The play loop

- One question is shown at a time, with a per-question countdown.
- **Wrong answer** (or the timer running out) → the correct option is revealed, then the next
  question loads.
- **Correct answer** → the play ends and XP is awarded.
- With an attempt limit set, running out of attempts ends the play in **failure** (no XP) and may
  start a cooldown.

Because a play ends on the **first correct answer**, a player rarely answers the whole pool —
most questions in a session are just a buffer that provides variety and room to be wrong before
repeating.

## Two independent limits (this trips people up)

| Setting | Axis | Meaning |
|---|---|---|
| **Games per day** (`xp_games_quiz`) | the *day* | How many **plays** earn XP per day. Also sets the daily XP cap: `XP per game × games per day`. |
| **Questions per session** (`quiz_session_size`) | inside *one play* | How many questions are drawn into a **single play** (the "deck" of that round). |

*Example* — with *XP per game = 25*, *games per day = 3*, *questions per session = 20*: a player
can complete **3 plays** today (cap 75 XP), and **each** play is built from a fresh draw of **20**
questions. The two numbers answer different questions: *how many rounds today* vs *how big each
round's deck is*.

## Fresh questions and repeats

- Each page load builds a **brand-new shuffled session** — there is no saved "session of the day".
- Questions are remembered as "seen today" **only when a play is completed with a correct
  answer**. Those are then excluded from the **other plays of the same day**, so completed plays
  never repeat questions.
- **Leaving mid-play or failing records nothing** — reopening the page gives a fresh random draw
  that may include the same questions.
- The "seen today" memory is **per day**: the next day starts clean and may re-draw questions
  from previous days. There is no cross-day exclusion.
- Reloading never grants extra XP — it only changes *which* questions appear; XP is still capped
  by *games per day* and only counts on a correct answer.

## Admin settings

*Site administration → Plugins → Local plugins → PlayerGames → Game rules*

| Setting | Default | What it does |
|---|---|---|
| **Time per question (seconds)** | 120 (2 min) | Countdown per question. Reaching zero counts as a wrong answer and advances. `0` disables the timer. |
| **Maximum attempts** | 0 (unlimited) | Wrong answers (including timeouts) that end a play without XP. `0` keeps the original behaviour — the game continues until the player answers correctly. |
| **Cooldown after failing (minutes)** | 0 | When a play ends in failure, the quiz is locked for this many minutes. Persisted server-side (a page reload does **not** bypass it). Only meaningful together with an attempt limit. |
| **Questions per session** | 20 | Size of each play's draw, chosen from a capped list (5–50). |

## How the timer, attempts and cooldown interlock

- A timer expiry is treated exactly like a wrong answer.
- A play can only **fail** when a maximum-attempts limit is set; with unlimited attempts (`0`) the
  game loops until a correct answer, so the cooldown never triggers.
- A failed play awards **no XP and does not consume a daily play** — the cooldown is the only
  throttle, so with cooldown `0` the player may retry immediately.

## Why the session size is capped

Every question of a session is rendered into the page HTML at once (the browser just reveals them
one by one). A very large session would format and ship hundreds of questions the player will
never see — wasted server work and a heavy page. The capped list (max 50) keeps plenty of variety
without that cost. Truly large sessions would require a different architecture (loading questions
on demand), which is not how the game works today.
