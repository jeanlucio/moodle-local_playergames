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
 * Tests that the names the cartridge pages and the hub show are escaped exactly once.
 *
 * @package    local_playergames
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\local;

use local_playergames\cartridge\controller;
use local_playergames\hub\level_manager;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * A cartridge, a category or a concept with an ampersand in the name must reach the page escaped once:
 * format_string() already returns HTML, so a double-mustache tag would print "&amp;amp;".
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(text::class)]
#[CoversClass(controller::class)]
#[CoversClass(level_manager::class)]
final class text_test extends \advanced_testcase {
    /**
     * Runs a check under each value of formatstringstriptags; the formatter caches its result per container
     * without the setting in the key, so the container is reset between the two values.
     *
     * @param callable $check The assertions to run.
     */
    private function for_each_striptags(callable $check): void {
        foreach ([1, 0] as $striptags) {
            set_config('formatstringstriptags', $striptags);
            \core\di::reset_container();
            $check($striptags);
        }
    }

    public function test_plain_returns_the_text_as_typed(): void {
        $this->resetAfterTest();

        $this->for_each_striptags(function (int $striptags): void {
            $this->assertSame('Q & A > 3', text::plain('Q & A > 3'), "striptags={$striptags}");
            $this->assertSame('Q & A', text::plain('<b>Q</b> &amp; A'), "striptags={$striptags}");
            $this->assertSame('', text::plain(null), "striptags={$striptags}");
        });
    }

    public function test_the_level_title_is_plain_text(): void {
        global $DB;

        $this->resetAfterTest();
        level_manager::get_levels();
        $DB->set_field('local_playergames_levels', 'title', 'Knight & Squire', ['level' => 2]);

        $this->for_each_striptags(function (int $striptags): void {
            $this->assertSame('Knight & Squire', level_manager::title_for_level(2), "striptags={$striptags}");
        });
    }

    public function test_the_cartridge_pages_escape_the_names_once(): void {
        global $DB, $PAGE;

        $this->resetAfterTest();
        $this->setAdminUser();
        $PAGE->set_url('/local/playergames/cartridge.php');
        $PAGE->set_context(\context_system::instance());
        $uploader = $this->getDataGenerator()->create_user(['firstname' => 'Tom', 'lastname' => "O'Brien & Co"]);
        $now = time();
        $cartridgeid = $DB->insert_record('local_playergames_cartridges', (object) [
            'name' => 'Q & A > 3', 'version' => '1.0', 'language' => 'en', 'timecreated' => $now,
            'timemodified' => $now, 'uploadedby' => $uploader->id, 'author' => 'Ann & Bob', 'active' => 1,
            'type' => 'concept',
        ]);
        $categoryid = $DB->insert_record('local_playergames_categories', (object) [
            'cartridgeid' => $cartridgeid, 'name' => 'Tom & Jerry', 'sortorder' => 0, 'timecreated' => $now,
        ]);
        $DB->insert_record('local_playergames_concepts', (object) [
            'cartridgeid' => $cartridgeid, 'term' => 'Salt & Pepper', 'definition' => 'Spice & herb',
            'difficulty' => 1, 'categoryid' => $categoryid, 'language' => 'en',
        ]);

        $this->for_each_striptags(function (int $striptags) use ($cartridgeid, $PAGE): void {
            $output = $PAGE->get_renderer('core');
            $library = (new controller(\context_system::instance(), 'library', 0, 0))->get_renderable($output);
            $editor = (new controller(\context_system::instance(), 'editor', $cartridgeid, 0))->get_renderable($output);
            $html = $library['content_html'] . $editor['content_html'];

            $expected = ['Q &amp; A &gt; 3', 'O&#039;Brien &amp; Co', 'Tom &amp; Jerry', 'Salt &amp; Pepper', 'Spice &amp; herb'];
            foreach ($expected as $text) {
                $this->assertStringContainsString($text, $html, "striptags={$striptags}: {$text}");
            }
            $this->assertStringNotContainsString('&amp;amp;', $html, "striptags={$striptags}");
        });
    }

    public function test_the_edit_forms_fill_the_inputs_with_the_stored_text(): void {
        global $DB, $PAGE;

        $this->resetAfterTest();
        $this->setAdminUser();
        $PAGE->set_url('/local/playergames/cartridge.php');
        $PAGE->set_context(\context_system::instance());
        $now = time();
        $conceptcartridge = $DB->insert_record('local_playergames_cartridges', (object) [
            'name' => 'Words', 'version' => '1.0', 'language' => 'pt & en', 'timecreated' => $now,
            'timemodified' => $now, 'uploadedby' => 2, 'author' => 'Ann', 'active' => 1, 'type' => 'concept',
        ]);
        $conceptid = $DB->insert_record('local_playergames_concepts', (object) [
            'cartridgeid' => $conceptcartridge, 'term' => 'Salt & "Pepper"', 'definition' => 'Spice & herb',
            'difficulty' => 1, 'categoryid' => 0, 'language' => 'en',
        ]);
        $quizcartridge = $DB->insert_record('local_playergames_cartridges', (object) [
            'name' => 'Quiz', 'version' => '1.0', 'language' => 'en', 'timecreated' => $now,
            'timemodified' => $now, 'uploadedby' => 2, 'author' => 'Ann', 'active' => 1, 'type' => 'quiz',
        ]);
        $questionid = $DB->insert_record('local_playergames_concept_questions', (object) [
            'cartridgeid' => $quizcartridge, 'questiontext' => 'Tom & "Jerry"?', 'source' => 'manual',
            'difficulty' => 3, 'timecreated' => $now,
        ]);
        foreach ([['Cat & mouse', 1], ['Dog & bone', 0]] as $order => [$answer, $iscorrect]) {
            $DB->insert_record('local_playergames_concept_answers', (object) [
                'questionid' => $questionid, 'answertext' => $answer, 'iscorrect' => $iscorrect, 'sortorder' => $order,
            ]);
        }

        $ids = [$conceptcartridge, $conceptid, $quizcartridge, $questionid];
        $this->for_each_striptags(function (int $striptags) use ($ids, $PAGE): void {
            [$conceptcartridge, $conceptid, $quizcartridge, $questionid] = $ids;
            $output = $PAGE->get_renderer('core');
            $context = \context_system::instance();
            $library = (new controller($context, 'library', 0, 0))->get_renderable($output)['content_html'];
            $concept = (new controller($context, 'editor', $conceptcartridge, $conceptid))
                ->get_renderable($output)['content_html'];
            $quiz = (new controller($context, 'editor', $quizcartridge, 0, 'concepts', $questionid))
                ->get_renderable($output)['content_html'];

            $this->assertStringContainsString('pt &amp; en', $library, "striptags={$striptags}");
            $this->assertStringContainsString('value="Salt &amp; &quot;Pepper&quot;"', $concept, "striptags={$striptags}");
            $this->assertStringContainsString('value="Tom &amp; &quot;Jerry&quot;?"', $quiz, "striptags={$striptags}");
            $this->assertStringContainsString('value="Cat &amp; mouse"', $quiz, "striptags={$striptags}");
            foreach ([$library, $concept, $quiz] as $html) {
                $this->assertStringNotContainsString('&amp;amp;', $html, "striptags={$striptags}");
                $this->assertStringNotContainsString('&amp;quot;', $html, "striptags={$striptags}");
            }
        });
    }
}
