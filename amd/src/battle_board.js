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
 * The PlayerBattle board: pieces are plain elements moved with the Web Animations API, over a layer of
 * real buttons that carry the focus, the labels and the player's clicks, swipes and keys.
 *
 * It decides nothing. battle_game.js owns the grid and the rules; this module keeps a matching grid
 * of piece elements and moves them as it is told. Every animation is a promise that resolves when it ends.
 *
 * @module     local_playergames/battle_board
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const PIECE_TYPES = 6;

const SWAP_MS = 180;
const DESTROY_MS = 200;
const FALL_MS = 260;
const FADE_MS = 250;

const DIRECTIONS = {
    ArrowUp: [-1, 0],
    ArrowDown: [1, 0],
    ArrowLeft: [0, -1],
    ArrowRight: [0, 1],
};

/**
 * Loads and decodes the piece images, so the first draw does not show them popping in.
 *
 * @param {string} pixUrl Base URL of the battle images, with a trailing slash.
 * @returns {Promise<void>}
 */
const preload = pixUrl => Promise.all(Array.from({length: PIECE_TYPES}, async(unused, type) => {
    const image = new Image();
    image.src = `${pixUrl}piece${type}.png`;
    try {
        await image.decode();
    } catch (error) {
        throw new Error(`Could not load ${image.src}`);
    }
}));

/**
 * Creates the board and waits for its images to load.
 *
 * @param {object} options
 * @param {HTMLElement} options.parent Element the board goes into.
 * @param {string} options.pixUrl Base URL of the battle images, with a trailing slash.
 * @param {number} options.rows Board rows.
 * @param {number} options.cols Board columns.
 * @param {string} options.label Accessible name of the board.
 * @param {boolean} options.reducedMotion Whether animations should be skipped.
 * @param {Function} options.describe Called with (row, col, type), returns the accessible name of a cell.
 * @param {Function} options.onSelect Called with {row, col} when a piece is selected with the keyboard.
 * @returns {Promise<object>} The board controller.
 */
