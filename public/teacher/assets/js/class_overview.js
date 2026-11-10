// ===================== Add Material form (mirrors the New Assignment form) =====================
const btnShowUpload = document.getElementById('btnShowUpload');
const btnCancelUpload = document.getElementById('btnCancelUpload');
const uploadForm = document.getElementById('materialUploadForm');

if (uploadForm) {
    const $m = (id) => document.getElementById(id);
    const srcFile = uploadForm.querySelector('.material-source-file');
    const srcLink = uploadForm.querySelector('.material-source-link');
    const urlInput = $m('materialUrl');
    const fileInput = $m('materialFile');
    const drop = $m('matDrop'), chip = $m('matFile'), err = $m('matFileError');
    const MAX_MAT = 25 * 1024 * 1024;

    // Opens the form and hides the button while it's open (close with Cancel / the X)
    if (btnShowUpload) {
        btnShowUpload.addEventListener('click', () => {
            uploadForm.hidden = false;
            btnShowUpload.hidden = true;
            uploadForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
            setTimeout(() => $m('materialTitle') && $m('materialTitle').focus({ preventScroll: true }), 250);
        });
    }
    function closeUpload() {
        uploadForm.hidden = true;
        if (btnShowUpload) btnShowUpload.hidden = false;
        uploadForm.reset();
        uploadForm.dispatchEvent(new Event('reset'));
    }
    if (btnCancelUpload) btnCancelUpload.addEventListener('click', closeUpload);
    const btnClose = $m('btnCloseUpload');
    if (btnClose) btnClose.addEventListener('click', closeUpload);

    // File vs link
    function syncSource() {
        const checked = uploadForm.querySelector('input[name="source"]:checked');
        const isFile = !checked || checked.value === 'file';
        if (srcFile) srcFile.hidden = !isFile;
        if (srcLink) srcLink.hidden = isFile;
        if (urlInput) urlInput.required = !isFile;
    }
    uploadForm.querySelectorAll('input[name="source"]').forEach((r) => r.addEventListener('change', syncSource));

    // Dropzone
    function fmtSize(n) { return n >= 1048576 ? (n / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(n / 1024)) + ' KB'; }
    function showMatFile() {
        err.hidden = true;
        let f = fileInput.files && fileInput.files[0];
        if (f && f.size > MAX_MAT) {
            fileInput.value = '';
            err.textContent = '\u201c' + f.name + '\u201d is ' + fmtSize(f.size) + ' \u2014 the limit is 25 MB.';
            err.hidden = false;
            f = null;
        }
        chip.hidden = !f;
        drop.classList.toggle('has-file', !!f);
        if (f) { $m('matFileName').textContent = f.name; $m('matFileSize').textContent = fmtSize(f.size); }
    }
    fileInput.addEventListener('change', showMatFile);
    $m('matFileRemove').addEventListener('click', () => { fileInput.value = ''; showMatFile(); });
    ['dragenter', 'dragover'].forEach((ev) => drop.addEventListener(ev, (e) => { e.preventDefault(); drop.classList.add('is-over'); }));
    ['dragleave', 'drop'].forEach((ev) => drop.addEventListener(ev, () => drop.classList.remove('is-over')));
    drop.addEventListener('drop', (e) => {
        e.preventDefault();
        if (e.dataTransfer && e.dataTransfer.files.length) { fileInput.files = e.dataTransfer.files; showMatFile(); }
    });

    uploadForm.addEventListener('reset', () => setTimeout(() => { syncSource(); showMatFile(); }, 0));
    syncSource();
}

// Toggle the "New Assignment" form open/closed
const btnShowAssignmentForm = document.getElementById('btnShowAssignmentForm');
const btnCancelAssignmentForm = document.getElementById('btnCancelAssignmentForm');
const assignmentForm = document.getElementById('assignmentCreateForm');

