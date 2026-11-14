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
 * The PlayerBattle interface around the board: the status of both sides, the numbers that float up
 * when something hits, the question and the end of the battle.
 *
 * It is plain HTML filled in from the state it is given, so it can be read by a screen reader and
 * operated with a keyboard. The question and the end are dialogs: while one is open the rest of the
 * game is inert and Tab stays inside it.
 *
 * @module     local_playergames/battle_ui
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const LETTERS = 'ABCDE';
const FLOAT_MS = 1200;
const LOW_HP = 0.3;
const FOCUSABLE = 'button:not([disabled]):not([hidden]), a[href]';

/**
 * Creates the interface over the battle markup.
 *
 * @param {HTMLElement} root The battle container, rendered by the play_battle template.
 * @returns {object} The interface controller.
 */
export const createUi = root => {
    const cards = {
        player: root.querySelector('[data-side="player"]'),
        opponent: root.querySelector('[data-side="opponent"]'),
    };
    const live = root.querySelector('[data-region="live"]');
    const questionDialog = root.querySelector('[data-region="question"]');
    const endDialog = root.querySelector('[data-region="end"]');
    const soundButton = root.querySelector('[data-action="sound"]');

    /**
     * Makes everything but the dialogs inert, or active again.
     *
     * @param {boolean} flag Whether the rest of the game is inert.
     */
    const setInert = flag => {
        Array.from(root.children)
            .filter(element => element.getAttribute('role') !== 'dialog' && !element.hasAttribute('aria-live'))
            .forEach(element => {
                element.inert = flag;
            });
    };

    /**
     * Keeps Tab inside a dialog.
     *
     * @param {HTMLElement} dialog The dialog.
     * @param {KeyboardEvent} event The key event.
     */
    const trapTab = (dialog, event) => {
        if (event.key !== 'Tab') {
            return;
        }
        const items = Array.from(dialog.querySelectorAll(FOCUSABLE));
        if (items.length === 0) {
            return;
        }
        const first = items[0];
        const last = items[items.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    };
    questionDialog.addEventListener('keydown', event => trapTab(questionDialog, event));
    endDialog.addEventListener('keydown', event => trapTab(endDialog, event));

    /**
     * Fills a bar and the number beside it.
     *
     * @param {HTMLElement} card The side's card.
     * @param {string} name Bar name: hp, mana, shield or poison.
     * @param {number} value Current value.
     * @param {number} max Value of a full bar.
     * @param {string} text What to write beside the bar.
     */
    const setBar = (card, name, value, max, text) => {
        const percent = Math.max(0, Math.min(100, (value / max) * 100));
        const bar = card.querySelector(`[data-bar="${name}"]`);
        bar.firstElementChild.style.width = `${percent}%`;
        bar.setAttribute('aria-valuenow', String(Math.round(percent)));
        card.querySelector(`[data-field="${name}"]`).textContent = text;
    };

    return {
        /**
         * Shows the state of both sides.
         *
         * @param {object} state The combat state.
         */
        render(state) {
            ['player', 'opponent'].forEach(side => {
                const card = cards[side];
                const hp = Math.max(0, Math.ceil(state[`${side}Hp`]));
                const maxHp = state[`${side}MaxHp`];
                setBar(card, 'hp', hp, maxHp, `${hp}/${maxHp}`);
                const hpBar = card.querySelector('[data-bar="hp"]').firstElementChild;
                hpBar.classList.toggle('bg-danger', hp / maxHp <= LOW_HP);
                hpBar.classList.toggle('bg-success', hp / maxHp > LOW_HP);
                setBar(card, 'mana', state[`${side}Mana`], 100, `${Math.round(state[`${side}Mana`])}`);
                setBar(card, 'shield', state[`${side}ShieldMeter`], 100, `${Math.round(state[`${side}ShieldMeter`])}`);
                const rounds = state[`${side}PoisonRounds`];
                const meter = Math.round(state[`${side}PoisonMeter`]);
                setBar(card, 'poison', state[`${side}PoisonMeter`], 100, rounds > 0 ? `${meter} (${rounds})` : `${meter}`);
                card.querySelector('[data-field="multiplier"]').textContent = `x${state[`${side}Multiplier`].toFixed(1)}`;
                card.querySelector('[data-badge="shield"]').hidden = !state[`${side}ShieldReady`];
            });
        },

        /**
         * Marks whose turn it is.
         *
         * @param {string} side 'player' or 'opponent'.
         */
        setTurn(side) {
            Object.entries(cards).forEach(([name, card]) => card.classList.toggle('is-active', name === side));
        },

        /**
         * Floats a short text up from a side's card.
         *
         * @param {string} side 'player' or 'opponent'.
         * @param {string} text What to show.
         * @param {string} kind damage, heal or info.
         */
        float(side, text, kind) {
            const element = document.createElement('span');
            element.className = `pg-battle-float is-${kind}`;
            element.textContent = text;
            cards[side].querySelector('.pg-battle-floats').appendChild(element);
            window.setTimeout(() => element.remove(), FLOAT_MS);
        },

        /**
         * Says something to screen readers.
         *
         * @param {string} text The message.
         */
        announce(text) {
            live.textContent = text;
        },

        /**
         * Shows a question and waits for the answer and for the player to continue.
         *
         * The page does not know which option is right: the server judges the choice and says so.
         *
         * @param {object} question {key, questiontext, answers: {text}[]}.
         * @param {function(string, number): Promise<object>} judge Asks the server about a choice, and
         *     resolves with {correct, correctindex, feedback}.
         * @param {string} errorMessage What to say when the server could not be asked.
         * @returns {Promise<boolean>} Whether the answer was right.
         */
        askQuestion(question, judge, errorMessage) {
            return new Promise(resolve => {
                const feedback = questionDialog.querySelector('[data-field="feedback"]');
                const next = questionDialog.querySelector('[data-action="continue"]');
                const holder = questionDialog.querySelector('[data-field="answers"]');
                questionDialog.querySelector('[data-field="questiontext"]').innerHTML = question.questiontext;
                feedback.hidden = true;
                feedback.innerHTML = '';
                next.hidden = true;
                holder.textContent = '';

                let answered = false;
                const buttons = question.answers.map((answer, index) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'pg-battle-answer';
                    const letter = document.createElement('span');
                    letter.className = 'pg-battle-answer-letter';
                    letter.textContent = LETTERS[index] || String(index + 1);
                    const text = document.createElement('span');
                    text.innerHTML = answer.text;
                    button.append(letter, text);
                    holder.appendChild(button);
                    return button;
                });

                const choose = async chosen => {
                    if (answered) {
                        return;
                    }
                    answered = true;
                    buttons.forEach(button => {
                        button.disabled = true;
                    });
                    let verdict;
                    try {
                        verdict = await judge(question.key, chosen);
                    } catch (error) {
                        window.console.error(error);
                        // The answer was not taken: let the player pick again.
                        answered = false;
                        buttons.forEach(button => {
                            button.disabled = false;
                        });
                        feedback.textContent = errorMessage;
                        feedback.hidden = false;
                        return;
                    }
                    buttons.forEach((button, index) => {
                        button.classList.toggle('is-correct', index === verdict.correctindex);
                        button.classList.toggle('is-wrong', index === chosen && !verdict.correct);
                    });
                    feedback.textContent = '';
                    feedback.hidden = true;
                    if (verdict.feedback) {
                        feedback.innerHTML = verdict.feedback;
                        feedback.hidden = false;
                    }
                    next.hidden = false;
                    next.focus();
                    next.onclick = () => {
                        questionDialog.hidden = true;
                        questionDialog.onkeydown = null;
                        setInert(false);
                        resolve(verdict.correct);
                    };
                };
                buttons.forEach((button, index) => button.addEventListener('click', () => choose(index)));
                questionDialog.onkeydown = event => {
                    const index = LETTERS.indexOf(event.key.toUpperCase());
                    const byNumber = Number(event.key) - 1;
                    const target = index >= 0 && event.key.length === 1 ? index : byNumber;
                    if (Number.isInteger(target) && target >= 0 && target < buttons.length) {
                        choose(target);
                    }
                };

                setInert(true);
                questionDialog.hidden = false;
                buttons[0].focus();
            });
        },

        /**
         * Shows the end of the battle, before the result is saved.
         *
         * @param {boolean} victory Whether the player won.
         * @param {string} summary The questions line.
         */
        showEnd(victory, summary) {
            const title = endDialog.querySelector('[data-field="endtitle"]');
            title.textContent = victory ? title.dataset.victory : title.dataset.defeat;
            title.tabIndex = -1;
            endDialog.querySelector('[data-field="endsummary"]').textContent = summary;
            endDialog.querySelector('[data-field="endxp"]').textContent = '';
            endDialog.querySelector('[data-field="enderror"]').hidden = true;
            endDialog.querySelector('[data-action="retry"]').hidden = true;
            endDialog.querySelector('[data-action="again"]').hidden = true;
            setInert(true);
            endDialog.hidden = false;
            title.focus();
        },

        /**
         * Shows the result of saving: the XP, and whether the player may play again.
         *
         * @param {string} xpText The XP line, or an empty string.
         * @param {boolean} canPlayAgain Whether another battle may be played today.
         */
        setSaved(xpText, canPlayAgain) {
            endDialog.querySelector('[data-field="endxp"]').textContent = xpText;
            endDialog.querySelector('[data-field="enderror"]').hidden = true;
            endDialog.querySelector('[data-action="retry"]').hidden = true;
            const again = endDialog.querySelector('[data-action="again"]');
            again.hidden = !canPlayAgain;
            again.onclick = () => window.location.reload();
        },

        /**
         * Shows that saving failed, with a button to try again.
         *
         * @param {string} message What went wrong.
         * @param {Function} retry Called when the player asks to try again.
         */
        setSaveError(message, retry) {
            const error = endDialog.querySelector('[data-field="enderror"]');
            error.textContent = message;
            error.hidden = false;
            const button = endDialog.querySelector('[data-action="retry"]');
            button.hidden = false;
            button.onclick = () => {
                button.hidden = true;
                error.hidden = true;
                retry();
            };
            button.focus();
        },

        /**
         * Reflects the sound state on its button.
         *
         * @param {boolean} flag Whether sound is on.
         */
        setSound(flag) {
            soundButton.setAttribute('aria-pressed', flag ? 'true' : 'false');
            const icon = soundButton.querySelector('[data-region="soundicon"]');
            icon.classList.toggle('fa-volume-up', flag);
            icon.classList.toggle('fa-volume-off', !flag);
        },

        /**
         * Calls a function when the sound button is pressed.
         *
         * @param {Function} handler Receives the new state.
         */
        onSound(handler) {
            soundButton.addEventListener('click', () => {
                handler(soundButton.getAttribute('aria-pressed') !== 'true');
            });
        },

        /**
         * Removes the loading text once the board is up.
         */
        ready() {
            const loading = root.querySelector('[data-region="loading"]');
            if (loading) {
                loading.remove();
            }
        },
    };
};
