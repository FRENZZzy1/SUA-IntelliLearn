// courses.js
// Extracted from courses.php. Expects two globals to be defined by an inline
// <script> block BEFORE this file is loaded (see courses.php):
//   window.TOTAL_COURSES        (int)
//   window.courseOfferingsData  (object keyed by offering_id)

// ---- Sidebar collapse/expand (shared with sidebar module) ----
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('collapsed');
}

// ---- Sidebar nav active state (shared with sidebar module) ----
function setActive(el) {
    document.querySelectorAll('.nav-item').forEach(item => item.classList.remove('active'));
    el.classList.add('active');
}

// ---- Archive / restore confirmation ----
// Nothing is deleted any more; archiving just hides the item (restorable).
function confirmArchive(type) {
    const messages = {
        'subject':         'Archive this subject? It can no longer be used for new classes. Existing classes are not affected.',
        'restore-subject': 'Restore this subject?',
    };
    return confirm(messages[type] || messages['restore-subject']);
}

// ---- View tabs: Classes / Subjects / Sections / Search & Export ----
// Exactly one view is shown at a time. The active tab is mirrored in the
// URL (?open=...) so a refresh keeps the admin on the same tab.
const CS_VIEWS = ['classes', 'subjects', 'sections', 'export'];

function showView(view) {
    if (CS_VIEWS.indexOf(view) === -1) view = 'classes';

    CS_VIEWS.forEach(function (v) {
        const panel = document.getElementById('view-' + v);
        if (panel) panel.classList.toggle('open', v === view);

        const tab = document.querySelector('[data-view-tab="' + v + '"]');
        if (tab) {
            tab.classList.toggle('active', v === view);
            tab.setAttribute('aria-selected', v === view ? 'true' : 'false');
        }
    });

    try {
        const url = new URL(window.location.href);
        if (view === 'classes') {
            url.searchParams.delete('open');
        } else {
            url.searchParams.set('open', view);
        }
        window.history.replaceState(null, '', url.toString());
    } catch (e) { /* non-critical */ }
}

// Kept for backwards compatibility with older markup.
function togglePanel(view) {
    showView(view);
}

// ---- Click-to-copy class code ----
function copyClassCode(btn) {
    const code = btn.dataset.code || '';
    const done = function () {
        const icon = btn.querySelector('i');
        btn.classList.add('copied');
        if (icon) icon.className = 'fas fa-check';
        setTimeout(function () {
            btn.classList.remove('copied');
            if (icon) icon.className = 'far fa-copy';
        }, 1300);
    };

    if (navigator.clipboard && window.isSecureContext !== false) {
        navigator.clipboard.writeText(code).then(done).catch(function () { legacyCopy(code); done(); });
    } else {
        legacyCopy(code);
        done();
    }
}

function legacyCopy(text) {
    const ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    try { document.execCommand('copy'); } catch (e) { /* ignore */ }
    document.body.removeChild(ta);
}

// ---- Generic modal submit helper ----
// Posts a form to an endpoint, shows validation errors inline, and
// reloads on success so the flash message + updated list/stats appear.
function syncScheduleDays(form) {
    const selected = Array.from(form.querySelectorAll('input[name="schedule_day_options[]"]:checked')).map(input => input.value);
    const hidden = form.querySelector('input[name="schedule_days"]');
    if (hidden) hidden.value = selected.join(', ');
}

function setScheduleDayCheckboxes(form, rawValue) {
    const boxes = Array.from(form.querySelectorAll('input[name="schedule_day_options[]"]'));
    const names = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    const selected = new Set();
    const raw = String(rawValue || '').trim();
    if (raw.includes(',')) {
        raw.split(',').map(v => v.trim().toLowerCase()).forEach(token => {
            const match = names.find(day => day.toLowerCase() === token);
            if (match) selected.add(match);
        });
    } else {
        const compact = raw.replace(/[^a-z]/gi, '');
        let i = 0;
        while (i < compact.length) {
            if (compact.slice(i, i + 2).toLowerCase() === 'th') { selected.add('Thursday'); i += 2; continue; }
            const map = { M: 'Monday', T: 'Tuesday', W: 'Wednesday', F: 'Friday', S: 'Saturday' };
            if (map[compact[i].toUpperCase()]) selected.add(map[compact[i].toUpperCase()]);
            i++;
        }
    }
    boxes.forEach(box => { box.checked = selected.has(box.value); });
    const hidden = form.querySelector('input[name="schedule_days"]');
    if (hidden) hidden.value = Array.from(selected).join(', ');
}

function submitModalForm(form, url, submitBtn, errorBox, idleLabel, openPanel) {
    syncScheduleDays(form);
    errorBox.hidden = true;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

    fetch(url, {
        method: 'POST',
        headers: { 'Accept': 'application/json' },
        body: new FormData(form)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            if (openPanel) {
                const params = new URLSearchParams(window.location.search);
                params.set('open', openPanel);
                window.location.href = 'courses.php?' + params.toString();
            } else {
                location.reload();
            }
        } else {
            errorBox.innerHTML = data.errors.map(err => '<div>' + err + '</div>').join('');
            errorBox.hidden = false;
            submitBtn.disabled = false;
            submitBtn.innerHTML = idleLabel;
        }
    })
    .catch(() => {
        errorBox.innerHTML = '<div>Something went wrong. Please try again.</div>';
        errorBox.hidden = false;
        submitBtn.disabled = false;
        submitBtn.innerHTML = idleLabel;
    });
}

