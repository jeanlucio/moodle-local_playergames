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
 * PlayerQuiz game interaction module.
 *
 * Shows one question at a time with a per-question countdown. The page never knows which option is
 * right: every answer (or a timeout) is sent to the server, which says whether it was correct, counts
 * the wrong answers, ends the play and pays the XP. A wrong answer reveals the correct option and the
 * general feedback the server returns, then waits for the player to continue — it never advances on
 * its own. A correct answer opens the result as a modal over the reviewable question; running out of
 * attempts opens a failure modal.
 *
 * @module     local_playergames/play_quiz
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Modal from 'core/modal';
import Notification from 'core/notification';

let currentIndex = 0;
let attemptNumber = 1;
let finished = false;
let busy = false;
let endKind = '';
let container = null;
let questions = [];
let questionSeconds = 0;
let timerEl = null;
let timerInterval = null;
let deadline = 0;

/**
 * Sends one request to the page and returns the server's JSON answer.
 *
 * @param {Object} params Form fields, besides the sesskey.
 * @returns {Promise<Object>} The decoded response.
 */
const post = async(params) => {
    const response = await fetch(container.dataset.action, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({sesskey: container.dataset.sesskey, ...params}),
    });
    return response.json();
};

/**
 * Stops and hides the per-question countdown timer.
 */
const clearTimer = () => {
    if (timerInterval !== null) {
        window.clearInterval(timerInterval);
        timerInterval = null;
    }
    if (timerEl) {
        timerEl.classList.add('d-none');
    }
};

/**
 * Renders the remaining time as MM:SS in the timer badge.
 *
 * @param {number} seconds Whole seconds remaining.
 */
const renderTimer = (seconds) => {
    if (!timerEl) {
        return;
    }
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    timerEl.textContent = `${mins}:${String(secs).padStart(2, '0')}`;
};

/**
 * Starts the countdown for the current question. When it reaches zero the
 * server is told, and counts the question as a wrong answer. Does nothing
 * when the timer is disabled (zero seconds configured).
 *
 * @param {number} seconds Seconds the question has left.
 */
const startTimer = (seconds) => {
    clearTimer();
    if (questionSeconds <= 0 || !timerEl) {
        return;
    }
    deadline = Date.now() + seconds * 1000;
    timerEl.classList.remove('d-none');
    renderTimer(seconds);
    timerInterval = window.setInterval(() => {
        const remaining = Math.max(0, Math.round((deadline - Date.now()) / 1000));
        renderTimer(remaining);
        if (remaining <= 0) {
            clearTimer();
            handleTimeout();
        }
    }, 250);
};

/**
 * Shows the "continue" button so the player can move to the next question.
 */
const showContinue = () => {
    const wrap = document.getElementById('pg-quiz-continue-wrap');
    if (wrap) {
        wrap.classList.remove('d-none');
    }
};

/**
 * Hides the "continue" button.
 */
const hideContinue = () => {
    const wrap = document.getElementById('pg-quiz-continue-wrap');
    if (wrap) {
        wrap.classList.add('d-none');
    }
};

/**
 * Reveals the question at the given pool index, resets its controls and restarts the timer.
 *
 * @param {number} index Zero-based index into the questions pool.
 * @param {number} seconds Seconds the question has left.
 */
const showQuestion = (index, seconds) => {
    questions.forEach(q => {
        q.classList.add('d-none');
    });
    hideContinue();
    currentIndex = index;
    const current = questions[index];
    current.classList.remove('d-none');
    const feedback = current.querySelector('.pg-quiz-feedback');
    if (feedback) {
        feedback.classList.add('d-none');
    }
    current.querySelectorAll('.pg-quiz-answer').forEach(b => {
        b.disabled = false;
        b.classList.remove('btn-success', 'btn-danger');
        b.classList.add('btn-outline-primary');
    });
    const progressText = document.getElementById('pg-quiz-progress-text');
    if (progressText) {
        const label = container.dataset.strAttempt || 'Attempt';
        progressText.textContent = `${label} ${attemptNumber}`;
    }
    startTimer(seconds);
};

/**
 * Locks the options of the current question.
 */
const lockAnswers = () => {
    questions[currentIndex].querySelectorAll('.pg-quiz-answer').forEach(b => {
        b.disabled = true;
    });
};

/**
 * Highlights the option the server named as correct and shows the feedback it sent.
 *
 * @param {Object} result The server's verdict.
 */
