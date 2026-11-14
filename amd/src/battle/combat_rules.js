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
 * Pure combat rules for PlayerBattle — decides what happens to HP, meters and mana, never how
 * to render it.
 *
 * Every function takes plain data and returns plain data. None touches the DOM or
 * AJAX, and none mutates the state it is given: each returns a changed copy. The two sides,
 * 'player' and 'opponent', are symmetric — the same rule runs for whoever made the match —
 * and their fields share one naming scheme, `${side}Hp`, `${side}MaxHp`, `${side}Multiplier`,
 * `${side}ShieldMeter`, `${side}ShieldReady`, `${side}PoisonMeter`, `${side}PoisonRounds` and
 * `${side}Mana`.
 *
 * The six pieces:
 *  - Star: +0.1 to the side's damage multiplier, spent when its question lands a critical hit
 *    and lost when the question is missed.
 *  - Grimoire: fills a poison meter; when full, the other side is poisoned for three turns.
 *  - Orb: fills the mana bar; when full, the side faces a question.
 *  - Sword: direct damage, with a bonus for matches of four and five.
 *  - Shield: fills a shield meter; when full, the next damage the side takes is cancelled.
 *  - Potion: heals.
 *
 * UMD-wrapped so the same file loads as an AMD module in the browser and as a plain CommonJS
 * module in Node, where tests/js/battle/combat_rules.test.js runs it with `node --test`.
 *
 * @module     local_playergames/battle/combat_rules
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/* global module */
(function(define) {
    define([], () => {
        'use strict';

        // Piece types, as the board stores them. The number in play is PIECE_TYPES.
        const PIECES = {STAR: 0, GRIMOIRE: 1, ORB: 2, SWORD: 3, SHIELD: 4, POTION: 5};
        const PIECE_TYPES = 6;

        // A meter or the mana bar triggers its effect when it reaches this value.
        const METER_FULL = 100;

        // What one destroyed piece adds to its meter.
        const GAIN_PER_PIECE = {mana: 20, shield: 10, poison: 10, multiplier: 0.1};

        // A correct answer hits this many times harder than a plain hit.
        const CRITICAL_MULTIPLIER = 3;

        // Turns of poison one full Grimoire meter causes.
        const POISON_ROUNDS = 3;

        // A Potion heals this share of the side's damage unit.
        const POTION_HEAL = 0.25;

        const OTHER = {player: 'opponent', opponent: 'player'};

        /**
         * The numbers a battle is played with.
         *
         * Chosen by simulating battles (a player who picks the best move, against this
         * opponent): with every answer right the player wins about 99% of the time, with
         * two in three right about 77%, and guessing blindly among five options about 20%.
         * A battle lasts roughly 18 to 29 turns and asks 5 to 9 questions.
         *
         * @return {{playerHp: number, opponentHp: number, playerDamage: number, opponentDamage: number}}
         *  Starting HP of each side and each side's damage unit: what a match of three Swords hits for.
         */
        function defaultConfig() {
            return {playerHp: 200, opponentHp: 200, playerDamage: 10, opponentDamage: 8};
        }

        /**
         * The state a battle starts in.
         *
         * @param {object} config See defaultConfig().
         * @return {object} Full HP, empty meters, no poison, multipliers at 1.
         */
        function initialState(config) {
            const side = (hp) => ({
                Hp: hp, MaxHp: hp, Multiplier: 1, ShieldMeter: 0, ShieldReady: false,
                PoisonMeter: 0, PoisonRounds: 0, Mana: 0,
            });
            const state = {};
            [['player', config.playerHp], ['opponent', config.opponentHp]].forEach(([name, hp]) => {
                Object.entries(side(hp)).forEach(([field, value]) => {
                    state[`${name}${field}`] = value;
                });
            });
            return state;
        }

        /**
         * The damage unit of a side.
         *
         * @param {string} side 'player' or 'opponent'.
         * @param {object} config See defaultConfig().
         * @return {number}
         */
        function unit(side, config) {
            return config[`${side}Damage`];
        }

        /**
         * Damage multiplier for a match of the given size: 1 for three, 1.5 for four, 2 for five.
         *
         * @param {number} size Pieces in the match.
         * @return {number}
         */
        function comboMultiplier(size) {
            return 1 + (0.5 * Math.max(0, size - 3));
        }

        /**
         * Hits a side, which a ready shield cancels completely.
         *
         * @param {object} state Current state (not changed).
         * @param {string} target 'player' or 'opponent'.
         * @param {number} amount Damage before the shield.
         * @return {{state: object, applied: number, shieldConsumed: boolean}} applied is what HP lost.
         */
        function applyHit(state, target, amount) {
            const next = Object.assign({}, state);
            const shieldConsumed = next[`${target}ShieldReady`];
            const applied = shieldConsumed ? 0 : Math.min(amount, next[`${target}Hp`]);
            next[`${target}Hp`] -= applied;
            if (shieldConsumed) {
                next[`${target}ShieldReady`] = false;
            }
            return {state: next, applied, shieldConsumed};
        }

        /**
         * Settles the meters that crossed their limit after a match.
         *
         * @param {object} state Being built (changed in place).
         * @param {string} side The side that made the match.
         */
        function settleMeters(state, side) {
            if (state[`${side}ShieldMeter`] >= METER_FULL) {
                state[`${side}ShieldMeter`] = 0;
                state[`${side}ShieldReady`] = true;
            }
            if (state[`${side}PoisonMeter`] >= METER_FULL) {
                state[`${side}PoisonMeter`] -= METER_FULL;
                state[`${OTHER[side]}PoisonRounds`] += POISON_ROUNDS;
            }
        }

        /**
         * Resolves one match: what the destroyed pieces give the side that made it, the Sword
         * damage to the other side, and whether the mana bar filled.
         *
         * @param {object} params
         * @param {string} params.side 'player' or 'opponent': who made the match.
         * @param {Array<number>} params.destroyedTypes Type of every piece destroyed.
         * @param {Array<{type: number, size: number}>} params.matchGroups The matches made: only their
         *  type and size count, never which pieces.
         * @param {object} params.state Current state (not changed).
         * @param {object} params.config See defaultConfig().
         * @return {{state: object, hit: {amount: number, applied: number, shieldConsumed: boolean},
         *  healGained: number, questionTriggered: boolean}} hit is the Sword damage to the other
         *  side, after its shield. questionTriggered means the side's mana filled and was spent.
         */
        function resolveMatchEffects({side, destroyedTypes, matchGroups, state, config}) {
            let next = Object.assign({}, state);
            let healGained = 0;

            destroyedTypes.forEach(type => {
                if (type === PIECES.POTION) {
                    const healed = Math.min(
                        next[`${side}MaxHp`] - next[`${side}Hp`],
                        unit(side, config) * POTION_HEAL
                    );
                    next[`${side}Hp`] += healed;
                    healGained += healed;
                } else if (type === PIECES.STAR) {
                    next[`${side}Multiplier`] += GAIN_PER_PIECE.multiplier;
                } else if (type === PIECES.SHIELD) {
                    next[`${side}ShieldMeter`] += GAIN_PER_PIECE.shield;
                } else if (type === PIECES.GRIMOIRE) {
                    next[`${side}PoisonMeter`] += GAIN_PER_PIECE.poison;
                } else if (type === PIECES.ORB) {
                    next[`${side}Mana`] += GAIN_PER_PIECE.mana;
                }
            });
            next[`${side}Multiplier`] = Math.round(next[`${side}Multiplier`] * 10) / 10;
            settleMeters(next, side);

            // Sword damage is worked out per match, not per piece, so the size of the match
            // drives the bonus: a match of three is exactly one damage unit.
            let sword = 0;
            matchGroups.forEach(group => {
                if (group.type === PIECES.SWORD) {
                    sword += unit(side, config) * comboMultiplier(group.size);
                }
            });
            const amount = Math.round(sword * next[`${side}Multiplier`]);
            const hit = amount > 0 ?
                applyHit(next, OTHER[side], amount) :
                {state: next, applied: 0, shieldConsumed: false};
            next = hit.state;

            let questionTriggered = false;
            if (next[`${side}Mana`] >= METER_FULL) {
                next[`${side}Mana`] -= METER_FULL;
                questionTriggered = true;
            }

            return {
                state: next,
                hit: {amount, applied: hit.applied, shieldConsumed: hit.shieldConsumed},
                healGained,
                questionTriggered,
            };
        }

        /**
         * Resolves a question answered by a side. A correct answer lands a critical hit on the
         * other side, spending the multiplier. A wrong one costs the side a plain hit from the
         * other, and the multiplier.
         *
         * @param {string} side 'player' or 'opponent': who faced the question.
         * @param {boolean} correct Whether it was answered correctly.
         * @param {object} state Current state (not changed).
         * @param {object} config See defaultConfig().
         * @return {{state: object, target: string, amount: number, applied: number, shieldConsumed: boolean}}
         *  target is the side that was hit.
         */
        function questionOutcome(side, correct, state, config) {
            const target = correct ? OTHER[side] : side;
            const amount = correct ?
                Math.round(unit(side, config) * CRITICAL_MULTIPLIER * state[`${side}Multiplier`]) :
                unit(OTHER[side], config);
            const hit = applyHit(state, target, amount);
            hit.state[`${side}Multiplier`] = 1;
            return {state: hit.state, target, amount, applied: hit.applied, shieldConsumed: hit.shieldConsumed};
        }

        /**
         * Starts a side's turn: the poison on it ticks, for the damage unit of whoever poisoned it.
         * Poison ignores the shield.
         *
         * @param {string} side 'player' or 'opponent': whose turn begins.
         * @param {object} state Current state (not changed).
         * @param {object} config See defaultConfig().
         * @return {{state: object, poisonTicked: boolean, damage: number}}
         */
        function startTurn(side, state, config) {
            const next = Object.assign({}, state);
            if (next[`${side}PoisonRounds`] <= 0) {
                return {state: next, poisonTicked: false, damage: 0};
            }
            const damage = Math.min(unit(OTHER[side], config), next[`${side}Hp`]);
            next[`${side}Hp`] -= damage;
            next[`${side}PoisonRounds`] -= 1;
            return {state: next, poisonTicked: true, damage};
        }

        /**
         * How the battle stands.
         *
         * @param {object} state Current state.
         * @return {?string} 'victory', 'defeat', or null while both sides stand.
         */
        function result(state) {
            if (state.opponentHp <= 0) {
                return 'victory';
            }
            return state.playerHp <= 0 ? 'defeat' : null;
        }

        return {
            PIECES,
            PIECE_TYPES,
            CRITICAL_MULTIPLIER,
            defaultConfig,
            initialState,
            comboMultiplier,
            applyHit,
            resolveMatchEffects,
            questionOutcome,
            startTurn,
            result,
        };
    });
}(typeof define === 'function' && define.amd ? define : (deps, factory) => {
    module.exports = factory();
}));
