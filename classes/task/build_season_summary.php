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
 * Adhoc task that saves the final results of a closed season.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\task;

use local_playergames\hub\season_summary;

/**
 * Writes the season_summary rows of one season, queued when the season closes.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class build_season_summary extends \core\task\adhoc_task {
    /**
     * Returns the human-readable task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_build_season_summary', 'local_playergames');
    }

    /**
     * Saves the results of the season named in the custom data.
     *
     * @return void
     */
    public function execute(): void {
        global $DB;

        $seasonid = (int) ($this->get_custom_data()->seasonid ?? 0);
        if ($seasonid <= 0 || !$DB->record_exists('local_playergames_seasons', ['id' => $seasonid])) {
            return;
        }
        $written = season_summary::build($seasonid);
        mtrace("Saved {$written} results for season {$seasonid}.");
    }
}