if (btnShowAssignmentForm && assignmentForm) {
    // Opens the form and hides the button while it's open (closing is done by Cancel / the X),
    // so the button never acts as a second "cancel".
    btnShowAssignmentForm.addEventListener('click', () => {
        assignmentForm.hidden = false;
        btnShowAssignmentForm.hidden = true;
    });
}
if (btnCancelAssignmentForm && assignmentForm) {
    btnCancelAssignmentForm.addEventListener('click', () => {
        assignmentForm.hidden = true;
        if (btnShowAssignmentForm) btnShowAssignmentForm.hidden = false;
        assignmentForm.reset();
        assignmentForm.dispatchEvent(new Event('reset'));
    });
}

// ---- New-assignment form niceties: close button, due-date chips, attempts stepper, file drop ----
(function () {
    if (!assignmentForm) return;
    const $ = (id) => document.getElementById(id);

    // Focus the title when the form opens
    if (btnShowAssignmentForm) {
        btnShowAssignmentForm.addEventListener('click', () => {
            if (!assignmentForm.hidden) {
                assignmentForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
                setTimeout(() => $('assignmentTitle') && $('assignmentTitle').focus({ preventScroll: true }), 250);
            }
        });
    }
    const closeBtn = $('btnCloseAssignmentForm');
    if (closeBtn) closeBtn.addEventListener('click', () => btnCancelAssignmentForm.click());

    // Quick due-date chips (end of day) and Clear
    const due = $('assignmentDueDate');
    const pad = (n) => String(n).padStart(2, '0');
    document.querySelectorAll('#asgDueQuick button').forEach((b) => {
        b.addEventListener('click', () => {
            const days = parseInt(b.dataset.days, 10);
            if (!days) { due.value = ''; return; }
            const d = new Date();
            d.setDate(d.getDate() + days);
            d.setHours(23, 59, 0, 0);
            due.value = d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes());
        });
    });

    // Attempts stepper (1-10)
    const att = $('assignmentMaxAttempts');
    const stepBtns = assignmentForm.querySelectorAll('.asg-stepper button');
    function clampAttempts() {
        let v = parseInt(att.value, 10);
        if (isNaN(v)) v = 1;
        v = Math.min(10, Math.max(1, v));
        att.value = v;
        stepBtns[0].disabled = v <= 1;
        stepBtns[1].disabled = v >= 10;
    }
    stepBtns.forEach((b) => b.addEventListener('click', () => {
        att.value = (parseInt(att.value, 10) || 1) + parseInt(b.dataset.step, 10);
        clampAttempts();
    }));
    att.addEventListener('change', clampAttempts);
    clampAttempts();

    // Attachment dropzone
    const MAX = 25 * 1024 * 1024;
    const input = $('assignmentFile'), drop = $('asgDrop'), chip = $('asgFile'), err = $('asgFileError');
    function fmt(n) { return n >= 1048576 ? (n / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(n / 1024)) + ' KB'; }
    function showFile() {
        const f = input.files && input.files[0];
        err.hidden = true;
        if (f && f.size > MAX) {
            input.value = '';
            err.textContent = '“' + f.name + '” is ' + fmt(f.size) + ' — the limit is 25 MB.';
            err.hidden = false;
        }
        const file = input.files && input.files[0];
        chip.hidden = !file;
        drop.classList.toggle('has-file', !!file);
        if (file) { $('asgFileName').textContent = file.name; $('asgFileSize').textContent = fmt(file.size); }
    }
    input.addEventListener('change', showFile);
    $('asgFileRemove').addEventListener('click', () => { input.value = ''; showFile(); });
    ['dragenter', 'dragover'].forEach((ev) => drop.addEventListener(ev, (e) => { e.preventDefault(); drop.classList.add('is-over'); }));
    ['dragleave', 'drop'].forEach((ev) => drop.addEventListener(ev, () => drop.classList.remove('is-over')));
    drop.addEventListener('drop', (e) => {
        e.preventDefault();
        if (e.dataTransfer && e.dataTransfer.files.length) { input.files = e.dataTransfer.files; showFile(); }
    });

    assignmentForm.addEventListener('reset', () => setTimeout(() => { showFile(); clampAttempts(); }, 0));
})();

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
        f.attempts.max = 10;
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
        f.attempts.value = Math.min(10, (parseInt(f.attempts.value, 10) || 0) + 1);
    });
})();

