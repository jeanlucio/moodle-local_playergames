# 🔡 How PlayerGuess Works

PlayerGuess is a daily Wordle-style mini-game: guess a term from the active cartridge, letter by
letter, within a limited number of attempts.

## The play loop

- The board shows the term's **definition** (as a hint) and its length, but not the term itself.
- Each guess must be the exact same length as the target and contain only letters.
- After each guess, every letter is marked **correct** (right letter, right position), **present**
  (right letter, wrong position) or **absent** — the classic Wordle feedback, computed
  server-side so the term is never exposed to the browser before the round ends.
- **Correct guess** → the round ends in a win and XP is awarded.
- **Running out of attempts** → the round ends in a loss and the term is revealed.
- Guessing and validation always happen server-side against the day's assigned concept — the
  client never receives or trusts anything about the target term until the round is over.

## One concept per day, shared assignment pipeline

Every night, a scheduled task assigns one random concept to PlayerGuess (and, independently, to
PlayerQuiz) from the currently active cartridges — the same pipeline used across the daily
mini-games. For PlayerGuess specifically, only concepts whose term:

- has a normalised length within the admin-configured range (default 4–8 letters), and
- is a single alphabetic word — no spaces, digits or hyphens

are eligible. This filter is applied identically at assignment time and again when validating a
guess, so a term that happens to fit the length window but contains, say, a hyphen is never
assigned to a PlayerGuess day.

## Admin settings

*Site administration → Plugins → Local plugins → PlayerGames → Game rules*

| Setting | Default | What it does |
|---|---|---|
| **Maximum attempts** | 6 | Guesses allowed before the round ends in a loss and the term is revealed. |
| **Minimum term length** | 4 | Shortest term eligible for the daily assignment. |
| **Maximum term length** | 8 | Longest term eligible for the daily assignment. |

*Site administration → Plugins → Local plugins → PlayerGames → XP and rewards*

| Setting | Default | What it does |
|---|---|---|
| **XP per Guess game** | 25 | XP awarded on a winning round. |
| **Guess games per day** | 1 | How many wins per day earn XP — once reached, the game shows "already played today". |

## Round state and its limits

The in-progress round (guesses made so far, whether it's finished, whether it was won) is kept in
the user's **session**, not in a database table. It automatically resets if the stored round no
longer matches today's assigned concept (a new day, or a re-assigned concept). This means:

- Leaving the page and coming back within the same session resumes the round exactly where it
  was left, including a finished round — a win or a loss stays final for the rest of the day in
  that session.
- Only a **win** is recorded server-side (through the same daily-play accounting used by the
  other games); a loss is not written anywhere outside the session.
- **Known limitation:** because a loss leaves no server-side trace, logging out or continuing
  from a different browser session gives a fresh, empty board for the same day's term — the new
  session has no memory of the earlier loss. A win is unaffected: it is already recorded, so the
  "already played today" screen still applies. This mirrors a tradeoff `mod_playerwords` makes for
  its own session-backed rounds, accepted in favour of not adding a dedicated database table for a
  same-day, single-device play.
