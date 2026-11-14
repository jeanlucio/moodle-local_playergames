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
 * Tests for the plugin callbacks in lib.php.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames;

use context_course;
use context_system;
use context_user;
use navigation_node;

/**
 * Unit tests for the callbacks in lib.php.
 *
 * @package    local_playergames
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers ::local_playergames_extend_navigation_user_settings
 * @covers ::local_playergames_pluginfile
 */
final class lib_test extends \advanced_testcase {
    /**
     * Loads lib.php, which Moodle only includes when it looks for a callback.
     *
     * @return void
     */
    protected function setUp(): void {
        global $CFG;

        parent::setUp();
        require_once($CFG->dirroot . '/local/playergames/lib.php');
    }

    /**
     * Runs the preferences callback for a user's page, and returns the entry it added.
     *
     * @param \stdClass $user The user whose preferences page is shown.
     * @return navigation_node|false The node, or false when nothing was added.
     */
    private function preferences_entry(\stdClass $user) {
        $root = navigation_node::create('Preferences');
        $course = get_course(SITEID);

        local_playergames_extend_navigation_user_settings(
            $root,
            $user,
            context_user::instance($user->id),
            $course,
            context_course::instance($course->id)
        );

        return $root->find('local_playergames_gamification', navigation_node::TYPE_SETTING);
    }

    /**
     * A user finds the gamification page among their own preferences.
     */
    public function test_the_gamification_entry_is_in_the_users_own_preferences(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $node = $this->preferences_entry($user);

        $this->assertNotFalse($node);
        $this->assertSame('/local/playergames/gamification_preferences.php', $node->action->out_as_local_url(false));
    }

    /**
     * Another user's preferences page, and a user who cannot see the hub, get no entry.
     */
    public function test_the_gamification_entry_is_only_for_the_own_page_of_a_user_who_may_see_the_hub(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->assertFalse($this->preferences_entry($other));

        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('local/playergames:viewhub', CAP_PROHIBIT, $roleid, context_system::instance()->id, true);
        role_assign($roleid, $user->id, context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();

        $this->assertFalse($this->preferences_entry($user));
    }

    /**
     * The file callback answers "not found" when the bank serves nothing for the URL.
     */
    public function test_pluginfile_answers_false_when_there_is_nothing_to_serve(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());

        $this->assertFalse(local_playergames_pluginfile(
            null,
            null,
            context_system::instance(),
            'questiontext',
            [999999, 'chart.png'],
            false
        ));
    }
}