// ===================== Edit quiz modal =====================
// Opens from the pencil / Edit buttons (.js-edit-quiz). Each button carries the quiz's
// current values in data-quiz, so no extra request is needed.
(function () {
    const modal = document.getElementById('quizEditModal');
    if (!modal) return;

    const $ = (id) => document.getElementById(id);
    const f = {
        id: $('qeId'), fromDetail: $('qeFromDetail'), title: $('qeTitle'), desc: $('qeDescription'),
        from: $('qeFrom'), until: $('qeUntil'), dateHint: $('qeDateHint'),
        attempts: $('qeAttempts'), attemptsHint: $('qeAttemptsHint'),
        timeLimit: $('qeTimeLimit'), status: $('qeStatus'),
    };

    const pad = (n) => String(n).padStart(2, '0');
    const toLocalInput = (d) =>
        d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes());

    function updateDateHint() {
        const from = f.from.value ? new Date(f.from.value) : null;
        const until = f.until.value ? new Date(f.until.value) : null;
        if (from && until && until <= from) {
            f.dateHint.textContent = 'The due date must be after the available-from date.';
        } else if (until && until.getTime() < Date.now()) {
            f.dateHint.textContent = 'The due date is in the past — students will be locked out.';
        } else if (!from && !until) {
            f.dateHint.textContent = 'No dates set — students can take it any time while it is published.';
        } else {
            f.dateHint.textContent = '';
        }
    }

    function openModal(btn) {
        let data;
        try { data = JSON.parse(btn.dataset.quiz); } catch (e) { return; }

        f.id.value = data.id;
        f.fromDetail.value = btn.dataset.fromDetail ? '1' : '';
        f.title.value = data.title;
        f.desc.value = data.description || '';
        f.from.value = data.from || '';
        f.until.value = data.until || '';
        f.attempts.value = data.maxAttempts;
        f.attempts.min = Math.max(1, data.attemptsUsed || 0);
        f.attempts.max = 10;
        f.timeLimit.value = data.timeLimit === '' ? '' : data.timeLimit;
        f.status.value = data.status;

        f.attemptsHint.textContent = data.attemptsUsed > 0
            ? 'A student has already used ' + data.attemptsUsed + ' — you can raise this but not go below it.'
            : 'No attempts yet.';
        updateDateHint();

        modal.hidden = false;
        f.title.focus();
    }

    function closeModal() { modal.hidden = true; }

    document.querySelectorAll('.js-edit-quiz').forEach((btn) => {
        btn.addEventListener('click', () => openModal(btn));
    });
    $('qeClose').addEventListener('click', closeModal);
    $('qeCancel').addEventListener('click', closeModal);
    modal.addEventListener('mousedown', (e) => { if (e.target === modal) closeModal(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !modal.hidden) closeModal(); });

    // Quick-extend the due date: add days to the current one, or to now if empty/already past.
    modal.querySelectorAll('[data-qe-add-days]').forEach((chip) => {
        chip.addEventListener('click', () => {
            const days = parseInt(chip.dataset.qeAddDays, 10);
            let base = f.until.value ? new Date(f.until.value) : new Date();
            if (isNaN(base.getTime()) || base.getTime() < Date.now()) base = new Date();
            base.setDate(base.getDate() + days);
            f.until.value = toLocalInput(base);
            updateDateHint();
        });
    });
    $('qeClearFrom').addEventListener('click', () => { f.from.value = ''; updateDateHint(); });
    $('qeClearUntil').addEventListener('click', () => { f.until.value = ''; updateDateHint(); });
    f.from.addEventListener('input', updateDateHint);
    f.until.addEventListener('input', updateDateHint);
})();


// Arriving from the dashboard's grading queue (#student-<id>): scroll to that
// student's row and flash it so the teacher can grade right away.
(function () {
    var m = /^#student-(\d+)$/.exec(location.hash);
    if (!m) return;
    var row = document.getElementById('student-' + m[1]);
    if (!row) return;
    row.scrollIntoView({ behavior: 'smooth', block: 'center' });
    row.classList.add('row-flash');
    var input = row.querySelector('input[type="number"]');
    if (input) setTimeout(function () { input.focus({ preventScroll: true }); }, 500);
})();
