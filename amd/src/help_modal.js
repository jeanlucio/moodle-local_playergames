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
 * Shared "how PlayerGames works" help modal, wired on every page that
 * renders nav_header.mustache, and later reused by block_playergames.
 *
 * @module     local_playergames/help_modal
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Modal from 'core/modal';

/**
 * Opens the static help content in a modal when the trigger is clicked.
 * The content node is moved into the modal once and stays there.
 */
const init = () => {
    const trigger = document.querySelector('[data-help-trigger]');
    const source = document.getElementById('pg-help-content');
    if (!trigger || !source) {
        return;
    }
    let modal = null;
    trigger.addEventListener('click', async() => {
        if (!modal) {
            modal = await Modal.create({
                title: trigger.dataset.modalTitle ?? '',
                large: true,
            });
            const body = modal.getBody()[0];
            source.hidden = false;
            body.appendChild(source);
        }
        modal.show();
    });
};

export {init};
