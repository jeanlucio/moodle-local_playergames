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
 * The connections of the ecosystem map: which cards they join and the curve drawn between them.
 *
 * UMD-wrapped like the PlayerGuess key filter, so it is tested in Node without a browser.
 *
 * @module     local_playergames/dashboard/graph
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/* global module */
(function(define) {
    define([], () => {
        'use strict';

        /**
         * Reads the edge list the page embeds.
         *
         * @param {string} text The JSON the page holds.
         * @return {Array<{from: string, to: string, type: string}>} The edges that name both of their ends;
         *     an empty list when the JSON is broken or not a list.
         */
        const parseEdges = text => {
            let edges;
            try {
                edges = JSON.parse(text);
            } catch (e) {
                return [];
            }
            if (!Array.isArray(edges)) {
                return [];
            }
            return edges.filter(edge => edge && typeof edge.from === 'string' && typeof edge.to === 'string');
        };

        /**
         * Builds the cards each card is joined to, in both directions.
         *
         * @param {Array<{from: string, to: string}>} edges The edge list.
         * @return {Map<string, Set<string>>} The neighbours of each card.
         */
        const neighbours = edges => {
            const map = new Map();
            const add = (a, b) => {
                if (!map.has(a)) {
                    map.set(a, new Set());
                }
                map.get(a).add(b);
            };
            edges.forEach(edge => {
                add(edge.from, edge.to);
                add(edge.to, edge.from);
            });
            return map;
        };

        /**
         * Returns the SVG path of a connection between two card centres.
         *
         * @param {{x: number, y: number}} a The centre the edge starts from.
         * @param {{x: number, y: number}} b The centre it ends at.
         * @return {string} A cubic curve that leaves and arrives horizontally.
         */
        const edgePath = (a, b) => {
            const midx = a.x + (b.x - a.x) * 0.5;
            return `M ${a.x} ${a.y} C ${midx} ${a.y} ${midx} ${b.y} ${b.x} ${b.y}`;
        };

        return {parseEdges, neighbours, edgePath};
    });
}(typeof define === 'function' && define.amd ? define : (deps, factory) => {
    module.exports = factory();
}));
