/**
 * quiz_generator.js
 * Vanilla JS — no build step, matches the rest of the app's front end.
 *
 * Flow:
 *   1. Teacher fills the generator form -> POST assets/api/generate_quiz.php
 *   2. Response (draft questions) is rendered as editable cards
 *   3. Teacher edits inline, adds/removes questions or choices
 *   4. Save as Draft / Publish -> POST assets/api/save_quiz.php
 */
(function () {
    'use strict';

    const csrfToken = document.getElementById('qgCsrfToken').value;

    const form = document.getElementById('qgGenerateForm');
    const loadingBox = document.getElementById('qgLoading');
    const genAlert = document.getElementById('qgGenerateAlert');
    const reviewSection = document.getElementById('qgReviewSection');
    const questionList = document.getElementById('qgQuestionList');
    const modelBadge = document.getElementById('qgModelBadge');
    const saveAlert = document.getElementById('qgSaveAlert');
    const generateBtn = document.getElementById('qgGenerateBtn');

    let state = {
        offeringId: null,
        jobId: null,
        questions: [], // {question_text, question_type, points, ai_generated, teacher_edited, choices:[{text,is_correct}], correct_answer}
    };

    // ---------------- Type pills (visual radio group) ----------------
    document.querySelectorAll('.qg-pill').forEach((pill) => {
        pill.addEventListener('click', () => {
            document.querySelectorAll('.qg-pill').forEach((p) => p.classList.remove('active'));
            pill.classList.add('active');
            pill.querySelector('input').checked = true;
        });
    });

    // ---------------- Helpers ----------------
    function showAlert(el, type, messages) {
        el.className = 'qg-alert active qg-alert--' + type;
        if (Array.isArray(messages)) {
            el.innerHTML = messages.length > 1
                ? '<ul>' + messages.map((m) => `<li>${escapeHtml(m)}</li>`).join('') + '</ul>'
                : escapeHtml(messages[0] || '');
        } else {
            el.textContent = messages;
        }
    }

    function hideAlert(el) {
        el.className = 'qg-alert';
        el.innerHTML = '';
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str ?? '';
        return div.innerHTML;
    }

    function blankMcq() {
        return {
            question_text: '',
            question_type: 'mcq',
            points: 1,
            ai_generated: false,
            teacher_edited: false,
            choices: [
                { text: '', is_correct: true },
                { text: '', is_correct: false },
                { text: '', is_correct: false },
                { text: '', is_correct: false },
            ],
        };
    }

    // ---------------- Generate ----------------
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        hideAlert(genAlert);

        const formData = new FormData(form);
        const payload = {
            csrf_token: csrfToken,
            offering_id: formData.get('offering_id'),
            num_items: formData.get('num_items'),
            question_type: formData.get('question_type'),
            difficulty: formData.get('difficulty'),
            topic: formData.get('topic'),
            title: formData.get('title'),
        };

        generateBtn.disabled = true;
        loadingBox.classList.add('active');
        reviewSection.classList.remove('active');

        try {
            const res = await fetch('assets/api/quiz_api/generate_quiz.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(payload),
            });

            let data;
            try {
                data = await res.json();
            } catch {
                throw new Error('Unexpected server response. Please try again.');
            }

            if (!res.ok || !data.success) {
                showAlert(genAlert, 'error', data.errors || ['Something went wrong. Please try again.']);
                return;
            }

            state.offeringId = payload.offering_id;
            state.jobId = data.job_id;
            state.questions = data.questions.map((q) => ({ ...q, ai_generated: true, teacher_edited: false }));

            document.getElementById('qgQuizTitle').value = data.quiz.title;
            document.getElementById('qgQuizDescription').value = data.quiz.description;
            modelBadge.innerHTML = `<i class="fas fa-robot"></i> Generated with ${escapeHtml(data.model_used)}`;

            renderQuestions();
            reviewSection.classList.add('active');
            hideAlert(saveAlert);
            reviewSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
        } catch (err) {
            showAlert(genAlert, 'error', [err.message || 'Network error. Please try again.']);
        } finally {
            generateBtn.disabled = false;
            loadingBox.classList.remove('active');
        }
    });

    // ---------------- Render questions ----------------
    function renderQuestions() {
        questionList.innerHTML = '';
        state.questions.forEach((q, index) => {
            questionList.appendChild(buildQuestionCard(q, index));
        });
    }

    function buildQuestionCard(q, index) {
        const card = document.createElement('div');
        card.className = 'qg-qcard';
        card.dataset.index = index;

        card.innerHTML = `
            <div class="qg-qcard-head">
                <span class="qg-qnum">Q${index + 1}</span>
                <div style="display:flex; align-items:center; gap:10px;">
                    <span class="qg-edited-tag ${q.teacher_edited ? 'active' : ''}">Edited</span>
                    <select class="qg-qtype-select" data-field="question_type">
                        <option value="mcq" ${q.question_type === 'mcq' ? 'selected' : ''}>Multiple choice</option>
                        <option value="true_false" ${q.question_type === 'true_false' ? 'selected' : ''}>True / False</option>
                        <option value="short_answer" ${q.question_type === 'short_answer' ? 'selected' : ''}>Identification</option>
                    </select>
                    <button type="button" class="qg-btn qg-btn--danger-ghost qg-remove-q"><i class="fas fa-trash"></i></button>
                </div>
            </div>
            <textarea class="qg-qtext" data-field="question_text" placeholder="Question text">${escapeHtml(q.question_text)}</textarea>
            <div class="qg-answer-area"></div>
        `;

        renderAnswerArea(card, q);

        // Question text edits
        card.querySelector('[data-field="question_text"]').addEventListener('input', (e) => {
            q.question_text = e.target.value;
            markEdited(card, q);
        });

        // Type change -> rebuild answer area with sensible defaults
        card.querySelector('[data-field="question_type"]').addEventListener('change', (e) => {
            const newType = e.target.value;
            q.question_type = newType;
            if (newType === 'mcq' && q.choices.length < 2) {
                q.choices = [
                    { text: '', is_correct: true },
                    { text: '', is_correct: false },
                    { text: '', is_correct: false },
                    { text: '', is_correct: false },
                ];
            } else if (newType === 'true_false') {
                q.choices = [
                    { text: 'True', is_correct: true },
                    { text: 'False', is_correct: false },
                ];
            } else if (newType === 'short_answer') {
                q.correct_answer = q.correct_answer || '';
            }
            renderAnswerArea(card, q);
            markEdited(card, q);
        });

        card.querySelector('.qg-remove-q').addEventListener('click', () => {
            state.questions.splice(index, 1);
            renderQuestions();
        });

        return card;
    }

    function markEdited(card, q) {
        q.teacher_edited = true;
        const tag = card.querySelector('.qg-edited-tag');
        if (tag) tag.classList.add('active');
    }

    function renderAnswerArea(card, q) {
        const area = card.querySelector('.qg-answer-area');
        area.innerHTML = '';

        if (q.question_type === 'short_answer') {
            const wrap = document.createElement('div');
            wrap.className = 'qg-sa-answer';
            wrap.innerHTML = `
                <i class="fas fa-check-circle" style="color:var(--ok)"></i>
                <input type="text" placeholder="Accepted answer" value="${escapeHtml(q.correct_answer || '')}">
            `;
            wrap.querySelector('input').addEventListener('input', (e) => {
                q.correct_answer = e.target.value;
                markEdited(card, q);
            });
            area.appendChild(wrap);
            return;
        }

        const choicesWrap = document.createElement('div');
        choicesWrap.className = 'qg-choices';
        const groupName = 'correct_' + card.dataset.index + '_' + Math.random().toString(36).slice(2, 7);

        q.choices.forEach((choice, cIndex) => {
            const row = document.createElement('div');
            row.className = 'qg-choice-row';
            const lockText = q.question_type === 'true_false'; // don't let teachers retype True/False
            row.innerHTML = `
                <input type="radio" name="${groupName}" ${choice.is_correct ? 'checked' : ''}>
                <input type="text" value="${escapeHtml(choice.text)}" ${lockText ? 'readonly' : ''} placeholder="Choice text">
                ${q.question_type === 'mcq' && q.choices.length > 2 ? '<button type="button" class="remove-choice"><i class="fas fa-times"></i></button>' : '<span style="width:26px"></span>'}
            `;

            row.querySelector('input[type="radio"]').addEventListener('change', () => {
                q.choices.forEach((c) => (c.is_correct = false));
                choice.is_correct = true;
                markEdited(card, q);
            });

            if (!lockText) {
                row.querySelector('input[type="text"]').addEventListener('input', (e) => {
                    choice.text = e.target.value;
                    markEdited(card, q);
                });
            }

            const removeBtn = row.querySelector('.remove-choice');
            if (removeBtn) {
                removeBtn.addEventListener('click', () => {
                    q.choices.splice(cIndex, 1);
                    if (!q.choices.some((c) => c.is_correct) && q.choices.length) {
                        q.choices[0].is_correct = true;
                    }
                    renderAnswerArea(card, q);
                    markEdited(card, q);
                });
            }

            choicesWrap.appendChild(row);
        });

        area.appendChild(choicesWrap);

        if (q.question_type === 'mcq' && q.choices.length < 6) {
            const addChoiceBtn = document.createElement('button');
            addChoiceBtn.type = 'button';
            addChoiceBtn.className = 'qg-btn qg-btn--ghost qg-btn--sm';
            addChoiceBtn.style.marginTop = '8px';
            addChoiceBtn.innerHTML = '<i class="fas fa-plus"></i> Add choice';
            addChoiceBtn.addEventListener('click', () => {
                q.choices.push({ text: '', is_correct: false });
                renderAnswerArea(card, q);
                markEdited(card, q);
            });
            area.appendChild(addChoiceBtn);
        }
    }

    // ---------------- Add question manually ----------------
    document.getElementById('qgAddQuestion').addEventListener('click', () => {
        state.questions.push(blankMcq());
        renderQuestions();
        questionList.lastElementChild.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });

    // ---------------- Save (draft / publish) ----------------
    async function saveQuiz(status) {
        hideAlert(saveAlert);

        if (!state.offeringId) {
            showAlert(saveAlert, 'error', ['Generate a quiz first.']);
            return;
        }

        const title = document.getElementById('qgQuizTitle').value.trim();
        if (!title) {
            showAlert(saveAlert, 'error', ['Quiz title is required.']);
            return;
        }
        if (!state.questions.length) {
            showAlert(saveAlert, 'error', ['Add at least one question.']);
            return;
        }

        const payload = {
            csrf_token: csrfToken,
            offering_id: state.offeringId,
            job_id: state.jobId,
            title: title,
            description: document.getElementById('qgQuizDescription').value.trim(),
            time_limit_minutes: document.getElementById('qgTimeLimit').value || null,
            max_attempts: document.getElementById('qgMaxAttempts').value || 1,
            shuffle_questions: document.getElementById('qgShuffle').checked,
            available_from: document.getElementById('qgAvailableFrom').value || null,
            available_until: document.getElementById('qgAvailableUntil').value || null,
            status: status,
            questions: state.questions,
        };

        const saveDraftBtn = document.getElementById('qgSaveDraftBtn');
        const publishBtn = document.getElementById('qgPublishBtn');
        saveDraftBtn.disabled = true;
        publishBtn.disabled = true;

        try {
            const res = await fetch('assets/api/quiz_api/save_quiz.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(payload),
            });

            let data;
            try {
                data = await res.json();
            } catch {
                throw new Error('Unexpected server response. Please try again.');
            }

            if (!res.ok || !data.success) {
                showAlert(saveAlert, 'error', data.errors || ['Could not save the quiz.']);
                return;
            }

            showAlert(saveAlert, 'success', [data.message || 'Saved.']);
            setTimeout(() => window.location.reload(), 1200);
        } catch (err) {
            showAlert(saveAlert, 'error', [err.message || 'Network error. Please try again.']);
        } finally {
            saveDraftBtn.disabled = false;
            publishBtn.disabled = false;
        }
    }



    // Helper to return current local datetime formatted for <input type="datetime-local">
    function getNowForInput() {
        const now = new Date();
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
        return now.toISOString().slice(0, 16);
    }

    // Inside form.addEventListener('submit', ...) after data is returned successfully:
    document.getElementById('qgAvailableFrom').value = getNowForInput();
    document.getElementById('qgAvailableUntil').value = ''; // clear any prior value

    document.getElementById('qgSaveDraftBtn').addEventListener('click', () => saveQuiz('draft'));
    document.getElementById('qgPublishBtn').addEventListener('click', () => saveQuiz('published'));
})();
