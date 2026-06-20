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
 * Tests for the AI API key helper.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Unit tests for {@see api_key_helper}.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(api_key_helper::class)]
final class api_key_helper_test extends \advanced_testcase {
    public function test_personal_key_save_get_and_clear(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        $this->assertSame('', api_key_helper::get_personal_key('gemini', (int) $user->id));
        $this->assertFalse(api_key_helper::has_personal_key((int) $user->id));

        api_key_helper::save_user_key('gemini', 'abc123', (int) $user->id);
        $this->assertSame('abc123', api_key_helper::get_personal_key('gemini', (int) $user->id));
        $this->assertTrue(api_key_helper::has_personal_key((int) $user->id));

        // Saving an empty value clears the key.
        api_key_helper::save_user_key('gemini', '', (int) $user->id);
        $this->assertSame('', api_key_helper::get_personal_key('gemini', (int) $user->id));
    }

    public function test_get_key_prefers_personal_over_site(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        set_config('groq_key', 'site-key', 'local_playergames');

        // With only a site key configured, that is returned.
        $this->assertSame('site-key', api_key_helper::get_key('groq', (int) $user->id));

        // A personal key takes precedence.
        api_key_helper::save_user_key('groq', 'personal-key', (int) $user->id);
        $this->assertSame('personal-key', api_key_helper::get_key('groq', (int) $user->id));
    }

    public function test_openai_baseurl_and_model_defaults(): void {
        $this->resetAfterTest();
        $this->assertSame(
            'https://api.openai.com/v1/chat/completions',
            api_key_helper::get_openai_baseurl()
        );
        $this->assertSame('gpt-4o-mini', api_key_helper::get_openai_model());

        set_config('openai_baseurl', 'https://example.test/v1', 'local_playergames');
        set_config('openai_model', 'custom-model', 'local_playergames');
        $this->assertSame('https://example.test/v1', api_key_helper::get_openai_baseurl());
        $this->assertSame('custom-model', api_key_helper::get_openai_model());
    }

    public function test_has_any_key_true_with_site_key(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        set_config('openai_key', 'k', 'local_playergames');
        $this->assertTrue(api_key_helper::has_any_key((int) $user->id));
    }
}
