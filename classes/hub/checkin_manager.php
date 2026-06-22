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
 * Records the daily check-in for a Player Hub visitor.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\hub;

use context_system;
use local_playergames\local\access;
use local_playergames\local\preferences;
use stdClass;

/**
 * Awards the once-per-day check-in.
 *
 * The caller (hub.php) is responsible for access control — by the time the hub
 * renders, the per-user opt-out and the allowed_participants restriction have
 * already been enforced, so this only needs the season and the per-day guard.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class checkin_manager {
    /**
     * Records today's check-in for the user if not already done this day.
     *
     * @param int $userid The user checking in.
     * @return void
     */
    public static function record(int $userid): void {
        global $DB;

        $season = season_manager::get_active();
        if (!$season) {
            return;
        }

        $snapshot  = season_manager::get_config_snapshot($season);
        $checkinxp = isset($snapshot['xp_checkin_daily']) ? (int) $snapshot['xp_checkin_daily'] : 5;
        $gamedate  = mktime(0, 0, 0, (int) date('n'), (int) date('j'), (int) date('Y'));

        $alreadydone = $DB->record_exists(
            'local_playergames_daily_scores',
            ['userid' => $userid, 'gamedate' => $gamedate, 'gametype' => 'checkin']
        );
        if ($alreadydone) {
            return;
        }

        $record             = new stdClass();
        $record->userid     = $userid;
        $record->gamedate   = $gamedate;
        $record->gametype   = 'checkin';
        $record->completed  = 1;
        $record->xpawarded  = 0;
        $record->attempts   = 1;
        $record->timeplayed = time();

        try {
            $DB->insert_record('local_playergames_daily_scores', $record);
        } catch (\dml_write_exception $e) {
            // A concurrent request already inserted today's check-in.
            return;
        }

        $awarded = xp_manager::award($userid, $checkinxp, 'checkin', $gamedate, (int) $season->id);
        if ($awarded > 0) {
            $DB->set_field(
                'local_playergames_daily_scores',
                'xpawarded',
                $awarded,
                ['userid' => $userid, 'gamedate' => $gamedate, 'gametype' => 'checkin']
            );
        }

        mission_manager::update($userid, (int) $season->id, 'checkin');

        // When configured to, the check-in also counts as a day of activity and
        // keeps the streak alive; otherwise only completing a game advances it.
        if (get_config('local_playergames', 'streak_activity_source') === 'games_checkin') {
            streak_manager::record_activity($userid);
        }

        // The check-in XP may have raised the level, so refresh streak missions
        // and re-check achievements (mirrors the previous login-based flow).
        $streak = streak_manager::get_or_create($userid);
        mission_manager::update($userid, (int) $season->id, 'streak_updated', [
            'streak' => (int) $streak->currentstreak,
        ]);

        $profile = xp_manager::get_or_create_profile($userid, (int) $season->id);
        achievement_manager::check($userid, (int) $season->id, 'game_played', [
            'gamedate' => $gamedate,
            'level'    => (int) $profile->level,
            'streak'   => (int) $streak->currentstreak,
        ]);
    }

    /**
     * Whether the user takes part in the gamification (same gate as the hub).
     *
     * Mirrors hub.php: respects the per-user opt-out and the allowed_participants
     * setting (students-only excludes non-admin staff; staff-only excludes
     * students). Used by the page-load hook to decide whether to check in.
     *
     * @param int $userid The user to test.
     * @return bool
     */
    public static function is_participant(int $userid): bool {
        if (!preferences::is_gamification_enabled($userid)) {
            return false;
        }

        $allowed = get_config('local_playergames', 'allowed_participants') ?: 'students';
        $isstaff = access::is_staff($userid);
        $isadmin = has_capability('moodle/site:config', context_system::instance(), $userid);

        if ($allowed === 'students' && $isstaff && !$isadmin) {
            return false;
        }
        if ($allowed === 'staff' && !$isstaff) {
            return false;
        }

        return true;
    }
}
