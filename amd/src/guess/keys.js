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
 * Decides which key presses of the page belong to the PlayerGuess board.
 *
 * The board listens on the whole document, so a physical keyboard plays without focusing anything first.
 * The page also has fields of its own (the search and the messages of the header, a dialog): what is typed
 * there is not a guess, and the Enter that sends a message must not send the guess too.
 *
 * UMD-wrapped like the PlayerBattle rules, so the same file loads as an AMD module in the browser and as a
 * plain CommonJS module in Node, where it is tested without a browser.
 *
 * @module     local_playergames/guess/keys
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/* global module */
(function(define) {
    define([], () => {
        'use strict';

        /** Elements that take typing of their own. */
        const EDITABLE = 'input, textarea, select, [contenteditable=""], [contenteditable="true"]';

        /** Containers whose keys belong to what they show, not to the board behind them. */
        const DIALOG = '[role="dialog"], dialog, .modal';

        /** Elements that Enter already activates. */
        const ACTIVATABLE = 'button, a[href], summary, [role="button"]';

        /**
         * Returns the board key a key press stands for, or null when it is not the board's.
         *
         * @param {KeyboardEvent} event The key press.
         * @return {string|null} 'ENTER', 'BACKSPACE', an upper-case letter, or null.
         */
        const boardKey = event => {
            if (event.ctrlKey || event.altKey || event.metaKey || event.defaultPrevented) {
                return null;
            }
            const target = event.target;
            const inside = selector => Boolean(target && typeof target.closest === 'function' && target.closest(selector));
            if (inside(EDITABLE) || (target && target.isContentEditable) || inside(DIALOG)) {
                return null;
            }

            if (event.key === 'Enter') {
                // Enter on a button clicks it: on a key of the on-screen keyboard that would type the letter and
                // send the guess at once.
                return inside(ACTIVATABLE) ? null : 'ENTER';
            }
            if (event.key === 'Backspace') {
                return 'BACKSPACE';
            }
            return /^[a-zA-Z]$/.test(event.key) ? event.key.toUpperCase() : null;
        };

        return {boardKey};
    });
}(typeof define === 'function' && define.amd ? define : (deps, factory) => {
    module.exports = factory();
}));