// ---- Term-sibling accordion: each class's older terms are rendered as
// hidden .sibling-row rows sharing the same data-group as their primary
// (most-recent-term) row. The toggle button just flips them between
// display:none and visible, and flips its own arrow/label to match.
function toggleCourseSiblings(btn) {
    const key = btn.dataset.group;
    const expand = btn.getAttribute('aria-expanded') !== 'true';
    const siblings = document.querySelectorAll(
        '#courseTableBody tr.sibling-row[data-group="' + CSS.escape(key) + '"]'
    );

    siblings.forEach(row => {
        row.style.display = expand ? '' : 'none';
        row.dataset.expanded = expand ? 'true' : 'false';
    });

    btn.setAttribute('aria-expanded', expand ? 'true' : 'false');
    btn.querySelector('.chevron').textContent = expand ? '▾' : '▸';
}

// ---- Live search: instantly filters the Class List table ----
// Matches each row's pre-baked data-search string (subject + section +
// teacher name, all lowercase) against whatever the admin types.
// No server round-trip — pure client-side show/hide.
//
// Sibling (older-term) rows are collapsed by default, so a plain
// row-by-row filter would either leave a matching sibling hidden or
// force every sibling open once the box is cleared. Instead this groups
// rows by data-group first: if anything in a group matches, the primary
// row is always shown for context, and while actively searching every
// sibling in that group is revealed too (so the matching term is visible
// without hunting for the toggle). Clearing the search restores each
// group to whatever its toggle arrow was last set to.
let noResultsRow = null;

function getOrCreateNoResultsRow() {
    if (noResultsRow) return noResultsRow;
    const tbody = document.getElementById('courseTableBody');
    noResultsRow = document.createElement('tr');
    noResultsRow.className = 'no-results-row';
    noResultsRow.innerHTML = '<td colspan="8"><div class="empty-state"><div class="empty-icon"><i class="fas fa-magnifying-glass"></i></div><strong>No classes match your search</strong><span>Try a different teacher, subject, or section.</span></div></td>';
    tbody.appendChild(noResultsRow);
    return noResultsRow;
}

function filterCourseList(rawValue) {
    const q = rawValue.trim().toLowerCase();
    const clearBtn = document.getElementById('liveSearchClearBtn');
    clearBtn.classList.toggle('show', q.length > 0);
    const searching = q !== '';

    const groups = new Map();
    document.querySelectorAll('#courseTableBody tr.course-row').forEach(row => {
        const key = row.dataset.group || row;
        if (!groups.has(key)) groups.set(key, { primary: null, siblings: [] });
        const group = groups.get(key);
        if (row.classList.contains('sibling-row')) {
            group.siblings.push(row);
        } else {
            group.primary = row;
        }
    });

    let visible = 0;
    groups.forEach(group => {
        const allRows = [group.primary, ...group.siblings].filter(Boolean);
        const groupMatches = allRows.some(row => q === '' || row.dataset.search.includes(q));

        if (!groupMatches) {
            allRows.forEach(row => { row.style.display = 'none'; });
            return;
        }

        group.primary.style.display = '';
        visible++;

        group.siblings.forEach(sib => {
            if (searching) {
                sib.style.display = '';
                visible++;
            } else {
                // Restore each sibling to whatever the toggle arrow last set.
                sib.style.display = sib.dataset.expanded === 'true' ? '' : 'none';
            }
        });
    });

    const rows = document.querySelectorAll('#courseTableBody tr.course-row');
    const noRow = getOrCreateNoResultsRow();
    noRow.style.display = (visible === 0 && rows.length > 0) ? '' : 'none';

    const label = window.COURSES_ARCHIVED_VIEW
        ? 'Showing ' + visible + ' archived ' + (visible === 1 ? 'class' : 'classes')
        : 'Showing ' + visible + ' of ' + window.TOTAL_COURSES + ' courses';
    const top = document.getElementById('courseCountTop');
    const bottom = document.getElementById('courseCountBottom');
    if (top) top.textContent = label;
    if (bottom) bottom.textContent = label;
}

function clearCourseListSearch() {
    const input = document.getElementById('liveSearchInput');
    input.value = '';
    filterCourseList('');
    input.focus();
}

// ---- Search & Export panel ----
const exportEndpoints = {
    teacher: 'assests/api/export_teacher_classes.php?teacher_id=',
    section: 'assests/api/export_section_classes.php?section_id=',
    student: 'assests/api/export_student_classes.php?student_id=',
};
const exportSelected_ = { teacher: null, section: null, student: null };
const exportDebounce_ = { teacher: null, section: null, student: null };

