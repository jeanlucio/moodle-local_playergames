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
 * The PlayerBattle turn loop: ties the rules, the board and the interface together.
 *
 * The rules decide what happens (battle/board_rules and battle/combat_rules), the board shows the
 * pieces moving and the interface shows the numbers; this module only runs the turns in order,
 * asks the questions, and sends the result to the server when the battle ends.
 *
 * @module     local_playergames/battle_game
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import BoardRules from 'local_playergames/battle/board_rules';
import CombatRules from 'local_playergames/battle/combat_rules';
import Prng from 'local_playergames/battle/prng';

const ROWS = 8;
const COLS = 8;
const MAX_CASCADE = 30;
const OPPONENT_THINKS_MS = 700;
const PIECE_KEYS = ['star', 'grimoire', 'orb', 'sword', 'shield', 'potion'];

/**
 * Fills the {$a} placeholders of a Moodle string.
 *
 * @param {string} text The string.
 * @param {string|number|object} [value] The value of {$a}, or an object for {$a->name}.
 * @returns {string}
 */
const format = (text, value) => {
    if (value !== null && typeof value === 'object') {
        return Object.entries(value).reduce(
            (result, [name, item]) => result.replaceAll(`{$a->${name}}`, () => String(item)),
            text
        );
    }
    return text.replaceAll('{$a}', () => String(value));
};

const sleep = milliseconds => new Promise(resolve => window.setTimeout(resolve, milliseconds));

/**
 * Creates the battle.
 *
 * @param {object} options
 * @param {object} options.board The board controller (battle_board).
 * @param {object} options.ui The interface controller (battle_ui).
 * @param {object} options.sound The sound module (battle_sound).
 * @param {object} options.config The page config: questions, strings, posturl, sesskey.
 * @param {boolean} options.reducedMotion Whether to skip the pauses made for the eye.
 * @returns {object} The game controller: start runs the whole battle.
 */
