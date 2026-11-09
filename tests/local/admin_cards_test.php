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
 * Tests for the cards of the Administration tab.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\local;

use local_playergames\hub\level_manager;
use local_playergames\output\administration;
use local_playergames\output\nav_header;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Unit tests for {@see admin_cards}.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(admin_cards::class)]
#[CoversClass(administration::class)]
final class admin_cards_test extends \advanced_testcase {
    /**
     * Returns every card of a user by its key.
     *
     * @param int $userid The user.
     * @return array[]
     */
    private function cards_of(int $userid): array {
        $cards = [];
        foreach (admin_cards::for_user($userid) as $group) {
            foreach ($group['cards'] as $card) {
                $cards[$card['key']] = $card;
            }
        }
        return $cards;
    }

    /**
     * Inserts a season.
     *
     * @param string $status active, upcoming or closed.
     * @param string $name The name.
     * @param int $start Start timestamp.
     * @return int The id.
     */
    private function make_season(string $status, string $name = 'Season', int $start = 0): int {
        global $DB;
        $start = $start ?: time() - DAYSECS;
        return (int) $DB->insert_record('local_playergames_seasons', (object) [
            'name' => $name, 'startdate' => $start, 'enddate' => $start + (DAYSECS * 30), 'status' => $status,
            'config_snapshot' => json_encode([]), 'timecreated' => $start, 'timemodified' => $start,
        ]);
    }

    /**
     * Gives a new user the manager role in the system context.
     *
     * @return int The user id.
     */
    private function make_manager(): int {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        role_assign(
            (int) $DB->get_field('role', 'id', ['shortname' => 'manager']),
            $user->id,
            \context_system::instance()->id
        );
        return (int) $user->id;
    }

    public function test_an_administrator_gets_every_card_in_the_order_of_the_groups(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $groups = admin_cards::for_user((int) $GLOBALS['USER']->id);

        $this->assertSame(['start', 'rules', 'help'], array_column($groups, 'key'));
        $keys = array_map(static fn($group): array => array_column($group['cards'], 'key'), $groups);
        $this->assertSame(['cartridges', 'seasons', 'seasongames'], $keys[0]);
        $this->assertSame(
            ['participation', 'xp', 'games', 'levels', 'avatars', 'streak', 'learning'],
            $keys[1]
        );
        $help = admin_cards::has_ai_hub() ? ['aikeys', 'docs'] : ['docs'];
        $this->assertSame($help, $keys[2]);
    }

    public function test_a_manager_gets_only_the_cartridges_and_the_documentation(): void {
        $this->resetAfterTest();
        $userid = $this->make_manager();

        $groups = admin_cards::for_user($userid);

        $this->assertTrue(admin_cards::can_open_any($userid));
        $this->assertSame(['start', 'help'], array_column($groups, 'key'));
        $this->assertSame(['cartridges'], array_column($groups[0]['cards'], 'key'));
        $this->assertSame(['docs'], array_column($groups[1]['cards'], 'key'));
    }

    public function test_a_student_gets_no_tab_and_no_card(): void {
        $this->resetAfterTest();
        $student = $this->getDataGenerator()->create_user();
        $course  = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');

        $this->assertFalse(admin_cards::can_open_any((int) $student->id));
        $this->assertSame([], admin_cards::for_user((int) $student->id));
    }

    public function test_the_tab_is_in_the_navigation_only_for_who_can_open_a_card(): void {
        global $PAGE;
        $this->resetAfterTest();
        $renderer = $PAGE->get_renderer('core');
        $keys = fn(): array => array_column(
            (new nav_header('hub'))->export_for_template($renderer)['items'],
            'key'
        );

        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertNotContains('admin', $keys());

        $this->setAdminUser();
        $this->assertContains('admin', $keys());
    }

