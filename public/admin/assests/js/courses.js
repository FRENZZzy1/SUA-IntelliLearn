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

// ---- Delete confirmation ----
function confirmDelete(type) {
    const messages = {
        course:  'Delete this course? This cannot be undone.',
        section: 'Delete this section? This cannot be undone.',
        subject: 'Delete this subject? This cannot be undone.',
    };
    return confirm(messages[type] || messages.course);
}

// ---- Toggle the Sections / Subjects list panels ----
// Each panel is independent and hidden by default; clicking its
// "View..." button reveals it (and clicking again hides it).
function togglePanel(view, btn) {
    const panel = document.getElementById('view-' + view);
    const isOpen = panel.classList.toggle('open');
    btn.classList.toggle('active', isOpen);

    if (isOpen) {
        panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
}

// ---- Generic modal submit helper ----
// Posts a form to an endpoint, shows validation errors inline, and
// reloads on success so the flash message + updated list/stats appear.
function submitModalForm(form, url, submitBtn, errorBox, idleLabel, openPanel) {
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
    noResultsRow.innerHTML = '<td colspan="10">No courses match your search.</td>';
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

    const label = 'Showing ' + visible + ' of ' + window.TOTAL_COURSES + ' courses';
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

function exportSearchInput(type, value) {
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
                dropdown.innerHTML = '<div class="export-search-dropdown-empty">No matches found.</div>';
                dropdown.hidden = false;
                return;
            }

            data.results.forEach(function (r) {
                const item = document.createElement('div');
                item.className = 'export-search-dropdown-item';
                item.innerHTML = '<div class="esdi-name"></div><div class="esdi-sub"></div>';
                item.querySelector('.esdi-name').textContent = r.label;
                item.querySelector('.esdi-sub').textContent = r.sublabel;
                item.onclick = function () { selectExportEntity(type, r.id, r.label, r.sublabel); };
                dropdown.appendChild(item);
            });
            dropdown.hidden = false;
        })
        .catch(() => {
            dropdown.innerHTML = '<div class="export-search-dropdown-empty">Something went wrong.</div>';
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

    chip.innerHTML = '<span>' + escapeHtml(label) + ' <span style="font-weight:400; color:#3b82f6;">· ' + escapeHtml(sublabel) + '</span></span>'
        + '<button type="button" onclick="clearExportSelection(\'' + type + '\')" aria-label="Clear">&times;</button>';
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
    document.getElementById('e_schedule_days').value = course.schedule_days || '';
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
let vsCourseInfo = null;  // { subject_name, section_name, grade_level, strand, capacity }

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
    exportBtn.disabled = true;

    // reset search/filter state each time the modal is opened for a course
    searchInput.value = '';
    genderFilter.value = 'all';

    errorBox.hidden = true;
    title.textContent = 'Enrolled Students';
    subtitle.textContent = '';
    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding: 24px; color: var(--text-muted);">Loading...</td></tr>';
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
        subtitle.textContent = 'Grade ' + vsCourseInfo.grade_level + (vsCourseInfo.strand ? ' · ' + vsCourseInfo.strand : '')
            + ' · ' + (filtered ? list.length + ' of ' + vsAllStudents.length + ' shown' : vsAllStudents.length + '/' + vsCourseInfo.capacity + ' enrolled');
    }

    if (vsAllStudents.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding: 24px; color: var(--text-muted);">No students enrolled in this course yet.</td></tr>';
        return;
    }

    if (list.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding: 24px; color: var(--text-muted);">No students match your search.</td></tr>';
        return;
    }

    tbody.innerHTML = list.map(function (s) {
        const fullName = [s.firstname, s.middlename, s.lastname].filter(Boolean).join(' ');
        const statusLabel = s.status.charAt(0).toUpperCase() + s.status.slice(1);
        const enrolledDate = s.enrolled_at ? new Date(s.enrolled_at.replace(' ', 'T')).toLocaleDateString() : '—';
        const genderLabel = s.Gender ? (s.Gender.charAt(0).toUpperCase() + s.Gender.slice(1)) : '—';
        return '<tr>'
            + '<td>' + escapeHtml(s.student_lrn) + '</td>'
            + '<td>' + escapeHtml(fullName) + '</td>'
            + '<td>' + (s.email ? escapeHtml(s.email) : '— None —') + '</td>'
            + '<td>' + escapeHtml(genderLabel) + '</td>'
            + '<td><span class="status-dot-badge ' + (s.status === 'active' ? 'active' : 'inactive') + '"><span class="dot"></span>' + escapeHtml(statusLabel) + '</span></td>'
            + '<td>' + escapeHtml(enrolledDate) + '</td>'
            + '</tr>';
    }).join('');
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
    document.getElementById('viewStudentsOverlay').classList.remove('open');
    currentViewOfferingId = null;
    vsAllStudents = [];
    vsCourseInfo = null;
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

function toggleTermMode(n, mode) {
    document.getElementById('term_' + n + '_month_fields').hidden = (mode !== 'month');
    document.getElementById('term_' + n + '_date_fields').hidden = (mode !== 'date');
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
    document.getElementById('es_strand').value = section.strand;
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