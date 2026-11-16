<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Shared pieces of the daily game pages.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\local;

use core\output\notification;
use local_playergames\games\season_game_config;
use moodle_url;
use stdClass;

/**
 * What every game page shows when there is no active season to play in.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class game_page {
    /**
     * The notice a game page shows instead of the game when no season is active.
     *
     * A page that carried on would call a game "already played today" only because it found no
     * season to count the plays in.
     *
     * @return string HTML: the notice and a button back to the hub.
     */
    public static function no_season_html(): string {
        global $OUTPUT;

        return $OUTPUT->notification(get_string('hub_no_season', 'local_playergames'), notification::NOTIFY_INFO, false)
            . $OUTPUT->continue_button(new moodle_url('/local/playergames/hub.php'));
    }

    /**
     * The notice a game page shows instead of the game when the admin switched it off for the season.
     *
     * @return string HTML: the notice and a button back to the hub.
     */
    public static function disabled_html(): string {
        global $OUTPUT;

        return $OUTPUT->notification(get_string('game_disabled', 'local_playergames'), notification::NOTIFY_INFO, false)
            . $OUTPUT->continue_button(new moodle_url('/local/playergames/hub.php'));
    }

    /**
     * The notice a game page shows in place of the game when it cannot be played in the season, if any.
     *
     * One answer for all four games, so that a game the administrator switched off does not fall into the
     * "no terms, configure a cartridge" notice of the two word games, which cannot tell a game that is off
     * from a season with nothing to play.
     *
     * @param stdClass|null $season The active season, or null when there is none.
     * @param string $gametype The game: quiz, guess, fill or battle.
     * @return string|null HTML of the notice, or null when the game can go on.
     */
    public static function blocked_html(?stdClass $season, string $gametype): ?string {
        if ($season === null) {
            return self::no_season_html();
        }
        if (season_game_config::is_disabled((int) $season->id, $gametype)) {
            return self::disabled_html();
        }
        return null;
    }
}
