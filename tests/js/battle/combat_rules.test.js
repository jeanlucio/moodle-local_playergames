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
 * Headless tests for the combat rules, run via `node --test` with no RequireJS or
 * browser.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

'use strict';

const {test} = require('node:test');
const assert = require('node:assert/strict');
const Combat = require('../../../amd/src/battle/combat_rules.js');

const {STAR, GRIMOIRE, ORB, SWORD, SHIELD, POTION} = Combat.PIECES;
const config = {playerHp: 200, opponentHp: 200, playerDamage: 10, opponentDamage: 8};

/**
 * A fresh state, frozen: any rule that changes the state it is given throws.
 *
 * @param {object} changes Fields to start with a different value.
 * @return {object}
 */
const stateWith = (changes = {}) => Object.freeze(Object.assign(Combat.initialState(config), changes));

/**
 * Resolves a match with the shared config.
 *
 * @param {string} side Who made the match.
 * @param {Array<number>} destroyedTypes Pieces destroyed.
 * @param {Array<{type: number, size: number}>} matchGroups Matches made.
 * @param {object} state The state before.
 * @return {object}
 */
const play = (side, destroyedTypes, matchGroups, state = stateWith()) =>
    Combat.resolveMatchEffects({side, destroyedTypes, matchGroups, state, config});

/**
 * Pieces of one type, as many as asked.
 *
 * @param {number} type The piece type.
 * @param {number} count How many.
 * @return {Array<number>}
 */
const pieces = (type, count) => Array(count).fill(type);

test('the six piece types are numbered from 0 and PIECE_TYPES counts them', () => {
    assert.deepEqual(Object.values(Combat.PIECES).sort(), [0, 1, 2, 3, 4, 5]);
    assert.equal(Combat.PIECE_TYPES, 6);
});

test('a battle starts at full HP with empty meters and no multiplier bonus', () => {
    const state = Combat.initialState(config);
    assert.equal(state.playerHp, 200);
    assert.equal(state.opponentMaxHp, 200);
    ['player', 'opponent'].forEach(side => {
        assert.equal(state[`${side}Multiplier`], 1);
        assert.equal(state[`${side}Mana`], 0);
        assert.equal(state[`${side}ShieldMeter`], 0);
        assert.equal(state[`${side}ShieldReady`], false);
        assert.equal(state[`${side}PoisonMeter`], 0);
        assert.equal(state[`${side}PoisonRounds`], 0);
    });
});

test('the defaults are the balance that was simulated: change them only after simulating again', () => {
    assert.deepEqual(Combat.defaultConfig(), {playerHp: 200, opponentHp: 200, playerDamage: 10, opponentDamage: 8});
});

test('a match of four hits half again as hard as three, and five twice as hard', () => {
    assert.deepEqual([2, 3, 4, 5, 6].map(Combat.comboMultiplier), [1, 1, 1.5, 2, 2.5]);
});

test('Swords hit the other side for the damage unit of whoever matched them', () => {
    assert.equal(play('player', pieces(SWORD, 3), [{type: SWORD, size: 3}]).state.opponentHp, 190);
    assert.equal(play('player', pieces(SWORD, 4), [{type: SWORD, size: 4}]).state.opponentHp, 185);
    assert.equal(play('player', pieces(SWORD, 5), [{type: SWORD, size: 5}]).state.opponentHp, 180);
    assert.equal(play('opponent', pieces(SWORD, 3), [{type: SWORD, size: 3}]).state.playerHp, 192);
});

test('two Sword matches in one move add up', () => {
    const result = play('player', pieces(SWORD, 6), [{type: SWORD, size: 3}, {type: SWORD, size: 3}]);
    assert.equal(result.state.opponentHp, 180);
    assert.equal(result.hit.amount, 20);
});

test('only Sword matches hurt: other matches of any size do not', () => {
    const result = play('player', pieces(POTION, 5), [{type: POTION, size: 5}]);
    assert.equal(result.hit.amount, 0);
    assert.equal(result.state.opponentHp, 200);
});

test('Stars raise the multiplier a tenth each, and the multiplier raises Sword hits', () => {
    const starred = play('player', pieces(STAR, 3), [{type: STAR, size: 3}]).state;
    assert.equal(starred.playerMultiplier, 1.3);
    const hit = play('player', pieces(SWORD, 3), [{type: SWORD, size: 3}], starred);
    assert.equal(hit.hit.amount, 13);
});

test('a Potion heals a quarter of the damage unit and never past full HP', () => {
    const hurt = stateWith({playerHp: 150});
    assert.equal(play('player', pieces(POTION, 3), [{type: POTION, size: 3}], hurt).state.playerHp, 157.5);
    const nearly = stateWith({playerHp: 199});
    const healed = play('player', pieces(POTION, 3), [{type: POTION, size: 3}], nearly);
    assert.equal(healed.state.playerHp, 200);
    assert.equal(healed.healGained, 1);
});

