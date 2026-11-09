# ⚔️ How PlayerBattle Works

PlayerBattle is the daily match-3 mini-game. The player fights an opponent on an 8×8 board:
lining up pieces hurts the opponent, and the **Orb** brings a question — a right answer lands a critical
hit. The questions come from the same sources as PlayerQuiz.

## The board

Swap two neighbouring pieces to line up three or more of the same kind. The pieces that match are
destroyed, new ones fall in, and any match they make counts too (a cascade). A swap that makes no match
slides back and does not cost the turn. The player moves first, then the opponent, in turns.

| Piece | Effect |
|---|---|
| ⭐ **Star** | +0.1 to the damage multiplier of whoever matched it. The multiplier raises Sword hits and the critical hit, and is spent by the critical hit or lost on a wrong answer. |
| 📖 **Grimoire** | Fills a poison meter. When full, the other side is poisoned for three turns: it loses the damage unit of whoever poisoned it at the start of each of its turns, and the shield does not stop it. |
| ❓ **Orb** | Fills the mana bar (20 per piece, 100 is full). When it fills, the player faces a question. |
| ⚔️ **Sword** | Direct damage: one damage unit for a match of three, ×1.5 for four, ×2 for five. |
| 🛡️ **Shield** | Fills a shield meter. When full, it cancels the next hit taken (a Sword, a critical hit or a wrong answer). Poison goes through it. |
| 🧪 **Potion** | Heals a quarter of the damage unit per piece, up to the maximum HP. |

Both sides start with 200 HP. A match of three Swords hits for 10 when the player makes it and for 8
when the opponent does. These numbers are fixed: they come from simulated battles, in which a player who
answers every question right wins almost always, one who answers two in three about three times in four,
and one who guesses blindly among five options about one time in five. A battle lasts roughly 18 to 29
turns and asks 5 to 9 questions.

## The questions

- When the mana bar fills, the board stops and a question appears; the battle goes on after the answer.
- A **right answer** is a critical hit on the opponent: three times the damage unit, times the multiplier.
- A **wrong answer** costs the player one plain hit from the opponent, and the multiplier.
- The opponent has no questions: when its own mana fills, it lands a critical hit by itself.
- Ten questions are drawn for each battle and asked in a shuffled order. If they run out, the order is
  drawn again, never starting with the question just asked.
- Cartridge text is shown as plain text; text from the Question Bank goes through the Moodle filters. The
  feedback of a question, when it has one, is shown after the answer.

## XP and daily plays

PlayerBattle follows the same daily rule as the other games: how much XP a play gives and how many plays a
day count come from the Game XP settings the season was created with.

- **A victory** spends one daily play and gives the season's XP for the game. Past the day's quota, a victory
  is still saved, but gives no XP.
- **A defeat** is saved but gives no XP and **does not spend the play**: the player may try again at once.
- The day keeps the **best result**: a victory over a defeat, then the one that dealt the most damage.
- The first victory completes the **First Victory** mission.
- The questions shown count as seen for the day, in a defeat too, so the next try brings new ones. When
  every question has been seen, they repeat rather than leaving the player stuck.

A result is accepted once for each battle the page opened, so a result sent without playing, or sent
twice, earns nothing.

## Content and settings

PlayerBattle asks **quiz-type cartridges** and, optionally, a **Moodle Question Bank** category kept for
reuse (multiple-choice questions; see [How PlayerQuiz Works](#playerquiz)), as chosen for the season in the *Season games* card of the [Administration](#administration)
tab.

| Where | Setting |
|---|---|
| *Season games* | Whether PlayerBattle runs in the season and where its questions come from. |
| *Rules → Game XP* | XP per PlayerBattle game and how many plays a day give XP. |

## Playing it

- **Mouse or touch:** click a piece and then a neighbour, or drag a piece towards a neighbour.
- **Keyboard:** the arrow keys move between the pieces, **Enter** selects one, and an arrow key then swaps it
  with the neighbour in that direction. What is under the cursor, and what happens, is announced to screen
  readers.
- The question and the end of the battle are dialogs: while one is open, the keyboard stays inside it. The
  answers can also be chosen with the keys 1 to 5 or A to E.
- Animations are skipped when the browser asks for reduced motion.
- **Sound** is off until the player turns it on with the *Sound* button; the choice is kept in that browser.

## Privacy

The result of each day is stored with the player: whether it was a victory, the damage dealt, the questions
answered right and in total, the XP, the duration and the time. It is exported and deleted with the rest of
the player's data, and removed with the season by the retention task.
