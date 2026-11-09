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
 * Headless tests for the seeded random generator, run via `node --test` with no RequireJS,
 * Phaser or browser. Loads amd/src/battle/prng.js through Node's CommonJS require(), the
 * branch its UMD wrapper takes outside a `define()` environment.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

'use strict';

const {test} = require('node:test');
const assert = require('node:assert/strict');
const prng = require('../../../amd/src/battle/prng.js');

const take = (rng, count) => Array.from({length: count}, () => rng());

test('the same seed always produces the same sequence', () => {
    assert.deepEqual(take(prng.create(42), 8), take(prng.create(42), 8));
});

test('different seeds produce different sequences', () => {
    assert.notDeepEqual(take(prng.create(1), 8), take(prng.create(2), 8));
});

test('every value is in [0, 1)', () => {
    const values = take(prng.create(7), 5000);
    assert.ok(values.every(value => value >= 0 && value < 1));
});

test('the sequence is fixed: a change would alter every board a seed produced', () => {
    const values = take(prng.create(42), 3).map(value => value.toFixed(6));
    assert.deepEqual(values, ['0.601104', '0.448291', '0.852466']);
});

test('a negative or fractional seed is coerced like an unsigned 32-bit integer', () => {
    assert.deepEqual(take(prng.create(-1), 4), take(prng.create(4294967295), 4));
});
