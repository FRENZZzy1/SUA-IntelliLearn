// Toggle the "Add Material" form open/closed
const btnShowUpload = document.getElementById('btnShowUpload');
const btnCancelUpload = document.getElementById('btnCancelUpload');
const uploadForm = document.getElementById('materialUploadForm');

if (btnShowUpload && uploadForm) {
    btnShowUpload.addEventListener('click', () => {
        uploadForm.hidden = !uploadForm.hidden;
    });
}
if (btnCancelUpload && uploadForm) {
    btnCancelUpload.addEventListener('click', () => {
        uploadForm.hidden = true;
        uploadForm.reset();
    });
}

// Toggle file-vs-link fields in the upload form
document.querySelectorAll('input[name="source"]').forEach((radio) => {
    radio.addEventListener('change', () => {
        const isFile = document.querySelector('input[name="source"]:checked').value === 'file';
        document.querySelector('.material-source-file').hidden = !isFile;
        document.querySelector('.material-source-link').hidden = isFile;
    });
});

// Toggle the "New Assignment" form open/closed
const btnShowAssignmentForm = document.getElementById('btnShowAssignmentForm');
const btnCancelAssignmentForm = document.getElementById('btnCancelAssignmentForm');
const assignmentForm = document.getElementById('assignmentCreateForm');

if (btnShowAssignmentForm && assignmentForm) {
    btnShowAssignmentForm.addEventListener('click', () => {
        assignmentForm.hidden = !assignmentForm.hidden;
    });
}
if (btnCancelAssignmentForm && assignmentForm) {
    btnCancelAssignmentForm.addEventListener('click', () => {
        assignmentForm.hidden = true;
        assignmentForm.reset();
    });
}

// ===================== Attendance =====================
const attendanceForm = document.getElementById('attendanceForm');
if (attendanceForm) {
    // Keep the status-pill's highlighted state in sync with its radio input.
    attendanceForm.querySelectorAll('.attendance-status-group').forEach((group) => {
        group.querySelectorAll('input[type="radio"]').forEach((radio) => {
            radio.addEventListener('change', () => {
                group.querySelectorAll('.status-pill').forEach((pill) => pill.classList.remove('checked'));
                radio.closest('.status-pill').classList.add('checked');
            });
        });
    });

    // "Mark all" bulk-action buttons
    attendanceForm.querySelectorAll('[data-mark-all]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const status = btn.getAttribute('data-mark-all');
            attendanceForm.querySelectorAll('.attendance-status-group').forEach((group) => {
                const radio = group.querySelector(`input[value="${status}"]`);
                if (radio) {
                    radio.checked = true;
                    group.querySelectorAll('.status-pill').forEach((pill) => pill.classList.remove('checked'));
                    radio.closest('.status-pill').classList.add('checked');
                }
            });
        });
    });
}

// ===================== Quiz answer review modal =====================
// The modal is server-rendered (it's already in the DOM when ?attempt_id= is
// on the URL), so "closing" it just means following the link that re-renders
// the page without that param. This just adds the backdrop-click and
// Escape-key shortcuts on top of the explicit close (x) button.
const answerReviewModal = document.getElementById('answerReviewModal');
if (answerReviewModal) {
    const closeLink = answerReviewModal.querySelector('.modal-close');

    const closeAnswerReviewModal = () => {
        if (closeLink) {
            window.location.href = closeLink.getAttribute('href');
        }
    };

    answerReviewModal.addEventListener('click', (event) => {
        if (event.target === answerReviewModal) {
            closeAnswerReviewModal();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeAnswerReviewModal();
        }
    });

    // Keep each Correct/Incorrect pill's highlighted state in sync with its
    // radio input, and offer a quick auto-fill: picking "Correct" fills in
    // full points (if the box is empty/0) and picking "Incorrect" zeroes it
    // out (if it was at full points) — the teacher can still type over either.
    answerReviewModal.querySelectorAll('.answer-grade-row').forEach((row) => {
        const maxPoints = parseFloat(row.getAttribute('data-max-points')) || 0;
        const pointsInput = row.querySelector('.answer-grade-points input');
        const statusChip = row.closest('.answer-review-item')?.querySelector('[data-answer-status-chip]');

        row.querySelectorAll('input[type="radio"]').forEach((radio) => {
            radio.addEventListener('change', () => {
                row.querySelectorAll('.answer-grade-pill').forEach((pill) => pill.classList.remove('checked'));
                radio.closest('.answer-grade-pill').classList.add('checked');

                const isCorrect = radio.value === '1';
                if (pointsInput) {
                    const currentVal = parseFloat(pointsInput.value);
                    if (isCorrect && (isNaN(currentVal) || currentVal === 0)) {
                        pointsInput.value = maxPoints;
                    } else if (!isCorrect && currentVal === maxPoints) {
                        pointsInput.value = 0;
                    }
                }
                if (statusChip) {
                    statusChip.textContent = isCorrect ? 'Correct' : 'Incorrect';
                    statusChip.classList.remove('chip-present', 'chip-absent', 'chip-late', 'chip-missing');
                    statusChip.classList.add(isCorrect ? 'chip-present' : 'chip-absent');
                }
            });
        });
    });
}

