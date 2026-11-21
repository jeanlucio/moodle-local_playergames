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
 * Headless tests for the key filter of the PlayerGuess board, run via `node --test`.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

'use strict';

const {test} = require('node:test');
const assert = require('node:assert/strict');
const Keys = require('../../../amd/src/guess/keys.js');

/**
 * Builds a stand-in for the element a key press comes from.
 *
 * @param {Array<string>} matches The selectors the element or one of its ancestors matches.
 * @param {boolean} editable Whether the element is content-editable.
 * @return {object} An object with closest() and isContentEditable.
 */
const element = (matches = [], editable = false) => ({
    isContentEditable: editable,
    closest: selector => (matches.some(match => selector.split(', ').includes(match)) ? {} : null),
});

/**
 * Builds a key press.
 *
 * @param {string} key The key.
 * @param {object} target The element it comes from.
 * @param {object} extra Modifier flags.
 * @return {object} A KeyboardEvent stand-in.
 */
const press = (key, target = element(), extra = {}) => ({key, target, ...extra});

test('letters, Enter and Backspace on the page reach the board', () => {
    assert.equal(Keys.boardKey(press('a')), 'A');
    assert.equal(Keys.boardKey(press('Z')), 'Z');
    assert.equal(Keys.boardKey(press('Enter')), 'ENTER');
    assert.equal(Keys.boardKey(press('Backspace')), 'BACKSPACE');
    assert.equal(Keys.boardKey(press('1')), null);
    assert.equal(Keys.boardKey(press('ArrowLeft')), null);
});

test('shortcuts with a modifier are left to the browser', () => {
    assert.equal(Keys.boardKey(press('c', element(), {ctrlKey: true})), null);
    assert.equal(Keys.boardKey(press('a', element(), {metaKey: true})), null);
    assert.equal(Keys.boardKey(press('a', element(), {altKey: true})), null);
    assert.equal(Keys.boardKey(press('a', element(), {defaultPrevented: true})), null);
});

// Regression test: what was typed in the messages drawer or the search of the header went into the guess,
// and the Enter that sent the message sent the guess too, spending one of the day's attempts.
test('typing in a field of the page never reaches the board', () => {
    for (const field of ['input', 'textarea', 'select', '[contenteditable="true"]']) {
        assert.equal(Keys.boardKey(press('o', element([field]))), null, field);
        assert.equal(Keys.boardKey(press('Enter', element([field]))), null, field);
        assert.equal(Keys.boardKey(press('Backspace', element([field]))), null, field);
    }
    assert.equal(Keys.boardKey(press('o', element([], true))), null);
});

test('a dialog open over the board keeps its keys', () => {
    assert.equal(Keys.boardKey(press('a', element(['[role="dialog"]']))), null);
    assert.equal(Keys.boardKey(press('Enter', element(['.modal']))), null);
});

test('Enter on a button clicks the button instead of sending the guess', () => {
    assert.equal(Keys.boardKey(press('Enter', element(['button']))), null);
    assert.equal(Keys.boardKey(press('Enter', element(['a[href]']))), null);
    // A letter typed while a key of the on-screen keyboard has the focus still plays.
    assert.equal(Keys.boardKey(press('b', element(['button']))), 'B');
});
