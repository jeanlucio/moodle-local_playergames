# 📖 Usage

## For Administrators

1. Install the plugin and complete the Moodle upgrade step.
2. Optionally install **[local_aihub](https://github.com/jeanlucio/moodle-local_aihub)** if you
   want AI-assisted cartridge generation with personal/site keys (see
   [AI Provider Chain](#ai-provider-chain)) — otherwise cartridges can still be created manually
   or generated through Moodle's own `core_ai`.
3. Create a content cartridge in the **Cartridges** page (upload one or generate it): the games need
   something to ask.
4. Create the first season in the **Seasons** page and activate it, setting name and dates.
5. Choose the games the season runs, and where their questions come from, in **Season games**.
6. Review the rules (XP, levels, who plays) under **Rules**: the defaults work, and you can tune them.
7. Monitor gamification impact in the **Engagement Meter** page.

Steps 3 to 6 are cards in the **Administration** tab, in this order (the Engagement Meter has its own tab); see
[Administration](#administration).

## For Teachers

1. If `local_aihub` is installed, access its **My AI Keys** page to configure a personal API key
   (optional — site keys work if the admin configured them).
2. Use the PlayerGames hub or any Player plugin — AI features will use the configured key chain
   automatically.

## For Students

1. Visit the **Player Hub** to see your XP, level, ranking position, streak, and daily missions.
2. Play the daily mini-games to earn XP.
3. Toggle "Show in ranking" in your profile to control ranking visibility.
