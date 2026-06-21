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
 * Player ecosystem dashboard interactions.
 *
 * Draws the typed relation connectors between plugin cards (measuring real DOM
 * positions, recomputed on resize), highlights a card's connections on hover or
 * focus, and opens a core/modal with the card's pre-rendered detail block.
 *
 * @module     local_playergames/dashboard
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Modal from 'core/modal';

const SVGNS = 'http://www.w3.org/2000/svg';

/**
 * Opens a modal for the given plugin card.
 *
 * @param {Element} card The .pg-eco-card element.
 */
const openCardModal = async(card) => {
    const component = card.dataset.component;
    const bodyEl = document.getElementById('pg-modal-' + component);
    if (!bodyEl) {
        return;
    }
    const nameEl = card.querySelector('.pg-eco-card-name');
    await Modal.create({
        title: nameEl ? nameEl.textContent : component,
        body: bodyEl.innerHTML,
        show: true,
        removeOnClose: true,
    });
};

/**
 * Returns the centre of an element in coordinates relative to a container.
 *
 * @param {Element} el The measured element.
 * @param {DOMRect} containerRect The container's bounding rectangle.
 * @return {{x: number, y: number}}
 */
const centreOf = (el, containerRect) => {
    const rect = el.getBoundingClientRect();
    return {
        x: rect.left - containerRect.left + rect.width / 2,
        y: rect.top - containerRect.top + rect.height / 2,
    };
};

/**
 * Draws every connector path into the overlay SVG.
 *
 * @param {Element} container The .pg-eco element.
 * @param {SVGElement} overlay The .pg-eco-overlay SVG element.
 * @param {Array<{from: string, to: string, type: string}>} edges The edge list.
 */
const drawEdges = (container, overlay, edges) => {
    const width = container.clientWidth;
    const height = container.clientHeight;
    overlay.setAttribute('viewBox', `0 0 ${width} ${height}`);
    overlay.setAttribute('preserveAspectRatio', 'none');
    while (overlay.firstChild) {
        overlay.removeChild(overlay.firstChild);
    }

    const containerRect = container.getBoundingClientRect();
    edges.forEach(edge => {
        const fromEl = container.querySelector(`.pg-eco-card[data-component="${edge.from}"]`);
        const toEl = container.querySelector(`.pg-eco-card[data-component="${edge.to}"]`);
        if (!fromEl || !toEl) {
            return;
        }
        const a = centreOf(fromEl, containerRect);
        const b = centreOf(toEl, containerRect);
        const midx = a.x + (b.x - a.x) * 0.5;
        const path = document.createElementNS(SVGNS, 'path');
        path.setAttribute('d', `M ${a.x} ${a.y} C ${midx} ${a.y} ${midx} ${b.y} ${b.x} ${b.y}`);
        path.setAttribute('class', `pg-eco-edge pg-edge-${edge.type}`);
        path.dataset.from = edge.from;
        path.dataset.to = edge.to;
        overlay.appendChild(path);
    });
};

/**
 * Wires hover/focus highlighting of a card and its connections.
 *
 * @param {Element} container The .pg-eco element.
 * @param {Element} card The card to wire.
 * @param {Map<string, Set<string>>} neighbours Adjacency map.
 */
const wireHighlight = (container, card, neighbours) => {
    const component = card.dataset.component;

    const activate = () => {
        container.classList.add('pg-eco-dim');
        card.classList.add('pg-eco-card-active');
        (neighbours.get(component) || new Set()).forEach(other => {
            const el = container.querySelector(`.pg-eco-card[data-component="${other}"]`);
            if (el) {
                el.classList.add('pg-eco-card-linked');
            }
        });
        container.querySelectorAll('.pg-eco-edge').forEach(edge => {
            if (edge.dataset.from === component || edge.dataset.to === component) {
                edge.classList.add('pg-eco-edge-active');
            }
        });
    };

    const reset = () => {
        container.classList.remove('pg-eco-dim');
        container.querySelectorAll('.pg-eco-card-active, .pg-eco-card-linked').forEach(el => {
            el.classList.remove('pg-eco-card-active', 'pg-eco-card-linked');
        });
        container.querySelectorAll('.pg-eco-edge-active').forEach(el => {
            el.classList.remove('pg-eco-edge-active');
        });
    };

    card.addEventListener('mouseenter', activate);
    card.addEventListener('mouseleave', reset);
    card.addEventListener('focus', activate);
    card.addEventListener('blur', reset);
};

/**
 * Reads the edge list embedded in the page.
 *
 * @return {Array<{from: string, to: string, type: string}>}
 */
const readEdges = () => {
    const el = document.getElementById('pg-ecosystem-edges');
    if (!el) {
        return [];
    }
    try {
        return JSON.parse(el.textContent) || [];
    } catch (e) {
        return [];
    }
};

/**
 * Builds an undirected adjacency map from the edge list.
 *
 * @param {Array<{from: string, to: string}>} edges The edge list.
 * @return {Map<string, Set<string>>}
 */
const buildNeighbours = (edges) => {
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
 * Initialises the ecosystem map.
 */
const init = () => {
    const container = document.querySelector('.pg-eco');
    const overlay = container ? container.querySelector('.pg-eco-overlay') : null;
    if (!container || !overlay) {
        return;
    }

    const edges = readEdges();
    const neighbours = buildNeighbours(edges);
    const cards = container.querySelectorAll('.pg-eco-card');

    cards.forEach(card => {
        card.addEventListener('click', () => openCardModal(card));
        card.addEventListener('keydown', e => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                openCardModal(card);
            }
        });
        wireHighlight(container, card, neighbours);
    });

    let frame = null;
    const redraw = () => {
        if (frame) {
            window.cancelAnimationFrame(frame);
        }
        frame = window.requestAnimationFrame(() => drawEdges(container, overlay, edges));
    };

    redraw();
    window.addEventListener('resize', redraw);
    if (window.ResizeObserver) {
        new window.ResizeObserver(redraw).observe(container);
    }
};

export default {init};
