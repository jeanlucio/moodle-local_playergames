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
 * The most XP a player can earn in a season.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\hub;

use local_playergames\games\season_game_config;
use stdClass;

/**
 * Adds up the XP of a season played perfectly: every game won on every day, every check-in, every mission.
 *
 * The games and the check-in follow the rules the season froze when it was created; the missions are the ones on
 * now. The XP of the courses, kept apart from the XP of the season, is not part of it.
 */
final class season_xp {
    /**
     * How many days a season lasts, counting the first and the last.
     *
     * @param stdClass $season The season, with startdate and enddate.
     * @return int
     */
    public static function days(stdClass $season): int {
        $start = \local_playergames\local\day::midnight((int) $season->startdate);
        $end = \local_playergames\local\day::midnight((int) $season->enddate);
        return max(0, (int) round(($end - $start) / DAYSECS) + 1);
    }

    /**
     * The most XP of a season, part by part.
     *
     * @param stdClass $season The season.
     * @return array With days, games (XP by game type), checkin, missions and total.
     */
    public static function ceiling(stdClass $season): array {
        $snapshot = season_manager::get_config_snapshot($season);
        $days = self::days($season);
        $configs = season_game_config::get_all_for_season((int) $season->id);

        $games = [];
        $plays = [];
        foreach (season_game_config::GAMETYPES as $gametype) {
            if (isset($configs[$gametype]) && !$configs[$gametype]->enabled) {
                continue;
            }
            $plays[$gametype] = daily_play_manager::games_per_day($snapshot, $gametype);
            $games[$gametype] = daily_play_manager::xp_per_game($snapshot, $gametype) * $plays[$gametype] * $days;
        }
        $checkin = min(
            (int) ($snapshot['xp_checkin_daily'] ?? 5) * $days,
            (int) ($snapshot['xp_cap_checkin_season'] ?? 150)
        );

        $earned = array_sum($games) + $checkin;
        $missions = 0;
        $cumulative = [];
        foreach (mission_manager::get_enabled() as $mission) {
            $target = (int) $mission->targetvalue;
            $xp = (int) $mission->xpreward;
            if ($mission->type === 'daily') {
                // Once a day, when the games of a day are enough to meet it.
                $missions += $target <= array_sum($plays) ? $xp * $days : 0;
            } else if ($mission->type === 'battle_win') {
                $missions += $target <= ($plays['battle'] ?? 0) * $days ? $xp : 0;
            } else if ($mission->type === 'cumulative') {
                // Reached with the XP of the rest, so it is weighed last.
                $cumulative[] = $mission;
            } else {
                $missions += $target <= $days ? $xp : 0;
            }
        }
        foreach ($cumulative as $mission) {
            $missions += (int) $mission->targetvalue <= $earned + $missions ? (int) $mission->xpreward : 0;
        }

        return [
            'days' => $days,
            'games' => $games,
            'checkin' => $checkin,
            'missions' => $missions,
            'total' => $earned + $missions,
        ];
    }
}
