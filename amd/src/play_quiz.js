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
 * Displays one question at a time. A wrong answer (or a question whose timer
 * runs out) loads the next concept; a correct answer submits XP and shows the
 * result. When a maximum number of attempts is configured, running out ends
 * the play in failure and reports it so the server can start the cooldown.
 *
 * @module     local_playergames/play_quiz
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {

    let currentIndex = 0;
    let attemptNumber = 1;
    let wrongCount = 0;
    let submitted = false;
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
     * Reveals the question at the given pool index, updates the attempt counter
     * and restarts the timer.
     *
     * @param {number} index Zero-based index into the questions pool.
     */
    const showQuestion = (index) => {
        questions.forEach(q => {
            q.classList.add('d-none');
        });
        if (questions.length > 0) {
            const current = questions[index];
            current.classList.remove('d-none');
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
        const allBtns = Array.from(question.querySelectorAll('.pg-quiz-answer'));
        allBtns.forEach(b => {
            b.disabled = true;
            if (b.dataset.correct === '1') {
                b.classList.remove('btn-outline-primary');
                b.classList.add('btn-success');
            }
        });
    };

    /**
     * Advances to the next question after a wrong answer or a timeout. When the
     * configured attempt limit is reached, the play ends in failure instead.
     */
    const advanceAfterWrong = () => {
        wrongCount++;
        if (maxAttempts > 0 && wrongCount >= maxAttempts) {
            fail();
            return;
        }
        attemptNumber++;
        currentIndex = (currentIndex + 1) % questions.length;
        showQuestion(currentIndex);
    };

    /**
     * Handles the current question's timer running out: treated as a wrong
     * answer with the correct option revealed before advancing.
     */
    const handleTimeout = () => {
        if (submitted) {
            return;
        }
        revealCorrect();
        window.setTimeout(advanceAfterWrong, 1200);
    };

    /**
     * Handles a click on an answer button.
     * Correct: highlights green then submits score.
     * Wrong: highlights red, reveals correct answer, then advances.
     *
     * @param {HTMLButtonElement} btn The clicked answer button.
     */
    const handleAnswer = (btn) => {
        clearTimer();
        const question = questions[currentIndex];
        const allBtns = Array.from(question.querySelectorAll('.pg-quiz-answer'));
        allBtns.forEach(b => {
            b.disabled = true;
        });

        const isCorrect = btn.dataset.correct === '1';
        btn.classList.remove('btn-outline-primary');

        if (isCorrect) {
            btn.classList.add('btn-success');
            window.setTimeout(() => submitScore(), 1200);
        } else {
            btn.classList.add('btn-danger');
            allBtns.forEach(b => {
                if (b.dataset.correct === '1') {
                    b.classList.remove('btn-outline-primary');
                    b.classList.add('btn-success');
                }
            });
            window.setTimeout(advanceAfterWrong, 1200);
        }
    };

    /**
     * Sends the correct-answer event to the server and shows the result screen.
     * The XP count is updated once the server responds.
     */
    const submitScore = async() => {
        if (submitted) {
            return;
        }
        submitted = true;
        clearTimer();

        showResult();

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
    };

    /**
     * Reports the failed play to the server (to start the cooldown) and shows
     * the failure screen.
     */
    const fail = async() => {
        if (submitted) {
            return;
        }
        submitted = true;
        clearTimer();

        showFailed();

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
    };

    /**
     * Hides all question cards and the progress/timer indicators.
     */
    const hidePlay = () => {
        questions.forEach(q => {
            q.classList.add('d-none');
        });
        const progressText = document.getElementById('pg-quiz-progress-text');
        if (progressText) {
            progressText.classList.add('d-none');
        }
        clearTimer();
    };

    /**
     * Hides the play area and reveals the success result panel.
     */
    const showResult = () => {
        hidePlay();
        const result = document.getElementById('pg-quiz-result');
        if (result) {
            result.classList.remove('d-none');
        }
    };

    /**
     * Hides the play area and reveals the failure panel.
     */
    const showFailed = () => {
        hidePlay();
        const failed = document.getElementById('pg-quiz-failed');
        if (failed) {
            failed.classList.remove('d-none');
        }
    };

    return {
        /**
         * Initialises the quiz: reads DOM state, shows the first question,
         * and attaches the delegated answer-click listener.
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
        },
    };
});