// Characters each export search box accepts. Everything else is blocked.
//   teacher / student: letters + spaces only (no numbers, no special characters)
//   section: letters, digits, spaces and the dash (-) only
const exportAllowed = {
    teacher: { bad: /[^\p{L}\p{M} ]/gu,       test: /^[\p{L}\p{M} ]*$/u,
               msg: 'Letters only. Numbers and special characters are not allowed.' },
    student: { bad: /[^\p{L}\p{M} ]/gu,       test: /^[\p{L}\p{M} ]*$/u,
               msg: 'Letters only. Numbers and special characters are not allowed.' },
    section: { bad: /[^\p{L}\p{M}0-9 \-]/gu, test: /^[\p{L}\p{M}0-9 \-]*$/u,
               msg: 'Letters, numbers and the dash (-) only. Other special characters are not allowed.' },
};
const exportWarnTimer_ = {};

function exportCleanValue(type, text) {
    return text.replace(exportAllowed[type].bad, '').replace(/^ +/, '').replace(/ {2,}/g, ' ');
}

function exportWarn(type, show) {
    const input = document.getElementById('export' + capitalize(type) + 'Input');
    if (!input) return;
    let el = document.getElementById('export' + capitalize(type) + 'Warn');
    if (!el) {
        el = document.createElement('small');
        el.id = 'export' + capitalize(type) + 'Warn';
        el.setAttribute('role', 'alert');
        el.style.cssText = 'display:none;margin-top:6px;font-size:.78rem;line-height:1.35;color:#dc2626;';
        input.closest('.export-search-box').insertAdjacentElement('afterend', el);
    }
    clearTimeout(exportWarnTimer_[type]);
    if (show) {
        el.textContent = exportAllowed[type].msg;
        el.style.display = 'block';
        exportWarnTimer_[type] = setTimeout(() => { el.style.display = 'none'; }, 2500);
    } else {
        el.style.display = 'none';
    }
}

// Block disallowed characters as they are typed / pasted into the three search boxes.
['teacher', 'section', 'student'].forEach(function (type) {
    const input = document.getElementById('export' + capitalize(type) + 'Input');
    if (!input) return;
    input.addEventListener('beforeinput', function (e) {
        if (e.isComposing || !e.inputType || e.inputType.indexOf('insert') !== 0) return;
        if (typeof e.data !== 'string' || e.data === '') return;   // paste/drop handled by input event
        if (!exportAllowed[type].test.test(e.data)) {
            e.preventDefault();
            exportWarn(type, true);
        }
    });
    input.addEventListener('compositionend', function () {
        input.dispatchEvent(new Event('input', { bubbles: true }));
    });
});

function exportSearchInput(type, value) {
    // Strip anything that slipped through (paste, drop, autofill, IME).
    const input = document.getElementById('export' + capitalize(type) + 'Input');
    const cleaned = exportCleanValue(type, value);
    if (cleaned !== value) {
        const pos = input.selectionStart;
        input.value = cleaned;
        try { const p = Math.max(0, pos - (value.length - cleaned.length)); input.setSelectionRange(p, p); } catch (_) {}
        exportWarn(type, true);
        value = cleaned;
    }

    // Typing again after a selection invalidates it.
    if (exportSelected_[type]) {
        exportSelected_[type] = null;
        document.getElementById('export' + capitalize(type) + 'Chip').hidden = true;
        document.getElementById('export' + capitalize(type) + 'Btn').disabled = true;
    }

    clearTimeout(exportDebounce_[type]);
    const dropdown = document.getElementById('export' + capitalize(type) + 'Dropdown');

    const q = value.trim();
    if (q.length < 1) {
        dropdown.hidden = true;
        dropdown.innerHTML = '';
        return;
    }

    exportDebounce_[type] = setTimeout(() => {
        fetch('assests/api/search_entities.php?type=' + type + '&q=' + encodeURIComponent(q), {
            headers: { 'Accept': 'application/json' }
        })
        .then(res => res.json())
        .then(data => {
            dropdown.innerHTML = '';
            if (!data.success || data.results.length === 0) {
                dropdown.innerHTML = '<div class="export-search-dropdown-empty"><i class="fas fa-magnifying-glass"></i><span>No matches found</span><small>Try a different spelling.</small></div>';
                dropdown.hidden = false;
                return;
            }

            const icons = { teacher: 'fa-chalkboard-teacher', section: 'fa-layer-group', student: 'fa-user-graduate' };
            const head = document.createElement('div');
            head.className = 'esd-head';
            head.textContent = data.results.length + (data.results.length === 1 ? ' match' : ' matches');
            dropdown.appendChild(head);

            data.results.forEach(function (r) {
                // sublabel looks like "Department · 2 classes" -> split meta / class count
                const parts = String(r.sublabel || '').split(' \u00b7 ');
                const count = parts.length > 1 ? parts.pop() : '';
                const meta = parts.join(' \u00b7 ');
                const num = parseInt(count, 10);

                const item = document.createElement('div');
                item.className = 'export-search-dropdown-item';
                item.setAttribute('role', 'option');
                item.innerHTML =
                    '<span class="esdi-avatar esdi-' + type + '"></span>' +
                    '<span class="esdi-text"><span class="esdi-name"></span><span class="esdi-sub"></span></span>' +
                    '<span class="esdi-badge"></span>';

                const avatar = item.querySelector('.esdi-avatar');
                if (type === 'section') {
                    avatar.innerHTML = '<i class="fas ' + icons.section + '"></i>';
                } else {
                    const w = String(r.label).trim().split(/\s+/);
                    avatar.textContent = ((w[0] || '').charAt(0) + (w.length > 1 ? w[w.length - 1].charAt(0) : '')).toUpperCase();
                }

                // Highlight the typed text inside the name
                const nameEl = item.querySelector('.esdi-name');
                const idx = r.label.toLowerCase().indexOf(q.toLowerCase());
                if (idx >= 0) {
                    nameEl.appendChild(document.createTextNode(r.label.slice(0, idx)));
                    const mark = document.createElement('mark');
                    mark.textContent = r.label.slice(idx, idx + q.length);
                    nameEl.appendChild(mark);
                    nameEl.appendChild(document.createTextNode(r.label.slice(idx + q.length)));
                } else {
                    nameEl.textContent = r.label;
                }

                item.querySelector('.esdi-sub').textContent = meta || count;
                const badge = item.querySelector('.esdi-badge');
                badge.textContent = count;
                if (!count) badge.style.display = 'none';
                if (num === 0) badge.classList.add('is-empty');

                item.onclick = function () { selectExportEntity(type, r.id, r.label, r.sublabel); };
                dropdown.appendChild(item);
            });
            dropdown.hidden = false;
        })
        .catch(() => {
            dropdown.innerHTML = '<div class="export-search-dropdown-empty"><i class="fas fa-triangle-exclamation"></i><span>Something went wrong</span><small>Please try again.</small></div>';
            dropdown.hidden = false;
        });
    }, 250);
}

