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
 * Player ecosystem map.
 *
 * Selecting a card (click, Enter or Space) draws only its connections, dims the plugins it has nothing to do with
 * and shows, below the map, what it uses and what uses it. Selecting it again, or pressing Escape, clears it.
 *
 * @module     local_playergames/dashboard
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Graph from 'local_playergames/dashboard/graph';

const SVGNS = 'http://www.w3.org/2000/svg';

/**
 * Returns the box of an element in coordinates relative to a container.
 *
 * @param {Element} el The measured element.
 * @param {DOMRect} containerRect The container's bounding rectangle.
 * @return {{left: number, right: number, top: number, bottom: number}}
 */
const boxOf = (el, containerRect) => {
    const rect = el.getBoundingClientRect();
    return {
        left: rect.left - containerRect.left,
        right: rect.right - containerRect.left,
        top: rect.top - containerRect.top,
        bottom: rect.bottom - containerRect.top,
    };
};

/**
 * Draws the connectors of one card into the overlay, or none.
 *
 * @param {Element} container The .pg-eco element.
 * @param {SVGElement} overlay The .pg-eco-overlay SVG element.
 * @param {Array<{from: string, to: string, type: string}>} edges The edges to draw.
 */
const drawEdges = (container, overlay, edges) => {
    overlay.setAttribute('viewBox', `0 0 ${container.clientWidth} ${container.clientHeight}`);
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
        const path = document.createElementNS(SVGNS, 'path');
        path.setAttribute('d', Graph.edgePath(boxOf(fromEl, containerRect), boxOf(toEl, containerRect)));
        path.setAttribute('class', `pg-eco-edge pg-edge-${edge.type}`);
        overlay.appendChild(path);
    });
};

/**
 * Reads the edge list embedded in the page.
 *
 * @return {Array<{from: string, to: string, type: string}>}
 */
const readEdges = () => {
    const el = document.getElementById('pg-ecosystem-edges');
    return el ? Graph.parseEdges(el.textContent) : [];
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
    const root = container.closest('.local-playergames-dashboard') || document;
    const hint = root.querySelector('.pg-eco-hint');
    const details = root.querySelectorAll('.pg-eco-detail');
    const edges = readEdges();
    const neighbours = Graph.neighbours(edges);
    const cards = container.querySelectorAll('.pg-eco-card');
    let selected = null;

    const show = () => {
        const near = neighbours.get(selected) || new Set();
        container.classList.toggle('pg-eco-dim', selected !== null);
        cards.forEach(card => {
            const component = card.dataset.component;
            card.classList.toggle('pg-eco-card-active', component === selected);
            card.classList.toggle('pg-eco-card-linked', near.has(component));
            card.setAttribute('aria-pressed', String(component === selected));
        });
        details.forEach(detail => {
            detail.hidden = detail.dataset.component !== selected;
        });
        if (hint) {
            hint.hidden = selected !== null;
        }
        drawEdges(container, overlay, edges.filter(edge => edge.from === selected || edge.to === selected));
    };

    const panel = root.querySelector('.pg-eco-panel');
    const choose = card => {
        selected = card.dataset.component === selected ? null : card.dataset.component;
        show();
        // On a phone the panel is far below the card: bring it into view, as nothing on the map changes there.
        if (selected !== null && panel && window.matchMedia('(max-width: 575.98px)').matches) {
            panel.scrollIntoView({block: 'nearest', behavior: 'smooth'});
        }
    };

    cards.forEach(card => {
        card.addEventListener('click', () => choose(card));
        card.addEventListener('keydown', e => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                choose(card);
            }
        });
    });
    root.addEventListener('keydown', e => {
        if (e.key === 'Escape' && selected !== null) {
            selected = null;
            show();
        }
    });

    let frame = null;
    const redraw = () => {
        if (frame) {
            window.cancelAnimationFrame(frame);
        }
        frame = window.requestAnimationFrame(show);
    };
    show();
    window.addEventListener('resize', redraw);
    if (window.ResizeObserver) {
        new window.ResizeObserver(redraw).observe(container);
    }
};

export default {init};
