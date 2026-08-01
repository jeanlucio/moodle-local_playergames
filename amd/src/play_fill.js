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
 * PlayerFill game interaction module.
 *
 * A crossword-style board: one row per word, each with its own guess field. Every
 * submitted guess is validated server-side (the words are never sent to the client)
 * and the server returns the full puzzle panel, since a correct guess can reveal
 * shared letters in other pending rows too. The play ends once every word is solved,
 * or the moment any single word runs out of attempts.
 *
 * @module     local_playergames/play_fill
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Modal from 'core/modal';

let container = null;
let rowsEl = null;
let messageEl = null;
let finished = false;
let won = false;
let submitting = false;
let endKind = '';

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
    const actions = document.getElementById('pg-fill-endactions');
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
 * Rebuilds one row's tile markup from the server's per-position tile state.
 *
 * @param {HTMLElement} row Row element for one word.
 * @param {Array} tiles One {letter, revealed, slotnum} entry per letter position.
 */
const renderTiles = (row, tiles) => {
    const tilesEl = row.querySelector('.pg-fill-tiles');
    tilesEl.innerHTML = '';
    tiles.forEach((tile) => {
        const tileEl = document.createElement('div');
        tileEl.className = 'pg-fill-tile' + (tile.revealed ? ' pg-fill-tile-revealed' : '');
        if (tile.revealed) {
            tileEl.textContent = tile.letter;
        } else {
            const slotEl = document.createElement('span');
            slotEl.className = 'pg-fill-slotnum';
            slotEl.setAttribute('aria-hidden', 'true');
            slotEl.textContent = tile.slotnum;
            tileEl.appendChild(slotEl);
        }
        tilesEl.appendChild(tileEl);
    });
};

/**
 * Applies one word's updated state (tiles, solved badge, guess form, reveal) to its row.
 *
 * @param {Object} word Per-word payload returned by the server.
 */
const applyWordUpdate = (word) => {
    const row = rowsEl.querySelector(`.pg-fill-row[data-conceptid="${word.conceptid}"]`);
    if (!row) {
        return;
    }

    renderTiles(row, word.tiles);

    const badge = row.querySelector('.pg-fill-solved-badge');
    if (badge) {
        badge.classList.toggle('d-none', !word.resolved);
    }

    const guessform = row.querySelector('.pg-fill-guessform');
    if (guessform) {
        guessform.classList.toggle('d-none', word.resolved || word.exhausted || finished);
    }

    let revealEl = row.querySelector('.pg-fill-revealword');
    if (word.revealword) {
        if (!revealEl) {
            revealEl = document.createElement('div');
            revealEl.className = 'pg-fill-revealword text-muted small';
            row.appendChild(revealEl);
        }
        revealEl.textContent = word.revealword;
    } else if (revealEl) {
        revealEl.remove();
    }
};

/**
 * Sends one word's guess to the server for validation and scoring.
 *
 * @param {number} conceptid Concept id identifying the row being guessed.
 * @param {string} guess Raw guess text typed by the player.
 */
const submitGuess = async(conceptid, guess) => {
    if (submitting || finished) {
        return;
    }
    if (!guess) {
        showMessage(container.dataset.strInvalid);
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
                action: 'submit_word_guess',
                conceptid: String(conceptid),
                guess,
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

    showMessage('');
    finished = data.finished;
    won = data.won;
    data.words.forEach(applyWordUpdate);
    submitting = false;

    if (finished) {
        showEndActions();
        endKind = won ? 'pg-fill-result' : 'pg-fill-failed';
        if (won) {
            const xpEl = document.getElementById('pg-fill-xp-count');
            if (xpEl) {
                xpEl.textContent = String(data.xpawarded);
            }
        }
        openEndModal(endKind);
    }
};

export default {
    /**
     * Wires each row's guess field and submit button.
     */
    init: () => {
        container = document.getElementById('pg-fill-container');
        if (!container) {
            return;
        }
        rowsEl = document.getElementById('pg-fill-rows');
        messageEl = document.getElementById('pg-fill-message');

        rowsEl.addEventListener('click', (e) => {
            const btn = e.target.closest('.pg-fill-submit');
            if (!btn) {
                return;
            }
            const row = btn.closest('.pg-fill-row');
            const input = row.querySelector('.pg-fill-input');
            submitGuess(parseInt(row.dataset.conceptid, 10), input.value.trim());
        });

        rowsEl.addEventListener('keydown', (e) => {
            if (e.key !== 'Enter') {
                return;
            }
            const input = e.target.closest('.pg-fill-input');
            if (!input) {
                return;
            }
            const row = input.closest('.pg-fill-row');
            submitGuess(parseInt(row.dataset.conceptid, 10), input.value.trim());
        });

        const reopenBtn = document.getElementById('pg-fill-view-result');
        if (reopenBtn) {
            reopenBtn.addEventListener('click', () => openEndModal(endKind));
        }
    },
};