function selectExportEntity(type, id, label, sublabel) {
    exportSelected_[type] = { id, label };

    const input = document.getElementById('export' + capitalize(type) + 'Input');
    const dropdown = document.getElementById('export' + capitalize(type) + 'Dropdown');
    const chip = document.getElementById('export' + capitalize(type) + 'Chip');
    const btn = document.getElementById('export' + capitalize(type) + 'Btn');

    input.value = label;
    dropdown.hidden = true;
    dropdown.innerHTML = '';

    const parts = String(sublabel || '').split(' \u00b7 ');
    chip.innerHTML = '<i class="fas fa-circle-check esc-check"></i>'
        + '<span class="esc-text"><strong>' + escapeHtml(label) + '</strong><small>' + escapeHtml(parts.join(' \u00b7 ')) + '</small></span>'
        + '<button type="button" onclick="clearExportSelection(\'' + type + '\')" aria-label="Clear selection">&times;</button>';
    chip.hidden = false;
    btn.disabled = false;
}

function clearExportSelection(type) {
    exportSelected_[type] = null;
    document.getElementById('export' + capitalize(type) + 'Input').value = '';
    document.getElementById('export' + capitalize(type) + 'Chip').hidden = true;
    document.getElementById('export' + capitalize(type) + 'Btn').disabled = true;
    document.getElementById('export' + capitalize(type) + 'Input').focus();
}

function exportSelected(type) {
    const sel = exportSelected_[type];
    if (!sel) return;
    window.location = exportEndpoints[type] + encodeURIComponent(sel.id);
}

function capitalize(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
}

// Close any open export dropdown when clicking elsewhere on the page.
document.addEventListener('click', function (e) {
    ['teacher', 'section', 'student'].forEach(function (type) {
        const box = document.getElementById('export' + capitalize(type) + 'Input')?.closest('.export-search-box');
        const dropdown = document.getElementById('export' + capitalize(type) + 'Dropdown');
        if (box && dropdown && !box.contains(e.target)) {
            dropdown.hidden = true;
        }
    });
});

// ---- Update Course modal ----
function openEditCourseModal(triggerElOrData) {
    const course = (triggerElOrData instanceof Element)
        ? JSON.parse(triggerElOrData.dataset.course)
        : triggerElOrData;

    document.getElementById('e_offering_id').value = course.offering_id;
    document.getElementById('e_subject_id').value = course.subject_id;
    document.getElementById('e_section_id').value = course.section_id;
    document.getElementById('e_teacher_id').value = course.teacher_id;
    document.getElementById('e_quarter_display').textContent = course.quarter || '—';
    document.getElementById('e_school_year_id').value = course.school_year_id;
    document.getElementById('e_capacity').value = course.capacity;
    document.getElementById('e_status').value = course.status;
    setScheduleDayCheckboxes(document.getElementById('editCourseForm'), course.schedule_days || '');
    document.getElementById('e_start_time').value = course.start_time || '';
    document.getElementById('e_end_time').value = course.end_time || '';

    document.getElementById('editCourseErrors').hidden = true;
    document.getElementById('editCourseOverlay').classList.add('open');
}

function closeEditCourseModal() {
    document.getElementById('editCourseOverlay').classList.remove('open');
}

// ---- Course data lookup + auto-open (used when arriving from the dashboard) ----
(function openEditFromQueryString() {
    const params = new URLSearchParams(window.location.search);
    const editId = params.get('edit_offering');
    if (!editId) return;

    const data = window.courseOfferingsData[editId];
    if (data) {
        openEditCourseModal(data);
    }

    // Clean the URL so a page refresh doesn't reopen the modal.
    params.delete('edit_offering');
    const newUrl = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
    window.history.replaceState({}, '', newUrl);
})();

