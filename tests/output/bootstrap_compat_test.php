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
 * Bootstrap 5 utilities the markup uses that Moodle 4.5, on Bootstrap 4, does not have.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\output;

/**
 * Every Bootstrap 5 only class the plugin puts in its markup has a rule of its own in styles.css.
 *
 * The plugin supports Moodle 4.5, whose Boost theme is built on Bootstrap 4 and only bridges a few Bootstrap 5
 * names (the start/end spacing, text and float classes, g-0, btn-close). Any other Bootstrap 5 utility does
 * nothing there, which no test on Moodle 5.x and no linter notices: the page only looks different.
 *
 * @package    local_playergames
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class bootstrap_compat_test extends \basic_testcase {
    /** @var string[] Bootstrap 5 utilities Bootstrap 4 does not define and the Moodle 4.5 bridge does not add. */
    private const BS5_ONLY = [
        'd(-(sm|md|lg|xl|xxl))?-grid',
        '(row-|column-)?gap-[0-5]',
        'fw-(bolder|bold|semibold|medium|normal|lighter|light)',
        'fs-[1-6]',
        'fst-(italic|normal)',
        'lh-(1|sm|base|lg)',
        'form-select(-sm|-lg)?',
        'form-range',
        'form-floating',
        'g-[1-5]',
        'g[xy]-[0-5]',
        'text-bg-[a-z]+',
        '(bg|border)-[a-z]+-subtle',
        'text-[a-z]+-emphasis',
        'link-(underline|offset|opacity)[a-z0-9-]*',
        'rounded-[1-5]',
        'opacity-(0|25|50|75|100)',
        'z-(0|1|2|3|n1)',
        'object-fit-[a-z]+',
        '[vh]stack',
        'ratio-(1x1|4x3|16x9|21x9)',
        'translate-middle(-[xy])?',
        '(top|bottom|start|end)-(0|50|100)',
        'visually-hidden(-focusable)?',
    ];

    /**
     * Lists the Bootstrap 5 only classes each source file of the plugin uses.
     *
     * @return array Files keyed by class name.
     */
    private function bs5_classes_in_use(): array {
        global $CFG;
        $root = $CFG->dirroot . '/local/playergames';
        $files = array_merge(
            glob($root . '/templates/*.mustache'),
            glob($root . '/amd/src/*.js'),
            glob($root . '/amd/src/*/*.js'),
            glob($root . '/*.php'),
            glob($root . '/classes/*.php'),
            glob($root . '/classes/*/*.php')
        );
        // A plugin prefix (pg-gap-2) or a longer name (align-items-start-0) is not the Bootstrap class.
        $pattern = '/(?<![\w-])(' . implode('|', self::BS5_ONLY) . ')(?![\w-])/';

        $found = [];
        foreach ($files as $file) {
            // Only what can become a class is read: the class attributes of a template, the string literals of
            // a script or a PHP file. A comment speaking of a "top-50" ranking is not markup.
            preg_match_all($pattern, $this->markup_of($file), $matches);
            foreach (array_unique($matches[1]) as $class) {
                $found[$class][] = substr($file, strlen($root) + 1);
            }
        }
        ksort($found);
        return $found;
    }

    /**
     * Reads the part of a source file that can become a class name.
     *
     * The class attributes of a template, the string literals of a PHP file or of a script: a comment that
     * speaks of a "top-50" ranking is not markup.
     *
     * @param string $file Path of the file.
     * @return string Those parts, separated by spaces.
     */
    private function markup_of(string $file): string {
        $source = file_get_contents($file);
        if (str_ends_with($file, '.mustache')) {
            preg_match_all('/class="([^"]*)"/', $source, $parts);
            return implode(' ', $parts[1]);
        }
        if (str_ends_with($file, '.php')) {
            $strings = [];
            foreach (token_get_all($source) as $token) {
                if (is_array($token) && in_array($token[0], [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE], true)) {
                    $strings[] = $token[1];
                }
            }
            return implode(' ', $strings);
        }
        // A script: comments out first, since an apostrophe in one would open a string that is not there.
        $source = preg_replace('~/\*.*?\*/|(?<![:\'"])//[^\n]*~s', ' ', $source);
        preg_match_all('/\'((?:[^\'\\\\]|\\\\.)*)\'|"((?:[^"\\\\]|\\\\.)*)"|\x60((?:[^\x60\\\\]|\\\\.)*)\x60/', $source, $parts);
        return implode(' ', array_merge($parts[1], $parts[2], $parts[3]));
    }

    /**
     * Each of those classes has a rule in styles.css, so Moodle 4.5 shows what Moodle 5.x shows.
     */
    public function test_every_bootstrap5_only_class_in_use_has_a_rule_of_its_own(): void {
        global $CFG;
        $css = file_get_contents($CFG->dirroot . '/local/playergames/styles.css');
        $inuse = $this->bs5_classes_in_use();
        // The scan must keep finding the classes it was written for, or a broken pattern passes silently.
        $this->assertArrayHasKey('d-grid', $inuse);
        $this->assertArrayHasKey('form-select', $inuse);

        $missing = [];
        foreach ($inuse as $class => $files) {
            if (!preg_match('/\.' . preg_quote($class, '/') . '(?![\w-])/', $css)) {
                $missing[] = $class . ' (' . implode(', ', $files) . ')';
            }
        }

        $this->assertSame([], $missing, 'Bootstrap 5 only classes with no rule in styles.css for Moodle 4.5.');
    }
}
