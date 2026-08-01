# 📝 How PlayerFill Works

PlayerFill is a daily crossword-style mini-game: solve every one of the day's terms, where
letters shared between terms are revealed automatically as other terms are solved.

## The play loop

- Each day's puzzle is made of several terms (see "Word count per day" below), shown as one row
  per term with its **definition** as a permanent hint.
- Every letter position in the puzzle is assigned a shared **slot number**: the same letter,
  anywhere across any of the day's terms, always gets the same number. A hidden position shows its
  slot number instead of a blank; once revealed, it shows the letter.
- A guess is checked as a **whole word**, not letter by letter — type the full term for a row and
  submit it.
- **Correct guess** → that row is marked solved, and every slot it covers is revealed everywhere
  else in the puzzle. This can immediately solve another still-pending row too, when every one of
  its letters happens to already be covered by rows solved so far (the cascade effect) — without
  the player ever typing that row's answer directly.
- **Solving every term** → the day ends in a win and XP is awarded.
- **Running out of attempts on any single term** → the whole day ends in a loss immediately (since
  a win requires every term to be solved, one exhausted term already makes that impossible), and
  every unsolved term's spelling is revealed.
- Guessing and validation always happen server-side against the day's assigned terms — the client
  never receives or trusts anything about an unsolved term's spelling until its row is solved or
  the day ends.

## Word count per day, shared assignment pipeline

Every night, a scheduled task assigns a configurable number of concepts to PlayerFill (default 5,
admin-configurable between 4 and 8) from the currently active cartridges — the same pipeline used
across the daily mini-games, extended for PlayerFill's need for several terms at once instead of
one. Only concepts whose term:

- has a normalised length within the admin-configured range (default 3–12 letters), and
- is a single alphabetic word — no spaces, digits or hyphens

are eligible, the same filter PlayerGuess applies to its own single daily term. If fewer eligible
concepts exist than the configured word count, PlayerFill is simply left unassigned for that day
rather than starting an undersized puzzle.

## Admin settings

*Site administration → Plugins → Local plugins → PlayerGames → Game rules*

| Setting | Default | What it does |
|---|---|---|
| **Fill words per day** | 5 | How many terms make up each day's puzzle. Clamped between 4 and 8. |
| **Fill maximum attempts per word** | 4 | Guesses allowed on a single term before the whole day ends in a loss. |
| **Fill minimum term length** | 3 | Shortest term eligible for the daily assignment. |
| **Fill maximum term length** | 12 | Longest term eligible for the daily assignment. |

*Site administration → Plugins → Local plugins → PlayerGames → XP and rewards*

| Setting | Default | What it does |
|---|---|---|
| **XP per Fill game** | 25 | XP awarded on a winning day. |
| **Fill games per day** | 1 | How many wins per day earn XP — once reached, the game shows "already played today". |

## Round state and its limits

The in-progress puzzle (which terms are solved, which slots are revealed, attempts used per term)
is kept in the user's **session**, not in a database table — the same tradeoff PlayerGuess makes,
and for the same reason: every guess needs server-side validation against a term that must never
be exposed to the browser ahead of time. It automatically rebuilds if the stored puzzle no longer
matches today's assigned terms (a new day, or a re-assigned set).

- Leaving the page and coming back within the same session resumes the puzzle exactly where it was
  left, including a finished day — a win or a loss stays final for the rest of the day in that
  session.
- Only a **win** is recorded server-side (through the same daily-play accounting used by the other
  games); a loss is not written anywhere outside the session.
- **Known limitation:** because a loss leaves no server-side trace, logging out or continuing from
  a different browser session gives a fresh puzzle for the same day's terms — the new session has
  no memory of the earlier loss. A win is unaffected: it is already recorded, so the "already
  played today" screen still applies. This mirrors the same accepted tradeoff PlayerGuess makes.

## Adapted from mod_playercross

PlayerFill's letter-to-slot algorithm is ported from the standalone `mod_playercross` activity's
puzzle builder. `mod_playercross` itself evolved a richer mechanic on top of it — a separate
cipher-locked "mystery phrase" solved through clue words, with a bonus for guessing it directly and
per-attempt partial grading — none of which carries over here: PlayerGames pays a fixed amount of
XP per win regardless of how it was reached, so partial-credit grading has no place in this hub,
and a set of peer terms that reveal each other is already a complete, self-contained puzzle without
needing a separate phrase layered on top.
