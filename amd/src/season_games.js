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
 * Keeps the switch of each game of a season and its cartridges in step: a game without a cartridge is off.
 *
 * @module     local_playergames/season_games
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Wires the switch and the cartridges of one game.
 *
 * @param {HTMLElement} game The card of the game.
 */
const wire = game => {
    const toggle = game.querySelector('[data-region="pg-game-switch"]');
    const boxes = [...game.querySelectorAll('[data-region="pg-game-cartridge"]')];
    const notice = game.querySelector('[data-region="pg-game-off"]');
    if (!toggle || boxes.length === 0) {
        return;
    }
    const countChecked = () => boxes.filter(box => box.checked).length;
    let checked = countChecked();

    const showNotice = () => {
        checked = countChecked();
        notice.textContent = checked === 0 ? notice.dataset.text : '';
    };

    boxes.forEach(box => box.addEventListener('change', () => {
        const before = checked;
        showNotice();
        if (checked === 0) {
            toggle.checked = false;
        } else if (before === 0) {
            toggle.checked = true;
        }
    }));

    toggle.addEventListener('change', () => {
        if (toggle.checked && checked === 0) {
            boxes.forEach(box => {
                box.checked = true;
            });
            showNotice();
        }
    });

    // The browser may bring back the boxes of an earlier visit, so the notice follows what is on the page.
    showNotice();
    if (checked === 0) {
        toggle.checked = false;
    }
};

/**
 * Wires every game of the page.
 */
export const init = () => {
    document.querySelectorAll('[data-region="pg-season-game"]').forEach(wire);
};
