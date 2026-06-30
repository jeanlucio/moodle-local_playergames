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
 * Tests for the avatar collection manager.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\hub;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Unit tests for {@see avatar_manager}.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(avatar_manager::class)]
final class avatar_manager_test extends \advanced_testcase {
    public function test_seed_defaults_creates_17_avatars_in_four_tiers(): void {
        $this->resetAfterTest();

        $avatars = avatar_manager::get_avatars();
        $this->assertCount(17, $avatars);

        $bytier = array_count_values(array_map(static fn($a): int => (int) $a->tier, $avatars));
        $this->assertSame(5, $bytier[1]);
        $this->assertSame(4, $bytier[2]);
        $this->assertSame(4, $bytier[3]);
        $this->assertSame(4, $bytier[4]);
    }

    public function test_seed_is_idempotent(): void {
        $this->resetAfterTest();
        avatar_manager::get_avatars();
        avatar_manager::seed_defaults();
        $this->assertCount(17, avatar_manager::get_avatars());
    }

    public function test_tier_threshold_defaults(): void {
        $this->resetAfterTest();
        $this->assertSame(1, avatar_manager::tier_threshold(1));
        $this->assertSame(5, avatar_manager::tier_threshold(2));
        $this->assertSame(10, avatar_manager::tier_threshold(3));
        $this->assertSame(20, avatar_manager::tier_threshold(4));
    }

    public function test_tier_threshold_reads_admin_setting(): void {
        $this->resetAfterTest();
        set_config('avatar_tier2_level', 3, 'local_playergames');
        $this->assertSame(3, avatar_manager::tier_threshold(2));
    }

    public function test_is_unlocked_respects_tier_level(): void {
        $this->resetAfterTest();
        // Tier 1 (robot) unlocks at level 1; tier 3 (elf) at level 10.
        $this->assertTrue(avatar_manager::is_unlocked('🤖', 1));
        $this->assertFalse(avatar_manager::is_unlocked('🧝', 9));
        $this->assertTrue(avatar_manager::is_unlocked('🧝', 10));
        $this->assertFalse(avatar_manager::is_unlocked('not-an-avatar', 99));
    }

    public function test_record_level_keeps_the_best(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        avatar_manager::record_level((int) $user->id, 3);
        avatar_manager::record_level((int) $user->id, 2);

        $this->assertSame(3, (int) avatar_manager::get_state((int) $user->id)->bestlevel);
    }

    public function test_equip_unlocked_then_unequip(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        avatar_manager::equip((int) $user->id, '🤖');
        $this->assertSame('🤖', avatar_manager::get_state((int) $user->id)->equipped_avatar);

        avatar_manager::equip((int) $user->id, '');
        $this->assertNull(avatar_manager::get_state((int) $user->id)->equipped_avatar);
    }

    public function test_equip_locked_avatar_throws(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        // Tier-3 avatar with a fresh user at best level 1.
        $this->expectException(\moodle_exception::class);
        avatar_manager::equip((int) $user->id, '🧝');
    }

    public function test_equip_allowed_after_reaching_tier_level(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        avatar_manager::record_level((int) $user->id, 10);
        avatar_manager::equip((int) $user->id, '🧝');

        $this->assertSame('🧝', avatar_manager::get_state((int) $user->id)->equipped_avatar);
    }

    public function test_get_collection_flags_unlocked_and_equipped(): void {
        $this->resetAfterTest();
        $user = (int) $this->getDataGenerator()->create_user()->id;
        avatar_manager::record_level($user, 10);
        avatar_manager::equip($user, '🤖');

        $byemoji = [];
        foreach (avatar_manager::get_collection($user) as $row) {
            $byemoji[$row['emoji']] = $row;
        }

        $this->assertCount(17, $byemoji);
        // Tier 1 robot: unlocked and equipped.
        $this->assertTrue($byemoji['🤖']['unlocked']);
        $this->assertTrue($byemoji['🤖']['equipped']);
        // Tier 3 elf: unlocked at level 10, not equipped.
        $this->assertTrue($byemoji['🧝']['unlocked']);
        $this->assertFalse($byemoji['🧝']['equipped']);
        // Tier 4 dragon: still locked at level 10 (needs 20).
        $this->assertFalse($byemoji['🐉']['unlocked']);
    }
}
