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
 * Tests for the removal of a cartridge.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\cartridge;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Unit tests for {@see cartridge_remover}.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(cartridge_remover::class)]
final class cartridge_remover_test extends \advanced_testcase {
    /**
     * Creates a cartridge with one concept and one quiz question.
     *
     * @return array [cartridge id, concept id]
     */
    private function make_cartridge(): array {
        global $DB;
        $now = time();
        $cartridgeid = (int) $DB->insert_record('local_playergames_cartridges', (object) [
            'name' => 'Cart', 'version' => '1.0', 'language' => 'en', 'type' => 'concept',
            'timecreated' => $now, 'timemodified' => $now, 'uploadedby' => 0, 'active' => 1,
        ]);
        $conceptid = (int) $DB->insert_record('local_playergames_concepts', (object) [
            'cartridgeid' => $cartridgeid, 'term' => 'Atom', 'definition' => 'Smallest unit',
        ]);
        $questionid = (int) $DB->insert_record('local_playergames_concept_questions', (object) [
            'cartridgeid' => $cartridgeid, 'questiontext' => 'Q?', 'timecreated' => $now,
        ]);
        $DB->insert_record('local_playergames_concept_answers', (object) [
            'questionid' => $questionid, 'answertext' => 'A', 'iscorrect' => 1, 'sortorder' => 0,
        ]);
        return [$cartridgeid, $conceptid];
    }

    /**
     * Gives a season's game a list of cartridges.
     *
     * @param string|null $cartridgeids The JSON list, or null.
     * @param string $gametype The game.
     * @return int The row id.
     */
    private function season_game(?string $cartridgeids, string $gametype = 'quiz'): int {
        global $DB;
        return (int) $DB->insert_record('local_playergames_season_games', (object) [
            'seasonid' => 1, 'gametype' => $gametype, 'enabled' => 1, 'source' => 'cartridge',
            'cartridgeids' => $cartridgeids, 'auxid' => 0,
        ]);
    }

    public function test_the_cartridge_and_its_content_are_removed(): void {
        global $DB;
        $this->resetAfterTest();
        [$cartridgeid, $conceptid] = $this->make_cartridge();
        [$othercartridge] = $this->make_cartridge();
        $DB->insert_record('local_playergames_daily_assignments', (object) [
            'gamedate' => mktime(0, 0, 0), 'gametype' => 'guess', 'conceptid' => $conceptid,
        ]);

        (new cartridge_remover())->delete($cartridgeid);

        $this->assertFalse($DB->record_exists('local_playergames_cartridges', ['id' => $cartridgeid]));
        $this->assertFalse($DB->record_exists('local_playergames_concepts', ['cartridgeid' => $cartridgeid]));
        $this->assertFalse($DB->record_exists('local_playergames_concept_questions', ['cartridgeid' => $cartridgeid]));
        $this->assertSame(0, $DB->count_records('local_playergames_daily_assignments'));
        $this->assertTrue($DB->record_exists('local_playergames_cartridges', ['id' => $othercartridge]));
        $this->assertTrue($DB->record_exists('local_playergames_concepts', ['cartridgeid' => $othercartridge]));
        $this->assertSame(1, $DB->count_records('local_playergames_concept_answers'));
    }

    /**
     * Regression test: a game restricted to the deleted cartridge kept a list of ids that no longer existed,
     * so it had no content and the settings page showed it as if it used every cartridge.
     */
    public function test_the_cartridge_leaves_the_lists_of_the_season_games(): void {
        global $DB;
        $this->resetAfterTest();
        [$cartridgeid] = $this->make_cartridge();
        [$other] = $this->make_cartridge();
        $only = $this->season_game(json_encode([$cartridgeid]), 'quiz');
        $both = $this->season_game(json_encode([$cartridgeid, $other]), 'battle');
        $unrelated = $this->season_game(json_encode([$other]), 'guess');
        $unrestricted = $this->season_game(null, 'fill');

        (new cartridge_remover())->delete($cartridgeid);

        $this->assertNull($DB->get_field('local_playergames_season_games', 'cartridgeids', ['id' => $only]));
        $this->assertSame(
            json_encode([$other]),
            $DB->get_field('local_playergames_season_games', 'cartridgeids', ['id' => $both])
        );
        $this->assertSame(
            json_encode([$other]),
            $DB->get_field('local_playergames_season_games', 'cartridgeids', ['id' => $unrelated])
        );
        $this->assertNull($DB->get_field('local_playergames_season_games', 'cartridgeids', ['id' => $unrestricted]));
    }

    public function test_a_missing_cartridge_is_a_no_op(): void {
        global $DB;
        $this->resetAfterTest();
        [$cartridgeid] = $this->make_cartridge();
        $rowid = $this->season_game(json_encode([$cartridgeid]));

        (new cartridge_remover())->delete($cartridgeid + 100);

        $this->assertSame(1, $DB->count_records('local_playergames_cartridges'));
        $this->assertSame(
            json_encode([$cartridgeid]),
            $DB->get_field('local_playergames_season_games', 'cartridgeids', ['id' => $rowid])
        );
    }
}
