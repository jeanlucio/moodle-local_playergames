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
 * The clock and the verdict marks of PlayerQuiz.
 *
 * UMD-wrapped like the PlayerGuess key filter, so it is tested in Node without a browser.
 *
 * @module     local_playergames/quiz/rules
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/* global module */
(function(define) {
    define([], () => {
        'use strict';

        /**
         * Writes a number of seconds as the timer shows it.
         *
         * @param {number} seconds The seconds left.
         * @return {string} Minutes and two-digit seconds, such as 1:05; never negative.
         */
        const clock = seconds => {
            const whole = Math.max(0, Math.floor(seconds));
            return `${Math.floor(whole / 60)}:${String(whole % 60).padStart(2, '0')}`;
        };

        /**
         * Returns the whole seconds left before a deadline.
         *
         * @param {number} deadline The deadline, in milliseconds.
         * @param {number} now The current time, in milliseconds.
         * @return {number} The seconds left, 0 once the deadline has passed.
         */
        const secondsLeft = (deadline, now) => Math.max(0, Math.round((deadline - now) / 1000));

        /**
         * Returns the question to show first, from the one the page names.
         *
         * @param {string|number} current The index the page names.
         * @param {number} count How many questions the page has.
         * @return {number} An index of an existing question.
         */
        const startIndex = (current, count) => Math.max(0, Math.min(parseInt(current, 10) || 0, count - 1));

        /**
         * Returns which options get a verdict mark after an answer.
         *
         * @param {number} correctindex The option the server says is right.
         * @param {number} choice The option picked, or -1 when the time ran out.
         * @return {{right: number, wrong: number|null}} The right option, and the picked one when it is wrong.
         */
        const marks = (correctindex, choice) => ({
            right: correctindex,
            wrong: choice >= 0 && choice !== correctindex ? choice : null,
        });

        return {clock, secondsLeft, startIndex, marks};
    });
}(typeof define === 'function' && define.amd ? define : (deps, factory) => {
    module.exports = factory();
}));
