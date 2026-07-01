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
 * Shared daily-play bookkeeping for PlayerGames game types.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\hub;

use local_playergames\event\game_completed;
use moodle_url;
use stdClass;

/**
 * Centralises the "N plays per day, each worth a fixed slice of XP" model.
 *
 * One daily_scores row is kept per user/day/gametype (matching the table's
 * unique key); xpawarded accumulates the day's XP and attempts counts the
 * plays. The daily XP cap enforced by xp_manager::award() equals
 * xp_per_game * xp_games, so the final play is trimmed to land on the exact
 * cap with no XP lost or exceeded.
 *
 * @package    local_playergames
 */
class daily_play_manager {
    /** @var array<string, string> Font-Awesome icon per game type. */
    public const GAME_ICONS = [
        'quiz'   => 'fa-question-circle',
        'guess'  => 'fa-spell-check',
        'fill'   => 'fa-crosshairs',
        'battle' => 'fa-dragon',
    ];

    /**
     * Number of plays allowed per day for a game type.
     *
     * @param array $snapshot Season config snapshot.
     * @param string $gametype Game type.
     * @return int At least 1.
     */
    public static function games_per_day(array $snapshot, string $gametype): int {
        $games = (int) ($snapshot['xp_games_' . $gametype] ?? 1);
        return max(1, $games);
    }

    /**
     * XP awarded per individual play of a game type.
     *
     * @param array $snapshot Season config snapshot.
     * @param string $gametype Game type.
     * @return int
     */
    public static function xp_per_game(array $snapshot, string $gametype): int {
        return (int) ($snapshot['xp_per_game_' . $gametype] ?? 25);
    }

    /**
     * Number of times the user has already played a game type today.
     *
     * @param int $userid User id.
     * @param string $gametype Game type.
     * @param int $gamedate Midnight timestamp of the day.
     * @return int
     */
    public static function attempts_today(int $userid, string $gametype, int $gamedate): int {
        global $DB;
        $attempts = $DB->get_field('local_playergames_daily_scores', 'attempts', [
            'userid'   => $userid,
            'gamedate' => $gamedate,
            'gametype' => $gametype,
        ]);
        return $attempts === false ? 0 : (int) $attempts;
    }

    /**
     * Plays still available today for a game type.
     *
     * @param int $userid User id.
     * @param string $gametype Game type.
     * @param int $gamedate Midnight timestamp of the day.
     * @param array $snapshot Season config snapshot.
     * @return int Zero or more.
     */
    public static function remaining_plays(int $userid, string $gametype, int $gamedate, array $snapshot): int {
        $remaining = self::games_per_day($snapshot, $gametype) - self::attempts_today($userid, $gametype, $gamedate);
        return max(0, $remaining);
    }

    /**
     * Records one play: awards capped XP, upserts the daily row and fires game_completed.
     *
     * @param int $userid User to credit.
     * @param string $gametype One of the supported game types.
     * @param int $gamedate Midnight timestamp of the day.
     * @param stdClass $season Active season record.
     * @param \context $context Context for the fired event.
     * @return array {success: bool, xpawarded: int, remaining: int}
     */
    public static function register_play(
        int $userid,
        string $gametype,
        int $gamedate,
        stdClass $season,
        \context $context
    ): array {
        global $DB;

        $snapshot = season_manager::get_config_snapshot($season);
        $games    = self::games_per_day($snapshot, $gametype);

        $existing = $DB->get_record('local_playergames_daily_scores', [
            'userid'   => $userid,
            'gamedate' => $gamedate,
            'gametype' => $gametype,
        ]);
        $attempts = $existing ? (int) $existing->attempts : 0;
        if ($attempts >= $games) {
            return ['success' => false, 'xpawarded' => 0, 'remaining' => 0];
        }

        $pergame   = self::xp_per_game($snapshot, $gametype);
        $xpawarded = xp_manager::award($userid, $pergame, $gametype, $gamedate, (int) $season->id);

        if ($existing) {
            $existing->completed  = 1;
            $existing->xpawarded  = (int) $existing->xpawarded + $xpawarded;
            $existing->attempts   = $attempts + 1;
            $existing->timeplayed = time();
            $DB->update_record('local_playergames_daily_scores', $existing);
            $scoreid = (int) $existing->id;
        } else {
            $record             = new stdClass();
            $record->userid     = $userid;
            $record->gamedate   = $gamedate;
            $record->gametype   = $gametype;
            $record->completed  = 1;
            $record->xpawarded  = $xpawarded;
            $record->attempts   = 1;
            $record->timeplayed = time();
            $scoreid = (int) $DB->insert_record('local_playergames_daily_scores', $record);
        }

        $event = game_completed::create([
            'objectid' => $scoreid,
            'context'  => $context,
            'userid'   => $userid,
            'other'    => [
                'seasonid'  => (int) $season->id,
                'gametype'  => $gametype,
                'gamedate'  => $gamedate,
                'xpawarded' => $xpawarded,
            ],
        ]);
        $event->trigger();

        return ['success' => true, 'xpawarded' => $xpawarded, 'remaining' => $games - ($attempts + 1)];
    }

    /**
     * Returns today's game completion status for a user, ready for display.
     *
     * Shared by the Hub page and block_playergames, so the icon/URL/quota
     * logic per game type only lives in one place.
     *
     * @param int $userid User id.
     * @param array $snapshot Season config snapshot, for the per-game daily quota.
     * @return array
     */
    public static function get_games_today(int $userid, array $snapshot): array {
        global $DB;

        $today        = mktime(0, 0, 0, (int) date('n'), (int) date('j'), (int) date('Y'));
        $hascartridge = $DB->record_exists('local_playergames_cartridges', ['active' => 1]);

        $playedrows = $DB->get_records_select(
            'local_playergames_daily_scores',
            'userid = :uid AND gamedate = :gd AND completed = 1',
            ['uid' => $userid, 'gd' => $today],
            '',
            'gametype, attempts'
        );
        $attempts = [];
        foreach ($playedrows as $r) {
            $attempts[$r->gametype] = (int) $r->attempts;
        }

        $gametypes = ['quiz', 'guess', 'fill', 'battle'];
        $namekeys  = [
            'quiz'   => 'hub_game_quiz',
            'guess'  => 'hub_game_guess',
            'fill'   => 'hub_game_fill',
            'battle' => 'hub_game_battle',
        ];
        $gameurls = [
            'quiz'   => (new moodle_url('/local/playergames/play_quiz.php'))->out(false),
            'guess'  => '',
            'fill'   => '',
            'battle' => '',
        ];
        $result = [];
        foreach ($gametypes as $gametype) {
            $games   = self::games_per_day($snapshot, $gametype);
            $done    = ($attempts[$gametype] ?? 0) >= $games;
            $locked  = !$hascartridge && !$done;
            $pending = !$done && !$locked;
            $result[] = [
                'gametype' => $gametype,
                'name'     => get_string($namekeys[$gametype], 'local_playergames'),
                'icon'     => self::GAME_ICONS[$gametype],
                'done'     => $done,
                'locked'   => $locked,
                'pending'  => $pending,
                'url'      => $pending ? ($gameurls[$gametype] ?? '') : '',
                'str_done'    => get_string('hub_game_done', 'local_playergames'),
                'str_pending' => get_string('hub_game_pending', 'local_playergames'),
                'str_locked'  => get_string('hub_game_locked', 'local_playergames'),
            ];
        }
        return $result;
    }
}
