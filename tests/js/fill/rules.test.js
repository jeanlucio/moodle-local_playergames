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
 * Headless tests for the round rules of PlayerFill, run via `node --test`.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

'use strict';

const {test} = require('node:test');
const assert = require('node:assert/strict');
const Rules = require('../../../amd/src/fill/rules.js');

test('a word takes guesses until it is solved, runs out of attempts or the round ends', () => {
    assert.equal(Rules.closed({resolved: false, exhausted: false}, false), false);
    assert.equal(Rules.closed({resolved: true, exhausted: false}, false), true);
    assert.equal(Rules.closed({resolved: false, exhausted: true}, false), true);
    assert.equal(Rules.closed({resolved: false, exhausted: false}, true), true);
});

test('the attempts left never go below zero, nor come from a missing setting', () => {
    assert.equal(Rules.attemptsLeft('4', 1), 3);
    assert.equal(Rules.attemptsLeft(4, 4), 0);
    assert.equal(Rules.attemptsLeft('4', 6), 0);
    assert.equal(Rules.attemptsLeft('', 1), 0);
    assert.equal(Rules.attemptsLeft('4', undefined), 4);
});

test('a guess that solves its word says so', () => {
    const words = [{conceptid: 7, resolved: true, exhausted: false, attemptsused: 1}];
    assert.deepEqual(Rules.outcome(words, 7, '4'), {kind: 'solved', left: 0});
});

test('a guess that misses says how many attempts the word has left', () => {
    const words = [
        {conceptid: 7, resolved: false, exhausted: false, attemptsused: 1},
        {conceptid: 8, resolved: true, exhausted: false, attemptsused: 2},
    ];
    assert.deepEqual(Rules.outcome(words, 7, '4'), {kind: 'wrong', left: 3});
});

// The words the guess did not aim at can change too (their letters are revealed), so the outcome must come
// from the word guessed, not from the first one that changed.
test('the outcome is the one of the word guessed, not of another word that changed', () => {
    const words = [
        {conceptid: 8, resolved: true, exhausted: false, attemptsused: 0},
        {conceptid: 7, resolved: false, exhausted: false, attemptsused: 2},
    ];
    assert.deepEqual(Rules.outcome(words, 7, '4'), {kind: 'wrong', left: 2});
});

test('a word that ran out of attempts, or one the answer does not list, gets no message', () => {
    const words = [{conceptid: 7, resolved: false, exhausted: true, attemptsused: 4}];
    assert.equal(Rules.outcome(words, 7, '4').kind, 'none');
    assert.equal(Rules.outcome(words, 9, '4').kind, 'none');
    assert.equal(Rules.outcome([], 7, '4').kind, 'none');
});
