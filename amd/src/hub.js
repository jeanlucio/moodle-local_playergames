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
 * Player Hub AMD module — ranking expand toggle, avatar equip and ranking
 * visibility, the latter two via AJAX so the page does not reload.
 *
 * @module     local_playergames/hub
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';

/**
 * Attaches click handlers to the "show full ranking / top 10" toggle buttons.
 */
const initRankingToggle = () => {
    document.querySelectorAll('.pg-hub-toggle-ranking').forEach(btn => {
        const wrapper = btn.closest('.pg-hub-rank-wrapper');
        if (!wrapper) {
            return;
        }

        const selfpos = parseInt(wrapper.dataset.selfpos ?? '0', 10);
        const extraRows = wrapper.querySelectorAll('.pg-hub-extra-row');
        const showLabel = btn.dataset.showLabel ?? '';
        const hideLabel = btn.dataset.hideLabel ?? '';

        // Hide button when there are no extra rows.
        if (extraRows.length === 0) {
            btn.closest('.text-center')?.classList.add('d-none');
            return;
        }

        // When user is ranked beyond top 10, start expanded so they can see themselves.
        let expanded = selfpos > 10;
        if (expanded) {
            extraRows.forEach(row => row.classList.remove('d-none'));
            btn.textContent = hideLabel;
        }

        btn.addEventListener('click', () => {
            expanded = !expanded;
            extraRows.forEach(row => {
                if (expanded) {
                    row.classList.remove('d-none');
                } else {
                    row.classList.add('d-none');
                }
            });
            btn.textContent = expanded ? hideLabel : showLabel;
        });
    });
};

/**
 * Reflects the newly equipped avatar across the grid and the header preview.
 *
 * @param {HTMLElement} grid The avatar grid container.
 * @param {string} equipped The now-equipped emoji, or '' when none.
 */
const applyEquipped = (grid, equipped) => {
    grid.querySelectorAll('[data-avatar-emoji]').forEach(btn => {
        const isnow = btn.dataset.avatarEmoji === equipped && equipped !== '';
        btn.classList.toggle('btn-primary', isnow);
        btn.classList.toggle('btn-outline-secondary', !isnow);
        btn.dataset.avatarEquip = isnow ? '' : btn.dataset.avatarEmoji;
        btn.setAttribute('aria-pressed', isnow ? 'true' : 'false');
    });
    const current = document.querySelector('.pg-hub-avatar-current');
    if (current) {
        current.textContent = equipped;
    }
};

/**
 * Wires the avatar grid: clicking an unlocked avatar equips it (or unequips
 * when it is the current one) via AJAX, without reloading.
 */
const initAvatars = () => {
    const grid = document.querySelector('.pg-hub-avatar-grid');
    if (!grid) {
        return;
    }
    grid.addEventListener('click', async event => {
        const btn = event.target.closest('[data-avatar-emoji]');
        if (!btn || btn.classList.contains('pg-hub-avatar-locked')) {
            return;
        }
        try {
            const [result] = await Ajax.call([{
                methodname: 'local_playergames_set_avatar',
                args: {emoji: btn.dataset.avatarEquip ?? ''},
            }]);
            applyEquipped(grid, result.equipped);
        } catch (error) {
            Notification.exception(error);
        }
    });
};

/**
 * Wires the "appear in ranking" switch to save via AJAX, reverting on error.
 */
const initRankingVisibility = () => {
    const toggle = document.querySelector('[data-ranking-visibility]');
    if (!toggle) {
        return;
    }
    toggle.addEventListener('change', async() => {
        try {
            await Ajax.call([{
                methodname: 'local_playergames_set_ranking_visibility',
                args: {show: toggle.checked ? 1 : 0},
            }]);
        } catch (error) {
            toggle.checked = !toggle.checked;
            Notification.exception(error);
        }
    });
};

/**
 * Initialises all hub interactions.
 */
const init = () => {
    initRankingToggle();
    initAvatars();
    initRankingVisibility();
};

export {init};
