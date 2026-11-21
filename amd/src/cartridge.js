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
 *  - The rename form of a category, opened from its row.
 *
 * @module     local_playergames/cartridge
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Notification from 'core/notification';
import {getString} from 'core/str';

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
 * Wires the rename buttons of the categories to the hidden rename form.
 */
const attachRenameCategoryButtons = () => {
    const renameForm = document.getElementById('pg-rename-cat-form');
    if (!renameForm) {
        return;
    }
    const renameIdInput = document.getElementById('pg-rename-cat-id');
    const renameNameInput = document.getElementById('pg-rename-cat-name');
    const cancelBtn = document.getElementById('pg-rename-cat-cancel');

    document.querySelectorAll('.pg-rename-cat').forEach(btn => {
        btn.addEventListener('click', () => {
            const {categoryid, name} = btn.dataset;
            renameIdInput.value = categoryid;
            renameNameInput.value = name;
            renameForm.classList.remove('d-none');
            renameNameInput.focus();
        });
    });

    if (cancelBtn) {
        cancelBtn.addEventListener('click', () => {
            renameForm.classList.add('d-none');
            renameNameInput.value = '';
        });
    }
};

/**
 * Initialises the behaviour of the cartridge page.
 */
export const init = () => {
    attachConfirmDialogs();
    attachRenameCategoryButtons();
};
