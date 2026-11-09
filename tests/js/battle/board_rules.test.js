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
 * Headless tests for the match-3 board rules, run via `node --test` with no RequireJS,
 * Phaser or browser.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

'use strict';

const {test} = require('node:test');
const assert = require('node:assert/strict');
const Board = require('../../../amd/src/battle/board_rules.js');
const Prng = require('../../../amd/src/battle/prng.js');

const ROWS = 8;
const COLS = 8;
const TYPES = 6;

/**
 * Builds a grid from rows of digits; an underscore is an empty cell.
 *
 * @param {Array<string>} rows One string per row.
 * @return {Array} The grid.
 */
const grid = rows => rows.map(row => row.split('').map(char => (char === '_' ? null : Number(char))));

/**
 * Runs the match scan the game runs.
 *
 * @param {Array} cells The grid.
 * @return {{destroyed: Array, groups: Array}}
 */
const scan = cells => {
    const destroyed = [];
    const groups = [];
    Board.checkHorizontal(cells, cells.length, cells[0].length, destroyed, groups);
    Board.checkVertical(cells, cells.length, cells[0].length, destroyed, groups);
    return {destroyed, groups};
};

test('a new grid has only the types in play and no match on it', () => {
    for (let seed = 1; seed <= 50; seed++) {
        const cells = Board.generateGrid(ROWS, COLS, Prng.create(seed), TYPES);
        assert.ok(cells.every(row => row.every(type => Number.isInteger(type) && type >= 0 && type < TYPES)));
        assert.equal(Board.hasAnyMatch(cells, ROWS, COLS), false);
    }
});

test('the number of types is the one asked for', () => {
    const cells = Board.generateGrid(ROWS, COLS, Prng.create(3), 3);
    assert.ok(cells.every(row => row.every(type => type < 3)));
    assert.deepEqual([...new Set(cells.flat())].sort(), [0, 1, 2]);
});

test('the same seed makes the same grid', () => {
    assert.deepEqual(
        Board.generateGrid(ROWS, COLS, Prng.create(11), TYPES),
        Board.generateGrid(ROWS, COLS, Prng.create(11), TYPES)
    );
});

test('a new piece is rolled again while it would complete three in a row', () => {
    // Two types, neighbours already 1 and 1: the first two rolls pick 1 and are refused.
    const rolls = [0.99, 0.99, 0.1];
    const type = Board.pickTypeAvoidingMatch([[1, 1, null]], 0, 2, () => rolls.shift(), 2);
    assert.equal(type, 0);
    assert.equal(rolls.length, 0);
});

test('a horizontal and a vertical match are found with their size', () => {
    const cells = grid([
        '1112',
        '3452',
        '0342',
        '5032',
    ]);
    const {destroyed, groups} = scan(cells);
    assert.deepEqual(groups.map(group => [group.type, group.cells.length]).sort(), [[1, 3], [2, 4]]);
    assert.equal(destroyed.length, 7);
});

test('a piece in two matches is destroyed once', () => {
    const cells = grid([
        '111',
        '1__',
        '1__',
    ]);
    const {destroyed, groups} = scan(cells);
    assert.equal(groups.length, 2);
    assert.equal(destroyed.length, 5);
});

test('type 0 is a piece, not an empty cell', () => {
    const {destroyed} = scan(grid(['000', '123', '321']));
    assert.equal(destroyed.length, 3);
});

test('empty cells never match', () => {
    assert.equal(scan(grid(['___', '___', '___'])).destroyed.length, 0);
});

test('swapping twice restores the grid', () => {
    const cells = grid(['12', '34']);
    Board.swapInGrid(cells, 0, 0, 1, 1);
    assert.deepEqual(cells, grid(['42', '31']));
    Board.swapInGrid(cells, 0, 0, 1, 1);
    assert.deepEqual(cells, grid(['12', '34']));
});

test('evaluateSwap names the type a swap would match and leaves the grid as it was', () => {
    const cells = grid([
        '1232',
        '3123',
        '2311',
        '1233',
    ]);
    const before = JSON.parse(JSON.stringify(cells));
    assert.equal(Board.evaluateSwap(cells, 4, 4, 2, 1, 2, 2), null);
    assert.equal(Board.evaluateSwap(cells, 4, 4, 1, 1, 2, 1), 1);
    assert.deepEqual(cells, before);
});

test('the opponent prefers a Sword match to an earlier one, and has no move on a dead board', () => {
    const cells = grid([
        '00105',
        '52412',
        '14524',
        '25241',
        '33134',
    ]);
    const first = Board.findMove(cells, 5, 5, null);
    assert.equal(Board.evaluateSwap(cells, 5, 5, first.r1, first.c1, first.r2, first.c2), 0);

    const move = Board.chooseMove(cells, 5, 5);
    assert.equal(Board.evaluateSwap(cells, 5, 5, move.r1, move.c1, move.r2, move.c2), 3);

    assert.equal(Board.chooseMove(grid(['0123', '2301', '0123', '2301']), 4, 4), null);
});

test('availability reflects whether any swap makes a match', () => {
    assert.equal(Board.hasAvailableMove(grid(['0123', '2301', '0123', '2301']), 4, 4), false);
    assert.equal(Board.hasAvailableMove(grid(['0010', '1101', '2323', '3232']), 4, 4), true);
});

test('shuffling until valid leaves a board with a move and no match, keeping the same pieces', () => {
    const cells = grid(['0123', '2301', '0123', '2301']);
    const pieces = cells.flat().sort();
    Board.shuffleUntilValid(cells, 4, 4, Prng.create(5));
    assert.equal(Board.hasAvailableMove(cells, 4, 4), true);
    assert.equal(Board.hasAnyMatch(cells, 4, 4), false);
    assert.deepEqual(cells.flat().sort(), pieces);
});

test('gravity drops pieces, refills from the top with the types in play, and reports both', () => {
    const cells = grid([
        '1_3',
        '2_4',
        '__5',
        '___',
    ]);
    const {fell, spawned} = Board.applyGravityToGrid(cells, 4, 3, Prng.create(9), 4);

    assert.ok(cells.every(row => row.every(type => Number.isInteger(type))));
    assert.deepEqual([cells[2][0], cells[3][0]], [1, 2]);
    assert.deepEqual([cells[1][2], cells[2][2], cells[3][2]], [3, 4, 5]);
    assert.equal(fell.length, 5);
    assert.equal(spawned.length, 12 - 5);
    assert.ok(spawned.every(piece => piece.type >= 0 && piece.type < 4 && cells[piece.row][piece.col] === piece.type));
});
