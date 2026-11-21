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
 * Headless tests for the clock and the verdict marks of PlayerQuiz, run via `node --test`.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

'use strict';

const {test} = require('node:test');
const assert = require('node:assert/strict');
const Rules = require('../../../amd/src/quiz/rules.js');

test('the clock shows minutes and two-digit seconds', () => {
    assert.equal(Rules.clock(125), '2:05');
    assert.equal(Rules.clock(60), '1:00');
    assert.equal(Rules.clock(9), '0:09');
    assert.equal(Rules.clock(0), '0:00');
});

test('the clock never shows a negative or fractional time', () => {
    assert.equal(Rules.clock(-3), '0:00');
    assert.equal(Rules.clock(59.9), '0:59');
});

test('the seconds left are rounded and stop at zero once the deadline has passed', () => {
    assert.equal(Rules.secondsLeft(10000, 0), 10);
    assert.equal(Rules.secondsLeft(10000, 9400), 1);
    assert.equal(Rules.secondsLeft(10000, 10000), 0);
    assert.equal(Rules.secondsLeft(10000, 15000), 0);
});

test('the first question is the one the page names, kept inside the questions there are', () => {
    assert.equal(Rules.startIndex('2', 5), 2);
    assert.equal(Rules.startIndex('9', 5), 4);
    assert.equal(Rules.startIndex('', 5), 0);
    assert.equal(Rules.startIndex('-1', 5), 0);
});

test('a right answer marks only the right option', () => {
    assert.deepEqual(Rules.marks(2, 2), {right: 2, wrong: null});
});

test('a wrong answer marks the right option and the one picked', () => {
    assert.deepEqual(Rules.marks(2, 0), {right: 2, wrong: 0});
});

test('a question whose time ran out marks only the right option', () => {
    assert.deepEqual(Rules.marks(2, -1), {right: 2, wrong: null});
});
