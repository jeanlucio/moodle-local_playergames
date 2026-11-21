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
 * What the keys and the verdicts of PlayerGuess do to the row being typed and to the on-screen keyboard.
 *
 * UMD-wrapped like the key filter, so the same file loads as an AMD module in the browser and as a plain
 * CommonJS module in Node, where it is tested without a browser.
 *
 * @module     local_playergames/guess/board
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/* global module */
(function(define) {
    define([], () => {
        'use strict';

        /** How far each verdict goes: a key never falls back to a weaker one. */
        const RANK = {absent: 0, present: 1, correct: 2};

        /**
         * Applies a board key to the row being typed.
         *
         * @param {string[]} guess The letters typed so far.
         * @param {string} key An upper-case letter, 'ENTER' or 'BACKSPACE'.
         * @param {number} length The length of the word.
         * @return {{guess: string[], action: string}} The row after the key, and 'submit' when the full row is
         *     sent, 'invalid' when Enter comes before the row is full, 'none' otherwise.
         */
        const type = (guess, key, length) => {
            if (key === 'ENTER') {
                return {guess, action: guess.length === length ? 'submit' : 'invalid'};
            }
            if (key === 'BACKSPACE') {
                return {guess: guess.slice(0, -1), action: 'none'};
            }
            if (/^[A-Z]$/.test(key) && guess.length < length) {
                return {guess: [...guess, key], action: 'none'};
            }
            return {guess, action: 'none'};
        };

        /**
         * Works out which keys of the on-screen keyboard change colour after a scored row.
         *
         * A letter that appears twice in the row takes the better of its two verdicts, and a key already
         * marked keeps its colour unless the row gives it a better one.
         *
         * @param {Object} status The verdict each key shows now, by letter.
         * @param {string[]} guess The letters of the row.
         * @param {string[]} feedback One of 'correct', 'present' or 'absent' per letter.
         * @return {Object} The new verdict of each key that changes, by letter.
         */
        const upgradeKeys = (status, guess, feedback) => {
            const changes = {};
            guess.forEach((letter, index) => {
                const shown = changes[letter] || status[letter];
                if (!shown || RANK[feedback[index]] > RANK[shown]) {
                    changes[letter] = feedback[index];
                }
            });
            return changes;
        };

        /**
         * Returns how many rows the board has.
         *
         * @param {string|number} configured The attempts the page announces.
         * @param {number} ceiling The most rows the board ever draws.
         * @return {number} The rows, 0 when the page announces none.
         */
        const rowCount = (configured, ceiling) => Math.max(0, Math.min(ceiling, parseInt(configured, 10) || 0));

        /**
         * Reads a scored row out for a screen reader.
         *
         * @param {string[]} guess The letters of the row.
         * @param {string[]} feedback One verdict per letter.
         * @param {Object} words The words for each verdict, by verdict.
         * @return {string} Each letter with its verdict, separated by semicolons.
         */
        const rowSummary = (guess, feedback, words) =>
            guess.map((letter, index) => `${letter} ${words[feedback[index]]}`).join('; ');

        return {type, upgradeKeys, rowCount, rowSummary};
    });
}(typeof define === 'function' && define.amd ? define : (deps, factory) => {
    module.exports = factory();
}));
