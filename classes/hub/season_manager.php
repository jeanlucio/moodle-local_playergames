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
 * Season lifecycle manager for local_playergames.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\hub;

use local_playergames\event\season_closed;
use local_playergames\event\season_created;
use local_playergames\games\season_game_config;
use stdClass;

/**
 * Creates, closes and auto-renews gamification seasons.
 *
 * config_snapshot is frozen at season creation and used by all managers
 * throughout the season so admin setting changes mid-season have no effect.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class season_manager {
    /** @var string Active season status. */
    const STATUS_ACTIVE = 'active';

    /** @var string Upcoming season status. */
    const STATUS_UPCOMING = 'upcoming';

    /** @var string Closed season status. */
    const STATUS_CLOSED = 'closed';

    /**
     * Returns the current active season, or null if none exists.
     *
     * @return stdClass|null
     */
    public static function get_active(): ?stdClass {
        global $DB;
        $record = $DB->get_record(
            'local_playergames_seasons',
            ['status' => self::STATUS_ACTIVE],
            '*',
            IGNORE_MISSING
        );
        return $record ?: null;
    }

    /**
     * Returns the active or next upcoming season, or null if neither exists.
     *
     * @return stdClass|null
     */
    public static function get_active_or_upcoming(): ?stdClass {
        global $DB;
        $record = $DB->get_record(
            'local_playergames_seasons',
            ['status' => self::STATUS_ACTIVE],
            '*',
            IGNORE_MISSING
        );
        if ($record) {
            return $record;
        }
        return $DB->get_record(
            'local_playergames_seasons',
            ['status' => self::STATUS_UPCOMING],
            '*',
            IGNORE_MISSING
        ) ?: null;
    }

    /**
     * Whether any season exists, whatever its status.
     *
     * @return bool
     */
    public static function has_any(): bool {
        global $DB;
        return $DB->count_records('local_playergames_seasons') > 0;
    }

    /**
     * Decodes and returns the config snapshot for a season record.
     *
     * @param stdClass $season Season record from the DB.
     * @return array
     */
    public static function get_config_snapshot(stdClass $season): array {
        if (empty($season->config_snapshot)) {
            return self::build_snapshot();
        }
        $data = json_decode($season->config_snapshot, true);
        return is_array($data) ? $data : self::build_snapshot();
    }

    /**
     * Builds a config snapshot from current admin settings.
     *
     * Called at season creation to freeze the active configuration.
     *
     * @return array
     */
    public static function build_snapshot(): array {
        $snapshot = [];
        foreach (['quiz', 'guess', 'fill', 'battle'] as $type) {
            $pergame = (int) get_config('local_playergames', 'xp_per_game_' . $type) ?: 25;
            $games   = (int) get_config('local_playergames', 'xp_games_' . $type) ?: 1;
            $snapshot['xp_per_game_' . $type] = $pergame;
            $snapshot['xp_games_' . $type]    = $games;
            // Derived daily cap consumed by xp_manager::award(): the last play is
            // trimmed to this value so the day total lands exactly on it.
            $snapshot['xp_cap_' . $type] = $pergame * $games;
        }
        $snapshot['xp_checkin_daily']      = (int) get_config('local_playergames', 'xp_checkin_daily') ?: 5;
        $snapshot['xp_cap_checkin_season'] = (int) get_config('local_playergames', 'xp_cap_checkin_season') ?: 150;
        $snapshot['allowed_participants']  = get_config('local_playergames', 'allowed_participants') ?: 'students';
        return $snapshot;
    }

    /**
     * Creates a new season and fires the season_created event.
     *
     * The season starts with status 'upcoming'. The admin must manually activate
     * it (or use the auto-activate option) via the UI (Phase 6).
     *
     * @param string $name Display name for the season.
     * @param int $startdate Unix timestamp for the start date.
     * @param int $enddate Unix timestamp for the end date.
     * @return stdClass The inserted season record.
     */
    public static function create(string $name, int $startdate, int $enddate): stdClass {
        global $DB, $USER;
        $now = time();
        $record = new stdClass();
        $record->name            = $name;
        $record->startdate       = $startdate;
        $record->enddate         = $enddate;
        $record->status          = self::STATUS_UPCOMING;
        $record->config_snapshot = json_encode(self::build_snapshot());
        $record->timecreated     = $now;
        $record->timemodified    = $now;
        $record->id = $DB->insert_record('local_playergames_seasons', $record);

        season_game_config::seed_defaults((int) $record->id);

        $event = season_created::create([
            'objectid' => $record->id,
            'context'  => \context_system::instance(),
            'userid'   => $USER->id,
        ]);
        $event->trigger();

        return $record;
    }

    /**
     * Activates a season that is in 'upcoming' status.
     *
     * Only one season can be active at a time. Throws if another season is active.
     *
     * @param int $seasonid
     * @return void
     */
    public static function activate(int $seasonid): void {
        global $DB;
        $existing = self::get_active();
        if ($existing && $existing->id !== $seasonid) {
            throw new \moodle_exception('error_season_already_active', 'local_playergames');
        }
        $DB->set_field(
            'local_playergames_seasons',
            'status',
            self::STATUS_ACTIVE,
            ['id' => $seasonid, 'status' => self::STATUS_UPCOMING]
        );
        $DB->set_field(
            'local_playergames_seasons',
            'timemodified',
            time(),
            ['id' => $seasonid]
        );
    }

    /**
     * Closes a season: sets status to 'closed' and fires season_closed event.
     *
     * Player profiles and history are preserved — XP is NOT reset here.
     * Rankings remain queryable by filtering on seasonid.
     *
     * @param int $seasonid
     * @return void
     */
    public static function close(int $seasonid): void {
        global $DB, $USER;
        $DB->set_field(
            'local_playergames_seasons',
            'status',
            self::STATUS_CLOSED,
            ['id' => $seasonid]
        );
        $DB->set_field(
            'local_playergames_seasons',
            'timemodified',
            time(),
            ['id' => $seasonid]
        );

        $event = season_closed::create([
            'objectid' => $seasonid,
            'context'  => \context_system::instance(),
            'userid'   => $USER->id ?? 0,
        ]);
        $event->trigger();
    }

    /**
     * Turns a date typed as YYYY-MM-DD into a timestamp in the user's timezone.
     *
     * A season starts at the first second of its first day and ends at the last second of its last
     * day, the way create_next() sets them, so the day announced as the last one can still be played.
     *
     * @param string $date The typed date, YYYY-MM-DD.
     * @param bool $endofday True for 23:59:59 of that day, false for 00:00:00.
     * @return int The timestamp, or 0 when the text is not a real date.
     */
    public static function date_to_timestamp(string $date, bool $endofday): int {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', trim($date), $matches)) {
            return 0;
        }
        [, $year, $month, $day] = array_map('intval', $matches);
        if (!checkdate($month, $day, $year)) {
            return 0;
        }

        return $endofday
            ? make_timestamp($year, $month, $day, 23, 59, 59)
            : make_timestamp($year, $month, $day, 0, 0, 0);
    }

    /**
     * Renames a season and, unless it is closed, changes its dates.
     *
     * The dates of a closed season are history, so only its name changes. The
     * status is never touched here: activating and closing have their own methods.
     *
     * @param int $seasonid
     * @param string $name New display name.
     * @param int $startdate Unix timestamp of the start, ignored for a closed season.
     * @param int $enddate Unix timestamp of the end, ignored for a closed season.
     * @return void
     * @throws \moodle_exception When the end does not come after the start.
     */
    public static function update(int $seasonid, string $name, int $startdate, int $enddate): void {
        global $DB;
        $season = $DB->get_record('local_playergames_seasons', ['id' => $seasonid], '*', MUST_EXIST);

        $record = new stdClass();
        $record->id = $seasonid;
        $record->name = $name;
        if ($season->status !== self::STATUS_CLOSED) {
            if ($enddate <= $startdate) {
                throw new \moodle_exception('error_season_invalid_dates', 'local_playergames');
            }
            $record->startdate = $startdate;
            $record->enddate = $enddate;
        }
        $record->timemodified = time();
        $DB->update_record('local_playergames_seasons', $record);
    }

    /**
     * Deletes a season that is not active, with everything the hub stored for it.
     *
     * Removes the player profiles (XP, streak of that season), mission progress, battle results and
     * game settings of the season. The daily scores are kept by date, not by season, so they go
     * only when no other season covers the same days. The active season cannot be deleted, as the
     * hub would be left with no season in the middle of a day of play.
     *
     * @param int $seasonid
     * @return void
     * @throws \moodle_exception When the season is the active one.
     */
    public static function delete(int $seasonid): void {
        global $DB;
        $season = $DB->get_record('local_playergames_seasons', ['id' => $seasonid], '*', MUST_EXIST);
        if ($season->status === self::STATUS_ACTIVE) {
            throw new \moodle_exception('error_season_delete_active', 'local_playergames');
        }

        $overlap = $DB->count_records_select(
            'local_playergames_seasons',
            'id <> :id AND startdate <= :end AND enddate >= :start',
            ['id' => $seasonid, 'start' => $season->startdate, 'end' => $season->enddate]
        );

        $transaction = $DB->start_delegated_transaction();
        if ($overlap === 0) {
            $DB->delete_records_select(
                'local_playergames_daily_scores',
                'gamedate BETWEEN :start AND :end',
                ['start' => $season->startdate, 'end' => $season->enddate]
            );
        }
        $DB->delete_records('local_playergames_battle_scores', ['seasonid' => $seasonid]);
        $DB->delete_records('local_playergames_mission_progress', ['seasonid' => $seasonid]);
        $DB->delete_records('local_playergames_season_games', ['seasonid' => $seasonid]);
        $DB->delete_records('local_playergames_player_profile', ['seasonid' => $seasonid]);
        $DB->delete_records('local_playergames_seasons', ['id' => $seasonid]);
        $transaction->allow_commit();
    }

    /**
     * Creates the next season inheriting the config_snapshot of the previous season.
     *
     * The new season starts the day after the previous season ended and lasts
     * for the number of months configured in season_duration_months.
     *
     * @param stdClass $previousseason The closed or expiring season record.
     * @return stdClass The new season record.
     */
    public static function create_next(stdClass $previousseason): stdClass {
        $months    = (int) get_config('local_playergames', 'season_duration_months') ?: 6;
        $startdate = $previousseason->enddate + 1;
        $enddate   = strtotime("+{$months} months", $startdate) - 1;
        $number    = self::next_season_number();
        $name      = get_string('defaultseasonname', 'local_playergames');
        $name      = preg_replace('/\d+$/', (string) $number, $name);

        $snapshot = self::get_config_snapshot($previousseason);
        $snapshot = array_merge($snapshot, self::build_snapshot());

        $now    = time();
        global $DB, $USER;
        $record = new stdClass();
        $record->name            = $name;
        $record->startdate       = $startdate;
        $record->enddate         = $enddate;
        $record->status          = self::STATUS_UPCOMING;
        $record->config_snapshot = json_encode($snapshot);
        $record->timecreated     = $now;
        $record->timemodified    = $now;
        $record->id = $DB->insert_record('local_playergames_seasons', $record);

        season_game_config::seed_defaults((int) $record->id);

        $event = season_created::create([
            'objectid' => $record->id,
            'context'  => \context_system::instance(),
            'userid'   => $USER->id ?? 0,
        ]);
        $event->trigger();

        return $record;
    }

    /**
     * Returns the next sequential season number.
     *
     * @return int
     */
    private static function next_season_number(): int {
        global $DB;
        $count = $DB->count_records('local_playergames_seasons');
        return $count + 1;
    }
}
