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
 * Scheduled task definitions for local_playergames.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname'  => '\local_playergames\task\assign_daily_games',
        'blocking'   => 0,
        'minute'     => '0',
        'hour'       => '0',
        'day'        => '*',
        'month'      => '*',
        'dayofweek'  => '*',
    ],
    [
        'classname'  => '\local_playergames\task\reset_daily_missions',
        'blocking'   => 0,
        'minute'     => '0',
        'hour'       => '0',
        'day'        => '*',
        'month'      => '*',
        'dayofweek'  => '*',
    ],
    [
        'classname'  => '\local_playergames\task\close_expired_seasons',
        'blocking'   => 0,
        'minute'     => '30',
        'hour'       => '0',
        'day'        => '*',
        'month'      => '*',
        'dayofweek'  => '*',
    ],
    [
        'classname'  => '\local_playergames\task\purge_old_scores',
        'blocking'   => 0,
        'minute'     => '0',
        'hour'       => '2',
        'day'        => '*',
        'month'      => '*',
        'dayofweek'  => '0',
    ],
    [
        'classname'  => '\local_playergames\task\recompute_learning_xp',
        'blocking'   => 0,
        'minute'     => 'R',
        'hour'       => 'R',
        'day'        => '*',
        'month'      => '*',
        'dayofweek'  => '*',
    ],
];
