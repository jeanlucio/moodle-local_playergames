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
 * Tests for the glossary importer.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\cartridge;

/**
 * Unit tests for {@see glossary_importer}.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_playergames\cartridge\glossary_importer
 */
final class glossary_importer_test extends \advanced_testcase {
    /**
     * Creates a glossary.
     *
     * @param bool $global Whether it is a global glossary.
     * @param string $name The name.
     * @return \stdClass The glossary.
     */
    private function make_glossary(bool $global, string $name = 'Terms'): \stdClass {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $glossary = $this->getDataGenerator()->create_module('glossary', ['course' => $course->id, 'name' => $name]);
        $DB->set_field('glossary', 'globalglossary', $global ? 1 : 0, ['id' => $glossary->id]);
        return $glossary;
    }

    /**
     * Adds an entry to a glossary.
     *
     * @param \stdClass $glossary The glossary.
     * @param string $concept The term.
     * @param string $definition The definition, as HTML.
     * @param int $approved Whether it is approved.
     * @return void
     */
    private function add_entry(\stdClass $glossary, string $concept, string $definition, int $approved = 1): void {
        $this->getDataGenerator()->get_plugin_generator('mod_glossary')->create_content($glossary, [
            'concept' => $concept, 'definition' => $definition, 'approved' => $approved,
        ]);
    }

    public function test_only_global_glossaries_are_listed_with_their_approved_entries(): void {
        $this->resetAfterTest();
        $global = $this->make_glossary(true, 'Global terms');
        $course = $this->make_glossary(false, 'Course terms');
        $this->add_entry($global, 'Mitosis', 'Cell division');
        $this->add_entry($global, 'Meiosis', 'Another one');
        $this->add_entry($global, 'Pending', 'Not approved', 0);
        $this->add_entry($course, 'Hidden', 'In a course');

        $listed = (new glossary_importer())->global_glossaries();

        $this->assertSame([(int) $global->id], array_map('intval', array_keys($listed)));
        $this->assertSame(2, $listed[$global->id]->entries);
        $this->assertSame('Global terms', $listed[$global->id]->name);
    }

    public function test_import_copies_the_approved_entries_as_plain_text_into_a_new_cartridge(): void {
        global $DB;
        $this->resetAfterTest();
        $glossary = $this->make_glossary(true, 'Biology');
        $this->add_entry($glossary, 'Mitosis', '<p>Cell <b>division</b></p>');
        $this->add_entry($glossary, 'Pending', 'Not approved', 0);
        $user = $this->getDataGenerator()->create_user();

        $result = (new glossary_importer())->import((int) $glossary->id, (int) $user->id);

        $cartridge = $DB->get_record('local_playergames_cartridges', ['id' => $result->cartridgeid], '*', MUST_EXIST);
        $this->assertSame('concept', $cartridge->type);
        $this->assertEquals(1, $cartridge->active);
        $this->assertEquals($user->id, $cartridge->uploadedby);
        $this->assertSame(get_string('cartridge_glossary_name', 'local_playergames', 'Biology'), $cartridge->name);
        $concepts = $DB->get_records('local_playergames_concepts', ['cartridgeid' => $cartridge->id]);
        $this->assertCount(1, $concepts);
        $concept = reset($concepts);
        $this->assertSame('Mitosis', $concept->term);
        $this->assertSame('Cell division', $concept->definition);
    }

    public function test_a_glossary_of_a_course_is_not_imported(): void {
        $this->resetAfterTest();
        $glossary = $this->make_glossary(false);
        $this->add_entry($glossary, 'Mitosis', 'Cell division');

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('error_glossary_not_global', 'local_playergames'));
        (new glossary_importer())->import((int) $glossary->id, 2);
    }

    public function test_a_glossary_with_nothing_approved_is_not_imported(): void {
        global $DB;
        $this->resetAfterTest();
        $glossary = $this->make_glossary(true);
        $this->add_entry($glossary, 'Pending', 'Not approved', 0);

        try {
            (new glossary_importer())->import((int) $glossary->id, 2);
            $this->fail('An empty import must be refused.');
        } catch (\moodle_exception $e) {
            $this->assertSame(get_string('error_glossary_empty', 'local_playergames'), $e->getMessage());
        }
        $this->assertSame(0, $DB->count_records('local_playergames_cartridges'));
    }
}
