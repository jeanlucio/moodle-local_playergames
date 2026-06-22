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

/**
 * Reads per-season game configuration from local_playergames_season_games.
 */
class season_game_config {
    /** @var string Source: cartridge only. */
    const SOURCE_CARTRIDGE = 'cartridge';

    /** @var string Source: Moodle question bank only (quiz/battle). */
    const SOURCE_QUESTIONBANK = 'questionbank';

    /** @var string Source: Moodle glossary only (guess/fill). */
    const SOURCE_GLOSSARY = 'glossary';

    /** @var string Source: both cartridge and secondary source. */
    const SOURCE_BOTH = 'both';

    /** @var string[] Game type keys configurable per season. */
    const GAMETYPES = ['quiz', 'battle', 'guess', 'fill'];

    /**
     * Seeds default game configuration rows for a season.
     *
     * Each game starts enabled, drawing from cartridges only. Game types that
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
                'source'       => self::SOURCE_CARTRIDGE,
                'cartridgeids' => null,
                'auxid'        => 0,
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
            'gametype, id, enabled, source, cartridgeids, auxid'
        );
    }

    /**
     * Whether a given source value includes the cartridge source.
     *
     * @param string $source Source constant value.
     * @return bool
     */
    public static function uses_cartridge(string $source): bool {
        return $source === self::SOURCE_CARTRIDGE || $source === self::SOURCE_BOTH;
    }

    /**
     * Whether a given source value includes the secondary source (questionbank or glossary).
     *
     * @param string $source Source constant value.
     * @return bool
     */
    public static function uses_secondary(string $source): bool {
        return $source !== self::SOURCE_CARTRIDGE;
    }
}
