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
 * An mtrace() wrapper that records the lines instead of printing them.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\task;

/**
 * Records a line of mtrace(), to be set as $CFG->mtrace_wrapper.
 *
 * @param string $line The line.
 * @param string $eol The end of the line.
 * @return void
 */
function mtrace_recorder(string $line, string $eol = "\n"): void {
    global $CFG;
    $CFG->local_playergames_mtrace_lines[] = $line;
}