document.getElementById('editCourseForm').addEventListener('submit', function (e) {
    e.preventDefault();
    submitModalForm(
        this,
        'update_course.php',
        document.getElementById('editCourseSubmitBtn'),
        document.getElementById('editCourseErrors'),
        '<i class="fas fa-check"></i> Save Changes'
    );
});

// ---- View Students modal ----
function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str ?? '';
    return div.innerHTML;
}

let currentViewOfferingId = null;
let vsAllStudents = [];   // full roster for the currently open offering
let vsCourseInfo = null;  // { subject_name, section_name, grade_level, capacity }
let vsSelected = new Set(); // student_ids ticked for unenrolling (survives search/filter changes)
let vsChanged = false;      // true once something was unenrolled -> refresh page counts on close

function openViewStudentsModal(offeringId) {
    const overlay = document.getElementById('viewStudentsOverlay');
    const errorBox = document.getElementById('viewStudentsErrors');
    const tbody = document.getElementById('vsStudentsTableBody');
    const title = document.getElementById('vsModalTitle');
    const subtitle = document.getElementById('vsModalSubtitle');
    const exportBtn = document.getElementById('vsExportBtn');
    const searchInput = document.getElementById('vsSearchInput');
    const genderFilter = document.getElementById('vsGenderFilter');

    currentViewOfferingId = offeringId;
    vsAllStudents = [];
    vsCourseInfo = null;
    vsSelected = new Set();
    vsChanged = false;
    exportBtn.disabled = true;
    updateVsUnenrollState();

    // reset search/filter state each time the modal is opened for a course
    searchInput.value = '';
    genderFilter.value = 'all';

    errorBox.hidden = true;
    title.textContent = 'Enrolled Students';
    subtitle.textContent = '';
    tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding: 24px; color: var(--text-muted);">Loading...</td></tr>';
    overlay.classList.add('open');

    fetch('assests/api/get_course_students.php?offering_id=' + encodeURIComponent(offeringId), {
        method: 'GET',
        headers: { 'Accept': 'application/json' }
    })
    .then(res => res.json())
    .then(data => {
        if (!data.success) {
            tbody.innerHTML = '';
            errorBox.innerHTML = (data.errors || ['Something went wrong.']).map(err => '<div>' + escapeHtml(err) + '</div>').join('');
            errorBox.hidden = false;
            return;
        }

        exportBtn.disabled = data.students.length === 0;

        vsAllStudents = data.students;
        vsCourseInfo = data.course;

        title.textContent = vsCourseInfo.subject_name + ' — ' + vsCourseInfo.section_name;

        renderVsStudents(vsAllStudents);
    })
    .catch(() => {
        tbody.innerHTML = '';
        errorBox.innerHTML = '<div>Something went wrong. Please try again.</div>';
        errorBox.hidden = false;
    });
}

// Renders a given list of students into the table and keeps the
// subtitle's enrolled count in sync with what's actually showing.
function renderVsStudents(list) {
    const tbody = document.getElementById('vsStudentsTableBody');
    const subtitle = document.getElementById('vsModalSubtitle');

    if (vsCourseInfo) {
        const filtered = list.length !== vsAllStudents.length;
        const activeCount = vsAllStudents.filter(function (s) { return s.status === 'enrolled'; }).length;
        subtitle.textContent = 'Grade ' + vsCourseInfo.grade_level
            + ' · ' + (filtered ? list.length + ' of ' + vsAllStudents.length + ' shown' : activeCount + '/' + vsCourseInfo.capacity + ' enrolled');
    }

    if (vsAllStudents.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding: 24px; color: var(--text-muted);">No students enrolled in this course yet.</td></tr>';
        updateVsUnenrollState();
        return;
    }

    if (list.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding: 24px; color: var(--text-muted);">No students match your search.</td></tr>';
        updateVsUnenrollState();
        return;
    }

    tbody.innerHTML = list.map(function (s) {
        const fullName = [s.firstname, s.middlename, s.lastname].filter(Boolean).join(' ');
        const statusLabel = s.status.charAt(0).toUpperCase() + s.status.slice(1);
        const enrolledDate = s.enrolled_at ? new Date(s.enrolled_at.replace(' ', 'T')).toLocaleDateString() : '—';
        const genderLabel = s.Gender ? (s.Gender.charAt(0).toUpperCase() + s.Gender.slice(1)) : '—';
        const isActive = s.status === 'enrolled';
        const checked = vsSelected.has(String(s.student_id)) ? ' checked' : '';
        return '<tr>'
            + '<td class="vs-check-cell">' + (isActive
                ? '<input type="checkbox" class="vs-row-check" value="' + escapeHtml(s.student_id) + '"' + checked + ' onchange="toggleVsStudent(this)" aria-label="Select ' + escapeHtml(fullName) + '">'
                : '') + '</td>'
            + '<td>' + escapeHtml(s.student_lrn) + '</td>'
            + '<td>' + escapeHtml(fullName) + '</td>'
            + '<td>' + (s.email ? escapeHtml(s.email) : '— None —') + '</td>'
            + '<td>' + escapeHtml(genderLabel) + '</td>'
            + '<td>' + (s.strand ? escapeHtml(s.strand) : '—') + '</td>'
            + '<td><span class="status-dot-badge ' + (s.status === 'enrolled' ? 'active' : 'inactive') + '"><span class="dot"></span>' + escapeHtml(statusLabel) + '</span></td>'
            + '<td>' + escapeHtml(enrolledDate) + '</td>'
            + '</tr>';
    }).join('');

    updateVsUnenrollState();
}

