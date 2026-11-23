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
 * Deletes everything the players earned, to start again from zero.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\local;

use local_playergames\event\data_reset as data_reset_event;

/**
 * Deletes the seasons and every record the plugin keeps about the players, and keeps the content and the settings.
 *
 * Deleting the seasons and the cartridges one by one leaves what belongs to no season: streaks, freezes,
 * achievements, avatar progress, the histories and the course XP mirrored from PlayerHUD. Only deleting the users
 * removed those. Every table of the plugin is in DELETED or in KEPT, and a test fails when a new one is in neither,
 * so a table added later never escapes the reset by being forgotten.
 *
 * @package    local_playergames
 */
class data_reset {
    /** @var array Tables deleted, by group as the page shows them; the first table of a group gives its count. */
    public const DELETED = [
        'seasons'    => ['local_playergames_seasons', 'local_playergames_season_games'],
        'results'    => ['local_playergames_season_summary'],
        'plays'      => [
            'local_playergames_daily_scores',
            'local_playergames_battle_scores',
            'local_playergames_rounds',
            'local_playergames_served_questions',
        ],
        'progress'   => ['local_playergames_player_profile', 'local_playergames_mission_progress'],
        'lifetime'   => [
            'local_playergames_streaks',
            'local_playergames_user_achievements',
            'local_playergames_player_avatars',
        ],
        'history'    => ['local_playergames_activity_log', 'local_playergames_freeze_log'],
        'learningxp' => ['local_playergames_student_xp_monthly', 'local_playergames_student_xp_cache'],
    ];

    /** @var string[] Tables kept: the content of the games and the definitions the site set up. */
    public const KEPT = [
        'local_playergames_cartridges',
        'local_playergames_concepts',
        'local_playergames_concept_questions',
        'local_playergames_concept_answers',
        'local_playergames_daily_assignments',
        'local_playergames_levels',
        'local_playergames_avatars',
        'local_playergames_missions',
        'local_playergames_achievements',
    ];

    /**
     * Records each group holds now.
     *
     * The seasons count the seasons alone: the game settings of a season go with it and say nothing more.
     *
     * @return int[] Keyed by group.
     */
    public static function counts(): array {
        global $DB;
        $counts = [];
        foreach (self::DELETED as $group => $tables) {
            $counted = $group === 'seasons' ? [$tables[0]] : $tables;
            $counts[$group] = 0;
            foreach ($counted as $table) {
                $counts[$group] += $DB->count_records($table);
            }
        }
        return $counts;
    }

    /**
     * Deletes every table of DELETED, in one transaction, and logs who did it.
     *
     * @return int Records deleted, counted as counts() counts them.
     */
    public static function run(): int {
        global $DB, $USER;

        $total = array_sum(self::counts());

        $transaction = $DB->start_delegated_transaction();
        foreach (self::DELETED as $tables) {
            foreach ($tables as $table) {
                $DB->delete_records($table);
            }
        }
        $transaction->allow_commit();

        // The rankings kept for a minute would still list the players for that minute.
        \cache::make('local_playergames', 'ranking')->purge();

        data_reset_event::create([
            'context'  => \context_system::instance(),
            'userid'   => (int) $USER->id,
            'other'    => ['records' => $total],
        ])->trigger();

        return $total;
    }
}
