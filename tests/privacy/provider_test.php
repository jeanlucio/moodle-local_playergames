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
 * Tests for the privacy provider.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Unit tests for {@see provider}.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(provider::class)]
final class provider_test extends \core_privacy\tests\provider_testcase {
    /**
     * Seeds gamification rows for a user across several tables.
     *
     * @param int $userid The user to seed data for.
     * @return void
     */
    private function seed_user(int $userid): void {
        global $DB;
        $now = time();
        $DB->insert_record('local_playergames_player_profile', (object) [
            'userid' => $userid, 'seasonid' => 1, 'xp' => 50, 'level' => 2,
            'showinranking' => 0, 'timecreated' => $now, 'timemodified' => $now,
        ]);
        $DB->insert_record('local_playergames_daily_scores', (object) [
            'userid' => $userid, 'gamedate' => mktime(0, 0, 0), 'gametype' => 'quiz',
            'completed' => 1, 'xpawarded' => 10, 'attempts' => 1, 'timeplayed' => $now,
        ]);
        $DB->insert_record('local_playergames_streaks', (object) [
            'userid' => $userid, 'currentstreak' => 3, 'longeststreak' => 3,
            'freezesavailable' => 0, 'lastactivedate' => mktime(0, 0, 0),
        ]);
    }

    /**
     * Inserts a cartridge uploaded by the given user.
     *
     * @param int $userid The uploader.
     * @return int Cartridge id.
     */
    private function seed_cartridge(int $userid): int {
        global $DB;
        $now = time();
        return (int) $DB->insert_record('local_playergames_cartridges', (object) [
            'name' => 'Pack', 'version' => '1.0', 'language' => 'en', 'type' => 'concept',
            'timecreated' => $now, 'timemodified' => $now, 'uploadedby' => $userid, 'active' => 1,
        ]);
    }

    public function test_get_metadata_returns_populated_collection(): void {
        $collection = new collection('local_playergames');
        $result = provider::get_metadata($collection);
        $this->assertSame($collection, $result);
        $this->assertNotEmpty($result->get_collection());
    }

    public function test_get_contexts_for_userid(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $this->seed_user((int) $user->id);

        $contextlist = provider::get_contexts_for_userid((int) $user->id);
        $this->assertCount(1, $contextlist->get_contextids());
        $this->assertSame(
            \context_system::instance()->id,
            (int) $contextlist->get_contextids()[0]
        );

        // A user with no data has no contexts.
        $this->assertCount(0, provider::get_contexts_for_userid((int) $other->id)->get_contextids());
    }

    public function test_get_users_in_context(): void {
        $this->resetAfterTest();
        $a = $this->getDataGenerator()->create_user();
        $b = $this->getDataGenerator()->create_user();
        $this->seed_user((int) $a->id);
        $this->seed_cartridge((int) $b->id);

        $userlist = new userlist(\context_system::instance(), 'local_playergames');
        provider::get_users_in_context($userlist);
        $ids = $userlist->get_userids();

        $this->assertContains((int) $a->id, $ids);
        $this->assertContains((int) $b->id, $ids);
    }

    public function test_export_user_data(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->seed_user((int) $user->id);

        $contextlist = new approved_contextlist(
            $user,
            'local_playergames',
            [\context_system::instance()->id]
        );
        provider::export_user_data($contextlist);

        $writer = writer::with_context(\context_system::instance());
        $this->assertTrue($writer->has_any_data());
    }

    public function test_delete_data_for_user(): void {
        global $DB;
        $this->resetAfterTest();
        $a = $this->getDataGenerator()->create_user();
        $b = $this->getDataGenerator()->create_user();
        $this->seed_user((int) $a->id);
        $this->seed_user((int) $b->id);
        $cartridgeid = $this->seed_cartridge((int) $a->id);

        $contextlist = new approved_contextlist(
            $a,
            'local_playergames',
            [\context_system::instance()->id]
        );
        provider::delete_data_for_user($contextlist);

        // User A's gamification data is gone; user B's is untouched.
        $this->assertFalse($DB->record_exists('local_playergames_player_profile', ['userid' => $a->id]));
        $this->assertTrue($DB->record_exists('local_playergames_player_profile', ['userid' => $b->id]));
        // The shared cartridge is preserved but the uploader link is anonymised.
        $this->assertSame(0, (int) $DB->get_field(
            'local_playergames_cartridges',
            'uploadedby',
            ['id' => $cartridgeid]
        ));
    }

    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;
        $this->resetAfterTest();
        $a = $this->getDataGenerator()->create_user();
        $b = $this->getDataGenerator()->create_user();
        $this->seed_user((int) $a->id);
        $this->seed_user((int) $b->id);
        $cartridgeid = $this->seed_cartridge((int) $a->id);

        provider::delete_data_for_all_users_in_context(\context_system::instance());

        $this->assertSame(0, $DB->count_records('local_playergames_player_profile'));
        $this->assertSame(0, $DB->count_records('local_playergames_daily_scores'));
        $this->assertSame(0, $DB->count_records('local_playergames_streaks'));
        // Cartridge content remains, with the uploader anonymised.
        $this->assertTrue($DB->record_exists('local_playergames_cartridges', ['id' => $cartridgeid]));
        $this->assertSame(0, (int) $DB->get_field(
            'local_playergames_cartridges',
            'uploadedby',
            ['id' => $cartridgeid]
        ));
    }

    public function test_delete_data_for_users(): void {
        global $DB;
        $this->resetAfterTest();
        $a = $this->getDataGenerator()->create_user();
        $b = $this->getDataGenerator()->create_user();
        $this->seed_user((int) $a->id);
        $this->seed_user((int) $b->id);

        $userlist = new approved_userlist(
            \context_system::instance(),
            'local_playergames',
            [(int) $a->id]
        );
        provider::delete_data_for_users($userlist);

        $this->assertFalse($DB->record_exists('local_playergames_player_profile', ['userid' => $a->id]));
        $this->assertTrue($DB->record_exists('local_playergames_player_profile', ['userid' => $b->id]));
    }

    public function test_export_user_preferences_exports_key_presence_only(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        set_user_preference('local_playergames_gemini_key', 'super-secret-value', $user);

        provider::export_user_preferences((int) $user->id);

        $writer = writer::with_context(\context_system::instance());
        $this->assertTrue($writer->has_any_data());
        $prefs = $writer->get_user_preferences('local_playergames');
        $this->assertObjectHasProperty('local_playergames_gemini_key', $prefs);
        $this->assertStringNotContainsString(
            'super-secret-value',
            $prefs->local_playergames_gemini_key->value
        );
    }
}
