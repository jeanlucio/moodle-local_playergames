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
 * Loads the Phaser engine shipped with the plugin, once per page, for every Player game.
 *
 * Phaser is injected as a script element resolved by its onload event, not as a static tag
 * queued through $PAGE->requires->js(). A static tag would sit in the page footer and race
 * core_message/message_drawer, which expects its own drawer markup (rendered further down the
 * same footer) to be in the DOM when its require() callback runs. Same approach as
 * filter_mathjaxloader.
 *
 * @module     local_playergames/phaser
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const PHASER_PATH = '/local/playergames/javascript/phaser.min.js';

let loadPromise = null;

/**
 * Injects the Phaser script and waits for it to run.
 *
 * @returns {Promise<void>}
 */
const injectScript = () => new Promise((resolve, reject) => {
    const script = document.createElement('script');
    script.type = 'text/javascript';
    script.onload = resolve;
    script.onerror = reject;
    script.src = `${M.cfg.wwwroot}${PHASER_PATH}`;
    document.getElementsByTagName('head')[0].appendChild(script);
});

/**
 * Injects the script and resolves the AMD module Phaser registers when it executes.
 *
 * @returns {Promise<object>} The Phaser namespace.
 */
const fetchPhaser = async() => {
    await injectScript();
    // The UMD bundle registers itself as the AMD module "Phaser" when it executes.
    const Phaser = await new Promise((resolve, reject) => {
        window.require(['Phaser'], resolve, reject);
    });
    window.Phaser = Phaser;
    return Phaser;
};

/**
 * Loads Phaser and returns it. Calling it again returns the same promise, so several games
 * or scenes on one page never inject the script twice. A failed load is not cached, so the
 * caller can retry.
 *
 * @returns {Promise<object>} The Phaser namespace, also set as window.Phaser.
 */
export const load = async() => {
    if (loadPromise === null) {
        loadPromise = fetchPhaser();
    }
    try {
        return await loadPromise;
    } catch (error) {
        loadPromise = null;
        throw error;
    }
};
