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
 * The PlayerBattle board: draws the pieces on a Phaser canvas, takes the player's swaps from the
 * pointer or the keyboard, and animates what the rules decided.
 *
 * It decides nothing. battle_game.js owns the grid and the rules; this module keeps a matching grid
 * of sprites and moves them as it is told. Every animation is a promise that resolves when it ends.
 *
 * @module     local_playergames/battle_board
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {load as loadPhaser} from 'local_playergames/phaser';

// The canvas is drawn at twice the size it is shown at, so the pieces stay sharp on high density screens.
const CELL = 160;
const PIECE_RATIO = 0.86;
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
 * Creates the board and waits for its assets to load.
 *
 * @param {object} options
 * @param {HTMLElement} options.parent Element the canvas goes into.
 * @param {string} options.pixUrl Base URL of the battle images, with a trailing slash.
 * @param {number} options.rows Board rows.
 * @param {number} options.cols Board columns.
 * @param {string} options.label Accessible name of the board.
 * @param {boolean} options.reducedMotion Whether animations should be skipped.
 * @param {Function} options.onCursor Called with {row, col} when the keyboard cursor moves or selects.
 * @returns {Promise<object>} The board controller.
 */
export const createBoard = async({parent, pixUrl, rows, cols, label, reducedMotion, onCursor}) => {
    const Phaser = await loadPhaser();

    let scene = null;
    let sprites = [];
    let overlay = null;
    let selected = null;
    let cursor = {row: 0, col: 0};
    let focused = false;
    let waiting = null;
    let dragFrom = null;

    const pieceSize = CELL * PIECE_RATIO;
    const centre = index => (index + 0.5) * CELL;

    /**
     * Runs tweens together and resolves when all of them end.
     *
     * @param {Array<object>} configs Phaser tween configs; the duration is applied here.
     * @returns {Promise<void>}
     */
    const animate = configs => new Promise(resolve => {
        if (configs.length === 0) {
            resolve();
            return;
        }
        let pending = configs.length;
        configs.forEach(config => {
            const {duration, onComplete, ...rest} = config;
            scene.tweens.add({
                ...rest,
                duration: reducedMotion ? 1 : duration,
                onComplete: () => {
                    if (onComplete) {
                        onComplete();
                    }
                    pending--;
                    if (pending === 0) {
                        resolve();
                    }
                },
            });
        });
    });

    /**
     * Adds a piece sprite at a cell.
     *
     * @param {number} row Row.
     * @param {number} col Column.
     * @param {number} type Piece type.
     * @param {number} y Vertical start, when it should not start in place.
     * @returns {object} The sprite.
     */
    const addPiece = (row, col, type, y = centre(row)) => {
        const piece = scene.add.image(centre(col), y, `piece${type}`).setDisplaySize(pieceSize, pieceSize);
        piece.row = row;
        piece.col = col;
        return piece;
    };

    /**
     * Redraws the selection and the keyboard cursor.
     */
    const drawOverlay = () => {
        overlay.clear();
        const square = (cell, colour, width) => {
            overlay.lineStyle(width, colour, 1);
            overlay.strokeRect(cell.col * CELL + 6, cell.row * CELL + 6, CELL - 12, CELL - 12);
        };
        if (selected !== null) {
            square(selected, 0xffc107, 10);
        }
        if (focused && waiting !== null) {
            square(cursor, 0xffffff, 6);
        }
    };

    /**
     * Hands a swap to whoever is waiting for the player's move.
     *
     * @param {object} from First cell.
     * @param {object} to Second cell, a neighbour of the first.
     */
    const requestSwap = (from, to) => {
        selected = null;
        dragFrom = null;
        if (waiting !== null) {
            const resolve = waiting;
            waiting = null;
            drawOverlay();
            resolve({r1: from.row, c1: from.col, r2: to.row, c2: to.col});
        }
    };

    const inside = (row, col) => row >= 0 && row < rows && col >= 0 && col < cols;
    const adjacent = (a, b) => Math.abs(a.row - b.row) + Math.abs(a.col - b.col) === 1;
    const cellAt = pointer => ({row: Math.floor(pointer.y / CELL), col: Math.floor(pointer.x / CELL)});

    /**
     * Pointer pressed: selects a piece, or swaps it with the selected neighbour.
     *
     * @param {object} pointer Phaser pointer.
     */
    const onPointerDown = pointer => {
        if (waiting === null) {
            return;
        }
        const cell = cellAt(pointer);
        if (!inside(cell.row, cell.col)) {
            return;
        }
        cursor = cell;
        if (selected !== null && adjacent(selected, cell)) {
            requestSwap(selected, cell);
            return;
        }
        if (selected !== null && selected.row === cell.row && selected.col === cell.col) {
            selected = null;
        } else {
            selected = cell;
            dragFrom = {...cell, x: pointer.x, y: pointer.y};
        }
        drawOverlay();
    };

    /**
     * Pointer moved while pressed: a swipe past a third of a cell swaps in its direction.
     *
     * @param {object} pointer Phaser pointer.
     */
    const onPointerMove = pointer => {
        if (dragFrom === null || !pointer.isDown) {
            return;
        }
        const dx = pointer.x - dragFrom.x;
        const dy = pointer.y - dragFrom.y;
        if (Math.max(Math.abs(dx), Math.abs(dy)) < CELL / 3) {
            return;
        }
        const target = Math.abs(dx) > Math.abs(dy) ?
            {row: dragFrom.row, col: dragFrom.col + Math.sign(dx)} :
            {row: dragFrom.row + Math.sign(dy), col: dragFrom.col};
        if (inside(target.row, target.col)) {
            requestSwap(dragFrom, target);
        } else {
            dragFrom = null;
        }
    };

    /**
     * Keyboard: arrows move the cursor, Enter or Space selects, and an arrow then swaps.
     *
     * @param {KeyboardEvent} event The key event.
     */
    const onKeyDown = event => {
        if (waiting === null) {
            return;
        }
        const direction = DIRECTIONS[event.key];
        if (direction) {
            event.preventDefault();
            const next = {row: cursor.row + direction[0], col: cursor.col + direction[1]};
            if (!inside(next.row, next.col)) {
                return;
            }
            if (selected !== null) {
                requestSwap(selected, next);
                return;
            }
            cursor = next;
            onCursor({...cursor, selected: false});
        } else if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            selected = selected === null ? {...cursor} : null;
            onCursor({...cursor, selected: selected !== null});
        } else if (event.key === 'Escape') {
            selected = null;
        } else {
            return;
        }
        drawOverlay();
    };

    let game = null;
    await new Promise((resolve, reject) => {
        const sceneConfig = {
            preload() {
                for (let type = 0; type < PIECE_TYPES; type++) {
                    this.load.image(`piece${type}`, `${pixUrl}piece${type}.png`);
                }
                this.load.on('loaderror', file => reject(new Error(`Could not load ${file.src}`)));
            },
            create() {
                scene = this;
                const background = this.add.graphics();
                for (let row = 0; row < rows; row++) {
                    for (let col = 0; col < cols; col++) {
                        background.fillStyle((row + col) % 2 === 0 ? 0x2b3039 : 0x353b46, 1);
                        background.fillRect(col * CELL, row * CELL, CELL, CELL);
                    }
                }
                overlay = this.add.graphics().setDepth(10);
                this.input.on('pointerdown', onPointerDown);
                this.input.on('pointermove', onPointerMove);
                this.input.on('pointerup', () => {
                    dragFrom = null;
                });
                resolve();
            },
        };
        game = new Phaser.Game({
            type: Phaser.AUTO,
            parent,
            width: cols * CELL,
            height: rows * CELL,
            backgroundColor: '#2b3039',
            scale: {mode: Phaser.Scale.FIT, autoCenter: Phaser.Scale.CENTER_BOTH},
            input: {touch: {capture: false}},
            scene: sceneConfig,
        });
    });

    const canvas = parent.querySelector('canvas');
    canvas.tabIndex = 0;
    canvas.setAttribute('role', 'application');
    canvas.setAttribute('aria-label', label);
    canvas.addEventListener('keydown', onKeyDown);
    canvas.addEventListener('focus', () => {
        focused = true;
        drawOverlay();
    });
    canvas.addEventListener('blur', () => {
        focused = false;
        drawOverlay();
    });

    return {
        /**
         * Draws every piece from a grid of types, fading them in. Used at the start and after a shuffle.
         *
         * @param {Array} types Grid of piece types.
         * @returns {Promise<void>}
         */
        async refresh(types) {
            sprites.flat().forEach(piece => piece && piece.destroy());
            sprites = types.map((rowTypes, row) => rowTypes.map((type, col) => addPiece(row, col, type).setAlpha(0)));
            await animate(sprites.flat().map(piece => ({targets: piece, alpha: 1, duration: FADE_MS})));
        },

        /**
         * Waits for the player to ask for a swap, by pointer or keyboard.
         *
         * @returns {Promise<{r1: number, c1: number, r2: number, c2: number}>}
         */
        nextMove() {
            return new Promise(resolve => {
                selected = null;
                waiting = resolve;
                drawOverlay();
            });
        },

        /**
         * Swaps two pieces with a tween.
         *
         * @param {{r1: number, c1: number, r2: number, c2: number}} move The two cells.
         * @returns {Promise<void>}
         */
        async swap({r1, c1, r2, c2}) {
            const first = sprites[r1][c1];
            const second = sprites[r2][c2];
            sprites[r1][c1] = second;
            sprites[r2][c2] = first;
            first.row = r2;
            first.col = c2;
            second.row = r1;
            second.col = c1;
            await animate([
                {targets: first, x: centre(c2), y: centre(r2), duration: SWAP_MS},
                {targets: second, x: centre(c1), y: centre(r1), duration: SWAP_MS},
            ]);
        },

        /**
         * Pops the pieces of a match.
         *
         * @param {Array<{row: number, col: number}>} cells The cells destroyed.
         * @returns {Promise<void>}
         */
        async clear(cells) {
            const doomed = cells.map(({row, col}) => {
                const piece = sprites[row][col];
                sprites[row][col] = null;
                return piece;
            });
            await animate(doomed.map(piece => ({
                targets: piece, scaleX: 0, scaleY: 0, alpha: 0, duration: DESTROY_MS, onComplete: () => piece.destroy(),
            })));
        },

        /**
         * Drops the pieces above the gaps and brings new ones in from the top, as the rules said.
         *
         * @param {{fell: Array, spawned: Array}} moves What applyGravityToGrid returned.
         * @returns {Promise<void>}
         */
        async gravity({fell, spawned}) {
            const tweens = [];
            fell.forEach(({fromRow, toRow, col}) => {
                const piece = sprites[fromRow][col];
                sprites[toRow][col] = piece;
                sprites[fromRow][col] = null;
                piece.row = toRow;
                tweens.push({targets: piece, y: centre(toRow), duration: FALL_MS});
            });
            const perColumn = {};
            spawned.forEach(({col}) => {
                perColumn[col] = (perColumn[col] || 0) + 1;
            });
            spawned.forEach(({row, col, type}) => {
                const piece = addPiece(row, col, type, centre(row - perColumn[col]));
                sprites[row][col] = piece;
                tweens.push({targets: piece, y: centre(row), duration: FALL_MS});
            });
            await animate(tweens);
        },

        /**
         * Gives the canvas keyboard focus.
         */
        focus() {
            canvas.focus();
        },

        /**
         * Stops the game and removes the canvas.
         */
        dispose() {
            game.destroy(true);
        },
    };
};
