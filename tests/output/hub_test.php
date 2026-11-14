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
 * Tests for the hub renderable.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\output;

use local_playergames\hub\avatar_manager;
use local_playergames\hub\checkin_manager;
use local_playergames\hub\mission_manager;
use local_playergames\hub\season_manager;
use local_playergames\hub\streak_manager;
use local_playergames\hub\xp_manager;

/**
 * Unit tests for {@see hub}.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_playergames\output\hub
 */
final class hub_test extends \advanced_testcase {
    /**
     * Exports the hub of a user and renders it.
     *
     * @param bool $isadmin Whether the user manages the site.
     * @return array {data: the exported data, html: the rendered page}
     */
    private function export_and_render(bool $isadmin): array {
        global $PAGE;
        $user     = $this->getDataGenerator()->create_user();
        $renderer = $PAGE->get_renderer('core');
        $data     = (new hub((int) $user->id, false, $isadmin, 'students'))->export_for_template($renderer);
        return ['data' => $data, 'html' => $renderer->render_from_template('local_playergames/hub', $data)];
    }

    public function test_an_administrator_is_offered_the_seasons_page_when_there_is_no_season(): void {
        $this->resetAfterTest();

        ['data' => $data, 'html' => $html] = $this->export_and_render(true);

        $this->assertTrue($data['noseason']);
        $this->assertStringContainsString('/local/playergames/manage_seasons.php', $data['manageurl']);
        $this->assertStringContainsString('manage_seasons.php', $html);
        $this->assertStringContainsString(get_string('season_manage_pagetitle', 'local_playergames'), $html);
    }

    public function test_anyone_else_is_only_told_there_is_no_season(): void {
        $this->resetAfterTest();

        ['data' => $data, 'html' => $html] = $this->export_and_render(false);

        $this->assertTrue($data['noseason']);
        $this->assertSame('', $data['manageurl']);
        $this->assertStringNotContainsString('manage_seasons.php', $html);
        $this->assertStringContainsString(get_string('hub_no_season', 'local_playergames'), $html);
    }

    /**
     * Opens a season that is running now.
     *
     * @param string $name Name of the season.
     * @return \stdClass The season.
     */
    private function start_season(string $name = 'Season 3'): \stdClass {
        $season = season_manager::create($name, time() - DAYSECS, time() + (30 * DAYSECS));
        season_manager::activate((int) $season->id);
        return $season;
    }

    /**
     * Creates a user with a profile in the season.
     *
     * @param \stdClass $season The season.
     * @param int $xp Season XP of the profile.
     * @param bool $show Whether the profile appears in the ranking.
     * @param array $userfields Fields of the user.
     * @return \stdClass The user.
     */
    private function player(\stdClass $season, int $xp, bool $show, array $userfields = []): \stdClass {
        global $DB;
        $user = $this->getDataGenerator()->create_user($userfields);
        $profile = xp_manager::get_or_create_profile((int) $user->id, (int) $season->id);
        $DB->update_record('local_playergames_player_profile', (object) [
            'id' => $profile->id,
            'xp' => $xp,
            'level' => xp_manager::get_level($xp),
            'showinranking' => (int) $show,
            'timemodified' => 1700000000,
        ]);
        return $user;
    }

    /**
     * Exports the hub for a user.
     *
     * @param \stdClass $user The viewer.
     * @param bool $isstaff Whether the viewer is staff.
     * @param bool $isadmin Whether the viewer manages the site.
     * @param string $allowed The allowed_participants setting.
     * @return array The template data.
     */
    private function export_for(
        \stdClass $user,
        bool $isstaff = false,
        bool $isadmin = false,
        string $allowed = 'students'
    ): array {
        global $PAGE;

        return (new hub((int) $user->id, $isstaff, $isadmin, $allowed))->export_for_template($PAGE->get_renderer('core'));
    }

