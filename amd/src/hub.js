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

import * as AvatarModal from 'local_playergames/avatar_modal';
import * as RankingToggle from 'local_playergames/ranking_toggle';

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
 * Initialises all hub interactions.
 */
const init = () => {
    initRankingToggle();
    AvatarModal.init();
    RankingToggle.wire('[data-ranking-visibility]', 'local_playergames_set_ranking_visibility');
    RankingToggle.wire(
        '[data-learning-ranking-visibility]',
        'local_playergames_set_learning_ranking_visibility'
    );
};

export {init};
