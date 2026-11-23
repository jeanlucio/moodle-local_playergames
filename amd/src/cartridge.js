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
 * Behaviour of the cartridge management page.
 *
 * Provides:
 *  - Delete confirmation dialog on forms with a data-pg-confirm attribute.
 *  - The AI usage policy of the site, asked for before a form sends anything to the AI.
 *
 * @module     local_playergames/cartridge
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Notification from 'core/notification';
import {getString} from 'core/str';
import {ensure as ensureAiPolicy} from 'local_playergames/ai_policy';

/**
 * Asks for confirmation before sending every form that carries a data-pg-confirm attribute, all of them deletions.
 */
const attachConfirmDialogs = () => {
    document.querySelectorAll('form[data-pg-confirm]').forEach(form => {
        form.addEventListener('submit', e => {
            e.preventDefault();
            // Every one of these forms deletes something, hence the delete dialog and its danger button.
            Notification.deleteCancel(
                // The labels go as promises, which the dialog waits for: a label filled in later would
                // show an English fallback to whoever submits before the strings arrive.
                getString('confirm', 'core'),
                form.dataset.pgConfirm,
                getString('delete', 'core'),
                // Sending the form this way does not fire the submit event again.
                () => form.submit(),
                () => null,
                {triggerElement: e.submitter ?? null}
            );
        });
    });
};

/**
 * Holds every form that carries a data-pg-ai-policy attribute until the AI usage policy of the site is accepted.
 */
const attachAiPolicyGate = () => {
    document.querySelectorAll('form[data-pg-ai-policy]').forEach(form => {
        form.addEventListener('submit', async e => {
            e.preventDefault();
            if (await ensureAiPolicy()) {
                // Sending the form this way does not fire the submit event again.
                form.submit();
            }
        });
    });
};

/**
 * Initialises the behaviour of the cartridge page.
 */
export const init = () => {
    attachConfirmDialogs();
    attachAiPolicyGate();
};
