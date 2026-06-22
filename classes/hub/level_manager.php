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
 * Configurable level ladder manager for local_playergames.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\hub;

use stdClass;

/**
 * Source of truth for the level ladder (minimum XP and title per level).
 *
 * The ladder lives in local_playergames_levels and is editable by admins.
 * On a fresh table it is seeded with the default 5-tier progression, whose
 * titles are taken from the level_title_{n} lang strings at seed time. From
 * then on the stored rows win, so admin edits are preserved. Admins can also
 * generate a longer linear ladder (fixed XP per level) via generate_linear().
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class level_manager {
    /**
     * Default ladder: level number => [minimum cumulative XP, title lang key].
     *
     * Five proportional tiers spread across a full season so the progression
     * stays meaningful with the daily XP caps.
     *
     * @var array<int, array{0: int, 1: string}>
     */
    const DEFAULT_LADDER = [
        1 => [0, 'level_title_1'],
        2 => [2000, 'level_title_2'],
        3 => [6000, 'level_title_3'],
        4 => [12000, 'level_title_4'],
        5 => [20000, 'level_title_5'],
    ];

    /** @var int Upper bound for generate_linear() to keep the editable table sane. */
    const MAX_GENERATED_LEVELS = 100;

    /** @var int Number of proportional title tiers used by generate_linear(). */
    const TITLE_TIERS = 5;

    /**
     * Returns the ladder rows ordered by ascending minimum XP (i.e. by level).
     *
     * Seeds the default ladder the first time the table is found empty.
     *
     * @return stdClass[]
     */
    public static function get_levels(): array {
        global $DB;
        $levels = array_values($DB->get_records('local_playergames_levels', null, 'minxp ASC'));
        if (empty($levels)) {
            self::seed_defaults();
            $levels = array_values($DB->get_records('local_playergames_levels', null, 'minxp ASC'));
        }
        return $levels;
    }

    /**
     * Inserts the default 5-tier ladder, translating titles at seed time.
     *
     * @return void
     */
    public static function seed_defaults(): void {
        global $DB;
        if ($DB->record_exists('local_playergames_levels', [])) {
            return;
        }
        $records = [];
        foreach (self::DEFAULT_LADDER as $level => [$minxp, $titlekey]) {
            $record        = new stdClass();
            $record->level = $level;
            $record->minxp = $minxp;
            $record->title = get_string($titlekey, 'local_playergames');
            $records[] = $record;
        }
        $DB->insert_records('local_playergames_levels', $records);
    }

    /**
     * Replaces the whole ladder with the given rows, renumbering by XP.
     *
     * Each row is ['minxp' => int, 'title' => string]. Rows are sorted by XP,
     * the lowest is forced to 0, and levels are renumbered 1..N.
     *
     * @param array $rows Each entry: ['minxp' => int, 'title' => string].
     * @return void
     */
    public static function save_ladder(array $rows): void {
        global $DB;
        usort($rows, fn(array $a, array $b): int => $a['minxp'] <=> $b['minxp']);

        $records = [];
        $level   = 1;
        foreach ($rows as $row) {
            $record        = new stdClass();
            $record->level = $level;
            $record->minxp = $level === 1 ? 0 : (int) $row['minxp'];
            $record->title = $row['title'];
            $records[] = $record;
            $level++;
        }

        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records('local_playergames_levels');
        $DB->insert_records('local_playergames_levels', $records);
        $transaction->allow_commit();
    }

    /**
     * Generates a linear ladder: a fixed XP step per level up to a maximum.
     *
     * Level L requires (L - 1) * $xpperlevel cumulative XP, so level 1 is always
     * 0. Titles are assigned from the five level_title_{1..5} strings spread
     * proportionally across the ladder. The whole ladder is replaced; admins can
     * still fine-tune individual rows afterwards. This spares admins from typing
     * a long ladder (e.g. 100 levels) by hand.
     *
     * @param int $xpperlevel XP step added at each level (clamped to >= 1).
     * @param int $maxlevel Number of levels to generate (clamped to 2..MAX_GENERATED_LEVELS).
     * @return void
     */
    public static function generate_linear(int $xpperlevel, int $maxlevel): void {
        global $DB;

        $xpperlevel = max(1, $xpperlevel);
        $maxlevel   = max(2, min($maxlevel, self::MAX_GENERATED_LEVELS));

        $tiers = [];
        for ($t = 1; $t <= self::TITLE_TIERS; $t++) {
            $tiers[] = get_string('level_title_' . $t, 'local_playergames');
        }

        $records = [];
        for ($level = 1; $level <= $maxlevel; $level++) {
            $tierindex = (int) floor(($level - 1) * self::TITLE_TIERS / $maxlevel);
            $tierindex = max(0, min(self::TITLE_TIERS - 1, $tierindex));

            $record        = new stdClass();
            $record->level = $level;
            $record->minxp = ($level - 1) * $xpperlevel;
            $record->title = $tiers[$tierindex];
            $records[] = $record;
        }

        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records('local_playergames_levels');
        $DB->insert_records('local_playergames_levels', $records);
        $transaction->allow_commit();
    }

    /**
     * Resets the ladder to the built-in default progression.
     *
     * @return void
     */
    public static function restore_defaults(): void {
        global $DB;
        $DB->delete_records('local_playergames_levels');
        self::seed_defaults();
    }

    /**
     * Returns the level corresponding to a cumulative XP total.
     *
     * @param int $xp Total cumulative XP.
     * @return int
     */
    public static function level_for_xp(int $xp): int {
        $level = 1;
        foreach (self::get_levels() as $row) {
            if ($xp >= (int) $row->minxp) {
                $level = (int) $row->level;
            } else {
                break;
            }
        }
        return $level;
    }

    /**
     * Returns the minimum XP required to reach a given level (clamped).
     *
     * @param int $level Target level.
     * @return int
     */
    public static function minxp_for_level(int $level): int {
        $bylevel = self::by_level();
        if (isset($bylevel[$level])) {
            return (int) $bylevel[$level]->minxp;
        }
        $level = max(1, min($level, self::max_level()));
        return isset($bylevel[$level]) ? (int) $bylevel[$level]->minxp : 0;
    }

    /**
     * Returns the title for a given level (clamped to the valid range).
     *
     * @param int $level Player level.
     * @return string
     */
    public static function title_for_level(int $level): string {
        $bylevel = self::by_level();
        if (!isset($bylevel[$level])) {
            $level = max(1, min($level, self::max_level()));
        }
        return isset($bylevel[$level]) ? format_string($bylevel[$level]->title) : '';
    }

    /**
     * Returns the highest configured level.
     *
     * @return int
     */
    public static function max_level(): int {
        $levels = self::get_levels();
        if (empty($levels)) {
            return 1;
        }
        return (int) end($levels)->level;
    }

    /**
     * Returns the ladder rows keyed by level number.
     *
     * @return array<int, stdClass>
     */
    private static function by_level(): array {
        $bylevel = [];
        foreach (self::get_levels() as $row) {
            $bylevel[(int) $row->level] = $row;
        }
        return $bylevel;
    }
}
