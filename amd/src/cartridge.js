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
 * AMD module for the cartridge management page.
 *
 * Provides:
 *  - Confirmation dialog on forms with data-pg-confirm attribute.
 *  - Export button redirect for the editor tab.
 *
 * @module     local_playergames/cartridge
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    'use strict';

    /**
     * Attaches a submit-time confirmation dialog to every form that carries
     * a data-pg-confirm attribute.
     */
    function attachConfirmDialogs() {
        var forms = document.querySelectorAll('form[data-pg-confirm]');
        forms.forEach(function(form) {
            form.addEventListener('submit', function(e) {
                var message = form.getAttribute('data-pg-confirm');
                if (!window.confirm(message)) {
                    e.preventDefault();
                }
            });
        });
    }

    /**
     * Wires the export button in the editor tab to navigate to the export URL.
     */
    function attachExportButton() {
        var btn = document.getElementById('pg-export-editor');
        if (!btn) {
            return;
        }
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var cartridgeid = btn.getAttribute('data-cartridgeid');
            if (cartridgeid) {
                window.location.href = btn.href
                    || (window.location.pathname
                        + '?action=export_cartridge&cartridgeid=' + cartridgeid);
            }
        });
    }

    /**
     * Wires rename-category buttons to reveal the hidden rename form.
     */
    function attachRenameCategoryButtons() {
        var renameForm = document.getElementById('pg-rename-cat-form');
        var renameIdInput = document.getElementById('pg-rename-cat-id');
        var renameNameInput = document.getElementById('pg-rename-cat-name');
        var cancelBtn = document.getElementById('pg-rename-cat-cancel');

        if (!renameForm) {
            return;
        }

        document.querySelectorAll('.pg-rename-cat').forEach(function(btn) {
            btn.addEventListener('click', function() {
                renameIdInput.value = btn.getAttribute('data-categoryid');
                renameNameInput.value = btn.getAttribute('data-name');
                renameForm.classList.remove('d-none');
                renameNameInput.focus();
            });
        });

        if (cancelBtn) {
            cancelBtn.addEventListener('click', function() {
                renameForm.classList.add('d-none');
                renameNameInput.value = '';
            });
        }
    }

    /**
     * Initialises all cartridge-page behaviours.
     */
    function init() {
        attachConfirmDialogs();
        attachExportButton();
        attachRenameCategoryButtons();
    }

    return {
        init: init
    };
});
