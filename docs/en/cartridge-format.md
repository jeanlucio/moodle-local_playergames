# 📦 Cartridge Format

Content cartridges are JSON files that power the mini-games. A **concept** cartridge (terms and
definitions, as below) feeds PlayerGuess and PlayerFill; a **quiz** cartridge (questions with their
answers) feeds PlayerQuiz and PlayerBattle:

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
  player after they answer; PlayerQuiz and PlayerBattle can also draw from a **Moodle Question Bank**
  category kept for reuse by the whole site, instead of or alongside cartridges
- Concept cartridges can be created from a **global glossary** (the *Glossary* tab of the cartridge
  page): the approved entries become concepts, up to the importer's limit. Glossaries of a single
  course are not offered — only those Moodle marks as global
- Admin can have multiple active cartridges simultaneously
- Multi-language: each cartridge declares its `language`
