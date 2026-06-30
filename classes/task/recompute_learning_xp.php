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
 * Scheduled task: recompute the windowed learning XP cache.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\task;

use local_playergames\hub\learning_xp_manager;

/**
 * Nightly batch recalculation of student_xp_cache.windowedxp from the monthly
 * buckets, and pruning of buckets that have aged out of the window.
 *
 * The observer already applies new XP changes to the cache immediately
 * (online); this task only corrects the window's aging (a bucket leaving the
 * window is dropped here, never on the hot path).
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class recompute_learning_xp extends \core\task\scheduled_task {
    /**
     * Returns the human-readable task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_recompute_learning_xp', 'local_playergames');
    }

    /**
     * Recomputes the windowed learning XP cache for every user.
     *
     * @return void
     */
    public function execute(): void {
        learning_xp_manager::recompute_all();
        mtrace('Recomputed the windowed learning XP cache.');
    }
}
