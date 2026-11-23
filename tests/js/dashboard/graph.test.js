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
 * Headless tests for the connections of the ecosystem map, run via `node --test`.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

'use strict';

const {test} = require('node:test');
const assert = require('node:assert/strict');
const Graph = require('../../../amd/src/dashboard/graph.js');

test('the edge list of the page is read as it is', () => {
    const edges = [{from: 'block_playerhud', to: 'local_playergames', type: 'data'}];
    assert.deepEqual(Graph.parseEdges(JSON.stringify(edges)), edges);
});

// The map wires every card from this list: anything else than a list of edges would stop the whole map.
test('a broken edge list or one that is not a list gives no edges', () => {
    assert.deepEqual(Graph.parseEdges('{'), []);
    assert.deepEqual(Graph.parseEdges(''), []);
    assert.deepEqual(Graph.parseEdges('null'), []);
    assert.deepEqual(Graph.parseEdges('{"from": "a", "to": "b"}'), []);
});

test('an edge that does not name both of its ends is left out', () => {
    const text = JSON.stringify([{from: 'a', to: 'b'}, {from: 'a'}, null, {from: 1, to: 'b'}, 'a']);
    assert.deepEqual(Graph.parseEdges(text), [{from: 'a', to: 'b'}]);
});

test('each card knows the cards it is joined to, whichever way the edge goes', () => {
    const map = Graph.neighbours([{from: 'a', to: 'b'}, {from: 'c', to: 'a'}, {from: 'a', to: 'b'}]);
    assert.deepEqual([...map.get('a')].sort(), ['b', 'c']);
    assert.deepEqual([...map.get('b')], ['a']);
    assert.deepEqual([...map.get('c')], ['a']);
    assert.equal(map.has('d'), false);
});

const box = (left, top) => ({left, right: left + 100, top, bottom: top + 40});

test('a connection joins the sides of the two cards that face each other, bending halfway', () => {
    assert.equal(Graph.edgePath(box(0, 0), box(300, 100)), 'M 100 20 C 200 20 200 120 300 120');
    assert.equal(Graph.edgePath(box(300, 100), box(0, 0)), 'M 300 120 C 200 120 200 20 100 20');
});

// A line from centre to centre ran under the cards of the column between them, and between two cards of the same
// column it ran under both.
test('two cards of the same column are joined by a loop out to their right', () => {
    assert.equal(Graph.edgePath(box(0, 0), box(0, 120)), 'M 100 20 C 144 20 144 140 100 140');
});
