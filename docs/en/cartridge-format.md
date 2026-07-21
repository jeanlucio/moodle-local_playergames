# 📦 Cartridge Format

Content cartridges are JSON files that power all mini-games. The same pack feeds PlayerQuiz,
PlayerGuess, PlayerFill, and PlayerBattle:

```json
{
  "name": "Gamification Fundamentals",
  "version": "1.0",
  "language": "en",
  "concepts": [
    {
      "term": "gamification",
      "definition": "Use of game elements in non-game contexts",
      "category": "fundamentals",
      "difficulty": 2
    }
  ]
}
```

- Validated by the importer before saving
- Terms for PlayerGuess: filtered by configurable length (default 4–8 letters) and must be a
  single alphabetic word (no spaces, digits or hyphens) — see
  [How PlayerGuess Works](#playerguess)
- Quiz-type cartridges: each question accepts an optional `generalfeedback` string, shown to the
  player after they answer; PlayerQuiz can also draw from the **Moodle Question Bank** instead of
  or alongside cartridges
- Admin can have multiple active cartridges simultaneously
- Multi-language: each cartridge declares its `language`
