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
 * Test fixture mimicking \block_playerhud\event\xp_changed's shape.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\event;

/**
 * A minimal \core\event\base subclass exposing relateduserid and
 * other['delta'], the only shape observer::playerhud_xp_changed() relies on.
 *
 * block_playerhud's own classes are not present in this plugin's CI codebase
 * (it is a separate repository), so tests use this stand-in instead of the
 * real \block_playerhud\event\xp_changed class. core\event\base::create()
 * requires the class to live in a 'component\event\name' namespace, hence
 * the placement here despite being a fixture, not an autoloaded plugin class.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fake_xp_changed extends \core\event\base {
    #[\Override]
    protected function init(): void {
        $this->data['crud']     = 'u';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
    }
}
