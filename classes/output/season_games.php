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
 * The games of a season, for the page that configures them.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\output;

use local_playergames\games\season_game_config;
use local_playergames\games\word_pool;
use local_playergames\local\text;
use renderable;
use renderer_base;
use stdClass;
use templatable;

/**
 * Exports, for each game of a season, its state, its cartridges and how far their words go.
 */
class season_games implements renderable, templatable {
    /** @var stdClass[] Saved configuration of the season, keyed by game type. */
    private array $saved;

    /** @var stdClass[] Cartridges with id, name, type and active, in the order they are listed. */
    private array $cartridges;

    /** @var array Words each concept cartridge has for each game, as word_pool::usage() counts them. */
    private array $usage;

    /**
     * Constructor.
     *
     * @param int $seasonid Season id.
     */
    public function __construct(int $seasonid) {
        global $DB;
        $this->saved = season_game_config::get_all_for_season($seasonid);
        $this->cartridges = $DB->get_records('local_playergames_cartridges', null, 'name ASC', 'id, name, type, active');
        $this->usage = word_pool::usage();
    }

    /**
     * Exports one entry per game.
     *
     * @param renderer_base $output The renderer.
     * @return array Template data, under the key 'gamerows'.
     */
    public function export_for_template(renderer_base $output): array {
        $rows = [];
        foreach (season_game_config::FAMILIES as $gametype => $family) {
            $rows[] = $this->game_row($gametype, $family);
        }
        return ['gamerows' => $rows];
    }

    /**
     * Builds the entry of a game.
     *
     * A season with no list for the game draws from every cartridge, so all of them are ticked.
     *
     * @param string $gametype Game type.
     * @param string $family Type of the cartridges the game draws from.
     * @return array
     */
    private function game_row(string $gametype, string $family): array {
        $saved = $this->saved[$gametype] ?? null;
        $decoded = json_decode((string) ($saved->cartridgeids ?? ''), true);
        $chosen = is_array($decoded) ? array_map('intval', $decoded) : null;
        $enabled = $saved === null || (bool) $saved->enabled;
        $haswords = in_array($gametype, word_pool::games(), true);

        $rows = [];
        $usable = 0;
        foreach ($this->cartridges as $cartridge) {
            if ($cartridge->type !== $family) {
                continue;
            }
            $id = (int) $cartridge->id;
            $checked = $chosen === null || in_array($id, $chosen, true);
            $row = [
                'id' => $id,
                'name' => text::plain($cartridge->name),
                'checked' => $checked,
                'inactive' => !$cartridge->active,
            ];
            if ($haswords) {
                $count = $this->usage[$id] ?? [$gametype => 0, 'total' => 0];
                // The game draws only from active cartridges, so the words of an inactive one do not count.
                $usable += $checked && $cartridge->active ? $count[$gametype] : 0;
                $row['words'] = get_string(
                    'season_game_words',
                    'local_playergames',
                    ['usable' => $count[$gametype], 'total' => $count['total']]
                );
            }
            $rows[] = $row;
        }

        return [
            'gametype' => $gametype,
            'gamename' => get_string('hub_game_' . $gametype, 'local_playergames'),
            'family' => $family,
            'enabled' => $enabled,
            'cartridges' => $rows,
            'nocartridges' => $rows === [],
            'nonechecked' => $rows !== [] && !in_array(true, array_column($rows, 'checked'), true),
            'wordrule' => $haswords ? $this->word_rule($gametype) : '',
            'fewwords' => $haswords && $enabled && $rows !== [] ? $this->few_words($gametype, $usable) : '',
        ];
    }

    /**
     * Says which words serve a game.
     *
     * @param string $gametype 'guess' or 'fill'.
     * @return string
     */
    private function word_rule(string $gametype): string {
        [$min, $max] = word_pool::length_range($gametype);
        return get_string('season_game_words_rule', 'local_playergames', ['min' => $min, 'max' => $max]);
    }

    /**
     * Warns that the cartridges in use do not have the words a game needs.
     *
     * @param string $gametype 'guess' or 'fill'.
     * @param int $usable How many words they have.
     * @return string Empty when they are enough.
     */
    private function few_words(string $gametype, int $usable): string {
        $needed = word_pool::needed($gametype);
        return $usable >= $needed ? '' : get_string(
            'season_game_words_few',
            'local_playergames',
            ['usable' => $usable, 'needed' => $needed]
        );
    }
}
