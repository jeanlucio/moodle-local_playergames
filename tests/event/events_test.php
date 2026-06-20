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
 * Tests for the plugin events.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\event;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Smoke tests asserting each event triggers and renders a description.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(achievement_earned::class)]
#[CoversClass(cartridge_deleted::class)]
#[CoversClass(cartridge_imported::class)]
#[CoversClass(game_completed::class)]
#[CoversClass(level_reached::class)]
#[CoversClass(season_closed::class)]
#[CoversClass(season_created::class)]
#[CoversClass(streak_broken::class)]
#[CoversClass(streak_updated::class)]
final class events_test extends \advanced_testcase {
    /**
     * Triggers an event and asserts it is captured and renders cleanly.
     *
     * @param \core\event\base $event The event instance to trigger.
     * @param string $class Expected event class name.
     * @return void
     */
    private function assert_well_formed(\core\event\base $event, string $class): void {
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $sink->close();

        $this->assertCount(1, $events);
        $this->assertInstanceOf($class, $events[0]);
        $this->assertIsString($class::get_name());
        $this->assertNotSame('', $events[0]->get_description());
    }

    public function test_events_trigger_and_describe(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $uid = (int) $user->id;
        $context = \context_system::instance();

        $this->assert_well_formed(achievement_earned::create([
            'objectid' => 1, 'context' => $context, 'userid' => $uid,
            'other' => ['achievementid' => 1],
        ]), achievement_earned::class);

        $this->assert_well_formed(cartridge_deleted::create([
            'objectid' => 1, 'context' => $context,
        ]), cartridge_deleted::class);

        $this->assert_well_formed(cartridge_imported::create([
            'objectid' => 1, 'context' => $context,
            'other' => ['conceptcount' => 10],
        ]), cartridge_imported::class);

        $this->assert_well_formed(game_completed::create([
            'objectid' => 1, 'context' => $context, 'userid' => $uid,
            'other' => ['gametype' => 'quiz', 'xpawarded' => 10],
        ]), game_completed::class);

        $this->assert_well_formed(level_reached::create([
            'objectid' => 1, 'context' => $context, 'userid' => $uid,
            'other' => ['seasonid' => 1, 'level' => 2],
        ]), level_reached::class);

        $this->assert_well_formed(season_closed::create([
            'objectid' => 1, 'context' => $context, 'userid' => $uid,
        ]), season_closed::class);

        $this->assert_well_formed(season_created::create([
            'objectid' => 1, 'context' => $context, 'userid' => $uid,
        ]), season_created::class);

        $this->assert_well_formed(streak_broken::create([
            'objectid' => 1, 'context' => $context, 'userid' => $uid,
            'other' => ['previousstreak' => 5],
        ]), streak_broken::class);

        $this->assert_well_formed(streak_updated::create([
            'objectid' => 1, 'context' => $context, 'userid' => $uid,
            'other' => ['currentstreak' => 3],
        ]), streak_updated::class);
    }
}
