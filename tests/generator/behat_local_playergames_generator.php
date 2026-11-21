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
 * Behat data generator of PlayerGames.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Lets a feature create PlayerGames data with "the following "local_playergames > ..." exist".
 *
 * @package    local_playergames
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_local_playergames_generator extends behat_generator_base {
    #[\Override]
    protected function get_creatable_entities(): array {
        return [
            'seasons' => [
                'singular' => 'season',
                'datagenerator' => 'season',
                'required' => ['name'],
            ],
            'cartridges' => [
                'singular' => 'cartridge',
                'datagenerator' => 'cartridge',
                'required' => ['name'],
            ],
            'concepts' => [
                'singular' => 'concept',
                'datagenerator' => 'concept',
                'required' => ['cartridge', 'term', 'definition'],
                'switchids' => ['cartridge' => 'cartridgeid'],
            ],
            'quiz questions' => [
                'singular' => 'quiz question',
                'datagenerator' => 'quiz_question',
                'required' => ['cartridge', 'questiontext', 'correct'],
                'switchids' => ['cartridge' => 'cartridgeid'],
            ],
        ];
    }

    /**
     * Looks up a cartridge by its name.
     *
     * @param string $name The name of the cartridge.
     * @return int Its id.
     */
    protected function get_cartridge_id(string $name): int {
        global $DB;
        $id = $DB->get_field('local_playergames_cartridges', 'id', ['name' => $name]);
        if (!$id) {
            throw new Exception('There is no PlayerGames cartridge named "' . $name . '".');
        }
        return (int) $id;
    }
}