    public function test_the_season_cards_say_what_is_going_on(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $userid = (int) $GLOBALS['USER']->id;

        $cards = $this->cards_of($userid);
        $this->assertSame(get_string('admin_state_season_none', 'local_playergames'), $cards['seasons']['state']);
        $this->assertSame(get_string('admin_state_games_none', 'local_playergames'), $cards['seasongames']['state']);

        $this->make_season('upcoming', 'Next', time() + (DAYSECS * 10));
        $cards = $this->cards_of($userid);
        $this->assertStringContainsString(
            userdate(time() + (DAYSECS * 10), get_string('strftimedate', 'langconfig')),
            $cards['seasons']['state']
        );

        $id = $this->make_season('active', 'Spring');
        $cards = $this->cards_of($userid);
        $this->assertSame(get_string('admin_state_season_active', 'local_playergames', 'Spring'), $cards['seasons']['state']);
        $this->assertSame(
            get_string('admin_state_games', 'local_playergames', (object) ['on' => 0, 'total' => 4]),
            $cards['seasongames']['state']
        );

        global $DB;
        foreach (['quiz', 'guess'] as $game) {
            $DB->insert_record('local_playergames_season_games', (object) [
                'seasonid' => $id, 'gametype' => $game, 'enabled' => 1, 'source' => 'cartridge', 'auxid' => 0,
            ]);
        }
        $cards = $this->cards_of($userid);
        $this->assertSame(
            get_string('admin_state_games', 'local_playergames', (object) ['on' => 2, 'total' => 4]),
            $cards['seasongames']['state']
        );
    }

    public function test_the_cartridges_card_counts_the_active_ones_and_their_content(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $userid = (int) $GLOBALS['USER']->id;
        $this->assertSame(
            get_string('admin_state_cartridges_none', 'local_playergames'),
            $this->cards_of($userid)['cartridges']['state']
        );

        $now = time();
        $active = (int) $DB->insert_record('local_playergames_cartridges', (object) [
            'name' => 'A', 'version' => '1.0', 'language' => 'en', 'type' => 'quiz',
            'timecreated' => $now, 'timemodified' => $now, 'uploadedby' => 0, 'active' => 1,
        ]);
        $inactive = (int) $DB->insert_record('local_playergames_cartridges', (object) [
            'name' => 'B', 'version' => '1.0', 'language' => 'en', 'type' => 'quiz',
            'timecreated' => $now, 'timemodified' => $now, 'uploadedby' => 0, 'active' => 0,
        ]);
        foreach ([$active, $active, $inactive] as $cartridgeid) {
            $DB->insert_record('local_playergames_concept_questions', (object) [
                'conceptid' => null, 'cartridgeid' => $cartridgeid, 'questiontext' => 'Q', 'source' => 'manual',
                'difficulty' => 3, 'categoryid' => null, 'generalfeedback' => null, 'timecreated' => $now,
            ]);
        }

        $this->assertSame(
            get_string('admin_state_cartridges', 'local_playergames', (object) ['cartridges' => 1, 'items' => 2]),
            $this->cards_of($userid)['cartridges']['state']
        );
    }

    public function test_the_rule_cards_show_who_plays_and_how_many_levels(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $userid = (int) $GLOBALS['USER']->id;

        $this->assertSame(
            get_string(
                'admin_state_participation',
                'local_playergames',
                get_string('settings_participants_students', 'local_playergames')
            ),
            $this->cards_of($userid)['participation']['state']
        );
        set_config('allowed_participants', 'both', 'local_playergames');
        $this->assertSame(
            get_string(
                'admin_state_participation',
                'local_playergames',
                get_string('settings_participants_both', 'local_playergames')
            ),
            $this->cards_of($userid)['participation']['state']
        );

        level_manager::seed_defaults();
        $levels = $this->cards_of($userid)['levels'];
        $this->assertTrue($levels['hasstate']);
        $this->assertStringContainsString((string) count(level_manager::get_levels()), $levels['state']);
        $this->assertFalse($this->cards_of($userid)['xp']['hasstate']);
    }

    /**
     * Regression test: a season name with an ampersand is escaped once, not twice.
     */
    public function test_the_page_escapes_a_season_name_once_and_opens_the_documentation_in_a_new_tab(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->make_season('active', 'Spring & <b>Summer</b>');
        $renderer = $PAGE->get_renderer('core');

        $html = $renderer->render_from_template(
            'local_playergames/administration',
            (new administration((int) $GLOBALS['USER']->id))->export_for_template($renderer)
        );

        // The name is plain text: markup is stripped, and the template escapes the ampersand once.
        $this->assertStringContainsString('Active: Spring &amp; Summer', $html);
        $this->assertStringNotContainsString('&amp;amp;', $html);
        $this->assertStringNotContainsString('<b>', $html);
        $this->assertMatchesRegularExpression(
            '/href="https:[^"]*moodle-local_playergames[^"]*" target="_blank" rel="noopener"/',
            $html
        );
    }
}
