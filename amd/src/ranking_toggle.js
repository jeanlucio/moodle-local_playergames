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
 * Shared "appear in ranking" toggle wiring, used by the Player Hub page and
 * block_playergames's sidebar widget.
 *
 * @module     local_playergames/ranking_toggle
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';

/**
 * Wires an "appear in ranking" switch to save via AJAX, reverting on error.
 *
 * @param {string} selector Attribute selector for the checkbox.
 * @param {string} methodname Web service method name to call.
 * @param {boolean} reload Whether to reload the page on success. The Hub page
 *     has a ranking list that needs a reload to reflect the change; a widget
 *     with no list of its own (block_playergames) should pass false.
 */
const wire = (selector, methodname, reload = true) => {
    const toggle = document.querySelector(selector);
    if (!toggle) {
        return;
    }
    toggle.addEventListener('change', async() => {
        try {
            await Ajax.call([{
                methodname,
                args: {show: toggle.checked ? 1 : 0},
            }])[0];
            if (reload) {
                // Reload so the ranking list reflects the user appearing/leaving.
                window.location.reload();
            }
        } catch (error) {
            toggle.checked = !toggle.checked;
            Notification.exception(error);
        }
    });
};

export {wire};
