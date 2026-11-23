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
 * Tests for the games of a season, as the page that configures them shows them.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\output;

/**
 * Unit tests for {@see season_games}.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_playergames\output\season_games
 */
final class season_games_test extends \advanced_testcase {
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
            'name' => 'A & B', 'version' => '1.0', 'language' => 'en', 'type' => $type,
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
     * Exports the games of a season, keyed by game type.
     *
     * @param int $seasonid Season id.
     * @return array
     */
    private function rows(int $seasonid): array {
        global $PAGE;
        $data = (new season_games($seasonid))->export_for_template($PAGE->get_renderer('core'));
        return array_column($data['gamerows'], null, 'gametype');
    }

    /**
     * The cartridges are ticked as they were saved: all of them without a list, and none with an empty one.
     *
     * Regression test: unticking every cartridge saved "no list", and all of them came back ticked.
     */
    public function test_the_cartridges_are_ticked_as_they_were_saved(): void {
        global $DB;
        $this->resetAfterTest();
        $first = $this->add_cartridge(['apple']);
        $second = $this->add_cartridge(['grape']);
        $quiz = $this->add_cartridge([], 'quiz');
        $season = \local_playergames\hub\season_manager::create('Season', time() - DAYSECS, time() + DAYSECS);
        $game = fn(string $gametype): array => ['seasonid' => $season->id, 'gametype' => $gametype];

        foreach ($this->rows((int) $season->id) as $gametype => $row) {
            $this->assertNotContains(false, array_column($row['cartridges'], 'checked'), $gametype);
            $this->assertFalse($row['nonechecked'], $gametype);
            $this->assertSame('A & B', $row['cartridges'][0]['name']);
        }

        $DB->set_field('local_playergames_season_games', 'cartridgeids', json_encode([$second]), $game('guess'));
        $DB->set_field('local_playergames_season_games', 'cartridgeids', '[]', $game('fill'));
        $rows = $this->rows((int) $season->id);
        $this->assertEquals([$first => false, $second => true], array_column($rows['guess']['cartridges'], 'checked', 'id'));
        $this->assertEquals([$first => false, $second => false], array_column($rows['fill']['cartridges'], 'checked', 'id'));
        $this->assertFalse($rows['guess']['nonechecked']);
        $this->assertTrue($rows['fill']['nonechecked']);
        $this->assertSame([$quiz], array_column($rows['quiz']['cartridges'], 'id'));
    }

    /**
     * A word game warns when the cartridges it uses do not have the words it needs; a game switched off does not.
     */
    public function test_a_word_game_warns_about_too_few_words(): void {
        global $DB;
        $this->resetAfterTest();
        $phrases = $this->add_cartridge(['two words', '1822']);
        $this->add_cartridge(['apple', 'grape', 'lemon', 'mango', 'peach'], 'concept', 0);
        $season = \local_playergames\hub\season_manager::create('Season', time() - DAYSECS, time() + DAYSECS);
        $warning = fn(int $needed): string => get_string(
            'season_game_words_few',
            'local_playergames',
            ['usable' => 0, 'needed' => $needed]
        );

        // The words are in a cartridge that is switched off, so they do not count.
        $rows = $this->rows((int) $season->id);
        $this->assertSame($warning(1), $rows['guess']['fewwords']);
        $this->assertSame($warning(5), $rows['fill']['fewwords']);
        $this->assertSame('', $rows['quiz']['fewwords']);
        $this->assertSame('', $rows['quiz']['wordrule']);
        $this->assertEqualsCanonicalizing([true, false], array_column($rows['guess']['cartridges'], 'inactive'));
        $this->assertContains(
            get_string('season_game_words', 'local_playergames', ['usable' => 5, 'total' => 5]),
            array_column($rows['guess']['cartridges'], 'words')
        );

        // With no list every active cartridge counts, and with one ticked only that one does.
        $DB->set_field('local_playergames_cartridges', 'active', 1);
        $this->assertSame('', $this->rows((int) $season->id)['fill']['fewwords']);
        $DB->set_field(
            'local_playergames_season_games',
            'cartridgeids',
            json_encode([$phrases]),
            ['seasonid' => $season->id, 'gametype' => 'fill']
        );
        $this->assertSame($warning(5), $this->rows((int) $season->id)['fill']['fewwords']);
        $DB->set_field('local_playergames_season_games', 'enabled', 0, ['seasonid' => $season->id, 'gametype' => 'fill']);
        $this->assertSame('', $this->rows((int) $season->id)['fill']['fewwords']);
    }
}
