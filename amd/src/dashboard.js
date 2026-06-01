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
 * Handles click (and Enter/Space keydown) on SVG plugin nodes.
 * Each click opens a core/modal populated with pre-rendered content
 * from a hidden <div id="pg-modal-{component}"> in the page.
 *
 * @module     local_playergames/dashboard
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/modal'], function(Modal) {
    'use strict';

    /**
     * Opens a modal for the clicked SVG node.
     *
     * @param {Element} node SVG <g> element with data-component and data-name.
     */
    const openNodeModal = async(node) => {
        const component = node.dataset.component;
        const name = node.dataset.name;
        const bodyEl = document.getElementById('pg-modal-' + component);
        if (!bodyEl) {
            return;
        }
        await Modal.create({
            title: name,
            body: bodyEl.innerHTML,
            show: true,
            removeOnClose: true,
        });
    };

    /**
     * Initialises node interactions on the ecosystem SVG.
     */
    const init = () => {
        document.querySelectorAll('.pg-ecosystem-node').forEach(node => {
            node.addEventListener('click', () => openNodeModal(node));
            node.addEventListener('keydown', e => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    openNodeModal(node);
                }
            });
        });
    };

    return {init};
});
