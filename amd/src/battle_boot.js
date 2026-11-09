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
 * Starts PlayerBattle on its page: reads the config the server put in the markup, builds the
 * board, the interface and the game, and runs the battle.
 *
 * @module     local_playergames/battle_boot
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {createBoard} from 'local_playergames/battle_board';
import {createGame} from 'local_playergames/battle_game';
import {createUi} from 'local_playergames/battle_ui';
import * as sound from 'local_playergames/battle_sound';

const ROWS = 8;
const COLS = 8;

/**
 * Starts the battle.
 */
export const init = async() => {
    const root = document.getElementById('pg-battle-game');
    if (!root) {
        return;
    }
    const config = JSON.parse(root.dataset.config);
    const ui = createUi(root);
    const holder = root.querySelector('#pg-battle-board');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    ui.setSound(sound.init(config.pixurl));
    ui.onSound(flag => {
        sound.setEnabled(flag);
        ui.setSound(flag);
    });

    try {
        let game = null;
        const board = await createBoard({
            parent: holder,
            pixUrl: config.pixurl,
            rows: ROWS,
            cols: COLS,
            label: root.dataset.boardLabel,
            reducedMotion,
            onCursor: ({row, col, selected}) => {
                ui.announce(selected ? game.describeSelection(row, col) : game.describeCell(row, col));
            },
        });
        game = createGame({board, ui, sound, config, reducedMotion});
        await game.start();
    } catch (error) {
        window.console.error('PlayerBattle failed to start:', error);
        holder.textContent = config.strings.start_error;
    }
};
