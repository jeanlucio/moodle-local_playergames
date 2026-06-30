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
 * Avatar collection manager: catalog, level-tier unlocks and equip state.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\hub;

use stdClass;

/**
 * Manages the emoji avatar collection unlocked by level tier.
 *
 * Avatars are emoji (no File API), recreated natively in PlayerGames as a
 * level-tier reward (there is no shop here). Unlocks are permanent: based on
 * the highest level a user has ever reached (stored per user, season-agnostic),
 * so closing a season never takes an avatar away.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class avatar_manager {
    /**
     * Default catalog: emoji => [name lang key, tier]. 5/4/4/4 across 4 tiers.
     *
     * @var array<string, array{0: string, 1: int}>
     */
    const DEFAULT_AVATARS = [
        '🤖' => ['avatar_name_robot', 1],
        '👾' => ['avatar_name_alien', 1],
        '🦊' => ['avatar_name_fox', 1],
        '🐱' => ['avatar_name_cat', 1],
        '🐶' => ['avatar_name_dog', 1],
        '🕵️' => ['avatar_name_detective', 2],
        '🤺' => ['avatar_name_fencer', 2],
        '🧚' => ['avatar_name_fairy', 2],
        '🧜' => ['avatar_name_mermaid', 2],
        '🧝' => ['avatar_name_elf', 3],
        '🧙' => ['avatar_name_mage', 3],
        '🦸' => ['avatar_name_superhero', 3],
        '🥷' => ['avatar_name_ninja', 3],
        '🧛' => ['avatar_name_vampire', 4],
        '🦹' => ['avatar_name_supervillain', 4],
        '🐉' => ['avatar_name_dragon', 4],
        '🦁' => ['avatar_name_lion', 4],
    ];

    /**
     * Default minimum level to unlock each tier.
     *
     * @var array<int, int>
     */
    const DEFAULT_TIER_LEVELS = [1 => 1, 2 => 5, 3 => 10, 4 => 20];

    /**
     * Returns the avatar catalog ordered by tier then sortorder.
     *
     * Seeds the default 17-avatar collection the first time the table is empty.
     *
     * @return stdClass[]
     */
    public static function get_avatars(): array {
        global $DB;
        $avatars = array_values($DB->get_records('local_playergames_avatars', null, 'tier ASC, sortorder ASC'));
        if (empty($avatars)) {
            self::seed_defaults();
            $avatars = array_values($DB->get_records('local_playergames_avatars', null, 'tier ASC, sortorder ASC'));
        }
        return $avatars;
    }

    /**
     * Inserts the default 17-avatar collection, translating names at seed time.
     *
     * @return void
     */
    public static function seed_defaults(): void {
        global $DB;
        if ($DB->record_exists('local_playergames_avatars', [])) {
            return;
        }
        $records   = [];
        $sortorder = 0;
        foreach (self::DEFAULT_AVATARS as $emoji => [$namekey, $tier]) {
            $record            = new stdClass();
            $record->emoji     = $emoji;
            $record->name      = get_string($namekey, 'local_playergames');
            $record->tier      = $tier;
            $record->sortorder = $sortorder++;
            $records[] = $record;
        }
        $DB->insert_records('local_playergames_avatars', $records);
    }

    /**
     * Returns the minimum level required to unlock a tier.
     *
     * Reads the admin setting when present; otherwise falls back to the default.
     *
     * @param int $tier Tier number (1–4).
     * @return int Minimum level.
     */
    public static function tier_threshold(int $tier): int {
        $configured = get_config('local_playergames', 'avatar_tier' . $tier . '_level');
        if ($configured !== false && $configured !== '') {
            return (int) $configured;
        }
        return self::DEFAULT_TIER_LEVELS[$tier] ?? 1;
    }

    /**
     * Returns the per-user lifetime avatar state, creating it if absent.
     *
     * @param int $userid User id.
     * @return stdClass Row from local_playergames_player_avatars.
     */
    public static function get_state(int $userid): stdClass {
        global $DB;
        $state = $DB->get_record('local_playergames_player_avatars', ['userid' => $userid]);
        if ($state) {
            return $state;
        }
        $now   = time();
        $state = (object) [
            'userid'          => $userid,
            'bestlevel'       => 1,
            'equipped_avatar' => null,
            'timecreated'     => $now,
            'timemodified'    => $now,
        ];
        $state->id = $DB->insert_record('local_playergames_player_avatars', $state);
        return $state;
    }

    /**
     * Records a reached level, keeping the user's best (highest) level ever.
     *
     * Called whenever XP is awarded; only writes when the level grows, so
     * unlocks are permanent across seasons.
     *
     * @param int $userid User id.
     * @param int $level Level just reached.
     * @return void
     */
    public static function record_level(int $userid, int $level): void {
        global $DB;
        $state = self::get_state($userid);
        if ($level > (int) $state->bestlevel) {
            $state->bestlevel    = $level;
            $state->timemodified = time();
            $DB->update_record('local_playergames_player_avatars', $state);
        }
    }

    /**
     * Whether a given emoji avatar is unlocked at the given best level.
     *
     * @param string $emoji Avatar emoji.
     * @param int $bestlevel Highest level the user has reached.
     * @return bool
     */
    public static function is_unlocked(string $emoji, int $bestlevel): bool {
        foreach (self::get_avatars() as $avatar) {
            if ($avatar->emoji === $emoji) {
                return $bestlevel >= self::tier_threshold((int) $avatar->tier);
            }
        }
        return false;
    }

    /**
     * Equips an unlocked avatar for the user (empty string unequips).
     *
     * @param int $userid User id.
     * @param string $emoji Avatar emoji, or '' to unequip.
     * @return void
     * @throws \moodle_exception When the avatar is not unlocked for the user.
     */
    public static function equip(int $userid, string $emoji): void {
        global $DB;
        $state = self::get_state($userid);
        if ($emoji !== '' && !self::is_unlocked($emoji, (int) $state->bestlevel)) {
            throw new \moodle_exception('avatar_locked', 'local_playergames');
        }
        $state->equipped_avatar = ($emoji === '') ? null : $emoji;
        $state->timemodified    = time();
        $DB->update_record('local_playergames_player_avatars', $state);
    }

    /**
     * Returns the equipped avatar emoji for a user (empty when none). Read-only.
     *
     * @param int $userid User id.
     * @return string Equipped emoji, or '' when none.
     */
    public static function get_equipped(int $userid): string {
        global $DB;
        $state = $DB->get_record('local_playergames_player_avatars', ['userid' => $userid]);
        return ($state && $state->equipped_avatar) ? (string) $state->equipped_avatar : '';
    }

    /**
     * Returns the catalog annotated for a user. Read-only (no row creation).
     *
     * Each entry: emoji, name, tier, requiredlevel, and the unlocked/equipped
     * booleans based on the user's lifetime best level.
     *
     * @param int $userid User id.
     * @return array<int, array{emoji: string, name: string, tier: int,
     *     requiredlevel: int, unlocked: bool, equipped: bool}>
     */
    public static function get_collection(int $userid): array {
        global $DB;
        $state     = $DB->get_record('local_playergames_player_avatars', ['userid' => $userid]);
        $bestlevel = $state ? (int) $state->bestlevel : 1;
        $equipped  = ($state && $state->equipped_avatar) ? (string) $state->equipped_avatar : '';

        $collection = [];
        foreach (self::get_avatars() as $avatar) {
            $required     = self::tier_threshold((int) $avatar->tier);
            $collection[] = [
                'emoji'         => $avatar->emoji,
                'name'          => $avatar->name,
                'tier'          => (int) $avatar->tier,
                'requiredlevel' => $required,
                'unlocked'      => $bestlevel >= $required,
                'equipped'      => $equipped !== '' && $equipped === $avatar->emoji,
            ];
        }
        return $collection;
    }
}
