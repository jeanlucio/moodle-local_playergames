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
 * Tests for the words the word games may draw.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\games;

/**
 * Unit tests for {@see word_pool}.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_playergames\games\word_pool
 */
final class word_pool_test extends \advanced_testcase {
    /**
     * Adds a cartridge with the given terms.
     *
     * @param string[] $terms Terms.
     * @param string $type 'concept' or 'quiz'.
     * @param int $active Whether it is active.
     * @return int Cartridge id.
     */
    private function add_cartridge(array $terms, string $type = 'concept', int $active = 1): int {
        global $DB;
        $now = time();
        $id = (int) $DB->insert_record('local_playergames_cartridges', (object) [
            'name' => 'C', 'version' => '1.0', 'language' => 'en', 'type' => $type,
            'timecreated' => $now, 'timemodified' => $now, 'uploadedby' => 0, 'active' => $active,
        ]);
        foreach ($terms as $term) {
            $DB->insert_record('local_playergames_concepts', (object) [
                'cartridgeid' => $id, 'term' => $term, 'definition' => 'D',
            ]);
        }
        return $id;
    }

    /**
     * Only one word of letters, inside the range, serves a game.
     */
    public function test_fits(): void {
        foreach (['apple', 'Ação', 'PLANETAS'] as $term) {
            $this->assertTrue(word_pool::fits($term, [4, 8]), $term);
        }
        foreach (['', 'sol', 'gramatical', 'two words', 'AI-5', '1822', 'd\'água', 'U.S.A'] as $term) {
            $this->assertFalse(word_pool::fits($term, [4, 8]), $term);
        }
    }

    /**
     * The range comes from the settings, and one that matches nothing gives way to the default.
     */
    public function test_length_range(): void {
        $this->resetAfterTest();
        $this->assertSame([4, 8], word_pool::length_range('guess'));
        $this->assertSame([3, 12], word_pool::length_range('fill'));
        set_config('guess_minlen', 5, 'local_playergames');
        set_config('guess_maxlen', 6, 'local_playergames');
        $this->assertSame([5, 6], word_pool::length_range('guess'));
        set_config('guess_minlen', 9, 'local_playergames');
        $this->assertSame([9, 6], word_pool::configured_range('guess'));
        $this->assertSame([4, 8], word_pool::length_range('guess'));
    }

    /**
     * The shared range serves both games, and covers both when no length does.
     */
    public function test_shared_range(): void {
        $this->resetAfterTest();
        $this->assertSame([4, 8], word_pool::shared_range());
        set_config('fill_minlen', 6, 'local_playergames');
        $this->assertSame([6, 8], word_pool::shared_range());
        set_config('fill_minlen', 10, 'local_playergames');
        $this->assertSame([4, 12], word_pool::shared_range());
    }

    /**
     * Only active concept cartridges feed the games, narrowed to the ones the season keeps.
     */
    public function test_cartridges(): void {
        global $DB;
        $this->resetAfterTest();
        $first = $this->add_cartridge([]);
        $second = $this->add_cartridge([]);
        $this->add_cartridge([], 'concept', 0);
        $this->add_cartridge([], 'quiz');
        $this->assertEqualsCanonicalizing([$first, $second], word_pool::cartridges('guess'));

        $now = time();
        $seasonid = $DB->insert_record('local_playergames_seasons', (object) [
            'name' => 'S', 'startdate' => $now - DAYSECS, 'enddate' => $now + DAYSECS, 'status' => 'active',
            'config_snapshot' => '[]', 'timecreated' => $now, 'timemodified' => $now,
        ]);
        $DB->insert_record('local_playergames_season_games', (object) [
            'seasonid' => $seasonid, 'gametype' => 'guess', 'enabled' => 1, 'cartridgeids' => json_encode([$second]),
        ]);
        $this->assertSame([$second], word_pool::cartridges('guess'));
        $this->assertEqualsCanonicalizing([$first, $second], word_pool::cartridges('fill'));
    }

    /**
     * The terms are the ones that serve the game, and a limit stops the search early.
     */
    public function test_terms(): void {
        $this->resetAfterTest();
        $id = $this->add_cartridge(['apple', 'grape', 'two words', 'sol', 'gramatical']);
        $this->assertEqualsCanonicalizing(['apple', 'grape'], word_pool::terms('guess', [$id]));
        $this->assertEqualsCanonicalizing(['apple', 'grape', 'sol', 'gramatical'], word_pool::terms('fill', [$id]));
        $this->assertCount(1, word_pool::terms('guess', [$id], 1));
        $this->assertSame([], word_pool::terms('guess', []));
    }

    /**
     * PlayerGuess needs one word and PlayerFill as many as its puzzle has.
     */
    public function test_has_enough(): void {
        $this->resetAfterTest();
        $this->assertFalse(word_pool::has_enough('guess', word_pool::cartridges('guess')));
        $this->add_cartridge(['apple', 'grape', 'lemon', 'mango', 'two words']);
        $this->assertTrue(word_pool::has_enough('guess', word_pool::cartridges('guess')));
        $this->assertFalse(word_pool::has_enough('fill', word_pool::cartridges('fill')));
        $this->add_cartridge(['peach']);
        $this->assertTrue(word_pool::has_enough('fill', word_pool::cartridges('fill')));
        set_config('fill_num_words', 6, 'local_playergames');
        $this->assertSame(6, word_pool::needed('fill'));
        $this->assertFalse(word_pool::has_enough('fill', word_pool::cartridges('fill')));
    }

    /**
     * The page of the season counts the words of every cartridge, the inactive ones included.
     */
    public function test_usage(): void {
        $this->resetAfterTest();
        $first = $this->add_cartridge(['apple', 'sol', 'two words']);
        $second = $this->add_cartridge(['1822'], 'concept', 0);
        $empty = $this->add_cartridge([]);
        $usage = word_pool::usage();
        $this->assertSame(['total' => 3, 'guess' => 1, 'fill' => 2], $usage[$first]);
        $this->assertSame(['total' => 1, 'guess' => 0, 'fill' => 0], $usage[$second]);
        $this->assertArrayNotHasKey($empty, $usage);
    }
}