// ---- Unenroll (single or bulk) ----
function toggleVsStudent(checkbox) {
    if (checkbox.checked) {
        vsSelected.add(String(checkbox.value));
    } else {
        vsSelected.delete(String(checkbox.value));
    }
    updateVsUnenrollState();
}

// "Select all" ticks/unticks every active student currently visible
// (so it respects the search box and gender filter).
function toggleVsSelectAll(checked) {
    document.querySelectorAll('#vsStudentsTableBody .vs-row-check').forEach(function (cb) {
        cb.checked = checked;
        if (checked) {
            vsSelected.add(String(cb.value));
        } else {
            vsSelected.delete(String(cb.value));
        }
    });
    updateVsUnenrollState();
}

// Keeps the footer button label/enabled state and the header checkbox in sync.
function updateVsUnenrollState() {
    const btn = document.getElementById('vsUnenrollBtn');
    const label = document.getElementById('vsUnenrollLabel');
    const selectAll = document.getElementById('vsSelectAll');
    if (!btn || !label || !selectAll) return;

    const rowChecks = document.querySelectorAll('#vsStudentsTableBody .vs-row-check');
    const checkedCount = Array.prototype.filter.call(rowChecks, function (cb) { return cb.checked; }).length;

    btn.disabled = vsSelected.size === 0;
    label.textContent = vsSelected.size > 0 ? 'Unenroll (' + vsSelected.size + ')' : 'Unenroll';

    selectAll.disabled = rowChecks.length === 0;
    selectAll.checked = rowChecks.length > 0 && checkedCount === rowChecks.length;
    selectAll.indeterminate = checkedCount > 0 && checkedCount < rowChecks.length;
}

function unenrollSelectedStudents() {
    if (!currentViewOfferingId || vsSelected.size === 0) return;

    const ids = Array.from(vsSelected);
    const message = ids.length === 1
        ? 'Unenroll this student from the class? They will lose access to it, but their records are kept.'
        : 'Unenroll ' + ids.length + ' students from the class? They will lose access to it, but their records are kept.';
    if (!confirm(message)) return;

    const overlay = document.getElementById('viewStudentsOverlay');
    const errorBox = document.getElementById('viewStudentsErrors');
    const btn = document.getElementById('vsUnenrollBtn');

    const body = new URLSearchParams();
    body.append('csrf', overlay.dataset.csrf || '');
    body.append('offering_id', currentViewOfferingId);
    ids.forEach(function (id) { body.append('student_ids[]', id); });

    errorBox.hidden = true;
    btn.disabled = true;

    fetch('assests/api/unenroll_students.php', {
        method: 'POST',
        headers: { 'Accept': 'application/json' },
        body: body
    })
    .then(res => res.json())
    .then(data => {
        if (!data.success) {
            errorBox.innerHTML = (data.errors || ['Something went wrong.']).map(err => '<div>' + escapeHtml(err) + '</div>').join('');
            errorBox.hidden = false;
            updateVsUnenrollState();
            return;
        }

        // Reflect the change locally — no need to refetch the roster.
        const done = new Set(ids);
        vsAllStudents.forEach(function (s) {
            if (done.has(String(s.student_id))) s.status = 'dropped';
        });
        // Keep active students on top, matching the server's ordering.
        vsAllStudents.sort(function (a, b) {
            return (b.status === 'enrolled') - (a.status === 'enrolled');
        });
        vsSelected = new Set();
        vsChanged = true;
        filterViewStudents();
    })
    .catch(() => {
        errorBox.innerHTML = '<div>Something went wrong. Please try again.</div>';
        errorBox.hidden = false;
        updateVsUnenrollState();
    });
}

// Applies the current search text + gender filter to the in-memory
// roster (vsAllStudents) and re-renders. Runs entirely client-side,
// so no extra requests are made while typing/selecting.
function filterViewStudents() {
    const query = document.getElementById('vsSearchInput').value.trim().toLowerCase();
    const gender = document.getElementById('vsGenderFilter').value;

    const filtered = vsAllStudents.filter(function (s) {
        if (gender !== 'all' && (s.Gender || '').toLowerCase() !== gender) {
            return false;
        }

        if (query) {
            const fullName = [s.firstname, s.middlename, s.lastname].filter(Boolean).join(' ').toLowerCase();
            const lrn = String(s.student_lrn || '').toLowerCase();
            if (!fullName.includes(query) && !lrn.includes(query)) {
                return false;
            }
        }

        return true;
    });

    renderVsStudents(filtered);
}

function closeViewStudentsModal() {
    const overlay = document.getElementById('viewStudentsOverlay');
    const wasOpen = overlay.classList.contains('open');
    overlay.classList.remove('open');
    currentViewOfferingId = null;
    vsAllStudents = [];
    vsCourseInfo = null;
    vsSelected = new Set();

    // Enrollment numbers on the page behind the modal are now stale.
    if (wasOpen && vsChanged) {
        vsChanged = false;
        window.location.reload();
    }
}

