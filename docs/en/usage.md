# 📖 Usage

## For Administrators

1. Install the plugin and complete the Moodle upgrade step.
2. Optionally install **[local_aihub](https://github.com/jeanlucio/moodle-local_aihub)** if you
   want AI-assisted cartridge generation with personal/site keys (see
   [AI Provider Chain](#ai-provider-chain)) — otherwise cartridges can still be created manually
   or generated through Moodle's own `core_ai`.
3. Create the first season in the **Season Management** page, setting name, start/end dates, and
   the per-game XP and plays-per-day rewards.
4. Optionally tune the **Level Ladder** page — adjust XP thresholds and titles, restore the
   default ladder, or generate a longer linear one.
5. Upload or generate a content cartridge in the **Cartridge** page.
6. Monitor gamification impact in the **Engagement Meter** page.

## For Teachers

1. If `local_aihub` is installed, access its **My AI Keys** page to configure a personal API key
   (optional — site keys work if the admin configured them).
2. Use the PlayerGames hub or any Player plugin — AI features will use the configured key chain
   automatically.

## For Students

1. Visit the **Player Hub** to see your XP, level, ranking position, streak, and daily missions.
2. Play the daily mini-games to earn XP.
3. Toggle "Show in ranking" in your profile to control ranking visibility.