    /**
     * Makes a user a teacher, so the staff ranking tells them from the students.
     *
     * @param \stdClass $user The user.
     * @return void
     */
    private function make_teacher(\stdClass $user): void {
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'editingteacher');
    }

    /**
     * The profile card shows the season, level, missions and check-ins of the viewer.
     */
    public function test_the_profile_missions_and_checkins_of_the_season(): void {
        $this->resetAfterTest();
        $this->redirectEvents();
        set_config('xp_checkin_daily', 10, 'local_playergames');
        set_config('xp_cap_checkin_season', 50, 'local_playergames');
        $season = $this->start_season();
        $user = $this->getDataGenerator()->create_user();

        $data = $this->export_for($user);

        $this->assertFalse($data['noseason']);
        // The "Season" label is dropped from "Season 3" so the badge does not read "Season: Season 3".
        $this->assertSame('3', $data['season_name']);
        $this->assertSame(1, $data['level']);
        $this->assertSame(0, $data['xp']);
        $this->assertFalse($data['maxlevel']);
        $this->assertSame(5, $data['checkin_max']);
        $this->assertSame(0, $data['checkin_done']);
        $this->assertTrue($data['hascheckin']);
        $this->assertTrue($data['hasmissions']);
        $this->assertCount(5, $data['missions']);
        $this->assertSame([0], array_unique(array_column($data['missions'], 'current')));

        checkin_manager::record((int) $user->id);
        mission_manager::update((int) $user->id, (int) $season->id, 'game_played');
        $data = $this->export_for($user);

        $this->assertSame(1, $data['checkin_done']);
        $completed = array_filter($data['missions'], fn(array $mission): bool => $mission['completed']);
        $this->assertCount(1, $completed);
        $this->assertSame(100, reset($completed)['progress_pct']);
    }

    /**
     * A season whose name is only the label, or has none of it, is shown as it is.
     */
    public function test_the_season_name_is_only_stripped_of_a_leading_label(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $season = $this->start_season('Spring Cup');

        $this->assertSame('Spring Cup', $this->export_for($user)['season_name']);

        $DB->set_field('local_playergames_seasons', 'name', 'Season', ['id' => $season->id]);
        $this->assertSame('Season', $this->export_for($user)['season_name']);
    }

    /**
     * Students see the students' ranking, best first, without who hid themselves or was suspended.
     */
    public function test_students_see_the_ranking_of_the_students_in_order(): void {
        $this->resetAfterTest();
        $season = $this->start_season();
        $viewer = $this->player($season, 20, true, ['firstname' => 'Second']);
        $this->player($season, 30, true, ['firstname' => 'First']);
        $this->player($season, 10, true, ['firstname' => 'Third']);
        $this->player($season, 99, false, ['firstname' => 'Hidden']);
        $this->player($season, 98, true, ['firstname' => 'Suspended', 'suspended' => 1]);
        $teacher = $this->player($season, 97, true, ['firstname' => 'Teacher']);
        $this->make_teacher($teacher);

        $data = $this->export_for($viewer);

        $this->assertFalse($data['showbothtabs']);
        $this->assertTrue($data['hasstudentranking']);
        $this->assertFalse($data['hasstaffranking']);
        $this->assertSame([1, 2, 3], array_column($data['studentranking'], 'pos'));
        $this->assertSame([30, 20, 10], array_column($data['studentranking'], 'xp'));
        $this->assertStringContainsString('First', $data['studentranking'][0]['name']);
        $this->assertSame([false, true, false], array_column($data['studentranking'], 'isself'));
        $this->assertSame(2, $data['selfinstudents']);
        $this->assertSame(0, $data['selfinstaff']);
        $this->assertSame('', $data['str_your_position']);
        $this->assertFalse($data['studentranking'][0]['extra']);
    }

    /**
     * The ranking is shared through the cache, but who is the viewer is worked out again for each one.
     */
    public function test_the_cached_ranking_marks_the_current_viewer_each_time(): void {
        $this->resetAfterTest();
        $season = $this->start_season();
        $first = $this->player($season, 30, true);
        $second = $this->player($season, 20, true);

        $this->assertSame(1, $this->export_for($first)['selfinstudents']);
        $this->assertSame(2, $this->export_for($second)['selfinstudents']);
    }

    /**
     * Staff see the staff ranking only, and the administrator and a mixed site see both.
     */
    public function test_staff_and_administrators_see_the_staff_ranking(): void {
        $this->resetAfterTest();
        $season = $this->start_season();
        $teacher = $this->player($season, 40, true);
        $this->make_teacher($teacher);
        $student = $this->player($season, 50, true);

        $staffonly = $this->export_for($teacher, true, false, 'staff');
        $both = $this->export_for($teacher, true, false, 'both');
        $admin = $this->export_for($student, false, true, 'both');

        $this->assertFalse($staffonly['showbothtabs']);
        $this->assertSame([(int) $teacher->id], array_column($staffonly['staffranking'], 'userid'));
        $this->assertSame([], $staffonly['studentranking']);
        $this->assertSame(1, $staffonly['selfinstaff']);
        $this->assertTrue($both['showbothtabs']);
        $this->assertTrue($both['hasstaffranking']);
        $this->assertTrue($both['hasstudentranking']);
        $this->assertTrue($admin['showbothtabs']);
        $this->assertSame([(int) $student->id], array_column($admin['studentranking'], 'userid'));
    }

    /**
     * Without any staff the staff ranking is empty instead of listing everybody.
     */
    public function test_a_site_without_staff_has_an_empty_staff_ranking(): void {
        $this->resetAfterTest();
        $season = $this->start_season();
        $viewer = $this->player($season, 40, true);

        $data = $this->export_for($viewer, true, true, 'both');

        $this->assertSame([], $data['staffranking']);
        $this->assertCount(1, $data['studentranking']);
    }

    /**
     * An administrator can switch the ranking off, and nobody is listed.
     */
    public function test_the_ranking_can_be_switched_off(): void {
        $this->resetAfterTest();
        $season = $this->start_season();
        $viewer = $this->player($season, 40, true);
        set_config('enable_ranking', 0, 'local_playergames');

        $data = $this->export_for($viewer, false, true, 'both');

        $this->assertFalse($data['rankingenabled']);
        $this->assertFalse($data['showbothtabs']);
        $this->assertSame([], $data['studentranking']);
        $this->assertSame([], $data['staffranking']);
        $this->assertSame('', $data['str_your_position']);
    }

    /**
     * Someone who ranks below the fifty listed still learns their position.
     */
    public function test_a_viewer_below_the_listed_fifty_gets_their_position(): void {
        global $DB;
        $this->resetAfterTest();
        $season = $this->start_season();
        $users = [];
        for ($i = 0; $i < 52; $i++) {
            $users[] = $this->getDataGenerator()->create_user();
        }
        $records = [];
        foreach ($users as $i => $user) {
            $records[] = (object) [
                'userid' => $user->id, 'seasonid' => $season->id, 'xp' => 1000 - $i, 'level' => 1,
                'showinranking' => 1, 'timecreated' => 1700000000, 'timemodified' => 1700000000,
            ];
        }
        $DB->insert_records('local_playergames_player_profile', $records);

        $data = $this->export_for($users[51]);

        $this->assertCount(50, $data['studentranking']);
        $this->assertTrue($data['studentranking'][10]['extra']);
        $this->assertSame(0, $data['selfinstudents']);
        $this->assertSame(get_string('hub_your_position', 'local_playergames', 52), $data['str_your_position']);
    }

    /**
     * The learning XP is hidden until the admin turns it on, and for a staff-only site.
     */
    public function test_the_learning_xp_follows_the_setting_and_the_participants(): void {
        $this->resetAfterTest();
        $this->start_season();
        $user = $this->getDataGenerator()->create_user();

        $this->assertFalse($this->export_for($user)['showlearningxp']);

        set_config('showlearningxp', 1, 'local_playergames');
        $this->assertTrue($this->export_for($user)['showlearningxp']);
        $this->assertFalse($this->export_for($user, true, false, 'staff')['showlearningxp']);
    }

    /**
     * With the learning ranking on, it lists who earned XP, and a viewer with none is told so.
     */
    public function test_the_learning_ranking_lists_those_who_earned_xp(): void {
        global $DB;
        $this->resetAfterTest();
        $this->start_season();
        set_config('showlearningxp', 1, 'local_playergames');
        set_config('learningxpranking', 1, 'local_playergames');
        $top = $this->getDataGenerator()->create_user();
        $viewer = $this->getDataGenerator()->create_user();
        $idle = $this->getDataGenerator()->create_user();
        $hidden = $this->getDataGenerator()->create_user();
        $now = 1700000000;
        foreach ([[$top, 80, 1], [$viewer, 0, 1], [$idle, 0, 1], [$hidden, 90, 0]] as [$user, $xp, $show]) {
            $DB->insert_record('local_playergames_student_xp_cache', (object) [
                'userid' => $user->id, 'windowedxp' => $xp, 'showinranking' => $show,
                'timecreated' => $now, 'timemodified' => $now,
            ]);
        }

        $data = $this->export_for($viewer);

        $this->assertTrue($data['learningxprankingenabled']);
        $this->assertTrue($data['haslearningranking']);
        $this->assertSame([(int) $top->id], array_column($data['learningranking'], 'userid'));
        $this->assertSame(0, $data['learningxp']);
        $this->assertTrue($data['learningzeroxp']);
        $this->assertFalse($data['haslearningposition']);
        $this->assertSame(0, $data['selfinlearning']);
    }

    /**
     * A viewer with learning XP, who ranks below the fifty listed, gets their position.
     */
    public function test_a_viewer_below_the_listed_fifty_gets_their_learning_position(): void {
        global $DB;
        $this->resetAfterTest();
        $this->start_season();
        set_config('showlearningxp', 1, 'local_playergames');
        set_config('learningxpranking', 1, 'local_playergames');
        $users = [];
        $records = [];
        for ($i = 0; $i < 52; $i++) {
            $users[] = $user = $this->getDataGenerator()->create_user();
            $records[] = (object) [
                'userid' => $user->id, 'windowedxp' => 1000 - $i, 'showinranking' => 1,
                'timecreated' => 1700000000, 'timemodified' => 1700000000,
            ];
        }
        $DB->insert_records('local_playergames_student_xp_cache', $records);

        $data = $this->export_for($users[51]);

        $this->assertCount(50, $data['learningranking']);
        $this->assertSame(949, $data['learningxp']);
        $this->assertTrue($data['haslearningposition']);
        $this->assertSame(get_string('hub_your_position', 'local_playergames', 52), $data['str_learning_position']);
        $this->assertFalse($data['learningzeroxp']);
    }

    /**
     * The profile card lists the last freeze events, earned or used.
     */
    public function test_the_freeze_log_lists_what_was_earned_and_used(): void {
        global $DB;
        $this->resetAfterTest();
        $this->start_season();
        $user = $this->getDataGenerator()->create_user();
        streak_manager::add_freezes((int) $user->id, 1);
        $DB->insert_record('local_playergames_freeze_log', (object) [
            'userid' => $user->id, 'action' => 'used', 'amount' => 1, 'reason' => 'streak_break',
            'timecreated' => time() + 5,
        ]);

        $data = $this->export_for($user);

        $this->assertTrue($data['hasfreezelog']);
        $this->assertSame(
            [get_string('freeze_log_used', 'local_playergames'), get_string('freeze_log_earned', 'local_playergames', 1)],
            array_column($data['freezelog'], 'label')
        );
        $this->assertSame([false, true], array_column($data['freezelog'], 'isearned'));
        $this->assertSame(1, $data['freezes']);
    }

    /**
     * The avatar the viewer wears is shown, and the collection is grouped by tier with the worn one marked.
     */
    public function test_the_avatar_collection_is_grouped_by_tier_with_the_equipped_one(): void {
        $this->resetAfterTest();
        $this->start_season();
        $user = $this->getDataGenerator()->create_user();

        $before = $this->export_for($user);
        $emoji = $before['avatarsbytier'][0]['avatars'][0]['emoji'];
        avatar_manager::equip((int) $user->id, $emoji);
        $after = $this->export_for($user);

        $this->assertFalse($before['hasequippedavatar']);
        $this->assertSame($emoji, $before['avatarsbytier'][0]['avatars'][0]['equipvalue']);
        $this->assertGreaterThan(1, count($before['avatarsbytier']));
        $this->assertTrue($after['hasequippedavatar']);
        $this->assertSame($emoji, $after['equippedavatar']);
        $this->assertSame('', $after['avatarsbytier'][0]['avatars'][0]['equipvalue']);
        $this->assertNotSame('', $after['userpictureurl']);
    }

    /**
     * Everything the hub exports renders through its template.
     */
    public function test_a_hub_with_a_season_renders(): void {
        global $PAGE;
        $this->resetAfterTest();
        $season = $this->start_season();
        $viewer = $this->player($season, 20, true, ['firstname' => 'Visible']);

        $html = $PAGE->get_renderer('core')->render_from_template('local_playergames/hub', $this->export_for($viewer));

        $this->assertStringContainsString('Visible', $html);
        $this->assertStringContainsString(get_string('hub_ranking_section', 'local_playergames'), $html);
    }
}
