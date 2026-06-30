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
 * Tests for the set_ranking_visibility external function.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\external;

use core_external\external_api;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Unit tests for {@see set_ranking_visibility}.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(set_ranking_visibility::class)]
final class set_ranking_visibility_test extends \advanced_testcase {
    /**
     * Inserts an active season and returns its id.
     *
     * @return int Season id.
     */
    private function make_active_season(): int {
        global $DB;
        $now = time();
        return (int) $DB->insert_record('local_playergames_seasons', (object) [
            'name'            => 'Season',
            'startdate'       => $now - DAYSECS,
            'enddate'         => $now + (DAYSECS * 30),
            'status'          => 'active',
            'config_snapshot' => json_encode([]),
            'timecreated'     => $now,
            'timemodified'    => $now,
        ]);
    }

    public function test_execute_sets_visibility(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $seasonid = $this->make_active_season();

        $result = set_ranking_visibility::execute(1);
        $result = external_api::clean_returnvalue(set_ranking_visibility::execute_returns(), $result);

        $this->assertSame(1, $result['showinranking']);
        $profile = $DB->get_record(
            'local_playergames_player_profile',
            ['userid' => $USER->id, 'seasonid' => $seasonid],
            '*',
            MUST_EXIST
        );
        $this->assertSame(1, (int) $profile->showinranking);
    }

    public function test_execute_hides_visibility(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $seasonid = $this->make_active_season();

        set_ranking_visibility::execute(1);
        set_ranking_visibility::execute(0);

        $profile = $DB->get_record(
            'local_playergames_player_profile',
            ['userid' => $USER->id, 'seasonid' => $seasonid],
            '*',
            MUST_EXIST
        );
        $this->assertSame(0, (int) $profile->showinranking);
    }
}
