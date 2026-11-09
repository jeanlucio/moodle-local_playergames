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
 * Sound for PlayerBattle: the effects and the background music, off until the player turns them on.
 *
 * The choice is kept in the browser's local storage, a convenience for whoever plays on that
 * browser and not something the server needs. Audio is created the first time it is needed, so
 * a player who never turns sound on never downloads it.
 *
 * @module     local_playergames/battle_sound
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const STORAGE_KEY = 'local_playergames_battle_sound';
const MUSIC_VOLUME = 0.35;

let baseUrl = '';
let enabled = false;
let music = null;
const effects = {};

/**
 * Reads the stored choice, tolerating a browser that blocks storage.
 *
 * @returns {boolean}
 */
const readStored = () => {
    try {
        return window.localStorage.getItem(STORAGE_KEY) === '1';
    } catch {
        return false;
    }
};

/**
 * Remembers the choice, tolerating a browser that blocks storage.
 *
 * @param {boolean} flag Whether sound is on.
 */
const writeStored = flag => {
    try {
        window.localStorage.setItem(STORAGE_KEY, flag ? '1' : '0');
    } catch {
        // Nothing to do: the choice just is not remembered.
    }
};

/**
 * Plays an audio element, ignoring the browser refusing to before the first user gesture.
 *
 * @param {HTMLAudioElement} audio The element.
 */
const tryPlay = async audio => {
    try {
        await audio.play();
    } catch {
        // Autoplay was blocked; resume() tries again on the next gesture.
    }
};

/**
 * Prepares the module.
 *
 * @param {string} pixUrl Base URL of the battle files, with a trailing slash.
 * @returns {boolean} Whether sound starts on.
 */
export const init = pixUrl => {
    baseUrl = pixUrl;
    enabled = readStored();
    return enabled;
};

/**
 * Starts the music if sound is on and it is not playing. Called on the first gesture of the
 * player, when the browser lets sound start.
 */
export const resume = () => {
    if (!enabled) {
        return;
    }
    if (music === null) {
        music = new Audio(`${baseUrl}music.mp3`);
        music.loop = true;
        music.volume = MUSIC_VOLUME;
    }
    if (music.paused) {
        tryPlay(music);
    }
};

/**
 * Turns sound on or off.
 *
 * @param {boolean} flag The new state.
 */
export const setEnabled = flag => {
    enabled = flag;
    writeStored(flag);
    if (flag) {
        resume();
    } else if (music !== null) {
        music.pause();
    }
};

/**
 * Plays a short effect when sound is on.
 *
 * @param {string} name One of swap, match or hit.
 */
export const play = name => {
    if (!enabled) {
        return;
    }
    if (!effects[name]) {
        effects[name] = new Audio(`${baseUrl}${name}.mp3`);
    }
    effects[name].currentTime = 0;
    tryPlay(effects[name]);
};
