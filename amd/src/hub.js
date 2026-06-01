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
 * Player Hub AMD module — ranking toggle (top 10 / full list).
 *
 * @module     local_playergames/hub
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Attaches click handlers to all ranking toggle buttons.
 * Each button shows/hides rows with class pg-hub-extra-row.
 */
const init = () => {
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

export {init};
