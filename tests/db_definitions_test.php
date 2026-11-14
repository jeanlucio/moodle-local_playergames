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
 * Tests for the definition files under db/.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames;

/**
 * Checks that every class and method named in the definition files exists.
 *
 * Moodle reads tasks.php, hooks.php, events.php, services.php and caches.php by name and only fails when
 * the thing they name is needed: a task on the night cron runs, a hook on a page load. A class that was
 * renamed or moved without updating them stays green everywhere else, so the names are checked here.
 *
 * @package    local_playergames
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers ::xmldb_local_playergames_install
 */
final class db_definitions_test extends \advanced_testcase {
    /**
     * Loads a definition file and returns the variable it fills.
     *
     * @param string $file File name under db/.
     * @param string $variable Name of the variable the file defines.
     * @return array
     */
    private function load(string $file, string $variable): array {
        global $CFG;

        $$variable = [];
        require($CFG->dirroot . '/local/playergames/db/' . $file);
        return $$variable;
    }

    /**
     * Every scheduled task is a scheduled task with a name, and none is defined twice.
     */
    public function test_every_scheduled_task_exists(): void {
        $this->resetAfterTest();

        $classnames = [];
        foreach ($this->load('tasks.php', 'tasks') as $task) {
            $class = ltrim($task['classname'], '\\');
            $this->assertTrue(class_exists($class), "$class does not exist.");
            $this->assertInstanceOf(\core\task\scheduled_task::class, new $class());
            $this->assertNotSame('', (new $class())->get_name());
            $classnames[] = $class;
        }

        $this->assertNotEmpty($classnames);
        $this->assertSame($classnames, array_unique($classnames));
    }

    /**
     * Every class under classes/task is scheduled, so a new task is not forgotten there.
     */
    public function test_every_task_class_is_scheduled(): void {
        global $CFG;
        $this->resetAfterTest();

        $scheduled = array_map(
            fn(array $task): string => ltrim($task['classname'], '\\'),
            $this->load('tasks.php', 'tasks')
        );
        foreach (glob($CFG->dirroot . '/local/playergames/classes/task/*.php') as $path) {
            $this->assertContains('local_playergames\\task\\' . basename($path, '.php'), $scheduled);
        }
    }

    /**
     * Every hook callback points to a hook class and a method that can be called.
     */
    public function test_every_hook_callback_can_be_called(): void {
        $this->resetAfterTest();

        $callbacks = $this->load('hooks.php', 'callbacks');
        $this->assertNotEmpty($callbacks);
        foreach ($callbacks as $callback) {
            $this->assertTrue(class_exists($callback['hook']), $callback['hook'] . ' does not exist.');
            $this->assertTrue(is_callable($callback['callback']), $callback['callback'] . ' cannot be called.');
        }
    }

    /**
     * Every event observer points to a method that can be called.
     */
    public function test_every_event_observer_can_be_called(): void {
        $this->resetAfterTest();

        $observers = $this->load('events.php', 'observers');
        $this->assertNotEmpty($observers);
        foreach ($observers as $observer) {
            $this->assertTrue(is_callable($observer['callback']), $observer['callback'] . ' cannot be called.');
        }
    }

    /**
     * Every web service function runs a class whose method exists and whose capability is defined.
     */
    public function test_every_web_service_function_can_be_called(): void {
        $this->resetAfterTest();

        $functions = $this->load('services.php', 'functions');
        $this->assertNotEmpty($functions);
        foreach ($functions as $name => $function) {
            $this->assertTrue(
                is_callable([$function['classname'], $function['methodname']]),
                "$name points to a method that does not exist."
            );
            $this->assertNotNull(get_capability_info($function['capabilities']), "$name asks for an unknown capability.");
        }
    }

    /**
     * Every cache definition has a name string for the cache administration page.
     */
    public function test_every_cache_definition_has_a_name_string(): void {
        $this->resetAfterTest();

        $definitions = $this->load('caches.php', 'definitions');
        $this->assertNotEmpty($definitions);
        foreach (array_keys($definitions) as $name) {
            $this->assertTrue(
                get_string_manager()->string_exists('cachedef_' . $name, 'local_playergames'),
                "cachedef_$name is missing."
            );
        }
    }

    /**
     * The post-install hook runs and leaves the season setup to the administrator.
     */
    public function test_the_install_hook_creates_nothing(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/local/playergames/db/install.php');

        $this->assertTrue(xmldb_local_playergames_install());
        $this->assertSame(0, $DB->count_records('local_playergames_seasons'));
    }
}
