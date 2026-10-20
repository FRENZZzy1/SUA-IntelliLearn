// ---- Sidebar collapse/expand ----
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('collapsed');
    }

    function setActive(el) {
        document.querySelectorAll('.nav-item').forEach(item => item.classList.remove('active'));
        el.classList.add('active');
    }

    // ---- Add User panel ----
    function toggleAddUser() {
        const panel = document.getElementById('addUserPanel');
        panel.style.display = (panel.style.display === 'none' || panel.style.display === '') ? 'block' : 'none';
        if (panel.style.display === 'block') {
            panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    // ---- Role pill selector ----
    function setRole(el) {
    document.querySelectorAll('.role-pill').forEach(p => p.classList.remove('active'));
    el.classList.add('active');
    const role = el.dataset.role;
    document.getElementById('roleInput').value = role;

    const studentFields = document.getElementById('studentFields');
    const staffFields = document.getElementById('staffFields');
    const isStudent = role === 'student';

    // Show/hide the field containers
    studentFields.style.display = isStudent ? 'block' : 'none';
    staffFields.style.display = isStudent ? 'none' : 'block';

    // Handle student fields: enable when student, disable when staff
    studentFields.querySelectorAll('input, select, textarea').forEach(field => {
        field.disabled = !isStudent;
    });
    studentFields.querySelectorAll('[data-student-required]').forEach(el => {
        el.required = isStudent;
    });

    // Handle staff fields: enable when staff, disable when student
    staffFields.querySelectorAll('input, select, textarea').forEach(field => {
        field.disabled = isStudent;
    });
    staffFields.querySelectorAll('[data-staff-required]').forEach(el => {
        el.required = !isStudent;
    });
}

    // ---- Status pill selector ----
    function setStatus(el) {
        document.querySelectorAll('.status-pill').forEach(p => p.classList.remove('active'));
        el.classList.add('active');
        document.getElementById('statusInput').value = el.dataset.status;
    }

    // Initialize required attributes on load (default role = student)
    document.addEventListener('DOMContentLoaded', () => {
    // Default role is student — student fields enabled, staff fields disabled
    document.querySelectorAll('#studentFields input, #studentFields select, #studentFields textarea').forEach(f => f.disabled = false);
    document.querySelectorAll('#studentFields [data-student-required]').forEach(el => el.required = true);
    
    document.querySelectorAll('#staffFields input, #staffFields select, #staffFields textarea').forEach(f => f.disabled = true);
    document.querySelectorAll('#staffFields [data-staff-required]').forEach(el => el.required = false);
});

    // ---- Philippine mobile number validation ----
    const PH_MOBILE_PATTERN = /^(?:09\d{9}|\+639\d{9})$/;

    function normalizePhilippineMobileInput(value) {
        return value.trim().replace(/[\s-]+/g, '');
    }

    function validatePhilippineMobileInput(input) {
        if (!input || input.value.trim() === '') return true;
        const normalized = normalizePhilippineMobileInput(input.value);
        if (!PH_MOBILE_PATTERN.test(normalized)) {
            input.setCustomValidity('Use 09XXXXXXXXX or +63 9XX XXX XXXX format.');
            return false;
        }
        input.setCustomValidity('');
        return true;
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('input[name="guardian_contact"], input[name="contact"]').forEach(input => {
            input.addEventListener('input', () => validatePhilippineMobileInput(input));
            input.addEventListener('blur', () => validatePhilippineMobileInput(input));
        });

        const editForm = document.getElementById('editUserForm');
        if (editForm) {
            editForm.addEventListener('submit', event => {
                const contact = document.getElementById('editGuardianContact');
                if (contact && !validatePhilippineMobileInput(contact)) {
                    event.preventDefault();
                    contact.reportValidity();
                    contact.focus();
                }
            });
        }
    });

    // ---- Edit User ----
    function editUser(userId) {
        const card = document.querySelector(`[data-user-id="${userId}"]`);
        if (!card) return;

        const role = card.dataset.role;

        // Fields shared by every role
        document.getElementById('editUserId').value = userId;
        document.getElementById('editRoleInput').value = role;
        document.getElementById('editEmail').value = card.dataset.email;
        document.getElementById('editStatus').value = card.dataset.status;
        document.getElementById('editNewPassword').value = '';

        if (role === 'student' || role === 'teacher') {
            document.getElementById('editFirstname').value = card.dataset.firstname || '';
            document.getElementById('editLastname').value = card.dataset.lastname || '';
            document.getElementById('editMiddlename').value = card.dataset.middlename || '';
        }

        if (role === 'teacher') {
            document.getElementById('editDepartment').value = card.dataset.department || '';
            // Contact isn't persisted in the Teachers table yet, so this always starts
            // blank — fill in only if/when a contact column is added.
            document.getElementById('editContact').value = '';
            document.getElementById('editEmploymentStatus').value = card.dataset.employmentStatus || '';
            document.getElementById('editNotes').value = card.dataset.notes || '';
        }

        if (role === 'admin') {
            document.getElementById('editPosition').value = card.dataset.position || 'it_administrator';
            document.getElementById('editAccessLevel').value = card.dataset.accessLevel || 'limited';
            const permissionsField = document.getElementById('editPermissionsJson');
            if (permissionsField) permissionsField.value = card.dataset.adminPermissions || '';

            // Temporary Full access: blank duration = keep the current expiry.
            const editForm = document.getElementById('editUserForm');
            const daysInput = document.getElementById('editFullDays');
            const hasExpiry = card.dataset.accessLevel === 'full' && !!card.dataset.fullExpires;
            if (daysInput) daysInput.value = '';
            if (editForm) editForm.dataset.canKeepExpiry = hasExpiry ? '1' : '0';
            const keepHint = document.getElementById('editFullDaysHint');
            if (keepHint) keepHint.textContent = hasExpiry
                ? 'Currently ' + card.dataset.fullDaysLeft + ' day(s) left. Leave blank to keep it, or enter days to restart the countdown.'
                : '';
            if (window.syncFullAccessDays) window.syncFullAccessDays('editUserForm');
        } else if (window.syncFullAccessDays) {
            window.syncFullAccessDays('editUserForm'); // hides the Full-access days field for non-admins
        }

        if (role === 'student') {
            document.getElementById('editLrn').value = card.dataset.lrn || '';
            document.getElementById('editYearLevel').value = card.dataset.yearLevel || '';
            document.getElementById('editStrand').value = card.dataset.strand || '';
            document.getElementById('editGender').value = card.dataset.gender || 'Male';
            document.getElementById('editBirthdate').value = card.dataset.birthdate || '';
            document.getElementById('editAddress').value = card.dataset.address || '';
            document.getElementById('editGuardianName').value = card.dataset.guardian || '';
            document.getElementById('editGuardianContact').value = card.dataset.guardianContact || '';
        }

        // Show/hide the right block of fields for this role
        um_toggleEditStaffFields(role);

        document.getElementById('editModal').style.display = 'flex';
    }

    function closeEditModal() {
        document.getElementById('editModal').style.display = 'none';
    }

    // ---- View User ----
    function viewUser(userId) {
        const card = document.querySelector(`[data-user-id="${userId}"]`);
        if (!card) return;

        const roleColors = { admin: '#8B5CF6', teacher: '#1F6F54', student: '#2F9C74' };
        const roleLabels = { admin: 'Admin', teacher: 'Teacher', student: 'Student' };
        const statusLabels = { active: 'Active', inactive: 'Pending', suspended: 'Suspended' };
        const genderLabels = { Male: 'Male', Female: 'Female' };

        const avatarEl = card.querySelector('.user-avatar, .user-avatar-sm');
        const initials = avatarEl ? avatarEl.textContent.trim() : '?';
        const color = roleColors[card.dataset.role] || '#6B7280';

        let extraRows = '';
        if (card.dataset.role === 'student') {
            extraRows = `
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-id-card"></i> LRN</span>
                    <span class="detail-value">${card.dataset.lrn || 'Not set'}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-venus-mars"></i> Gender</span>
                    <span class="detail-value">${genderLabels[card.dataset.gender] || 'Not set'}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-sitemap"></i> Strand</span>
                    <span class="detail-value">${card.dataset.strand || 'None'}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-cake-candles"></i> Birthdate</span>
                    <span class="detail-value">${card.dataset.birthdate || 'Not set'}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-location-dot"></i> Address</span>
                    <span class="detail-value">${card.dataset.address || 'Not set'}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-people-roof"></i> Guardian</span>
                    <span class="detail-value">${card.dataset.guardian || 'Not set'}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-phone"></i> Guardian Contact</span>
                    <span class="detail-value">${card.dataset.guardianContact || 'Not set'}</span>
                </div>
            `;
        } else if (card.dataset.role === 'admin') {
            const positionLabels = { principal: 'Principal', registrar: 'Registrar', it_administrator: 'IT Administrator' };
            const accessLabels = { full: 'Full', limited: 'Limited', read_only: 'Read Only' };
            extraRows = `
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-user-tie"></i> Position</span>
                    <span class="detail-value">${positionLabels[card.dataset.position] || 'Not set'}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-key"></i> Access Level</span>
                    <span class="detail-value">${accessLabels[card.dataset.accessLevel] || 'Not set'}</span>
                </div>
                ${card.dataset.accessLevel === 'full' && card.dataset.fullExpires ? `
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-hourglass-half"></i> Full Access Ends</span>
                    <span class="detail-value">${card.dataset.fullExpires} (${card.dataset.fullDaysLeft} day(s) left)</span>
                </div>` : ''}
            `;
        }

        document.getElementById('viewProfileContent').innerHTML = `
            <div class="view-header">
                <div class="view-avatar" style="background:${color}">${initials}</div>
                <div class="view-info">
                    <h3>${card.dataset.fullname}</h3>
                    <span class="badge ${card.dataset.role}">${roleLabels[card.dataset.role]}</span>
                    <span class="badge ${card.dataset.status === 'active' ? 'active' : (card.dataset.status === 'suspended' ? 'inactive' : 'pending')}">${statusLabels[card.dataset.status]}</span>
                </div>
            </div>
            <div class="view-details">
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-envelope"></i> Email</span>
                    <span class="detail-value">${card.dataset.email || 'Not set'}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-user"></i> Username</span>
                    <span class="detail-value">@${card.dataset.username}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-building"></i> Department</span>
                    <span class="detail-value">${card.dataset.department || 'Not assigned'}</span>
                </div>
                ${extraRows}
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-id-badge"></i> User ID</span>
                    <span class="detail-value">SUA-${String(userId).padStart(5, '0')}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-calendar"></i> Created</span>
                    <span class="detail-value">${new Date(card.dataset.created).toLocaleDateString()}</span>
                </div>
            </div>
            <div class="history-section">
                <button type="button" class="btn-secondary history-toggle" id="historyToggleBtn"
                        onclick="um_toggleHistory(${Number(userId)})">
                    <i class="fas fa-clock-rotate-left"></i> History
                    <i class="fas fa-chevron-down history-chevron"></i>
                </button>
                <div class="history-panel" id="userHistoryPanel" style="display:none;"></div>
            </div>
        `;
        um_historyState.token++;   // drop any in-flight request from a previously viewed user
        document.getElementById('viewModal').style.display = 'flex';
    }

    function closeViewModal() {
        um_historyState.token++;
        document.getElementById('viewModal').style.display = 'none';
    }

    // ---- User activity history (View User modal > History) ----
    const um_historyState = { userId: null, category: 'all', offset: 0, loading: false, lastDay: null, token: 0 };

    const UM_HISTORY_ICONS = {
        enrollment:    'fa-user-check',
        materials:     'fa-file-arrow-up',
        assignments:   'fa-list-check',
        quizzes:       'fa-circle-question',
        grading:       'fa-pen-to-square',
        grades:        'fa-star',
        attendance:    'fa-calendar-check',
        announcements: 'fa-bullhorn',
        submissions:   'fa-paper-plane',
        evaluations:   'fa-clipboard-check'
    };

    function um_escape(value) {
        const div = document.createElement('div');
        div.textContent = value == null ? '' : String(value);
        return div.innerHTML;
    }

    function um_toggleHistory(userId) {
        const panel = document.getElementById('userHistoryPanel');
        const btn = document.getElementById('historyToggleBtn');
        if (!panel || !btn) return;

        if (panel.style.display !== 'none') {
            panel.style.display = 'none';
            btn.classList.remove('open');
            return;
        }

        panel.innerHTML = `
            <div class="history-toolbar">
                <label for="historyFilter"><i class="fas fa-filter"></i> Show</label>
                <select id="historyFilter" class="select-filter" onchange="um_changeHistoryCategory(this.value)">
                    <option value="all">All activity</option>
                </select>
            </div>
            <div class="history-list" id="historyList"></div>
            <div class="history-footer">
                <button type="button" class="btn-secondary" id="historyMoreBtn" style="display:none;" onclick="um_loadHistory(false)">Load more</button>
            </div>
        `;
        panel.style.display = 'block';
        btn.classList.add('open');

        um_historyState.userId = userId;
        um_historyState.category = 'all';
        um_loadHistory(true);
    }

    function um_changeHistoryCategory(category) {
        um_historyState.category = category;
        um_loadHistory(true);
    }

    function um_dayLabel(date) {
        const today = new Date();
        const yesterday = new Date();
        yesterday.setDate(today.getDate() - 1);
        if (date.toDateString() === today.toDateString()) return 'Today';
        if (date.toDateString() === yesterday.toDateString()) return 'Yesterday';
        return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
    }

    function um_renderHistoryItems(items, listEl) {
        let html = '';
        items.forEach(item => {
            // "2026-10-08 12:00:00" -> local Date
            const when = new Date(String(item.occurred_at).replace(' ', 'T'));
            const day = um_dayLabel(when);
            if (day !== um_historyState.lastDay) {
                html += `<div class="history-day">${um_escape(day)}</div>`;
                um_historyState.lastDay = day;
            }
            const icon = UM_HISTORY_ICONS[item.category] || 'fa-circle';
            const time = when.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
            html += `
                <div class="history-item">
                    <div class="history-icon cat-${um_escape(item.category)}"><i class="fas ${icon}"></i></div>
                    <div class="history-body">
                        <div class="history-action">${um_escape(item.action)}</div>
                        <div class="history-title">${um_escape(item.title)}</div>
                        ${item.detail ? `<div class="history-detail">${um_escape(item.detail)}</div>` : ''}
                    </div>
                    <div class="history-time" title="${um_escape(when.toLocaleString())}">${um_escape(time)}</div>
                </div>`;
        });
        listEl.insertAdjacentHTML('beforeend', html);
    }

    function um_historyError(listEl, message, reset) {
        const html = `<div class="history-empty error"><i class="fas fa-triangle-exclamation"></i> ${um_escape(message)}</div>`;
        if (reset) listEl.innerHTML = html;      // keep already-loaded entries when only "Load more" failed
        else listEl.insertAdjacentHTML('beforeend', html);
    }

    async function um_loadHistory(reset) {
        const listEl = document.getElementById('historyList');
        const moreBtn = document.getElementById('historyMoreBtn');
        const filterEl = document.getElementById('historyFilter');
        if (!listEl || um_historyState.userId == null) return;
        if (um_historyState.loading && !reset) return;

        if (reset) {
            um_historyState.offset = 0;
            um_historyState.lastDay = null;
            listEl.innerHTML = '<div class="history-empty"><i class="fas fa-spinner fa-spin"></i> Loading activity...</div>';
            moreBtn.style.display = 'none';
        }

        const token = ++um_historyState.token;
        um_historyState.loading = true;
        moreBtn.disabled = true;

        const params = new URLSearchParams({
            user_id: um_historyState.userId,
            category: um_historyState.category,
            offset: um_historyState.offset
        });

        try {
            const res = await fetch('assests/api/user_history.php?' + params.toString(), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await res.json();
            if (token !== um_historyState.token) return;   // a newer request / another user took over

            if (!data.success) {
                um_historyError(listEl, (data.errors || ['Could not load history.'])[0], reset);
                return;
            }

            // Fill the category filter once (options depend on the user's role).
            if (filterEl && filterEl.options.length <= 1) {
                filterEl.innerHTML = data.categories
                    .map(c => `<option value="${um_escape(c.key)}">${um_escape(c.label)}</option>`).join('');
                filterEl.value = um_historyState.category;
            }

            if (reset) listEl.innerHTML = '';

            if (data.items.length === 0 && um_historyState.offset === 0) {
                listEl.innerHTML = `<div class="history-empty"><i class="fas fa-inbox"></i> No activity recorded${um_historyState.category === 'all' ? '' : ' in this category'}.</div>`;
            } else {
                um_renderHistoryItems(data.items, listEl);
            }

            um_historyState.offset = data.next_offset;
            moreBtn.style.display = data.has_more ? '' : 'none';
        } catch (err) {
            if (token !== um_historyState.token) return;
            um_historyError(listEl, 'Could not load history. Please try again.', reset);
        } finally {
            if (token === um_historyState.token) {
                um_historyState.loading = false;
                moreBtn.disabled = false;
            }
        }
    }

    // Close modals on outside click
    window.onclick = function(event) {
        if (event.target.classList.contains('modal-overlay')) {
            event.target.style.display = 'none';
        }
    }

    // Auto-hide flash messages
    setTimeout(() => {
        const flash = document.getElementById('flashMessage');
        if (flash) {
            flash.style.opacity = '0';
            setTimeout(() => flash.remove(), 300);
        }
    }, 5000);

    function um_toggleEditStaffFields(role) {
                    var isAdmin = role === 'admin';
                    var isTeacher = role === 'teacher';
                    var isStudent = role === 'student';

                    document.getElementById('editNameRow').style.display = isAdmin ? 'none' : '';
                    document.getElementById('editMiddleNameRow').style.display = isAdmin ? 'none' : '';
                    document.getElementById('editStaffMetaRow').style.display = isTeacher ? '' : 'none';
                    document.getElementById('editTeacherOnlyFields').style.display = isTeacher ? '' : 'none';
                    document.getElementById('editAdminOnlyFields').style.display = isAdmin ? '' : 'none';
                    const studentFields = document.getElementById('editStudentFields');
                    const yearLevel = document.getElementById('editYearLevel');

                    studentFields.style.display = isStudent ? '' : 'none';

                    // Student-only fields must not participate in browser form
                    // validation when editing a teacher or admin.
                    if (yearLevel) {
                        yearLevel.required = isStudent;
                        if (!isStudent) yearLevel.setCustomValidity('');
                    }

                    document.getElementById('editTeacherEmailHint').style.display = isTeacher ? '' : 'none';
                }