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
 * Season game configuration helper.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\games;

use local_playergames\hub\season_manager;
use local_playergames\local\day;

/**
 * Reads per-season game configuration from local_playergames_season_games.
 */
class season_game_config {
    /**
     * @var string[] Every game of the hub, in the order the hub shows them.
     *
     * The one list of the games: XP settings, the season snapshot, the hub, the achievement for playing every game
     * and the season page all read it, so a new game added here reaches all of them.
     */
    const GAMETYPES = ['quiz', 'guess', 'fill', 'battle'];

    /** @var string[] The kind of cartridge each game draws from, by game, in the order of the season page. */
    const FAMILIES = ['quiz' => 'quiz', 'battle' => 'quiz', 'guess' => 'concept', 'fill' => 'concept'];

    /** @var string[] The Font Awesome icon of each game in the hub, by game. */
    const ICONS = [
        'quiz' => 'fa-question-circle',
        'guess' => 'fa-spell-check',
        'fill' => 'fa-crosshairs',
        'battle' => 'fa-dragon',
    ];

    /**
     * Seeds default game configuration rows for a season.
     *
     * Each game starts enabled, drawing from all its cartridges. Game types that
     * already have a row for this season are left untouched, so the method is
     * safe to call on existing seasons (e.g. from an upgrade step).
     *
     * @param int $seasonid Season id.
     * @return void
     */
    public static function seed_defaults(int $seasonid): void {
        global $DB;

        $existing = $DB->get_fieldset_select(
            'local_playergames_season_games',
            'gametype',
            'seasonid = ?',
            [$seasonid]
        );

        $records = [];
        foreach (self::GAMETYPES as $gametype) {
            if (in_array($gametype, $existing, true)) {
                continue;
            }
            $records[] = (object) [
                'seasonid'     => $seasonid,
                'gametype'     => $gametype,
                'enabled'      => 1,
                'cartridgeids' => null,
            ];
        }

        if (!empty($records)) {
            $DB->insert_records('local_playergames_season_games', $records);
        }
    }

    /**
     * Returns the enabled season_games record for the active season and given game type.
     *
     * Returns null when there is no active season, no record for this game type,
     * or the record has enabled = 0.
     *
     * @param string $gametype Game type key (e.g. 'quiz', 'battle', 'guess', 'fill').
     * @return \stdClass|null
     */
    public static function get_for_active_season(string $gametype): ?\stdClass {
        global $DB;

        $season = season_manager::get_active();
        if ($season === null) {
            return null;
        }

        $record = $DB->get_record('local_playergames_season_games', [
            'seasonid' => (int) $season->id,
            'gametype' => $gametype,
            'enabled'  => 1,
        ]);

        return $record ?: null;
    }

    /**
     * Drops today's words of the concept games that came from a cartridge the season no longer keeps for them.
     *
     * The word of the day is drawn at midnight from the cartridges the season keeps for the game, so narrowing
     * the list during the day would leave the old word being served until the next midnight.
     *
     * @param int $seasonid Season id.
     * @return bool Whether the words of a game were dropped, so the caller draws them again.
     */
    public static function release_todays_words_outside(int $seasonid): bool {
        global $DB;

        $today = day::today();
        $dropped = false;
        foreach (['guess', 'fill'] as $gametype) {
            $config = $DB->get_record('local_playergames_season_games', ['seasonid' => $seasonid, 'gametype' => $gametype]);
            $kept = $config && $config->cartridgeids !== null ? json_decode((string) $config->cartridgeids, true) : null;
            if (!is_array($kept) || $kept === []) {
                continue;
            }

            [$insql, $params] = $DB->get_in_or_equal(array_map('intval', $kept), SQL_PARAMS_NAMED, 'kept', false);
            $params += ['today' => $today, 'gametype' => $gametype];
            $outside = $DB->record_exists_sql(
                "SELECT 1
                   FROM {local_playergames_daily_assignments} a
                   JOIN {local_playergames_concepts} c ON c.id = a.conceptid
                  WHERE a.gamedate = :today AND a.gametype = :gametype AND c.cartridgeid $insql",
                $params
            );
            if ($outside) {
                $DB->delete_records('local_playergames_daily_assignments', ['gamedate' => $today, 'gametype' => $gametype]);
                $dropped = true;
            }
        }

        return $dropped;
    }

    /**
     * Whether the admin switched a game off for a season.
     *
     * get_for_active_season() returns null both for a game that was switched off and for a season
     * with no row for it (one created before per-season configuration), so it cannot tell the two
     * apart. Only the first must stop the game: Quiz and Battle keep a cartridge fallback for the second.
     *
     * @param int $seasonid Season id.
     * @param string $gametype Game type key.
     * @return bool True only when a row exists for the game with enabled = 0.
     */
    public static function is_disabled(int $seasonid, string $gametype): bool {
        global $DB;

        $enabled = $DB->get_field(
            'local_playergames_season_games',
            'enabled',
            ['seasonid' => $seasonid, 'gametype' => $gametype]
        );

        return $enabled !== false && (int) $enabled === 0;
    }

    /**
     * Returns all season_games records for a given season, keyed by gametype.
     *
     * @param int $seasonid Season id.
     * @return \stdClass[] Array keyed by gametype.
     */
    public static function get_all_for_season(int $seasonid): array {
        global $DB;

        return $DB->get_records(
            'local_playergames_season_games',
            ['seasonid' => $seasonid],
            '',
            'gametype, id, enabled, cartridgeids'
        );
    }
}
