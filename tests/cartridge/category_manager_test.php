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
 * Tests for the cartridge category manager.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\cartridge;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Unit tests for {@see category_manager}.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(category_manager::class)]
final class category_manager_test extends \advanced_testcase {
    /** @var int Cartridge id used across the test methods. */
    private int $cartridgeid;

    /** @var category_manager Subject under test. */
    private category_manager $manager;

    protected function setUp(): void {
        parent::setUp();
        global $DB;
        $this->resetAfterTest();
        $now = time();
        $this->cartridgeid = (int) $DB->insert_record('local_playergames_cartridges', (object) [
            'name' => 'Holder',
            'version' => '1.0',
            'language' => 'en',
            'type' => 'concept',
            'timecreated' => $now,
            'timemodified' => $now,
            'uploadedby' => 0,
            'active' => 1,
        ]);
        $this->manager = new category_manager();
    }

    public function test_create_assigns_incrementing_sortorder(): void {
        $first = $this->manager->create($this->cartridgeid, 'Alpha');
        $second = $this->manager->create($this->cartridgeid, 'Beta');

        global $DB;
        $a = $DB->get_record('local_playergames_categories', ['id' => $first], '*', MUST_EXIST);
        $b = $DB->get_record('local_playergames_categories', ['id' => $second], '*', MUST_EXIST);
        $this->assertSame(0, (int) $a->sortorder);
        $this->assertSame(1, (int) $b->sortorder);

        $all = $this->manager->get_categories($this->cartridgeid);
        $this->assertCount(2, $all);
    }

    public function test_create_empty_name_throws(): void {
        $this->expectException(\moodle_exception::class);
        $this->manager->create($this->cartridgeid, '   ');
    }

    /**
     * Regression test: the lookup used the name as received while create() stored it cut to 100 characters,
     * so a longer name was never found and each import added another category with the same name.
     */
    public function test_ensure_category_finds_a_name_longer_than_the_column(): void {
        global $DB;
        $long = str_repeat('Fundamentals ', 10);

        $id1 = $this->manager->ensure_category($this->cartridgeid, $long);
        $id2 = $this->manager->ensure_category($this->cartridgeid, $long);

        $this->assertSame($id1, $id2);
        $this->assertSame(1, $DB->count_records('local_playergames_categories', ['cartridgeid' => $this->cartridgeid]));
    }

    public function test_ensure_category_is_idempotent(): void {
        $id1 = $this->manager->ensure_category($this->cartridgeid, 'Science');
        $id2 = $this->manager->ensure_category($this->cartridgeid, 'Science');

        $this->assertSame($id1, $id2);
        $this->assertCount(1, $this->manager->get_categories($this->cartridgeid));
    }

    public function test_ensure_category_empty_returns_zero(): void {
        $this->assertSame(0, $this->manager->ensure_category($this->cartridgeid, '  '));
        $this->assertCount(0, $this->manager->get_categories($this->cartridgeid));
    }

    public function test_rename_changes_name(): void {
        global $DB;
        $id = $this->manager->create($this->cartridgeid, 'Old');
        $this->manager->rename($id, $this->cartridgeid, 'New');
        $this->assertSame('New', $DB->get_field('local_playergames_categories', 'name', ['id' => $id]));
    }

    public function test_rename_wrong_cartridge_throws(): void {
        $id = $this->manager->create($this->cartridgeid, 'Old');
        $this->expectException(\moodle_exception::class);
        // Ownership check: a foreign cartridge id must not be able to rename it.
        $this->manager->rename($id, $this->cartridgeid + 999, 'Hacked');
    }

    public function test_delete_nulls_concept_category(): void {
        global $DB;
        $catid = $this->manager->create($this->cartridgeid, 'Removable');
        $conceptid = (int) $DB->insert_record('local_playergames_concepts', (object) [
            'cartridgeid' => $this->cartridgeid,
            'term' => 'Term',
            'definition' => 'Def',
            'categoryid' => $catid,
            'difficulty' => 3,
            'language' => null,
        ]);

        $this->manager->delete($catid, $this->cartridgeid);

        $this->assertFalse($DB->record_exists('local_playergames_categories', ['id' => $catid]));
        $this->assertNull($DB->get_field('local_playergames_concepts', 'categoryid', ['id' => $conceptid]));
    }

    /**
     * Deleting a category also clears it from the questions of the cartridge.
     *
     * Regression test: the questions of a quiz cartridge were left pointing at a category that no
     * longer existed.
     */
    public function test_delete_nulls_question_category(): void {
        global $DB;
        $catid = $this->manager->create($this->cartridgeid, 'Removable');
        $questionid = (int) $DB->insert_record('local_playergames_concept_questions', (object) [
            'conceptid' => null, 'cartridgeid' => $this->cartridgeid, 'questiontext' => 'Q?',
            'source' => 'manual', 'difficulty' => 3, 'categoryid' => $catid,
            'generalfeedback' => null, 'timecreated' => time(),
        ]);

        $this->assertTrue($this->manager->delete($catid, $this->cartridgeid));

        $this->assertNull($DB->get_field('local_playergames_concept_questions', 'categoryid', ['id' => $questionid]));
    }

    /**
     * A category paired with another cartridge's id deletes nothing and touches nobody's content.
     *
     * Regression test: the concepts were cleared by category id alone, so naming another cartridge
     * stripped the category from the concepts of the one that owns it while the category stayed.
     */
    public function test_delete_with_another_cartridges_id_changes_nothing(): void {
        global $DB;
        $catid = $this->manager->create($this->cartridgeid, 'Mine');
        $conceptid = (int) $DB->insert_record('local_playergames_concepts', (object) [
            'cartridgeid' => $this->cartridgeid, 'term' => 'T', 'definition' => 'D',
            'categoryid' => $catid, 'difficulty' => 3, 'language' => null,
        ]);

        $this->assertFalse($this->manager->delete($catid, $this->cartridgeid + 999));

        $this->assertTrue($DB->record_exists('local_playergames_categories', ['id' => $catid]));
        $this->assertEquals($catid, $DB->get_field('local_playergames_concepts', 'categoryid', ['id' => $conceptid]));
    }
}