function exportViewStudents() {
    if (!currentViewOfferingId) return;
    window.location = 'assests/api/export_course_students.php?offering_id=' + encodeURIComponent(currentViewOfferingId);
}

// ---- New Section modal ----
function openAddSectionModal() {
    document.getElementById('addSectionOverlay').classList.add('open');
    document.getElementById('addSectionErrors').hidden = true;
}

function closeAddSectionModal() {
    document.getElementById('addSectionOverlay').classList.remove('open');
}

document.getElementById('addSectionForm').addEventListener('submit', function (e) {
    e.preventDefault();
    submitModalForm(
        this,
        'add_section.php',
        document.getElementById('addSectionSubmitBtn'),
        document.getElementById('addSectionErrors'),
        '<i class="fas fa-plus"></i> Add Section',
        'sections'
    );
});

// ---- Set Term Interval modal ----
function openTermIntervalModal() {
    document.getElementById('termIntervalOverlay').classList.add('open');
    document.getElementById('termIntervalErrors').hidden = true;
}

function closeTermIntervalModal() {
    document.getElementById('termIntervalOverlay').classList.remove('open');
}

document.getElementById('termIntervalForm').addEventListener('submit', function (e) {
    e.preventDefault();
    submitModalForm(
        this,
        'save_term_intervals.php',
        document.getElementById('termIntervalSubmitBtn'),
        document.getElementById('termIntervalErrors'),
        '<i class="fas fa-check"></i> Save Term Intervals'
    );
});

// ---- Update Section modal ----
function openEditSectionModal(triggerEl) {
    const section = JSON.parse(triggerEl.dataset.section);

    document.getElementById('es_section_id').value = section.section_id;
    document.getElementById('es_section_name').value = section.section_name;
    document.getElementById('es_grade_level').value = section.grade_level;
    document.getElementById('es_adviser_id').value = section.adviser_id;

    document.getElementById('editSectionErrors').hidden = true;
    document.getElementById('editSectionOverlay').classList.add('open');
}

function closeEditSectionModal() {
    document.getElementById('editSectionOverlay').classList.remove('open');
}

document.getElementById('editSectionForm').addEventListener('submit', function (e) {
    e.preventDefault();
    submitModalForm(
        this,
        'update_section.php',
        document.getElementById('editSectionSubmitBtn'),
        document.getElementById('editSectionErrors'),
        '<i class="fas fa-check"></i> Save Changes',
        'sections'
    );
});

// ---- New Subject modal ----
function openAddSubjectModal() {
    document.getElementById('addSubjectOverlay').classList.add('open');
    document.getElementById('addSubjectErrors').hidden = true;
}

function closeAddSubjectModal() {
    document.getElementById('addSubjectOverlay').classList.remove('open');
}

document.getElementById('addSubjectForm').addEventListener('submit', function (e) {
    e.preventDefault();
    submitModalForm(
        this,
        'add_subject.php',
        document.getElementById('addSubjectSubmitBtn'),
        document.getElementById('addSubjectErrors'),
        '<i class="fas fa-plus"></i> Add Subject',
        'subjects'
    );
});

// ---- Update Subject modal ----
function openEditSubjectModal(triggerEl) {
    const subject = JSON.parse(triggerEl.dataset.subject);

    document.getElementById('esub_subject_id').value = subject.subject_id;
    document.getElementById('esub_subject_name').value = subject.subject_name;
    document.getElementById('esub_description').value = subject.description;

    document.getElementById('editSubjectErrors').hidden = true;
    document.getElementById('editSubjectOverlay').classList.add('open');
}

function closeEditSubjectModal() {
    document.getElementById('editSubjectOverlay').classList.remove('open');
}

document.getElementById('editSubjectForm').addEventListener('submit', function (e) {
    e.preventDefault();
    submitModalForm(
        this,
        'update_subject.php',
        document.getElementById('editSubjectSubmitBtn'),
        document.getElementById('editSubjectErrors'),
        '<i class="fas fa-check"></i> Save Changes',
        'subjects'
    );
});

// ---- Shared: Escape closes whichever modal is open ----
document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    closeAddCourseModal();
    closeEditCourseModal();
    closeViewStudentsModal();
    closeAddSectionModal();
    closeEditSectionModal();
    closeAddSubjectModal();
    closeEditSubjectModal();
});

/* ---------- Input guards: subject name = letters only, section name = letters/numbers/dash ---------- */
/* Same look & behaviour as the user-management guards: bad characters are refused and a red
   inline message explains why (it fades after a moment; invalid final values keep their message). */
