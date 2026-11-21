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
 * Headless tests for the row and keyboard rules of PlayerGuess, run via `node --test`.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

'use strict';

const {test} = require('node:test');
const assert = require('node:assert/strict');
const Board = require('../../../amd/src/guess/board.js');

test('letters fill the row up to the length of the word and no further', () => {
    let row = [];
    for (const key of ['G', 'R', 'A', 'P', 'E', 'S']) {
        row = Board.type(row, key, 5).guess;
    }
    assert.deepEqual(row, ['G', 'R', 'A', 'P', 'E']);
});

test('Backspace takes the last letter back, and does nothing on an empty row', () => {
    assert.deepEqual(Board.type(['G', 'R'], 'BACKSPACE', 5), {guess: ['G'], action: 'none'});
    assert.deepEqual(Board.type([], 'BACKSPACE', 5), {guess: [], action: 'none'});
});

test('Enter sends only a full row, and says so when the row is short', () => {
    assert.equal(Board.type(['G', 'R', 'A', 'P', 'E'], 'ENTER', 5).action, 'submit');
    assert.equal(Board.type(['G', 'R'], 'ENTER', 5).action, 'invalid');
    assert.equal(Board.type([], 'ENTER', 5).action, 'invalid');
});

test('anything but an upper-case letter or a board key leaves the row as it is', () => {
    const row = ['G'];
    for (const key of ['g', '1', 'Ç', 'SHIFT', '', 'AB']) {
        const result = Board.type(row, key, 5);
        assert.equal(result.guess, row, key);
        assert.equal(result.action, 'none', key);
    }
});

test('typing never changes the row it was given', () => {
    const row = ['G', 'R'];
    Board.type(row, 'A', 5);
    Board.type(row, 'BACKSPACE', 5);
    assert.deepEqual(row, ['G', 'R']);
});

test('a key takes the verdict of the row the first time it is played', () => {
    assert.deepEqual(
        Board.upgradeKeys({}, ['G', 'R', 'A'], ['correct', 'present', 'absent']),
        {G: 'correct', R: 'present', A: 'absent'}
    );
});

test('a key never falls back to a weaker verdict', () => {
    const status = {G: 'correct', R: 'present'};
    assert.deepEqual(Board.upgradeKeys(status, ['G', 'R'], ['absent', 'absent']), {});
    assert.deepEqual(Board.upgradeKeys(status, ['G', 'R'], ['present', 'correct']), {R: 'correct'});
});

// A letter typed twice can be right in one place and absent in the other: the key shows the better verdict,
// whatever the order of the two in the row.
test('a letter that appears twice in a row takes the better of its verdicts', () => {
    assert.deepEqual(Board.upgradeKeys({}, ['E', 'E'], ['absent', 'correct']), {E: 'correct'});
    assert.deepEqual(Board.upgradeKeys({}, ['E', 'E'], ['correct', 'absent']), {E: 'correct'});
    assert.deepEqual(Board.upgradeKeys({E: 'absent'}, ['E', 'E'], ['present', 'absent']), {E: 'present'});
});

test('the board draws the attempts the page announces, up to its ceiling', () => {
    assert.equal(Board.rowCount('6', 10), 6);
    assert.equal(Board.rowCount('25', 10), 10);
    assert.equal(Board.rowCount('', 10), 0);
    assert.equal(Board.rowCount('-3', 10), 0);
    assert.equal(Board.rowCount('abc', 10), 0);
});

test('a scored row is read out letter by letter with its verdict', () => {
    const words = {correct: 'correct', present: 'in the word, wrong place', absent: 'not in the word'};
    assert.equal(
        Board.rowSummary(['G', 'R'], ['correct', 'absent'], words),
        'G correct; R not in the word'
    );
});
