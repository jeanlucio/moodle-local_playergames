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
 * Displays one question at a time. A wrong answer loads the next concept;
 * a correct answer submits XP and shows the result.
 *
 * @module     local_playergames/play_quiz
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {

    let currentIndex = 0;
    let attemptNumber = 1;
    let submitted = false;
    let container = null;
    let questions = [];
    const seen = new Set();

    /**
     * Reveals the question at the given pool index and updates the attempt counter.
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
    };

    /**
     * Handles a click on an answer button.
     * Correct: highlights green then submits score.
     * Wrong: highlights red, reveals correct answer, then cycles to next question.
     *
     * @param {HTMLButtonElement} btn The clicked answer button.
     */
    const handleAnswer = (btn) => {
        const question = questions[currentIndex];
        const allBtns = Array.from(question.querySelectorAll('.pg-quiz-answer'));
        allBtns.forEach(b => {
            b.disabled = true;
        });

        const isCorrect = btn.dataset.correct === '1';
        btn.classList.remove('btn-outline-primary');

        if (isCorrect) {
            btn.classList.add('btn-success');
            setTimeout(() => submitScore(), 1200);
        } else {
            btn.classList.add('btn-danger');
            allBtns.forEach(b => {
                if (b.dataset.correct === '1') {
                    b.classList.remove('btn-outline-primary');
                    b.classList.add('btn-success');
                }
            });
            setTimeout(() => {
                attemptNumber++;
                currentIndex = (currentIndex + 1) % questions.length;
                showQuestion(currentIndex);
            }, 1200);
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
     * Hides all question cards and reveals the result panel.
     */
    const showResult = () => {
        questions.forEach(q => {
            q.classList.add('d-none');
        });
        const progressText = document.getElementById('pg-quiz-progress-text');
        if (progressText) {
            progressText.classList.add('d-none');
        }
        const result = document.getElementById('pg-quiz-result');
        if (result) {
            result.classList.remove('d-none');
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