export const createBoard = async({parent, pixUrl, rows, cols, label, reducedMotion, describe, onSelect}) => {
    await preload(pixUrl);

    // Piece type of every cell, kept in step with the elements so the buttons can be named.
    let types = [];
    let sprites = [];
    let selected = null;
    let cursor = {row: 0, col: 0};
    let waiting = null;
    let swipe = null;
    let swiped = false;

    const root = document.createElement('div');
    root.className = 'pg-battle-grid';
    root.style.setProperty('--pg-board-cols', cols);
    root.style.setProperty('--pg-board-rows', rows);

    const layer = document.createElement('div');
    layer.className = 'pg-battle-pieces';
    layer.setAttribute('aria-hidden', 'true');

    const controls = document.createElement('div');
    controls.className = 'pg-battle-cells';
    controls.setAttribute('role', 'group');
    controls.setAttribute('aria-label', label);

    const buttons = [];
    for (let row = 0; row < rows; row++) {
        buttons.push([]);
        for (let col = 0; col < cols; col++) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'pg-battle-cell';
            button.dataset.row = row;
            button.dataset.col = col;
            button.tabIndex = row === 0 && col === 0 ? 0 : -1;
            button.setAttribute('aria-pressed', 'false');
            controls.appendChild(button);
            buttons[row].push(button);
        }
    }
    root.append(layer, controls);
    parent.appendChild(root);

    const inside = (row, col) => row >= 0 && row < rows && col >= 0 && col < cols;
    const adjacent = (a, b) => Math.abs(a.row - b.row) + Math.abs(a.col - b.col) === 1;
    const place = (row, col) => `translate(${col * 100}%, ${row * 100}%)`;

    /**
     * Runs animations together and resolves when all of them end.
     *
     * @param {Array<{element: HTMLElement, keyframes: Array<object>, duration: number, easing: string}>} configs
     * @param {Function} done Called once they have ended, to leave the elements in their final state.
     * @returns {Promise<void>}
     */
    const animate = async(configs, done = () => null) => {
        if (!reducedMotion) {
            await Promise.all(configs.map(({element, keyframes, duration, easing}) => {
                const animation = element.animate(keyframes, {duration, easing, fill: 'forwards'});
                return animation.finished.catch(() => null);
            }));
        }
        done();
        configs.forEach(({element}) => element.getAnimations().forEach(animation => animation.cancel()));
    };

    /**
     * Names every button after the piece it covers.
     */
    const relabel = () => {
        buttons.forEach((line, row) => line.forEach((button, col) => {
            button.setAttribute('aria-label', describe(row, col, types[row][col]));
        }));
    };

    /**
     * Shows which piece is selected.
     */
    const markSelected = () => {
        buttons.forEach((line, row) => line.forEach((button, col) => {
            const on = selected !== null && selected.row === row && selected.col === col;
            button.setAttribute('aria-pressed', on ? 'true' : 'false');
        }));
    };

    /**
     * Moves the keyboard cursor to a cell: the one button in the tab order and the one with the focus.
     *
     * @param {{row: number, col: number}} cell The cell.
     * @param {boolean} focus Whether to move the focus too.
     */
    const moveCursor = (cell, focus) => {
        buttons[cursor.row][cursor.col].tabIndex = -1;
        cursor = {row: cell.row, col: cell.col};
        const button = buttons[cursor.row][cursor.col];
        button.tabIndex = 0;
        if (focus) {
            button.focus();
        }
    };

    /**
     * Adds a piece element at a cell.
     *
     * @param {number} row Row.
     * @param {number} col Column.
     * @param {number} type Piece type.
     * @param {number} fromRow Row it starts from, when it should not start in place.
     * @returns {HTMLElement} The element.
     */
    const addPiece = (row, col, type, fromRow = row) => {
        const element = document.createElement('div');
        element.className = 'pg-battle-piece';
        element.style.transform = place(fromRow, col);
        const image = document.createElement('img');
        image.src = `${pixUrl}piece${type}.png`;
        image.alt = '';
        image.draggable = false;
        element.appendChild(image);
        layer.appendChild(element);
        return element;
    };

    /**
     * Hands a swap to whoever is waiting for the player's move.
     *
     * @param {object} from First cell.
     * @param {object} to Second cell, a neighbour of the first.
     */
    const requestSwap = (from, to) => {
        selected = null;
        swipe = null;
        markSelected();
        if (waiting !== null) {
            const resolve = waiting;
            waiting = null;
            resolve({r1: from.row, c1: from.col, r2: to.row, c2: to.col});
        }
    };

    /**
     * A button was activated, by click, tap, Enter or Space: selects the piece, or swaps it with the selected
     * neighbour.
     *
     * @param {Event} event The click.
     */
    const onClick = event => {
        const button = event.target.closest('.pg-battle-cell');
        if (button === null) {
            return;
        }
        if (swiped) {
            swiped = false;
            return;
        }
        if (waiting === null) {
            return;
        }
        const cell = {row: Number(button.dataset.row), col: Number(button.dataset.col)};
        moveCursor(cell, false);
        if (selected !== null && adjacent(selected, cell)) {
            requestSwap(selected, cell);
            return;
        }
        if (selected !== null && selected.row === cell.row && selected.col === cell.col) {
            selected = null;
        } else {
            selected = cell;
            if (event.detail === 0) {
                onSelect(cell);
            }
        }
        markSelected();
    };

    /**
     * Pointer pressed on a cell: remembers where, to tell a swipe from a tap.
     *
     * @param {PointerEvent} event The event.
     */
    const onPointerDown = event => {
        const button = event.target.closest('.pg-battle-cell');
        swiped = false;
        swipe = button === null ? null : {
            row: Number(button.dataset.row),
            col: Number(button.dataset.col),
            x: event.clientX,
            y: event.clientY,
        };
    };

    /**
     * Pointer moved while pressed: a swipe past a third of a cell swaps in its direction.
     *
     * @param {PointerEvent} event The event.
     */
    const onPointerMove = event => {
        if (swipe === null || waiting === null) {
            return;
        }
        const dx = event.clientX - swipe.x;
        const dy = event.clientY - swipe.y;
        const cellSize = root.clientWidth / cols;
        if (Math.max(Math.abs(dx), Math.abs(dy)) < cellSize / 3) {
            return;
        }
        const target = Math.abs(dx) > Math.abs(dy) ?
            {row: swipe.row, col: swipe.col + Math.sign(dx)} :
            {row: swipe.row + Math.sign(dy), col: swipe.col};
        const from = swipe;
        swipe = null;
        if (inside(target.row, target.col)) {
            swiped = true;
            moveCursor(from, false);
            requestSwap(from, target);
        }
    };

    /**
     * Keyboard: arrows move the cursor, or swap the selected piece in their direction. Enter and Space
     * are the buttons' own click.
     *
     * @param {KeyboardEvent} event The key event.
     */
    const onKeyDown = event => {
        const direction = DIRECTIONS[event.key];
        if (direction) {
            event.preventDefault();
            const next = {row: cursor.row + direction[0], col: cursor.col + direction[1]};
            if (!inside(next.row, next.col)) {
                return;
            }
            if (selected !== null) {
                if (waiting !== null) {
                    requestSwap(selected, next);
                }
                return;
            }
            moveCursor(next, true);
        } else if (event.key === 'Escape' && selected !== null) {
            selected = null;
            markSelected();
        }
    };

    controls.addEventListener('click', onClick);
    controls.addEventListener('keydown', onKeyDown);
    controls.addEventListener('pointerdown', onPointerDown);
    controls.addEventListener('pointermove', onPointerMove);
    const endSwipe = () => {
        swipe = null;
    };
    controls.addEventListener('pointerup', endSwipe);
    controls.addEventListener('pointercancel', endSwipe);

    return {
        /**
         * Draws every piece from a grid of types, fading them in. Used at the start and after a shuffle.
         *
         * @param {Array} grid Grid of piece types.
         * @returns {Promise<void>}
         */
        async refresh(grid) {
            sprites.flat().forEach(piece => piece && piece.remove());
            types = grid.map(line => [...line]);
            sprites = grid.map((line, row) => line.map((type, col) => addPiece(row, col, type)));
            relabel();
            await animate(sprites.flat().map(element => ({
                element, keyframes: [{opacity: 0}, {opacity: 1}], duration: FADE_MS, easing: 'ease-out',
            })));
        },

        /**
         * Waits for the player to ask for a swap, by pointer or keyboard.
         *
         * @returns {Promise<{r1: number, c1: number, r2: number, c2: number}>}
         */
        nextMove() {
            return new Promise(resolve => {
                selected = null;
                markSelected();
                waiting = resolve;
            });
        },

        /**
         * Swaps two pieces.
         *
         * @param {{r1: number, c1: number, r2: number, c2: number}} move The two cells.
         * @returns {Promise<void>}
         */
        async swap({r1, c1, r2, c2}) {
            const first = sprites[r1][c1];
            const second = sprites[r2][c2];
            sprites[r1][c1] = second;
            sprites[r2][c2] = first;
            [types[r1][c1], types[r2][c2]] = [types[r2][c2], types[r1][c1]];
            await animate([
                {element: first, keyframes: [{transform: place(r1, c1)}, {transform: place(r2, c2)}],
                    duration: SWAP_MS, easing: 'ease-in-out'},
                {element: second, keyframes: [{transform: place(r2, c2)}, {transform: place(r1, c1)}],
                    duration: SWAP_MS, easing: 'ease-in-out'},
            ], () => {
                first.style.transform = place(r2, c2);
                second.style.transform = place(r1, c1);
            });
            relabel();
        },

        /**
         * Pops the pieces of a match.
         *
         * @param {Array<{row: number, col: number}>} cells The cells destroyed.
         * @returns {Promise<void>}
         */
        async clear(cells) {
            const doomed = cells.map(({row, col}) => {
                const element = sprites[row][col];
                sprites[row][col] = null;
                return element;
            });
            // The image shrinks, not the piece: the piece holds its place with its own transform.
            await animate(doomed.map(element => ({
                element: element.firstElementChild,
                keyframes: [{opacity: 1, transform: 'scale(1)'}, {opacity: 0, transform: 'scale(0)'}],
                duration: DESTROY_MS,
                easing: 'ease-in',
            })), () => doomed.forEach(element => element.remove()));
        },

        /**
         * Drops the pieces above the gaps and brings new ones in from the top, as the rules said.
         *
         * @param {{fell: Array, spawned: Array}} moves What applyGravityToGrid returned.
         * @returns {Promise<void>}
         */
        async gravity({fell, spawned}) {
            const moves = [];
            fell.forEach(({fromRow, toRow, col}) => {
                const element = sprites[fromRow][col];
                sprites[toRow][col] = element;
                sprites[fromRow][col] = null;
                types[toRow][col] = types[fromRow][col];
                moves.push({element, from: fromRow, to: toRow, col});
            });
            const perColumn = {};
            spawned.forEach(({col}) => {
                perColumn[col] = (perColumn[col] || 0) + 1;
            });
            spawned.forEach(({row, col, type}) => {
                const element = addPiece(row, col, type, row - perColumn[col]);
                sprites[row][col] = element;
                types[row][col] = type;
                moves.push({element, from: row - perColumn[col], to: row, col});
            });
            await animate(moves.map(({element, from, to, col}) => ({
                element,
                keyframes: [{transform: place(from, col)}, {transform: place(to, col)}],
                duration: FALL_MS,
                easing: 'ease-in',
            })), () => moves.forEach(({element, to, col}) => {
                element.style.transform = place(to, col);
            }));
            relabel();
        },

        /**
         * Gives the keyboard focus to the cell the cursor is on.
         */
        focus() {
            buttons[cursor.row][cursor.col].focus();
        },

        /**
         * Removes the board.
         */
        dispose() {
            root.remove();
        },
    };
};
