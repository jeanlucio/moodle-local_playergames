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
 * Tests for the achievements page data.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\output;

/**
 * Unit tests for {@see achievements}.
 *
 * @package    local_playergames
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_playergames\output\achievements
 */
final class achievements_test extends \advanced_testcase {
    /**
     * Exports the page of a user.
     *
     * @param int $userid The user.
     * @return array The template data.
     */
    private function export(int $userid): array {
        global $PAGE;

        return (new achievements($userid))->export_for_template($PAGE->get_renderer('core'));
    }

    /**
     * Every achievement is listed, locked until the user earns it, and the page links back to the hub.
     */
    public function test_every_achievement_is_listed_and_locked_until_earned(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        $data = $this->export((int) $user->id);

        $this->assertSame($DB->count_records('local_playergames_achievements'), $data['total']);
        $this->assertGreaterThan(0, $data['total']);
        $this->assertSame(0, $data['totalearned']);
        $this->assertCount($data['total'], $data['items']);
        foreach ($data['items'] as $item) {
            $this->assertFalse($item['earned']);
            $this->assertTrue($item['locked']);
            $this->assertSame('', $item['earnedts']);
            $this->assertNotSame('', $item['name']);
        }
        $this->assertStringContainsString('/local/playergames/hub.php', $data['str_huburl']);
        $this->assertSame(get_string('achievements_pagetitle', 'local_playergames'), $data['str_pagetitle']);
    }

    /**
     * What the user earned comes first, with the date, and only that user's earnings count.
     */
    public function test_the_earned_achievements_come_first_with_their_date(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $this->export((int) $user->id);
        $ids = array_keys($DB->get_records('local_playergames_achievements', null, 'id DESC'));
        $earnedat = 1700000000;
        $DB->insert_record('local_playergames_user_achievements', (object) [
            'userid' => $user->id, 'achievementid' => $ids[0], 'timecreated' => $earnedat,
        ]);
        $DB->insert_record('local_playergames_user_achievements', (object) [
            'userid' => $other->id, 'achievementid' => $ids[1], 'timecreated' => $earnedat,
        ]);

        $data = $this->export((int) $user->id);

        $this->assertSame(1, $data['totalearned']);
        $this->assertTrue($data['items'][0]['earned']);
        $this->assertFalse($data['items'][0]['locked']);
        $this->assertSame(userdate($earnedat), $data['items'][0]['earnedts']);
        $this->assertFalse($data['items'][1]['earned']);
        $this->assertSame(get_string(
            $DB->get_field('local_playergames_achievements', 'namestring', ['id' => $ids[0]]),
            'local_playergames'
        ), $data['items'][0]['name']);
    }
}