test('ten Shield pieces arm the shield, which cancels the next hit completely and then is gone', () => {
    let state = play('player', pieces(SHIELD, 10), [{type: SHIELD, size: 5}, {type: SHIELD, size: 5}]).state;
    assert.equal(state.playerShieldReady, true);
    assert.equal(state.playerShieldMeter, 0);

    const blocked = play('opponent', pieces(SWORD, 3), [{type: SWORD, size: 3}], Object.freeze(state));
    assert.equal(blocked.state.playerHp, 200);
    assert.equal(blocked.hit.shieldConsumed, true);
    assert.equal(blocked.state.playerShieldReady, false);

    state = blocked.state;
    assert.equal(play('opponent', pieces(SWORD, 3), [{type: SWORD, size: 3}], Object.freeze(state)).state.playerHp, 192);
});

test('a Shield meter that is not full arms nothing', () => {
    const state = play('player', pieces(SHIELD, 9), [{type: SHIELD, size: 3}]).state;
    assert.equal(state.playerShieldReady, false);
    assert.equal(state.playerShieldMeter, 90);
});

test('a full Grimoire meter poisons the other side for three turns and keeps the surplus', () => {
    const state = play('player', pieces(GRIMOIRE, 12), [{type: GRIMOIRE, size: 4}]).state;
    assert.equal(state.opponentPoisonRounds, 3);
    assert.equal(state.playerPoisonMeter, 20);
    assert.equal(state.playerPoisonRounds, 0);
});

test('five Orbs fill the mana bar and ask a question; four do not', () => {
    const asked = play('player', pieces(ORB, 5), [{type: ORB, size: 5}]);
    assert.equal(asked.questionTriggered, true);
    assert.equal(asked.state.playerMana, 0);

    const almost = play('player', pieces(ORB, 4), [{type: ORB, size: 4}]);
    assert.equal(almost.questionTriggered, false);
    assert.equal(almost.state.playerMana, 80);
});

test('mana past the limit carries over to the next question', () => {
    const asked = play('player', pieces(ORB, 6), [{type: ORB, size: 6}]);
    assert.equal(asked.questionTriggered, true);
    assert.equal(asked.state.playerMana, 20);
});

test('the opponent fills its own mana bar', () => {
    const asked = play('opponent', pieces(ORB, 5), [{type: ORB, size: 5}]);
    assert.equal(asked.questionTriggered, true);
    assert.equal(asked.state.opponentMana, 0);
});

test('a correct answer lands a critical hit and spends the multiplier', () => {
    const plain = Combat.questionOutcome('player', true, stateWith(), config);
    assert.equal(plain.target, 'opponent');
    assert.equal(plain.amount, 30);
    assert.equal(plain.state.opponentHp, 170);

    const starred = Combat.questionOutcome('player', true, stateWith({playerMultiplier: 1.3}), config);
    assert.equal(starred.amount, 39);
    assert.equal(starred.state.playerMultiplier, 1);
});

test('a wrong answer costs the player a plain hit from the opponent and the multiplier', () => {
    const wrong = Combat.questionOutcome('player', false, stateWith({playerMultiplier: 1.5}), config);
    assert.equal(wrong.target, 'player');
    assert.equal(wrong.amount, 8);
    assert.equal(wrong.state.playerHp, 192);
    assert.equal(wrong.state.playerMultiplier, 1);
    assert.equal(wrong.state.opponentHp, 200);
});

test('a ready shield cancels the hit of a wrong answer too', () => {
    const wrong = Combat.questionOutcome('player', false, stateWith({playerShieldReady: true}), config);
    assert.equal(wrong.state.playerHp, 200);
    assert.equal(wrong.shieldConsumed, true);
    assert.equal(wrong.state.playerShieldReady, false);
});

test('the opponent answering correctly hits the player with its own unit', () => {
    const outcome = Combat.questionOutcome('opponent', true, stateWith(), config);
    assert.equal(outcome.target, 'player');
    assert.equal(outcome.amount, 24);
    assert.equal(outcome.state.playerHp, 176);
});

test('a hit never takes HP below zero', () => {
    const result = Combat.applyHit(stateWith({playerHp: 5}), 'player', 50);
    assert.equal(result.state.playerHp, 0);
    assert.equal(result.applied, 5);
});

test('poison ticks at the start of the poisoned side\'s turn, for the unit of whoever poisoned it', () => {
    const player = Combat.startTurn('player', stateWith({playerPoisonRounds: 2}), config);
    assert.equal(player.poisonTicked, true);
    assert.equal(player.damage, 8);
    assert.equal(player.state.playerHp, 192);
    assert.equal(player.state.playerPoisonRounds, 1);

    const opponent = Combat.startTurn('opponent', stateWith({opponentPoisonRounds: 1}), config);
    assert.equal(opponent.damage, 10);
    assert.equal(opponent.state.opponentPoisonRounds, 0);
});

test('poison ignores the shield and leaves a side with no poison alone', () => {
    const shielded = Combat.startTurn('player', stateWith({playerPoisonRounds: 1, playerShieldReady: true}), config);
    assert.equal(shielded.state.playerHp, 192);
    assert.equal(shielded.state.playerShieldReady, true);

    const clean = Combat.startTurn('player', stateWith(), config);
    assert.equal(clean.poisonTicked, false);
    assert.equal(clean.state.playerHp, 200);
});

test('the result is a victory when the opponent falls, a defeat when the player does, else open', () => {
    assert.equal(Combat.result(stateWith()), null);
    assert.equal(Combat.result(stateWith({opponentHp: 0})), 'victory');
    assert.equal(Combat.result(stateWith({playerHp: 0})), 'defeat');
});
