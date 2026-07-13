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
 * PlayerGuess game interaction module.
 *
 * A Wordle-style board: the player types one full-length guess per row using
 * the physical or the on-screen keyboard. Every submitted row is validated
 * server-side (the target term is never sent to the client) and coloured
 * with the returned per-letter feedback. The play ends on a correct guess or
 * once the configured number of rows is used.
 *
 * @module     local_playergames/play_guess
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Modal from 'core/modal';
import {getString} from 'core/str';

const LETTER_KEYS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split('');
const FEEDBACK_RANK = {absent: 0, present: 1, correct: 2};

let container = null;
let board = null;
let messageEl = null;
let targetLength = 0;
let maxAttempts = 0;
let currentRowIndex = 0;
let currentGuess = [];
let finished = false;
let submitting = false;
let endKind = '';
const keyStatus = {};

/**
 * Builds the empty board: maxAttempts rows of targetLength tiles each.
 */
const buildBoard = () => {
    for (let r = 0; r < maxAttempts; r++) {
        const row = document.createElement('div');
        row.className = 'pg-guess-row';
        row.dataset.row = String(r);
        for (let c = 0; c < targetLength; c++) {
            const tile = document.createElement('div');
            tile.className = 'pg-guess-tile';
            row.appendChild(tile);
        }
        board.appendChild(row);
    }
};

/**
 * Returns the tile elements for a given row index.
 *
 * @param {number} rowIndex Zero-based row index.
 * @return {HTMLElement[]}
 */
const tilesForRow = (rowIndex) => Array.from(board.children[rowIndex].children);

/**
 * Renders the letters currently being typed into the active row.
 */
const renderCurrentGuess = () => {
    const tiles = tilesForRow(currentRowIndex);
    tiles.forEach((tile, index) => {
        tile.textContent = currentGuess[index] || '';
        tile.classList.toggle('pg-guess-tile-filled', Boolean(currentGuess[index]));
    });
};

/**
 * Applies feedback colours to a finished row and upgrades the on-screen
 * keyboard's per-letter status without ever downgrading it.
 *
 * @param {number} rowIndex Row that was just scored.
 * @param {string[]} guess Letters submitted for this row.
 * @param {string[]} feedback One of 'correct'|'present'|'absent' per letter.
 */
const applyFeedback = (rowIndex, guess, feedback) => {
    const tiles = tilesForRow(rowIndex);
    tiles.forEach((tile, index) => {
        tile.textContent = guess[index];
        tile.classList.remove('pg-guess-tile-filled');
        tile.classList.add(`pg-guess-tile-${feedback[index]}`);

        const letter = guess[index];
        const current = keyStatus[letter];
        if (!current || FEEDBACK_RANK[feedback[index]] > FEEDBACK_RANK[current]) {
            keyStatus[letter] = feedback[index];
            const key = container.querySelector(`.pg-guess-key[data-key="${letter}"]`);
            if (key) {
                key.classList.remove('pg-guess-key-absent', 'pg-guess-key-present', 'pg-guess-key-correct');
                key.classList.add(`pg-guess-key-${feedback[index]}`);
            }
        }
    });
};

/**
 * Replays rows already guessed earlier today (e.g. after a page reload
 * mid-round), rebuilding the board and keyboard state without contacting
 * the server again.
 *
 * @param {Array} savedRows Rows persisted server-side: [{guess, feedback}].
 */
const replayRows = (savedRows) => {
    savedRows.forEach((row) => {
        const guess = row.guess.toUpperCase().split('');
        applyFeedback(currentRowIndex, guess, row.feedback);
        currentRowIndex++;
    });
};

/**
 * Shows a short inline validation message.
 *
 * @param {string} text Message to display.
 */
const showMessage = (text) => {
    messageEl.textContent = text;
};

/**
 * Reveals the persistent end-of-play actions (reopen result, back to hub).
 */
const showEndActions = () => {
    const actions = document.getElementById('pg-guess-endactions');
    if (actions) {
        actions.classList.remove('d-none');
    }
};

/**
 * Opens the end-of-play modal (result or failure) built from its source block.
 *
 * @param {string} sourceId Element id holding the modal body and title.
 */