// Enrollment Requests: approve / deny / reopen via fetch, then reload so the
// lists, nav badge and flash message all refresh from the server.
const requestsPanel = document.getElementById('requestsPanel');

if (requestsPanel) {
    const requestErrors = document.getElementById('requestErrors');

    const handleRequestClick = (e) => {
        const btn = e.target.closest('[data-request-action]');
        if (!btn) return;

        const action = btn.dataset.requestAction;
        const student = btn.dataset.student || 'this student';

        if (action === 'deny' && !window.confirm('Deny ' + student + '\'s request to join this class?')) {
            return;
        }

        const row = btn.closest('tr');
        const rowButtons = row ? row.querySelectorAll('[data-request-action]') : [btn];
        rowButtons.forEach((b) => { b.disabled = true; });
        requestErrors.hidden = true;

        const body = new FormData();
        body.append('csrf', requestsPanel.dataset.csrf);
        body.append('request_id', btn.dataset.requestId);
        body.append('action', action);

        fetch(requestsPanel.dataset.endpoint, {
            method: 'POST',
            headers: { 'Accept': 'application/json' },
            body: body
        })
        .then((res) => res.json())
        .then((data) => {
            if (data.success) {
                window.location.reload();
                return;
            }
            const msgs = data.errors || ['Something went wrong. Please try again.'];
            requestErrors.textContent = msgs.join(' ');
            requestErrors.hidden = false;
            rowButtons.forEach((b) => { b.disabled = false; });
            window.scrollTo({ top: 0, behavior: 'smooth' });
        })
        .catch(() => {
            requestErrors.textContent = 'Something went wrong. Please try again.';
            requestErrors.hidden = false;
            rowButtons.forEach((b) => { b.disabled = false; });
        });
    };

    // Pending list and the "Recently decided" list (Reopen) share one handler.
    requestsPanel.addEventListener('click', handleRequestClick);
    const decidedPanel = document.getElementById('decidedPanel');
    if (decidedPanel) decidedPanel.addEventListener('click', handleRequestClick);
}

// ===================== Edit assignment modal =====================
// Opens from the pencil / Edit buttons (.js-edit-assignment). Each button carries the
// assignment's current values in data-assignment, so no extra request is needed.
(function () {
    const modal = document.getElementById('assignmentEditModal');
    if (!modal) return;

    const $ = (id) => document.getElementById(id);
    const f = {
        id: $('aeId'), fromDetail: $('aeFromDetail'), title: $('aeTitle'), desc: $('aeDescription'),
        due: $('aeDue'), dueHint: $('aeDueHint'), attempts: $('aeAttempts'), attemptsHint: $('aeAttemptsHint'),
        points: $('aePoints'), type: $('aeType'),
    };

    const pad = (n) => String(n).padStart(2, '0');
    const toLocalInput = (d) =>
        d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes());

    function updateDueHint() {
        if (!f.due.value) {
            f.dueHint.textContent = 'No deadline — students can submit any time.';
            return;
        }
        const d = new Date(f.due.value);
        f.dueHint.textContent = d.getTime() < Date.now()
            ? 'This date is in the past — students will be locked out.'
            : 'Students can submit until this time.';
    }

    function openModal(btn) {
        let data;
        try { data = JSON.parse(btn.dataset.assignment); } catch (e) { return; }

        f.id.value = data.id;
        f.fromDetail.value = btn.dataset.fromDetail ? '1' : '';
        f.title.value = data.title;
        f.desc.value = data.description || '';
        f.due.value = data.due || '';
        f.attempts.value = data.maxAttempts;
        f.attempts.min = Math.max(1, data.attemptsUsed || 0);
        f.points.value = data.points;
        f.points.min = Math.max(1, data.highestScore || 0);
        f.type.value = data.type;

        f.attemptsHint.textContent = data.attemptsUsed > 0
            ? 'A student has already used ' + data.attemptsUsed + ' — you can raise this but not go below it.'
            : 'No submissions yet.';
        updateDueHint();

        modal.hidden = false;
        f.title.focus();
    }

    function closeModal() { modal.hidden = true; }

    document.querySelectorAll('.js-edit-assignment').forEach((btn) => {
        btn.addEventListener('click', () => openModal(btn));
    });
    $('aeClose').addEventListener('click', closeModal);
    $('aeCancel').addEventListener('click', closeModal);
    modal.addEventListener('mousedown', (e) => { if (e.target === modal) closeModal(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !modal.hidden) closeModal(); });

    // Deadline quick-extend: add days to the current deadline, or to now if it is empty/already past.
    modal.querySelectorAll('[data-add-days]').forEach((chip) => {
        chip.addEventListener('click', () => {
            const days = parseInt(chip.dataset.addDays, 10);
            let base = f.due.value ? new Date(f.due.value) : new Date();
            if (isNaN(base.getTime()) || base.getTime() < Date.now()) base = new Date();
            base.setDate(base.getDate() + days);
            f.due.value = toLocalInput(base);
            updateDueHint();
        });
    });
    $('aeClearDue').addEventListener('click', () => { f.due.value = ''; updateDueHint(); });
    f.due.addEventListener('input', updateDueHint);

    $('aeAddAttempt').addEventListener('click', () => {
        f.attempts.value = (parseInt(f.attempts.value, 10) || 0) + 1;
    });
})();