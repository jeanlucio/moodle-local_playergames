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
 * The cards of the Administration tab: what an administrator sets up, grouped in the order it is done.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\local;

use context_system;
use local_playergames\games\season_game_config;
use local_playergames\hub\season_manager;
use moodle_url;

/**
 * Builds the Administration tab for a user.
 *
 * Each card carries the permission its destination asks for, and a user gets only the cards they can
 * open. The same groups name the categories of the plugin in the Moodle administration tree.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class admin_cards {
    /** @var string Site documentation, in English. */
    private const DOCS_URL = 'https://jeanlucio.github.io/moodle-local_playergames/';

    /** @var string The same documentation in Portuguese. */
    private const DOCS_URL_PT = 'https://jeanlucio.github.io/moodle-local_playergames/pt.html';

    /** @var string Permission to manage the cartridges, which a manager has. */
    private const CAP_CARTRIDGES = 'local/playergames:managecartridges';

    /** @var string Permission to configure the game, which only a site administrator has by default. */
    private const CAP_CONFIG = 'moodle/site:config';

    /**
     * Whether the user can open at least one card, which is what shows them the tab.
     *
     * @param int $userid The user.
     * @return bool
     */
    public static function can_open_any(int $userid): bool {
        $context = context_system::instance();
        return has_capability(self::CAP_CONFIG, $context, $userid)
            || has_capability(self::CAP_CARTRIDGES, $context, $userid);
    }

    /**
     * The groups of cards the user can see, without the empty ones.
     *
     * @param int $userid The user.
     * @return array[] Groups: {key, title, cards}, each card {key, name, description, url, state, external}.
     */
    public static function for_user(int $userid): array {
        $context = context_system::instance();
        $groups  = [];
        foreach (self::definitions() as $group => $cards) {
            $visible = [];
            foreach ($cards as $card) {
                if (!self::can_see($card, $context, $userid)) {
                    continue;
                }
                $state     = isset($card['state']) ? $card['state']($userid) : '';
                $visible[] = [
                    'key'         => $card['key'],
                    'name'        => get_string($card['name'], 'local_playergames'),
                    'description' => get_string('admin_desc_' . $card['key'], 'local_playergames'),
                    'url'         => $card['url']->out(false),
                    'state'       => $state,
                    'hasstate'    => $state !== '',
                    'external'    => !empty($card['external']),
                ];
            }
            if ($visible !== []) {
                $groups[] = [
                    'key'   => $group,
                    'title' => get_string('admin_group_' . $group, 'local_playergames'),
                    'cards' => $visible,
                ];
            }
        }
        return $groups;
    }

    /**
     * Whether a card is for this user.
     *
     * @param array $card The card definition.
     * @param context_system $context The system context.
     * @param int $userid The user.
     * @return bool
     */
    private static function can_see(array $card, context_system $context, int $userid): bool {
        if (isset($card['available']) && !$card['available']()) {
            return false;
        }
        if (isset($card['cap'])) {
            return has_capability($card['cap'], $context, $userid);
        }
        // A card with no permission of its own is for anyone who sees the tab.
        return self::can_open_any($userid);
    }

    /**
     * The cards, by group, in the order they are shown. Mirrors the groups of the administration tree.
     *
     * @return array[]
     */
    private static function definitions(): array {
        $setting = static fn(string $section): moodle_url => new moodle_url('/admin/settings.php', ['section' => $section]);
        $docs    = current_language() === 'pt_br' ? self::DOCS_URL_PT : self::DOCS_URL;

        return [
            'start' => [
                [
                    'key' => 'cartridges', 'name' => 'admin_item_cartridges', 'cap' => self::CAP_CARTRIDGES,
                    'url' => new moodle_url('/local/playergames/cartridge.php'),
                    'state' => [self::class, 'state_cartridges'],
                ],
                [
                    'key' => 'seasons', 'name' => 'admin_item_seasons', 'cap' => self::CAP_CONFIG,
                    'url' => new moodle_url('/local/playergames/manage_seasons.php'),
                    'state' => [self::class, 'state_seasons'],
                ],
                [
                    'key' => 'seasongames', 'name' => 'admin_item_seasongames', 'cap' => self::CAP_CONFIG,
                    'url' => new moodle_url('/local/playergames/manage_season_games.php'),
                    'state' => [self::class, 'state_season_games'],
                ],
            ],
            'rules' => [
                [
                    'key' => 'participation', 'name' => 'season_setup_heading', 'cap' => self::CAP_CONFIG,
                    'url' => $setting('local_playergames_seasons'),
                    'state' => [self::class, 'state_participation'],
                ],
                [
                    'key' => 'xp', 'name' => 'settings_xp_heading', 'cap' => self::CAP_CONFIG,
                    'url' => $setting('local_playergames_xp'),
                ],
                [
                    'key' => 'games', 'name' => 'settings_games_heading', 'cap' => self::CAP_CONFIG,
                    'url' => $setting('local_playergames_games'),
                ],
                [
                    'key' => 'levels', 'name' => 'admin_item_levels', 'cap' => self::CAP_CONFIG,
                    'url' => new moodle_url('/local/playergames/manage_levels.php'),
                    'state' => [self::class, 'state_levels'],
                ],
                [
                    'key' => 'avatars', 'name' => 'settings_avatars_heading', 'cap' => self::CAP_CONFIG,
                    'url' => $setting('local_playergames_avatars'),
                ],
                [
                    'key' => 'streak', 'name' => 'settings_streak_heading', 'cap' => self::CAP_CONFIG,
                    'url' => $setting('local_playergames_streak'),
                ],
                [
                    'key' => 'learning', 'name' => 'settings_learningxp_heading', 'cap' => self::CAP_CONFIG,
                    'url' => $setting('local_playergames_learning'),
                ],
            ],
            'help' => [
                [
                    'key' => 'aikeys', 'name' => 'admin_item_aikeys', 'cap' => self::CAP_CONFIG,
                    'url' => $setting('local_aihub'),
                    'available' => [self::class, 'has_ai_hub'],
                    'state' => [self::class, 'state_ai_keys'],
                ],
                [
                    'key' => 'docs', 'name' => 'admin_item_docs',
                    'url' => new moodle_url($docs), 'external' => true,
                ],
            ],
        ];
    }

    /**
     * Whether the AI Hub plugin, where the AI keys are kept, is installed.
     *
     * @return bool
     */
    public static function has_ai_hub(): bool {
        return \core_component::get_component_directory('local_aihub') !== null;
    }

    /**
     * The state of the cartridges card.
     *
     * @param int $userid The user.
     * @return string
     */
    public static function state_cartridges(int $userid): string {
        global $DB;

        $ids = $DB->get_fieldset_select('local_playergames_cartridges', 'id', 'active = 1');
        if ($ids === []) {
            return get_string('admin_state_cartridges_none', 'local_playergames');
        }
        [$insql, $params] = $DB->get_in_or_equal($ids);
        $items = $DB->count_records_select('local_playergames_concept_questions', "cartridgeid $insql", $params)
            + $DB->count_records_select('local_playergames_concepts', "cartridgeid $insql", $params);
        return get_string('admin_state_cartridges', 'local_playergames', (object) [
            'cartridges' => count($ids),
            'items' => $items,
        ]);
    }

    /**
     * The state of the seasons card: the active season, else the next one.
     *
     * @param int $userid The user.
     * @return string
     */
    public static function state_seasons(int $userid): string {
        $season = season_manager::get_active_or_upcoming();
        if ($season === null) {
            return get_string('admin_state_season_none', 'local_playergames');
        }
        if ($season->status === season_manager::STATUS_ACTIVE) {
            return get_string('admin_state_season_active', 'local_playergames', text::plain($season->name));
        }
        return get_string(
            'admin_state_season_upcoming',
            'local_playergames',
            userdate((int) $season->startdate, get_string('strftimedate', 'langconfig'))
        );
    }

    /**
     * The state of the season games card: how many of the games the active (or next) season runs.
     *
     * @param int $userid The user.
     * @return string
     */
    public static function state_season_games(int $userid): string {
        global $DB;

        $season = season_manager::get_active_or_upcoming();
        if ($season === null) {
            return get_string('admin_state_games_none', 'local_playergames');
        }
        $on = $DB->count_records('local_playergames_season_games', ['seasonid' => $season->id, 'enabled' => 1]);
        return get_string('admin_state_games', 'local_playergames', (object) [
            'on' => $on,
            'total' => count(season_game_config::GAMETYPES),
        ]);
    }

    /**
     * The state of the participation card: who plays.
     *
     * @param int $userid The user.
     * @return string
     */
    public static function state_participation(int $userid): string {
        $allowed = get_config('local_playergames', 'allowed_participants') ?: 'students';
        $key = ['staff' => 'settings_participants_staff', 'both' => 'settings_participants_both'][$allowed]
            ?? 'settings_participants_students';
        return get_string('admin_state_participation', 'local_playergames', get_string($key, 'local_playergames'));
    }

    /**
     * The state of the levels card: how many levels the ladder has.
     *
     * @param int $userid The user.
     * @return string Empty before the ladder was ever created.
     */
    public static function state_levels(int $userid): string {
        global $DB;

        $count = $DB->count_records('local_playergames_levels');
        return $count > 0 ? get_string('admin_state_levels', 'local_playergames', $count) : '';
    }

    /**
     * The state of the AI keys card: whether a key can be used for the user.
     *
     * @param int $userid The user.
     * @return string
     */
    public static function state_ai_keys(int $userid): string {
        $available = class_exists(\local_aihub\ai::class) && \local_aihub\ai::is_available($userid);
        return get_string($available ? 'admin_state_aikeys_ok' : 'admin_state_aikeys_none', 'local_playergames');
    }
}