export const createGame = ({board, ui, sound, config, reducedMotion}) => {
    const {questions, strings} = config;
    const rules = CombatRules.defaultConfig();
    const rng = Prng.create(Math.floor(Math.random() * 4294967296));
    const types = CombatRules.PIECE_TYPES;

    let grid = [];
    let state = CombatRules.initialState(rules);
    let queue = [];
    let lastKey = null;
    let messages = [];
    const stats = {total: 0, correct: 0, damage: 0};

    const pieceName = type => strings[`piece_${PIECE_KEYS[type]}`];
    const sideName = side => strings[side];

    /**
     * Collects a message for the screen reader, said together when the move ends.
     *
     * @param {string} message The message.
     */
    const say = message => {
        messages.push(message);
    };

    const flush = () => {
        if (messages.length > 0) {
            ui.announce(messages.join(' '));
            messages = [];
        }
    };

    /**
     * The next question: all of them in a shuffled order, then the order is drawn again, never
     * starting with the question that was just asked.
     *
     * @returns {object} The question.
     */
    const nextQuestion = () => {
        if (queue.length === 0) {
            queue = questions.slice();
            for (let index = queue.length - 1; index > 0; index--) {
                const other = Math.floor(rng() * (index + 1));
                [queue[index], queue[other]] = [queue[other], queue[index]];
            }
            if (queue.length > 1 && queue[queue.length - 1].key === lastKey) {
                [queue[0], queue[queue.length - 1]] = [queue[queue.length - 1], queue[0]];
            }
        }
        const question = queue.pop();
        lastKey = question.key;
        return question;
    };

    /**
     * Shows the damage a hit did, or that the shield stopped it.
     *
     * @param {string} target Who was hit.
     * @param {object} hit {amount, applied, shieldConsumed}.
     */
    const showHit = (target, hit) => {
        if (hit.shieldConsumed) {
            ui.float(target, '0', 'info');
            say(strings.msg_blocked);
            return;
        }
        if (hit.applied <= 0) {
            return;
        }
        ui.float(target, `-${Math.round(hit.applied)}`, 'damage');
        say(format(target === 'opponent' ? strings.msg_hit_opponent : strings.msg_hit_player, Math.round(hit.applied)));
        sound.play('hit');
        if (target === 'opponent') {
            stats.damage += hit.applied;
        }
    };

    /**
     * Gives the board a new arrangement when no swap would make a match.
     */
    const reshuffle = async() => {
        BoardRules.shuffleUntilValid(grid, ROWS, COLS, rng);
        say(strings.msg_shuffled);
        await board.refresh(grid);
    };

    /**
     * Asks the server whether the option the player picked is the right one. The server owns the
     * answer key, so the page never holds it.
     *
     * @param {string} key The question's key.
     * @param {number} choice Zero-based option picked.
     * @returns {Promise<object>} {correct, correctindex, feedback}.
     */
    const judge = async(key, choice) => {
        const response = await fetch(config.posturl, {
            method: 'POST',
            body: new URLSearchParams({
                sesskey: config.sesskey,
                action: 'answer_question',
                key,
                choice: String(choice),
            }),
        });
        const data = await response.json();
        if (!data.success) {
            throw new Error('The server did not take the answer.');
        }
        return data;
    };

    /**
     * Resolves a question for a side and applies what it costs or gains.
     *
     * @param {string} side Who faced the question.
     */
    const runQuestion = async side => {
        let correct = true;
        if (side === 'player') {
            const question = nextQuestion();
            correct = await ui.askQuestion(question, judge, strings.answer_error);
            board.focus();
            stats.total++;
            if (correct) {
                stats.correct++;
            }
        }
        const outcome = CombatRules.questionOutcome(side, correct, state, rules);
        state = outcome.state;
        showHit(outcome.target, outcome);
        if (side === 'player') {
            say(correct ?
                format(strings.msg_correct, Math.round(outcome.amount)) :
                format(strings.msg_wrong, Math.round(outcome.amount)));
        } else {
            say(format(strings.msg_opponent_crit, Math.round(outcome.amount)));
        }
        ui.render(state);
    };

    /**
     * Takes the pieces off the grid.
     *
     * @param {Array<{row: number, col: number}>} cells The cells to empty.
     * @returns {Array<number>} The type of each piece taken.
     */
    const takePieces = cells => cells.map(cell => {
        const type = grid[cell.row][cell.col];
        grid[cell.row][cell.col] = null;
        return type;
    });

    /**
     * Lets the board settle after the battle was decided: the pieces fall and any match they make
     * is cleared, with no effect on either side, so the board behind the end screen is not left
     * with gaps or a match half played.
     */
    const settleBoard = async() => {
        for (let round = 0; round < MAX_CASCADE; round++) {
            await board.gravity(BoardRules.applyGravityToGrid(grid, ROWS, COLS, rng, types));
            const toDestroy = [];
            BoardRules.checkHorizontal(grid, ROWS, COLS, toDestroy, []);
            BoardRules.checkVertical(grid, ROWS, COLS, toDestroy, []);
            if (toDestroy.length === 0) {
                return;
            }
            takePieces(toDestroy);
            await board.clear(toDestroy);
        }
    };

    /**
     * Plays out a swap: the match, its effects and the cascade that follows.
     *
     * @param {string} side Who moved.
     * @param {object} move {r1, c1, r2, c2}.
     * @returns {Promise<?string>} 'victory', 'defeat' or null.
     */
    const resolveMove = async(side, move) => {
        sound.play('swap');
        await board.swap(move);
        BoardRules.swapInGrid(grid, move.r1, move.c1, move.r2, move.c2);

        for (let round = 0; round < MAX_CASCADE; round++) {
            const toDestroy = [];
            const groups = [];
            BoardRules.checkHorizontal(grid, ROWS, COLS, toDestroy, groups);
            BoardRules.checkVertical(grid, ROWS, COLS, toDestroy, groups);
            if (toDestroy.length === 0) {
                break;
            }
            const destroyedTypes = takePieces(toDestroy);

            const effects = CombatRules.resolveMatchEffects({
                side,
                destroyedTypes,
                matchGroups: groups.map(group => ({type: group.type, size: group.cells.length})),
                state,
                config: rules,
            });
            state = effects.state;
            sound.play('match');
            await board.clear(toDestroy);
            showHit(side === 'player' ? 'opponent' : 'player', effects.hit);
            if (effects.healGained > 0) {
                ui.float(side, `+${Math.round(effects.healGained)}`, 'heal');
                say(format(strings.msg_heal, {who: sideName(side), n: Math.round(effects.healGained)}));
            }
            ui.render(state);

            let result = CombatRules.result(state);
            if (result === null && effects.questionTriggered) {
                await runQuestion(side);
                result = CombatRules.result(state);
            }
            if (result !== null) {
                await settleBoard();
                return result;
            }
            await board.gravity(BoardRules.applyGravityToGrid(grid, ROWS, COLS, rng, types));
        }
        if (!BoardRules.hasAvailableMove(grid, ROWS, COLS)) {
            await reshuffle();
        }
        return null;
    };

    /**
     * Waits for a swap that makes a match; one that does not slides and comes back.
     *
     * @returns {Promise<object>} The move.
     */
    const playerMove = async() => {
        for (;;) {
            const move = await board.nextMove();
            sound.resume();
            if (BoardRules.evaluateSwap(grid, ROWS, COLS, move.r1, move.c1, move.r2, move.c2) !== null) {
                return move;
            }
            sound.play('swap');
            await board.swap(move);
            await board.swap({r1: move.r1, c1: move.c1, r2: move.r2, c2: move.c2});
            say(strings.msg_no_match);
            flush();
        }
    };

    const opponentMove = async() => {
        await sleep(reducedMotion ? 0 : OPPONENT_THINKS_MS);
        return BoardRules.chooseMove(grid, ROWS, COLS);
    };

    /**
     * One turn of a side: the poison ticks, then it swaps.
     *
     * @param {string} side Whose turn.
     * @returns {Promise<?string>} 'victory', 'defeat' or null.
     */
    const playTurn = async side => {
        ui.setTurn(side);
        say(side === 'player' ? strings.turn_player : strings.turn_opponent);

        const start = CombatRules.startTurn(side, state, rules);
        state = start.state;
        if (start.poisonTicked) {
            ui.float(side, `-${Math.round(start.damage)}`, 'damage');
            say(format(strings.msg_poison, {n: Math.round(start.damage), who: sideName(side)}));
            sound.play('hit');
            if (side === 'opponent') {
                stats.damage += start.damage;
            }
            ui.render(state);
            const result = CombatRules.result(state);
            if (result !== null) {
                return result;
            }
        }
        if (!BoardRules.hasAvailableMove(grid, ROWS, COLS)) {
            await reshuffle();
        }
        flush();

        const move = side === 'player' ? await playerMove() : await opponentMove();
        const result = await resolveMove(side, move);
        flush();
        return result;
    };

    /**
     * Sends the result to the server and shows what it answered.
     *
     * @param {boolean} victory Whether the player won.
     */
    const save = async victory => {
        try {
            const response = await fetch(config.posturl, {
                method: 'POST',
                body: new URLSearchParams({
                    sesskey: config.sesskey,
                    action: 'submit_result',
                    victory: victory ? '1' : '0',
                    bosshpdealt: String(Math.round(stats.damage)),
                }),
            });
            const data = await response.json();
            if (!data.success) {
                throw new Error('The server did not accept the result.');
            }
            // The server records the victory only when the battle looks played, whatever was claimed.
            const won = victory && data.victory;
            ui.setSaved(won ? format(strings.xp_earned, data.xpawarded) : '', data.remaining > 0 || !won);
        } catch (error) {
            window.console.error(error);
            ui.setSaveError(strings.save_error, () => save(victory));
        }
    };

    return {
        /**
         * Accessible name of a cell of the board.
         *
         * @param {number} row Row.
         * @param {number} col Column.
         * @param {number} type Type of the piece in it.
         * @returns {string}
         */
        describeCell(row, col, type) {
            return format(strings.msg_cell, {row: row + 1, col: col + 1, piece: pieceName(type)});
        },

        /**
         * Tells a piece has been selected with the keyboard, and what to do next.
         *
         * @param {number} row Row.
         * @param {number} col Column.
         * @returns {string}
         */
        describeSelection(row, col) {
            return format(strings.msg_selected, pieceName(grid[row][col]));
        },

        /**
         * Runs the battle from the first turn to the end.
         */
        async start() {
            grid = BoardRules.generateGrid(ROWS, COLS, rng, types);
            if (!BoardRules.hasAvailableMove(grid, ROWS, COLS)) {
                BoardRules.shuffleUntilValid(grid, ROWS, COLS, rng);
            }
            await board.refresh(grid);
            ui.ready();
            ui.render(state);
            board.focus();

            let side = 'player';
            let result = null;
            while (result === null) {
                result = await playTurn(side);
                side = side === 'player' ? 'opponent' : 'player';
            }
            const victory = result === 'victory';
            ui.setTurn('none');
            ui.showEnd(victory, format(strings.summary, {correct: stats.correct, total: stats.total}));
            await save(victory);
        },
    };
};
