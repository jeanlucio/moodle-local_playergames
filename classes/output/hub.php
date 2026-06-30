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
 * Renderable output class for the Player Hub page.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\output;

use cache;
use context_system;
use moodle_url;
use renderable;
use renderer_base;
use templatable;
use local_playergames\hub\avatar_manager;
use local_playergames\hub\daily_play_manager;
use local_playergames\hub\level_manager;
use local_playergames\hub\mission_manager;
use local_playergames\hub\season_manager;
use local_playergames\hub\streak_manager;
use local_playergames\hub\title_manager;
use local_playergames\hub\xp_manager;
use stdClass;

/**
 * Prepares all data needed by the hub Mustache template.
 *
 * Responsibilities:
 *   - Load user profile (XP, level, title, streak, freezes).
 *   - Load ranking from cache (or DB), split by participant group.
 *   - Load active missions and per-user progress (one bulk query).
 *   - Load today's game completion status.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hub implements renderable, templatable {
    /** @var array<string, string> Font-Awesome icon per game type. */
    private const GAME_ICONS = [
        'quiz'   => 'fa-question-circle',
        'guess'  => 'fa-spell-check',
        'fill'   => 'fa-crosshairs',
        'battle' => 'fa-dragon',
    ];

    /** @var int Maximum ranking rows fetched from DB. */
    private const RANKING_LIMIT = 50;

    /** @var int Current user ID. */
    private int $userid;

    /** @var bool True when user has moodle/course:manageactivities. */
    private bool $isstaff;

    /** @var bool True when user has moodle/site:config (sees both tabs). */
    private bool $isadmin;

    /** @var string Value of the allowed_participants setting. */
    private string $allowed;

    /**
     * Constructs the hub renderable.
     *
     * @param int    $userid   Current user ID.
     * @param bool   $isstaff  True when user has moodle/course:manageactivities.
     * @param bool   $isadmin  True when user has moodle/site:config (sees both tabs).
     * @param string $allowed  Value of the allowed_participants setting.
     */
    public function __construct(int $userid, bool $isstaff, bool $isadmin, string $allowed) {
        $this->userid   = $userid;
        $this->isstaff  = $isstaff;
        $this->isadmin  = $isadmin;
        $this->allowed  = $allowed;
    }

    /**
     * Exports all data needed by the hub Mustache template.
     *
     * @param renderer_base $output Moodle renderer (unused; kept for interface).
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $season = season_manager::get_active();
        if (!$season) {
            return [
                'noseason'      => true,
                'str_no_season' => get_string('hub_no_season', 'local_playergames'),
                'str_pagetitle' => get_string('hub_pagetitle', 'local_playergames'),
                'achievementsurl' => (new moodle_url('/local/playergames/achievements.php'))->out(false),
            ];
        }

        $context  = context_system::instance();
        $staffids = $this->get_staff_ids($context);

        $profile    = xp_manager::get_or_create_profile($this->userid, $season->id);
        $streak     = streak_manager::get_or_create($this->userid);
        $level      = xp_manager::get_level($profile->xp);
        $title      = title_manager::get_title($level);
        $leveldata  = $this->build_level_data($profile->xp, $level);
        $missions   = $this->get_missions($this->userid, $season->id);
        $snapshot     = season_manager::get_config_snapshot($season);
        $games      = $this->get_games_today($this->userid, $snapshot);
        $freezelog  = $this->build_freeze_log($this->userid);

        $checkindaily = (int) ($snapshot['xp_checkin_daily'] ?? 5);
        $checkincap   = (int) ($snapshot['xp_cap_checkin_season'] ?? 150);
        $checkinmax   = $checkindaily > 0 ? intdiv($checkincap, $checkindaily) : 0;
        $checkindone  = $this->get_season_checkins($season, $checkinmax);

        // Ranking is on by default; an admin can disable it so players only see
        // their own score. Treat an unset value (never saved) as enabled.
        $rankingsetting = get_config('local_playergames', 'enable_ranking');
        $rankingenabled = ($rankingsetting === false) ? true : (bool) $rankingsetting;

        $showbothtabs     = $rankingenabled
            && ($this->isadmin || ($this->allowed === 'both' && $this->isstaff));
        $studentranking   = [];
        $staffranking     = [];

        if ($rankingenabled) {
            if ($showbothtabs) {
                $studentranking = $this->get_ranking($season->id, $staffids, false);
                $staffranking   = $this->get_ranking($season->id, $staffids, true);
            } else if ($this->isstaff) {
                $staffranking = $this->get_ranking($season->id, $staffids, true);
            } else {
                $studentranking = $this->get_ranking($season->id, $staffids, false);
            }
        }

        $selfinstudents = $this->find_self_rank($studentranking);
        $selfinstaff    = $this->find_self_rank($staffranking);

        // When the user opted in but falls outside the loaded top-50, compute the
        // exact position with a single indexed COUNT so they still see their rank.
        $selfposition = 0;
        if ($rankingenabled && (bool) $profile->showinranking) {
            $wantstaff = $this->isstaff;
            $selfintop = $wantstaff ? $selfinstaff : $selfinstudents;
            if ($selfintop === 0) {
                $selfposition = xp_manager::get_season_position(
                    $this->userid,
                    $season->id,
                    (int) $profile->xp,
                    (int) $profile->timemodified,
                    $staffids,
                    $wantstaff
                );
            }
        }
        $strselfposition = $selfposition > 0
            ? get_string('hub_your_position', 'local_playergames', $selfposition)
            : '';

        // Avatar collection: equip value is empty for the equipped one (click = unequip).
        $equippedavatar = avatar_manager::get_equipped($this->userid);
        $avatars = array_map(static function (array $a): array {
            $a['equipvalue'] = $a['equipped'] ? '' : $a['emoji'];
            return $a;
        }, avatar_manager::get_collection($this->userid));

        $seasonlabel = get_string('hub_season_label', 'local_playergames');
        $seasonname  = format_string($season->name);
        // Auto-generated names are "Season N", which next to the "Season:" label
        // reads as "Season: Season 1". Drop a redundant leading label word so the
        // badge shows "Season: 1"; custom names without the prefix are untouched.
        $stripped = preg_replace('/^' . preg_quote($seasonlabel, '/') . '\s*/iu', '', $seasonname);
        $seasondisplay = ($stripped !== '' && $stripped !== null) ? $stripped : $seasonname;

        return [
            'noseason'           => false,
            'str_pagetitle'      => get_string('hub_pagetitle', 'local_playergames'),
            'str_achievements'   => get_string('achievements_pagetitle', 'local_playergames'),
            'season_name'        => $seasondisplay,
            'str_season_label'   => $seasonlabel,
            'level'              => $level,
            'title'              => $title,
            'xp'                 => $profile->xp,
            'xp_next'            => $leveldata['xp_next'],
            'xp_in_level'        => $leveldata['xp_in_level'],
            'level_range'        => $leveldata['level_range'],
            'progress_pct'       => $leveldata['progress_pct'],
            'maxlevel'           => $level >= level_manager::max_level(),
            'streak'             => (int) $streak->currentstreak,
            'freezes'            => (int) $streak->freezesavailable,
            'checkin_done'       => $checkindone,
            'checkin_max'        => $checkinmax,
            'hascheckin'         => $checkinmax > 0,
            'str_checkins'       => get_string('hub_checkins', 'local_playergames'),
            'rankingenabled'     => $rankingenabled,
            'showinranking'      => (bool) $profile->showinranking,
            'sesskey'            => sesskey(),
            'formaction'         => (new moodle_url('/local/playergames/hub.php'))->out(false),
            'achievementsurl'    => (new moodle_url('/local/playergames/achievements.php'))->out(false),
            'showbothtabs'       => $showbothtabs,
            'studentranking'     => $studentranking,
            'staffranking'       => $staffranking,
            'hasstudentranking'  => !empty($studentranking),
            'hasstaffranking'    => !empty($staffranking),
            'selfinstudents'     => $selfinstudents,
            'selfinstaff'        => $selfinstaff,
            'str_your_position'  => $strselfposition,
            'avatars'            => $avatars,
            'equippedavatar'     => $equippedavatar,
            'hasequippedavatar'  => $equippedavatar !== '',
            'str_avatars'        => get_string('hub_avatars_section', 'local_playergames'),
            'str_avatars_hint'   => get_string('hub_avatars_hint', 'local_playergames'),
            'str_avatar_locked'  => get_string('hub_avatar_locked', 'local_playergames'),
            'missions'               => $missions,
            'hasmissions'            => !empty($missions),
            'str_freeze_reward_title' => get_string('mission_freeze_reward_title', 'local_playergames'),
            'games'              => $games,
            'freezelog'          => $freezelog,
            'hasfreezelog'       => !empty($freezelog),
            'str_freeze_log'     => get_string('freeze_log_heading', 'local_playergames'),
            'str_profile'        => get_string('hub_profile_section', 'local_playergames'),
            'str_ranking'        => get_string('hub_ranking_section', 'local_playergames'),
            'str_missions'       => get_string('hub_missions_section', 'local_playergames'),
            'str_games'          => get_string('hub_games_section', 'local_playergames'),
            'str_xp'             => get_string('hub_xp', 'local_playergames'),
            'str_level'          => get_string('hub_level', 'local_playergames'),
            'str_streak'         => get_string('hub_streak', 'local_playergames'),
            'str_freezes'        => get_string('hub_freezes', 'local_playergames'),
            'str_xp_next'        => get_string('hub_xp_next_level', 'local_playergames'),
            'str_showinranking'  => get_string('hub_showinranking', 'local_playergames'),
            'str_ranking_stu'    => get_string('hub_ranking_tab_students', 'local_playergames'),
            'str_ranking_sta'    => get_string('hub_ranking_tab_staff', 'local_playergames'),
            'str_rank_pos'       => get_string('hub_rank_pos', 'local_playergames'),
            'str_rank_player'    => get_string('hub_rank_player', 'local_playergames'),
            'str_rank_xp'        => get_string('hub_rank_xp', 'local_playergames'),
            'str_rank_level'     => get_string('hub_rank_level', 'local_playergames'),
            'str_top10'          => get_string('hub_top10', 'local_playergames'),
            'str_show_all'       => get_string('hub_show_all', 'local_playergames'),
            'str_show_less'      => get_string('hub_show_less', 'local_playergames'),
            'str_invite'         => get_string('hub_invite_noprofile', 'local_playergames'),
            'str_join'           => get_string('hub_join_ranking', 'local_playergames'),
        ];
    }

    /**
     * Computes XP progress bar data for the current level.
     *
     * @param int $xp   Total season XP.
     * @param int $level Current level (1–max level).
     * @return array{xp_next: int, xp_in_level: int, level_range: int, progress_pct: int}
     */
    private function build_level_data(int $xp, int $level): array {
        if ($level >= level_manager::max_level()) {
            return ['xp_next' => 0, 'xp_in_level' => 0, 'level_range' => 0, 'progress_pct' => 100];
        }
        $current    = xp_manager::get_xp_for_level($level);
        $next       = xp_manager::get_xp_for_level($level + 1);
        $range      = $next - $current;
        $inlevel    = $xp - $current;
        $pct        = $range > 0 ? min(100, (int) round(($inlevel / $range) * 100)) : 100;
        return [
            'xp_next'    => $next - $xp,
            'xp_in_level' => $inlevel,
            'level_range' => $range,
            'progress_pct' => $pct,
        ];
    }

    /**
     * Returns user IDs of all users with moodle/course:manageactivities in the system context.
     *
     * @param context_system $context
     * @return int[]
     */
    private function get_staff_ids(context_system $context): array {
        global $CFG;

        $users = get_users_by_capability($context, 'moodle/course:manageactivities', 'u.id');
        $ids   = empty($users) ? [] : array_map('intval', array_column((array) $users, 'id'));

        // Site admins have all capabilities implicitly but are not returned by
        // get_users_by_capability. Merge them into the staff set.
        if (!empty($CFG->siteadmins)) {
            $adminids = array_map('intval', explode(',', $CFG->siteadmins));
            $ids      = array_values(array_unique(array_merge($ids, $adminids)));
        }

        return $ids;
    }

    /**
     * Counts the user's daily check-ins in the current season, clamped to the cap.
     *
     * @param \stdClass $season The active season.
     * @param int $max The maximum rewarded check-ins (season cap / daily XP).
     * @return int
     */
    private function get_season_checkins(\stdClass $season, int $max): int {
        global $DB;

        if ($max <= 0) {
            return 0;
        }

        $count = (int) $DB->count_records_select(
            'local_playergames_daily_scores',
            "userid = :uid AND gametype = 'checkin' AND gamedate BETWEEN :start AND :end",
            ['uid' => $this->userid, 'start' => $season->startdate, 'end' => $season->enddate]
        );

        return min($count, $max);
    }

    /**
     * Returns a ranking array for a participant group, using cache when available.
     *
     * @param int   $seasonid
     * @param int[] $staffids  All user IDs that have the staff capability.
     * @param bool  $wantstaff True to return the staff ranking; false for students.
     * @return array
     */
    private function get_ranking(int $seasonid, array $staffids, bool $wantstaff): array {
        global $DB;

        $cachekey = 'season_' . $seasonid . '_' . ($wantstaff ? 'staff' : 'students');
        $cache    = cache::make('local_playergames', 'ranking');
        $cached   = $cache->get($cachekey);
        if ($cached !== false) {
            return $cached;
        }

        $params = ['seasonid' => $seasonid];
        $filter = '';

        if (!empty($staffids)) {
            [$insql, $inparams] = $DB->get_in_or_equal(
                $staffids,
                SQL_PARAMS_NAMED,
                'uid',
                $wantstaff
            );
            $params = array_merge($params, $inparams);
            $filter = "AND p.userid {$insql}";
        } else if ($wantstaff) {
            // No staff users — staff ranking is empty.
            $cache->set($cachekey, []);
            return [];
        }

        $sql = "SELECT p.userid, p.xp, p.level,
                       u.firstname, u.lastname,
                       u.firstnamephonetic, u.lastnamephonetic,
                       u.middlename, u.alternatename
                  FROM {local_playergames_player_profile} p
                  JOIN {user} u ON u.id = p.userid
                 WHERE p.seasonid = :seasonid
                   AND p.showinranking = 1
                   {$filter}
              ORDER BY p.xp DESC, p.timemodified ASC, p.userid ASC";

        $records = $DB->get_records_sql($sql, $params, 0, self::RANKING_LIMIT);
        $ranking = [];
        $pos     = 1;
        foreach ($records as $r) {
            $ranking[] = [
                'pos'    => $pos,
                'name'   => fullname($r),
                'xp'     => (int) $r->xp,
                'level'  => (int) $r->level,
                'isself' => (int) $r->userid === $this->userid,
                'extra'  => $pos > 10,
            ];
            $pos++;
        }

        $cache->set($cachekey, $ranking);
        return $ranking;
    }

    /**
     * Returns the user's own position in a ranking array, or 0 if not present.
     *
     * @param array $ranking
     * @return int
     */
    private function find_self_rank(array $ranking): int {
        foreach ($ranking as $row) {
            if ($row['isself']) {
                return $row['pos'];
            }
        }
        return 0;
    }

    /**
     * Returns mission data with per-user progress for the given season.
     *
     * Uses a single bulk query to load all progress rows before iterating.
     *
     * @param int $userid
     * @param int $seasonid
     * @return array
     */
    private function get_missions(int $userid, int $seasonid): array {
        global $DB;

        $missions = mission_manager::get_all();
        if (empty($missions)) {
            return [];
        }

        $missionids = array_map(fn($m) => (int) $m->id, $missions);
        [$insql, $params] = $DB->get_in_or_equal($missionids, SQL_PARAMS_NAMED, 'mid');
        $params['userid']   = $userid;
        $params['seasonid'] = $seasonid;

        $progressrows = $DB->get_records_select(
            'local_playergames_mission_progress',
            "userid = :userid AND seasonid = :seasonid AND missionid {$insql}",
            $params
        );
        $progressbyid = [];
        foreach ($progressrows as $p) {
            $progressbyid[(int) $p->missionid] = $p;
        }

        $result = [];
        foreach ($missions as $mission) {
            $progress  = $progressbyid[(int) $mission->id] ?? null;
            $current   = $progress ? (int) $progress->currentvalue : 0;
            $completed = $progress ? (bool) $progress->completed : false;
            $target    = (int) $mission->targetvalue;
            $pct       = $target > 0 ? min(100, (int) round(($current / $target) * 100)) : 0;
            $result[]  = [
                'icon'         => $mission->icon,
                'name'         => get_string($mission->namestring, 'local_playergames'),
                'desc'         => get_string($mission->descstring, 'local_playergames'),
                'current'      => $current,
                'target'       => $target,
                'completed'    => $completed,
                'xpreward'     => (int) $mission->xpreward,
                'freezereward' => (int) ($mission->freezereward ?? 0),
                'progress_pct' => $pct,
            ];
        }
        return $result;
    }

    /**
     * Builds the freeze event log for display in the hub profile card.
     *
     * Returns the last 5 entries, translated for the current user locale.
     *
     * @param int $userid
     * @return array
     */
    private function build_freeze_log(int $userid): array {
        $entries = streak_manager::get_freeze_log($userid, 5);
        $result  = [];
        foreach ($entries as $entry) {
            if ($entry->action === 'earned') {
                $label = get_string('freeze_log_earned', 'local_playergames', (int) $entry->amount);
            } else {
                $label = get_string('freeze_log_used', 'local_playergames');
            }
            $result[] = [
                'timeformatted' => userdate((int) $entry->timecreated, get_string('strftimedatetime', 'langconfig')),
                'label'         => $label,
                'isearned'      => $entry->action === 'earned',
            ];
        }
        return $result;
    }

    /**
     * Returns game completion status for today.
     *
     * @param int $userid
     * @param array $snapshot Season config snapshot, for the per-game daily quota.
     * @return array
     */
    private function get_games_today(int $userid, array $snapshot): array {
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
            'quiz'   => (new \moodle_url('/local/playergames/play_quiz.php'))->out(false),
            'guess'  => '',
            'fill'   => '',
            'battle' => '',
        ];
        $result = [];
        foreach ($gametypes as $gametype) {
            $games   = daily_play_manager::games_per_day($snapshot, $gametype);
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