const openEndModal = async(sourceId) => {
    const el = document.getElementById(sourceId);
    if (!el) {
        return;
    }
    await Modal.create({
        title: el.dataset.title || '',
        body: el.innerHTML,
        show: true,
        removeOnClose: true,
    });
};

/**
 * Updates the attempt-progress message shown above the board.
 */
const updateProgress = async() => {
    const text = await getString('guess_attempt_progress', 'local_playergames', {
        used: currentRowIndex + 1,
        max: maxAttempts,
    });
    showMessage(text);
};

/**
 * Sends the current row to the server for validation and scoring.
 */
const submitGuess = async() => {
    if (submitting || finished || currentGuess.length !== targetLength) {
        return;
    }
    submitting = true;

    let data;
    try {
        const response = await fetch(container.dataset.action, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({
                sesskey: container.dataset.sesskey,
                action: 'submit_guess',
                guess: currentGuess.join(''),
            }),
        });
        data = await response.json();
    } catch (e) {
        submitting = false;
        return;
    }

    if (!data.success) {
        submitting = false;
        if (data.reason === 'invalid') {
            showMessage(container.dataset.strInvalid);
            return;
        }
        window.location.reload();
        return;
    }

    applyFeedback(currentRowIndex, currentGuess, data.feedback);
    currentRowIndex++;
    currentGuess = [];
    submitting = false;

    if (data.finished) {
        finished = true;
        showEndActions();
        if (data.won) {
            endKind = 'pg-guess-result';
            const xpEl = document.getElementById('pg-guess-xp-count');
            if (xpEl) {
                xpEl.textContent = String(data.xpawarded);
            }
        } else {
            endKind = 'pg-guess-failed';
            const msgEl = document.getElementById('pg-guess-failed-msg');
            if (msgEl) {
                msgEl.textContent = await getString('guess_failed_msg', 'local_playergames', data.target);
            }
        }
        openEndModal(endKind);
        return;
    }

    updateProgress();
};

/**
 * Handles one logical key press, from either the physical or the on-screen
 * keyboard.
 *
 * @param {string} key Upper-case letter, 'ENTER' or 'BACKSPACE'.
 */
const handleKey = (key) => {
    if (finished || submitting) {
        return;
    }
    if (key === 'ENTER') {
        if (currentGuess.length !== targetLength) {
            showMessage(container.dataset.strInvalid);
            return;
        }
        showMessage('');
        submitGuess();
        return;
    }
    if (key === 'BACKSPACE') {
        currentGuess.pop();
        renderCurrentGuess();
        return;
    }
    if (LETTER_KEYS.includes(key) && currentGuess.length < targetLength) {
        currentGuess.push(key);
        renderCurrentGuess();
    }
};

export default {
    /**
     * Initialises the board, replays any rows already played today and wires
     * the physical and on-screen keyboard interactions.
     */
    init: () => {
        container = document.getElementById('pg-guess-container');
        if (!container) {
            return;
        }
        board = document.getElementById('pg-guess-board');
        messageEl = document.getElementById('pg-guess-message');
        targetLength = parseInt(container.dataset.targetLength, 10) || 0;
        maxAttempts = parseInt(container.dataset.maxAttempts, 10) || 0;
        if (targetLength <= 0 || maxAttempts <= 0) {
            return;
        }

        buildBoard();

        let savedRows = [];
        try {
            savedRows = JSON.parse(container.dataset.rows || '[]');
        } catch (e) {
            savedRows = [];
        }
        replayRows(savedRows);
        updateProgress();

        document.addEventListener('keydown', (e) => {
            if (e.ctrlKey || e.altKey || e.metaKey) {
                return;
            }
            if (e.key === 'Enter') {
                handleKey('ENTER');
            } else if (e.key === 'Backspace') {
                handleKey('BACKSPACE');
            } else if (/^[a-zA-Z]$/.test(e.key)) {
                handleKey(e.key.toUpperCase());
            }
        });

        const keyboard = document.getElementById('pg-guess-keyboard');
        if (keyboard) {
            keyboard.addEventListener('click', (e) => {
                const btn = e.target.closest('.pg-guess-key');
                if (btn) {
                    handleKey(btn.dataset.key);
                }
            });
        }

        const reopenBtn = document.getElementById('pg-guess-view-result');
        if (reopenBtn) {
            reopenBtn.addEventListener('click', () => openEndModal(endKind));
        }
    },
};
