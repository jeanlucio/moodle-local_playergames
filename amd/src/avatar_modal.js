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
 * Shared avatar-collection modal — equip via AJAX, no page reload.
 *
 * Used by both the Player Hub page (local_playergames/hub) and
 * block_playergames's sidebar widget. Both templates reuse the exact same
 * selectors/classes ([data-avatar-trigger], #pg-avatar-collection,
 * .pg-hub-avatar-grid, .pg-hub-profile-avatar-emoji/-img) rather than
 * parameterising them, since the two never render on the same page.
 *
 * @module     local_playergames/avatar_modal
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Modal from 'core/modal';
import Notification from 'core/notification';

/**
 * Swaps the large profile avatar between the equipped emoji and the Moodle
 * profile picture.
 *
 * @param {string} equipped The equipped emoji, or '' for none.
 */
const updateProfileAvatar = equipped => {
    const trigger = document.querySelector('[data-avatar-trigger]');
    if (!trigger) {
        return;
    }
    const emoji = trigger.querySelector('.pg-hub-profile-avatar-emoji');
    const img = trigger.querySelector('.pg-hub-profile-avatar-img');
    if (equipped) {
        if (emoji) {
            emoji.textContent = equipped;
            emoji.hidden = false;
        }
        if (img) {
            img.hidden = true;
        }
    } else {
        if (emoji) {
            emoji.hidden = true;
        }
        if (img) {
            img.hidden = false;
        }
    }
};

/**
 * Reflects the newly equipped avatar across the grid and the profile picture.
 *
 * @param {HTMLElement} grid The avatar grid container.
 * @param {string} equipped The now-equipped emoji, or '' when none.
 */
const applyEquipped = (grid, equipped) => {
    grid.querySelectorAll('[data-avatar-emoji]').forEach(btn => {
        const isnow = btn.dataset.avatarEmoji === equipped && equipped !== '';
        btn.classList.toggle('pg-hub-avatar-equipped', isnow);
        btn.dataset.avatarEquip = isnow ? '' : btn.dataset.avatarEmoji;
        btn.setAttribute('aria-pressed', isnow ? 'true' : 'false');
    });
    updateProfileAvatar(equipped);
};

/**
 * Attaches the equip handler (delegated) to an avatar grid.
 *
 * @param {HTMLElement} grid The avatar grid container.
 */
const wireGrid = grid => {
    grid.addEventListener('click', async event => {
        const btn = event.target.closest('[data-avatar-emoji]');
        if (!btn || btn.classList.contains('pg-hub-avatar-locked')) {
            return;
        }
        try {
            // Ajax.call() returns an array of promises, one per request.
            const result = await Ajax.call([{
                methodname: 'local_playergames_set_avatar',
                args: {emoji: btn.dataset.avatarEquip ?? ''},
            }])[0];
            applyEquipped(grid, result.equipped);
        } catch (error) {
            Notification.exception(error);
        }
    });
};

/**
 * Opens the avatar collection in a modal when the profile avatar is clicked.
 * The collection node is moved into the modal once so equip state persists.
 */
const init = () => {
    const trigger = document.querySelector('[data-avatar-trigger]');
    const source = document.getElementById('pg-avatar-collection');
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
            wireGrid(source.querySelector('.pg-hub-avatar-grid'));
        }
        modal.show();
    });
};

export {init};
