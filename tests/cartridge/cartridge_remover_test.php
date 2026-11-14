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

/**
 * Unit tests for {@see cartridge_remover}.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_playergames\cartridge\cartridge_remover
 */
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
     * Creates a concept cartridge with no content.
     *
     * @return int Cartridge id.
     */
    private function new_cartridge(): int {
        global $DB;
        $now = time();
        return (int) $DB->insert_record('local_playergames_cartridges', (object) [
            'name' => 'Empty', 'version' => '1.0', 'language' => 'en', 'type' => 'concept',
            'timecreated' => $now, 'timemodified' => $now, 'uploadedby' => 0, 'active' => 1,
        ]);
    }

    /**
     * Adds a concept to a cartridge.
     *
     * @param int $cartridgeid The cartridge.
     * @param string $term The term.
     * @return int Concept id.
     */
    private function new_concept(int $cartridgeid, string $term): int {
        global $DB;
        return (int) $DB->insert_record('local_playergames_concepts', (object) [
            'cartridgeid' => $cartridgeid, 'term' => $term, 'definition' => 'A fruit',
        ]);
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

    /**
     * Deleting a concept removes it and the assignments of any day that point at it, only in its own cartridge.
     */
    public function test_delete_concept_removes_the_concept_and_its_assignments(): void {
        global $DB;
        $this->resetAfterTest();
        $mine = $this->new_cartridge();
        $other = $this->new_cartridge();
        $concept = $this->new_concept($mine, 'apple');
        $kept = $this->new_concept($mine, 'grape');
        $yesterday = mktime(0, 0, 0) - DAYSECS;
        foreach ([[$yesterday, $concept], [$yesterday, $kept]] as [$date, $id]) {
            $DB->insert_record('local_playergames_daily_assignments', (object) [
                'gamedate' => $date, 'gametype' => 'guess', 'conceptid' => $id,
            ]);
        }

        $this->assertFalse((new cartridge_remover())->delete_concept($concept, $other));
        $this->assertTrue($DB->record_exists('local_playergames_concepts', ['id' => $concept]));

        $this->assertFalse((new cartridge_remover())->delete_concept($concept, $mine));

        $this->assertFalse($DB->record_exists('local_playergames_concepts', ['id' => $concept]));
        $this->assertSame(
            [$kept],
            array_map('intval', array_values($DB->get_fieldset_select('local_playergames_daily_assignments', 'conceptid', '1 = 1')))
        );
    }

    /**
     * A concept that was one of today's words takes today's set of that game with it, so it can be drawn again.
     */
    public function test_delete_concept_drops_todays_set_of_the_game(): void {
        global $DB;
        $this->resetAfterTest();
        $cartridge = $this->new_cartridge();
        $first = $this->new_concept($cartridge, 'apple');
        $second = $this->new_concept($cartridge, 'grape');
        $today = mktime(0, 0, 0);
        foreach ([$first, $second] as $id) {
            $DB->insert_record('local_playergames_daily_assignments', (object) [
                'gamedate' => $today, 'gametype' => 'fill', 'conceptid' => $id,
            ]);
        }
        $DB->insert_record('local_playergames_daily_assignments', (object) [
            'gamedate' => $today, 'gametype' => 'guess', 'conceptid' => $second,
        ]);

        $this->assertTrue((new cartridge_remover())->delete_concept($first, $cartridge));

        $this->assertSame(0, $DB->count_records('local_playergames_daily_assignments', ['gametype' => 'fill']));
        $this->assertSame(1, $DB->count_records('local_playergames_daily_assignments', ['gametype' => 'guess']));
    }
}