const revealResult = (result) => {
    const question = questions[currentIndex];
    const buttons = Array.from(question.querySelectorAll('.pg-quiz-answer'));
    const right = buttons.find(b => parseInt(b.dataset.choice, 10) === result.correctindex);
    if (right) {
        right.classList.remove('btn-outline-primary');
        right.classList.add('btn-success');
    }
    const feedback = question.querySelector('.pg-quiz-feedback');
    const text = question.querySelector('.pg-quiz-feedback-text');
    if (feedback && text && result.feedback) {
        text.innerHTML = result.feedback;
        feedback.classList.remove('d-none');
    }
};

/**
 * Opens the end-of-play modal (result or failure) built from its source block.
 *
 * @param {string} sourceId Element id holding the modal body and title.
 */
const openEndModal = async(sourceId) => {
    const el = document.getElementById(sourceId);
    if (!el) {
        return;
    }
    await Modal.create({
        title: el.dataset.title || '',
        body: el.innerHTML,
        show: true,
        removeOnClose: true,
    });
};

/**
 * Reveals the persistent end-of-play actions (reopen result, back to hub).
 */
const showEndActions = () => {
    clearTimer();
    hideContinue();
    const actions = document.getElementById('pg-quiz-endactions');
    if (actions) {
        actions.classList.remove('d-none');
    }
};

/**
 * Applies the server's verdict on an answer: ends the play or offers to continue.
 *
 * @param {Object} result The server's verdict.
 */
const applyResult = (result) => {
    attemptNumber = result.attempt;
    revealResult(result);

    if (!result.finished) {
        showContinue();
        return;
    }

    finished = true;
    showEndActions();
    if (result.won) {
        endKind = 'pg-quiz-result';
        const xpEl = document.getElementById('pg-quiz-xp-count');
        if (xpEl) {
            xpEl.textContent = String(result.xpawarded);
        }
    } else {
        endKind = 'pg-quiz-failed';
    }
    openEndModal(endKind);
};

/**
 * Sends the player's choice (or -1 for a timeout) and shows what the server decides.
 *
 * @param {number} choice Zero-based option, or -1 when the time ran out.
 */
const submitChoice = async(choice) => {
    if (finished || busy) {
        return;
    }
    busy = true;
    clearTimer();
    lockAnswers();

    try {
        const result = await post({action: 'answer', question: currentIndex, choice});
        if (result.success) {
            applyResult(result);
        } else {
            // The play is no longer one the server accepts answers for (ended elsewhere, expired).
            window.location.reload();
        }
    } catch (error) {
        Notification.exception(error);
        questions[currentIndex].querySelectorAll('.pg-quiz-answer').forEach(b => {
            b.disabled = false;
        });
    } finally {
        busy = false;
    }
};

/**
 * Handles the current question's timer running out.
 */
const handleTimeout = () => {
    submitChoice(-1);
};

/**
 * Advances to the question the server names, after the player presses continue.
 */
const goToNextQuestion = async() => {
    if (finished || busy) {
        return;
    }
    busy = true;
    try {
        const result = await post({action: 'next'});
        if (result.success) {
            attemptNumber = result.attempt;
            showQuestion(result.index, questionSeconds);
        } else {
            window.location.reload();
        }
    } catch (error) {
        Notification.exception(error);
    } finally {
        busy = false;
    }
};

export default {
    /**
     * Initialises the quiz: reads DOM state, shows the current question and wires
     * the answer, continue and reopen-result interactions.
     */
    init: () => {
        container = document.getElementById('pg-quiz-container');
        if (!container) {
            return;
        }
        questions = Array.from(container.querySelectorAll('.pg-quiz-question'));

        if (questions.length === 0) {
            return;
        }

        questionSeconds = parseInt(container.dataset.questionSeconds, 10) || 0;
        attemptNumber = parseInt(container.dataset.attempt, 10) || 1;
        timerEl = document.getElementById('pg-quiz-timer');

        const start = Math.min(parseInt(container.dataset.current, 10) || 0, questions.length - 1);
        showQuestion(start, parseInt(container.dataset.firstSeconds, 10) || questionSeconds);

        container.addEventListener('click', (e) => {
            const btn = e.target.closest('.pg-quiz-answer');
            if (!btn || btn.disabled) {
                return;
            }
            submitChoice(parseInt(btn.dataset.choice, 10));
        });

        const continueBtn = document.getElementById('pg-quiz-continue');
        if (continueBtn) {
            continueBtn.addEventListener('click', goToNextQuestion);
        }

        const reopenBtn = document.getElementById('pg-quiz-view-result');
        if (reopenBtn) {
            reopenBtn.addEventListener('click', () => openEndModal(endKind));
        }
    },
};
