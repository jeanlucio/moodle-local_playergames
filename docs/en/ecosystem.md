# 🕹️ PlayerGames Ecosystem

PlayerGames is the hub of a broader gamification ecosystem. Together, these plugins transform
Moodle into an immersive experience:

* **PlayerGames Block:** Companion sidebar widget for the site front page and Dashboard —
  equipped avatar, level, XP, streak, today's games and ranking position, all delegated to this
  plugin. Requires `local_playergames`.
  👉 [github.com/jeanlucio/moodle-block_playergames](https://github.com/jeanlucio/moodle-block_playergames)

* **AI Hub:** Shared BYOK (bring your own key) broker — personal and site-wide Gemini/Groq/
  OpenAI-compatible keys, consumed by PlayerGames for AI-assisted cartridge generation. Optional:
  PlayerGames still works through Moodle's own `core_ai` without it.
  👉 [github.com/jeanlucio/moodle-local_aihub](https://github.com/jeanlucio/moodle-local_aihub)

* **PlayerHUD Block:** XP, levels, inventory, drops, quests, RPG classes, story, karma, and
  ranking inside each course. Its course XP can optionally mirror into PlayerGames'
  [Learning XP](#learning-xp) pool.
  👉 [github.com/jeanlucio/moodle-block_playerhud](https://github.com/jeanlucio/moodle-block_playerhud)

* **PlayerHUD Filter:** Enables item drops via shortcodes inside course content.
  👉 [github.com/jeanlucio/moodle-filter_playerhud](https://github.com/jeanlucio/moodle-filter_playerhud)

* **PlayerHUD Availability Restriction:** Restricts access to course activities based on the
  student's current level or collected items.
  👉 [github.com/jeanlucio/moodle-availability_playerhud](https://github.com/jeanlucio/moodle-availability_playerhud)

* **PlayerGroup:** Lets students autonomously form their own groups directly from the activity
  page.
  👉 [github.com/jeanlucio/moodle-mod_playergroup](https://github.com/jeanlucio/moodle-mod_playergroup)
