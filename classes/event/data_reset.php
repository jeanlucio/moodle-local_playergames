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
 * Event fired when an administrator deletes all the PlayerGames data.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\event;

/**
 * Fired by data_reset::run(), so the site log keeps who deleted the data and when.
 *
 * Other data: records — the number of records deleted.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class data_reset extends \core\event\base {
    #[\Override]
    protected function init(): void {
        $this->data['crud']     = 'd';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Returns the human-readable event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('event_data_reset', 'local_playergames');
    }

    /**
     * Returns a description of what happened.
     *
     * @return string
     */
    public function get_description(): string {
        $records = (int) ($this->other['records'] ?? 0);
        return "The user with id '{$this->userid}' deleted all the PlayerGames data: {$records} records.";
    }

    /**
     * The other data holds a count, which points at nothing a restore would map.
     *
     * @return bool
     */
    #[\Override]
    public static function get_other_mapping() {
        return false;
    }
}
