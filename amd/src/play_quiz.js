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
 * Shows one question at a time with a per-question countdown. A wrong answer
 * (or a question whose timer runs out) reveals the correct option and the
 * general feedback, then waits for the player to continue — it never advances
 * on its own. A correct answer ends the play and opens the result as a modal
 * over the reviewable question; running out of attempts opens a failure modal.
 *
 * @module     local_playergames/play_quiz
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Modal from 'core/modal';

let currentIndex = 0;
let attemptNumber = 1;
let wrongCount = 0;
let finished = false;
let endKind = '';
let container = null;
let questions = [];
let questionSeconds = 0;
let maxAttempts = 0;
let timerEl = null;
let timerInterval = null;
let deadline = 0;
const seen = new Set();

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
 * question is treated as a wrong answer. Does nothing when the timer is
 * disabled (zero seconds configured).
 */
const startTimer = () => {
    clearTimer();
    if (questionSeconds <= 0 || !timerEl) {
        return;
    }
    deadline = Date.now() + questionSeconds * 1000;
    timerEl.classList.remove('d-none');
    renderTimer(questionSeconds);
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
 * Reveals the question at the given pool index, resets its per-question
 * controls (feedback, continue) and restarts the timer.
 *
 * @param {number} index Zero-based index into the questions pool.
 */
const showQuestion = (index) => {
    questions.forEach(q => {
        q.classList.add('d-none');
    });
    hideContinue();
    if (questions.length > 0) {
        const current = questions[index];
        current.classList.remove('d-none');
        const feedback = current.querySelector('.pg-quiz-feedback');
        if (feedback) {
            feedback.classList.add('d-none');
        }
        const {source, sourceId} = current.dataset;
        if (source && sourceId) {
            seen.add(`${source}:${sourceId}`);
        }
    }
    const progressText = document.getElementById('pg-quiz-progress-text');
    if (progressText) {
        const label = container.dataset.strAttempt || 'Attempt';
        progressText.textContent = `${label} ${attemptNumber}`;
    }
    startTimer();
};

/**
 * Highlights the correct option within the current question.
 */
const revealCorrect = () => {
    const question = questions[currentIndex];
    question.querySelectorAll('.pg-quiz-answer').forEach(b => {
        b.disabled = true;
        if (b.dataset.correct === '1') {
            b.classList.remove('btn-outline-primary');
            b.classList.add('btn-success');
        }
    });
};

/**
 * Reveals the general feedback block below the current question, when present.
 */
const revealFeedback = () => {
    const question = questions[currentIndex];
    const feedback = question.querySelector('.pg-quiz-feedback');
    if (feedback) {
        feedback.classList.remove('d-none');
    }
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
 * Records a wrong answer or timeout. Ends the play in failure once the attempt
 * limit is reached; otherwise offers the continue button.
 */
const registerWrong = () => {
    wrongCount++;
    if (maxAttempts > 0 && wrongCount >= maxAttempts) {
        endWithFailure();
        return;
    }
    showContinue();
};

/**
 * Advances to the next question after the player presses continue.
 */
const goToNextQuestion = () => {
    if (finished) {
        return;
    }
    attemptNumber++;
    currentIndex = (currentIndex + 1) % questions.length;
    showQuestion(currentIndex);
};

/**
 * Handles the current question's timer running out: treated as a wrong answer
 * with the correct option and feedback revealed before the player continues.
 */
const handleTimeout = () => {
    if (finished) {
        return;
    }
    const question = questions[currentIndex];
    question.querySelectorAll('.pg-quiz-answer').forEach(b => {
        b.disabled = true;
    });
    revealCorrect();
    revealFeedback();
    registerWrong();
};

/**
 * Handles a click on an answer button.
 * Correct: highlights green, reveals feedback and ends the play.
 * Wrong: highlights red, reveals the correct answer and feedback, then waits.
 *
 * @param {HTMLButtonElement} btn The clicked answer button.
 */
const handleAnswer = (btn) => {
    clearTimer();
    const question = questions[currentIndex];
    question.querySelectorAll('.pg-quiz-answer').forEach(b => {
        b.disabled = true;
    });

    const isCorrect = btn.dataset.correct === '1';
    btn.classList.remove('btn-outline-primary');

    if (isCorrect) {
        btn.classList.add('btn-success');
        revealFeedback();
        endWithSuccess();
    } else {
        btn.classList.add('btn-danger');
        revealCorrect();
        revealFeedback();
        registerWrong();
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
 * Ends the play with a correct answer: submits the score, fills the XP value
 * and opens the result modal over the reviewable question.
 */
const endWithSuccess = async() => {
    if (finished) {
        return;
    }
    finished = true;
    endKind = 'pg-quiz-result';

    try {
        const response = await fetch(container.dataset.action, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({
                sesskey: container.dataset.sesskey,
                action: 'submit_correct',
                seen: JSON.stringify(Array.from(seen)),
            }),
        });
        const data = await response.json();
        if (data.success) {
            const xpEl = document.getElementById('pg-quiz-xp-count');
            if (xpEl) {
                xpEl.textContent = String(data.xpawarded);
            }
        }
    } catch (e) {
        void e;
    }

    showEndActions();
    openEndModal('pg-quiz-result');
};

/**
 * Ends the play in failure: reports it so the server can start the cooldown,
 * then opens the failure modal over the reviewable question.
 */
const endWithFailure = async() => {
    if (finished) {
        return;
    }
    finished = true;
    endKind = 'pg-quiz-failed';

    try {
        await fetch(container.dataset.action, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({
                sesskey: container.dataset.sesskey,
                action: 'submit_failed',
            }),
        });
    } catch (e) {
        void e;
    }

    showEndActions();
    openEndModal('pg-quiz-failed');
};

export default {
    /**
     * Initialises the quiz: reads DOM state, shows the first question and wires
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
        maxAttempts = parseInt(container.dataset.maxAttempts, 10) || 0;
        timerEl = document.getElementById('pg-quiz-timer');

        showQuestion(0);

        container.addEventListener('click', (e) => {
            const btn = e.target.closest('.pg-quiz-answer');
            if (!btn || btn.disabled) {
                return;
            }
            handleAnswer(btn);
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
