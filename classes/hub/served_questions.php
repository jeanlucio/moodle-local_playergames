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
 * Tracks which content a user has already seen on a given day.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\hub;

/**
 * Records and reads the per-day "already shown" set so repeated plays of a
 * game draw fresh content instead of repeating.
 *
 * Sourced items are identified by (source, sourceid) because different content
 * sources live in different id spaces (cartridge concept questions vs the
 * Moodle question bank). One row per item shown.
 *
 * @package    local_playergames
 */
class served_questions {
    /**
     * Returns the ids already shown to a user today, grouped by source.
     *
     * @param int $userid User id.
     * @param string $gametype Game type.
     * @param int $gamedate Midnight timestamp of the day.
     * @return array Map of source => int[] of sourceids (empty sources omitted).
     */
    public static function get_excluded(int $userid, string $gametype, int $gamedate): array {
        global $DB;
        $rows = $DB->get_records(
            'local_playergames_served_questions',
            ['userid' => $userid, 'gamedate' => $gamedate, 'gametype' => $gametype],
            '',
            'id, source, sourceid'
        );
        $excluded = [];
        foreach ($rows as $row) {
            $excluded[$row->source][] = (int) $row->sourceid;
        }
        return $excluded;
    }

    /**
     * Records a batch of shown items for a user today.
     *
     * Silently ignores duplicates already stored for the same day so a retry
     * or overlap does not create redundant rows.
     *
     * @param int $userid User id.
     * @param string $gametype Game type.
     * @param int $gamedate Midnight timestamp of the day.
     * @param array $items List of ['source' => string, 'sourceid' => int].
     * @return void
     */
    public static function record(int $userid, string $gametype, int $gamedate, array $items): void {
        global $DB;
        if (empty($items)) {
            return;
        }

        $existing = self::get_excluded($userid, $gametype, $gamedate);
        $now      = time();
        $records  = [];
        foreach ($items as $item) {
            $source   = (string) ($item['source'] ?? '');
            $sourceid = (int) ($item['sourceid'] ?? 0);
            if ($source === '' || $sourceid <= 0) {
                continue;
            }
            if (in_array($sourceid, $existing[$source] ?? [], true)) {
                continue;
            }
            $records[] = (object) [
                'userid'      => $userid,
                'gamedate'    => $gamedate,
                'gametype'    => $gametype,
                'source'      => $source,
                'sourceid'    => $sourceid,
                'timecreated' => $now,
            ];
            $existing[$source][] = $sourceid;
        }

        if (!empty($records)) {
            $DB->insert_records('local_playergames_served_questions', $records);
        }
    }
}