(function () {
    var RULES = {
        letters: {
            label: 'Subject name',
            bad: /[^\p{L}\p{M} ]/gu,
            test: /[^\p{L}\p{M} ]/u,
            final: /^[\p{L}\p{M}]+(?: [\p{L}\p{M}]+)*$/u,
            blocked: 'Subject name accepts letters only. Numbers and symbols are not allowed.',
            invalid: 'Subject name can only contain letters, with single spaces between words.'
        },
        section: {
            label: 'Section name',
            bad: /[^\p{L}\p{N} \-]/gu,
            test: /[^\p{L}\p{N} \-]/u,
            final: /^[\p{L}\p{N}]+(?:[ \-][\p{L}\p{N}]+)*$/u,
            blocked: 'Section name accepts letters, numbers and dashes (-) only. Other symbols are not allowed.',
            invalid: 'Section name can only contain letters, numbers, spaces and dashes (-), and must start and end with a letter or number.'
        }
    };

    function ruleFor(el) {
        return el && el.tagName === 'INPUT' ? RULES[el.getAttribute('data-input-rule')] : null;
    }

    // ----- inline message (same style as the user forms) -----
    function msgEl(input) {
        var el = input._fwMsg;
        if (el && el.isConnected) return el;
        el = document.createElement('small');
        el.className = 'ig-error';
        el.setAttribute('role', 'alert');
        el.style.cssText = 'display:none;margin-top:6px;font-size:.78rem;line-height:1.35;color:#dc2626;';
        input.insertAdjacentElement('afterend', el);
        input._fwMsg = el;
        return el;
    }
    function showError(input, text) {
        var el = msgEl(input);
        el.innerHTML = '<i class="fas fa-exclamation-circle"></i> ';
        el.appendChild(document.createTextNode(text));
        el.style.display = 'block';
        input.setAttribute('aria-invalid', 'true');
        input.style.borderColor = '#dc2626';
    }
    function clearError(input) {
        if (input._fwMsg) input._fwMsg.style.display = 'none';
        input.removeAttribute('aria-invalid');
        input.style.borderColor = '';
        clearTimeout(input._fwTimer);
    }
    // Message for a blocked character: disappears on its own unless the value is still invalid.
    function flash(input, rule, text) {
        showError(input, text);
        clearTimeout(input._fwTimer);
        input._fwTimer = setTimeout(function () { settle(input, rule, false); }, 2500);
    }
    // Show a persistent error only if the value really is invalid.
    function settle(input, rule, finalCheck) {
        var v = input.value.trim();
        if (v === '') {
            if (finalCheck && input.required) { showError(input, rule.label + ' is required.'); return false; }
            clearError(input); return true;
        }
        if (finalCheck && !rule.final.test(v)) { showError(input, rule.invalid); return false; }
        clearError(input); return true;
    }

    function clean(input, rule) {
        var v = input.value;
        var c = v.replace(rule.bad, '').replace(/^[ \-]+/, '').replace(/ {2,}/g, ' ');
        if (rule === RULES.section) c = c.replace(/-{2,}/g, '-');
        if (c === v) return null;
        var pos = input.selectionStart, shift = v.length - c.length;
        input.value = c;
        try { input.setSelectionRange(Math.max(0, pos - shift), Math.max(0, pos - shift)); } catch (_) {}
        return rule.test.test(v) ? rule.blocked : 'No leading or repeated spaces/dashes allowed in ' + rule.label.toLowerCase() + '.';
    }

    // Typing: refuse the character and explain why.
    document.addEventListener('beforeinput', function (e) {
        var input = e.target, rule = ruleFor(input);
        if (!rule || e.isComposing || !e.data) return;
        if (e.inputType === 'insertFromPaste' || e.inputType === 'insertFromDrop') return;
        if (rule.test.test(e.data)) { e.preventDefault(); flash(input, rule, rule.blocked); return; }
        // no leading space/dash, no doubled space/dash
        var before = input.value.slice(0, input.selectionStart || 0);
        if (/^[ \-]$/.test(e.data) && (before === '' || /[ \-]$/.test(before))) {
            e.preventDefault();
            flash(input, rule, before === ''
                ? rule.label + ' cannot start with a space or dash.'
                : 'Spaces and dashes cannot be repeated or placed next to each other.');
        }
    }, true);

    // Paste / drop / autofill fallback.
    document.addEventListener('input', function (e) {
        var input = e.target, rule = ruleFor(input);
        if (!rule || e.isComposing) return;
        var why = clean(input, rule);
        if (why) flash(input, rule, why);
        else if (!input._fwTimer || input.getAttribute('aria-invalid') !== 'true') settle(input, rule, false);
    }, true);

    // On leaving the field, check the finished value.
    document.addEventListener('focusout', function (e) {
        var input = e.target, rule = ruleFor(input);
        if (rule) settle(input, rule, input.value.trim() !== '');
    });

    // Block submit with a visible message when the value is invalid.
    document.addEventListener('submit', function (e) {
        var form = e.target, bad = null;
        if (!form || !form.querySelectorAll) return;
        form.querySelectorAll('input[data-input-rule]').forEach(function (input) {
            if (!input.disabled && input.getClientRects().length && !settle(input, ruleFor(input), true) && !bad) bad = input;
        });
        if (bad) { e.preventDefault(); e.stopImmediatePropagation(); bad.focus(); }
    }, true);

    // Clear messages when a form is reset.
    document.addEventListener('reset', function (e) {
        var form = e.target;
        if (form && form.querySelectorAll) form.querySelectorAll('input[data-input-rule]').forEach(clearError);
    }, true);
})();
