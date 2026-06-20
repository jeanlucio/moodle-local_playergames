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
    public function test_get_metadata_returns_populated_collection(): void {
        $collection = new collection('local_playergames');
        $result = provider::get_metadata($collection);

        $this->assertSame($collection, $result);
        // The provider declares the three personal AI key preferences plus the
        // three external AI destinations.
        $this->assertNotEmpty($result->get_collection());
    }

    public function test_export_user_preferences_with_no_key_exports_nothing(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        provider::export_user_preferences((int) $user->id);

        $writer = writer::with_context(\context_system::instance());
        $this->assertFalse($writer->has_any_data());
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
        // The secret value must never be exported, only its presence.
        $this->assertStringNotContainsString(
            'super-secret-value',
            $prefs->local_playergames_gemini_key->value
        );
    }
}
