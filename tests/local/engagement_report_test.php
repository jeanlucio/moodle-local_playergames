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
 * Tests for the engagement comparison report.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\local;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Unit tests for {@see engagement_report}.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(engagement_report::class)]
final class engagement_report_test extends \advanced_testcase {
    public function test_get_metrics_empty_returns_zeros(): void {
        $this->resetAfterTest();
        $metrics = (new engagement_report())->get_metrics([], 30);

        $this->assertSame(0, $metrics['course_count']);
        $this->assertSame(0.0, $metrics['events_per_user']);
        $this->assertSame(0.0, $metrics['completion_rate']);
        $this->assertSame(0.0, $metrics['grade_avg']);
    }

    public function test_get_metrics_counts_courses(): void {
        $this->resetAfterTest();
        $c1 = $this->getDataGenerator()->create_course();
        $c2 = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $c1->id);

        $metrics = (new engagement_report())->get_metrics([(int) $c1->id, (int) $c2->id], 30);

        $this->assertSame(2, $metrics['course_count']);
        $this->assertIsFloat($metrics['events_per_user']);
        $this->assertIsFloat($metrics['completion_rate']);
    }

    public function test_get_player_courseids_excludes_plain_course(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();

        $ids = (new engagement_report())->get_player_courseids();

        $this->assertIsArray($ids);
        // A course with no Player block or activity is not a "Player course".
        $this->assertNotContains((int) $course->id, $ids);
    }

    public function test_compare_splits_scope_into_with_and_without(): void {
        $this->resetAfterTest();
        $c1 = $this->getDataGenerator()->create_course();
        $c2 = $this->getDataGenerator()->create_course();

        $result = (new engagement_report())->compare(30, [(int) $c1->id, (int) $c2->id]);

        // Neither course has Player plugins, so both fall in the "without" group.
        $this->assertSame(0, $result['with']['course_count']);
        $this->assertSame(2, $result['without']['course_count']);
    }
}
