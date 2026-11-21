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
 * What the server's answer to a PlayerFill guess means for the rows and for the message under the board.
 *
 * UMD-wrapped like the PlayerGuess key filter, so it is tested in Node without a browser.
 *
 * @module     local_playergames/fill/rules
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/* global module */
(function(define) {
    define([], () => {
        'use strict';

        /**
         * Whether a word still takes guesses.
         *
         * @param {Object} word The word as the server sends it: resolved, exhausted.
         * @param {boolean} finished Whether the round is over.
         * @return {boolean} True when its guess field must be hidden.
         */
        const closed = (word, finished) => Boolean(word.resolved || word.exhausted || finished);

        /**
         * Returns the guesses a word has left.
         *
         * @param {string|number} max The attempts each word has, as the page announces them.
         * @param {number} used The attempts the word has used.
         * @return {number} The attempts left, never below zero.
         */
        const attemptsLeft = (max, used) => Math.max(0, (parseInt(max, 10) || 0) - (used || 0));

        /**
         * Tells what the guess just made did to its word, for the message the player and a screen reader get.
         *
         * A guess that missed changes nothing on screen but the tiles of the words others cleared, so it has to
         * be said; a word that ran out of attempts ends the round, which says it already.
         *
         * @param {Object[]} words The words as the server sends them.
         * @param {number} conceptid The word the guess was for.
         * @param {string|number} max The attempts each word has.
         * @return {{kind: string, left: number}} 'solved', 'wrong' (with the attempts left) or 'none'.
         */
        const outcome = (words, conceptid, max) => {
            const word = words.find(candidate => candidate.conceptid === conceptid);
            if (!word) {
                return {kind: 'none', left: 0};
            }
            if (word.resolved) {
                return {kind: 'solved', left: 0};
            }
            if (word.exhausted) {
                return {kind: 'none', left: 0};
            }
            return {kind: 'wrong', left: attemptsLeft(max, word.attemptsused)};
        };

        return {closed, attemptsLeft, outcome};
    });
}(typeof define === 'function' && define.amd ? define : (deps, factory) => {
    module.exports = factory();
}));
