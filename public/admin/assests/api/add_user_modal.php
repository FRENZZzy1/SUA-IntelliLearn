<?php
/* =====================================================================
   ADD USER — REUSABLE POPUP COMPONENT
   ---------------------------------------------------------------------
   Drop this file into any module with:

       <?php $aum_endpoint = '/public/admin/assests/api/add_user_handler.php';
             include '/full/or/relative/path/to/add_user_modal.php'; ?>

   ($aum_endpoint is optional — set it before the include if the handler
   lives somewhere other than the default path below. Every module can
   point at the SAME handler file, so there's only one copy of the
   create-user logic to maintain.)

   Then anywhere on the page:

       <button type="button" class="btn-primary" onclick="openAddUserModal()">
           <i class="fas fa-user-plus"></i> Add User
       </button>

   To open it pre-locked to one role (e.g. from the Teachers module, so
   staff can only add teachers and the role picker is hidden):

       <button onclick="openAddUserModal('teacher')">Add Teacher</button>

   To react when a user is successfully created (e.g. refresh a table),
   define this BEFORE the modal is opened (anywhere in your page's JS):

       window.onUserAdded = function(user) {
           console.log('New user created:', user);
           // e.g. location.reload(); or re-fetch your list via AJAX
       };

   Requires: Font Awesome (for icons) already loaded on the host page.
   This component is fully self-contained (its own CSS, prefixed "aum-"
   to avoid clashing with the host page's styles) and does NOT depend
   on user_management.css.
===================================================================== */

if (!isset($aum_endpoint)) {
    $aum_endpoint = '/public/admin/assests/api/add_user_handler.php';
}

// generateCSRFToken() is expected to already exist (from config.php,
// which the host module should already be including for its own auth).
$aum_csrf = function_exists('generateCSRFToken') ? generateCSRFToken() : '';
?>
<div class="aum-overlay" id="aumOverlay" style="display:none;" data-endpoint="<?= htmlspecialchars($aum_endpoint) ?>">
    <div class="aum-modal">
        <div class="aum-header">
            <h2><i class="fas fa-user-plus"></i>&nbsp; Add New User</h2>
            <div class="aum-close" onclick="closeAddUserModal()"><i class="fas fa-times"></i></div>
        </div>

        <div class="aum-alert" id="aumAlert"></div>

        <form id="aumForm" novalidate>
            <input type="hidden" name="csrf_token" id="aumCsrf" value="<?= htmlspecialchars($aum_csrf) ?>">
            <input type="hidden" name="action" value="create">

            <div class="aum-row">
                <div class="aum-group" id="aumRoleGroup">
                    <label>Role *</label>
                    <div class="aum-pills">
                        <div class="aum-pill active" data-role="student" onclick="aumSetRole(this)">
                            <i class="fas fa-user-graduate"></i> Student
                        </div>
                        <div class="aum-pill" data-role="teacher" onclick="aumSetRole(this)">
                            <i class="fas fa-chalkboard-teacher"></i> Teacher
                        </div>
                        <div class="aum-pill" data-role="admin" onclick="aumSetRole(this)">
                            <i class="fas fa-user-shield"></i> Admin
                        </div>
                    </div>
                    <input type="hidden" name="role" id="aumRoleInput" value="student">
                </div>
                <div class="aum-group">
                    <label>Status</label>
                    <div class="aum-pills">
                        <div class="aum-pill active" data-status="active" onclick="aumSetStatus(this)">Active</div>
                        <div class="aum-pill" data-status="inactive" onclick="aumSetStatus(this)">Inactive</div>
                        <div class="aum-pill" data-status="suspended" onclick="aumSetStatus(this)">Suspended</div>
                    </div>
                    <input type="hidden" name="status" id="aumStatusInput" value="active">
                </div>
            </div>

            <!-- ============================================================
                 STUDENT FIELDS
            ============================================================= -->
            <div id="aumStudentFields">
                <div class="aum-row">
                    <div class="aum-group">
                        <label>First Name *</label>
                        <input type="text" name="firstname" class="aum-control" placeholder="e.g. Maria" data-student-required>
                    </div>
                    <div class="aum-group">
                        <label>Last Name *</label>
                        <input type="text" name="lastname" class="aum-control" placeholder="e.g. Santos" data-student-required>
                    </div>
                </div>
                <div class="aum-row">
                    <div class="aum-group">
                        <label>Middle Name</label>
                        <input type="text" name="middlename" class="aum-control" placeholder="e.g. Clara">
                    </div>
                    <div class="aum-group">
                        <label>LRN * <small>(12 digits)</small></label>
                        <input type="text" name="lrn" class="aum-control" placeholder="e.g. 136090100234" pattern="\d{12}" maxlength="12" data-student-required>
                    </div>
                </div>
                <div class="aum-row">
                    <div class="aum-group">
                        <label>Email <small>(optional)</small></label>
                        <input type="email" name="email" id="aumStudentEmail" class="aum-control" placeholder="e.g. maria@sturiel.edu.ph">
                    </div>
                    <div class="aum-group">
                        <label>Birthdate *</label>
                        <input type="date" name="birthdate" class="aum-control" data-student-required>
                    </div>
                </div>
                <div class="aum-group">
                    <label>Address</label>
                    <input type="text" name="address" class="aum-control" placeholder="e.g. 123 Rizal St., Talisay City">
                </div>
                <div class="aum-row">
                    <div class="aum-group">
                        <label>Guardian Name</label>
                        <input type="text" name="guardian_name" class="aum-control" placeholder="e.g. Juana Santos">
                    </div>
                    <div class="aum-group">
                        <label>Guardian Contact</label>
                        <input type="tel" name="guardian_contact" class="aum-control" placeholder="e.g. +63 912 345 6789">
                    </div>
                </div>
                <div class="aum-hint">
                    <i class="fas fa-circle-info"></i>
                    Username &amp; password are generated automatically:
                    <strong>Username</strong> = STU-(last 4 digits of LRN)-(birthdate as MMDDYY),
                    <strong>Password</strong> = Last name + birthdate as MMDDYY.
                </div>
            </div>

            <!-- ============================================================
                 TEACHER / ADMIN FIELDS
            ============================================================= -->
            <div id="aumStaffFields" style="display:none;">
                <div class="aum-row" id="aumTeacherNameRow" style="display:none;">
                    <div class="aum-group">
                        <label>First Name *</label>
                        <input type="text" name="teacher_firstname" class="aum-control" placeholder="e.g. Maria" data-teacher-required>
                    </div>
                    <div class="aum-group">
                        <label>Last Name *</label>
                        <input type="text" name="teacher_lastname" class="aum-control" placeholder="e.g. Santos" data-teacher-required>
                    </div>
                </div>
                <div class="aum-row" id="aumTeacherMiddleNameRow" style="display:none;">
                    <div class="aum-group">
                        <label>Middle Name</label>
                        <input type="text" name="teacher_middlename" class="aum-control" placeholder="e.g. Clara">
                    </div>
                </div>

                <div class="aum-row">
                    <div class="aum-group">
                        <label>Email Address *</label>
                        <input type="email" name="email" id="aumStaffEmail" class="aum-control" placeholder="e.g. maria@sturiel.edu.ph" data-staff-required>
                        <small id="aumTeacherEmailHint" style="display:none;">This will also be the teacher's login username.</small>
                    </div>
                    <div class="aum-group" id="aumContactFieldGroup" style="display:none;">
                        <label>Contact Number</label>
                        <input type="tel" name="contact" class="aum-control" placeholder="e.g. +63 912 345 6789">
                    </div>
                </div>

                <div class="aum-row" id="aumDepartmentRow" style="display:none;">
                    <div class="aum-group">
                        <label>Department / Grade Level</label>
                        <input type="text" name="department" class="aum-control" placeholder="e.g. Grade 7 or Mathematics Dept">
                    </div>
                </div>

                <div class="aum-row" id="aumTeacherOnlyFields" style="display:none;">
                    <div class="aum-group">
                        <label>Employment Status</label>
                        <select name="employment_status" class="aum-control">
                            <option value="">-- Select --</option>
                            <option value="full-time">Full-time</option>
                            <option value="part-time">Part-time</option>
                        </select>
                    </div>
                    <div class="aum-group">
                        <label>Specialization (optional)</label>
                        <input type="text" name="specialization" class="aum-control" placeholder="e.g. Algebra, Biology">
                    </div>
                </div>

                <div class="aum-row" id="aumAdminOnlyFields" style="display:none;">
                    <div class="aum-group">
                        <label>Position *</label>
                        <select name="position" class="aum-control" data-admin-required>
                            <option value="principal">Principal</option>
                            <option value="registrar">Registrar</option>
                            <option value="staff" selected>Staff</option>
                        </select>
                    </div>
                    <div class="aum-group">
                        <label>Access Level *</label>
                        <select name="access_level" class="aum-control" data-admin-required>
                            <option value="full">Full</option>
                            <option value="limited" selected>Limited</option>
                            <option value="read_only">Read Only</option>
                        </select>
                    </div>
                </div>

                <div class="aum-row">
                    <div class="aum-group">
                        <label>Password * <small>(min 6 characters)</small></label>
                        <input type="password" name="password" class="aum-control" placeholder="Enter secure password" minlength="6" data-staff-required>
                    </div>
                </div>
            </div>

            <div class="aum-actions">
                <label class="aum-checkbox">
                    <input type="checkbox" name="send_email" value="1">
                    <i class="fas fa-envelope"></i> Send welcome email
                </label>
                <div class="aum-actions-right">
                    <button type="button" class="aum-btn-secondary" onclick="closeAddUserModal()">Cancel</button>
                    <button type="submit" class="aum-btn-primary" id="aumSubmitBtn">
                        <i class="fas fa-save"></i> Save User
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<style>
.aum-overlay {
    position: fixed; top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.5);
    display: flex; align-items: center; justify-content: center;
    z-index: 9999; padding: 20px; backdrop-filter: blur(4px);
}
.aum-modal {
    background: #fff; border-radius: 14px; padding: 26px;
    width: 100%; max-width: 640px; max-height: 90vh; overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0,0,0,0.2);
    animation: aumSlideIn 0.2s ease;
    font-family: 'DM Sans', Arial, sans-serif;
}
@keyframes aumSlideIn {
    from { opacity: 0; transform: scale(0.96) translateY(10px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}
.aum-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
.aum-header h2 { font-size: 1.15rem; margin: 0; color: #124029; }
.aum-close { cursor: pointer; color: #6b7280; width: 30px; height: 30px; border-radius: 8px; display:flex; align-items:center; justify-content:center; }
.aum-close:hover { background: #f0f4f1; color: #1f2937; }
.aum-alert { display: none; padding: 12px 14px; border-radius: 8px; margin-bottom: 14px; font-size: 0.88rem; }
.aum-alert.show { display: block; }
.aum-alert.success { background: #ecfdf3; color: #166534; border: 1px solid #bbf7d0; }
.aum-alert.error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
.aum-row { display: flex; gap: 14px; margin-bottom: 14px; flex-wrap: wrap; }
.aum-group { flex: 1; min-width: 180px; }
.aum-group label { display: block; font-size: 0.82rem; font-weight: 600; color: #1f2937; margin-bottom: 6px; }
.aum-group label small { font-weight: 400; color: #6b7280; }
.aum-control {
    width: 100%; padding: 9px 12px; border: 1px solid #e5e7eb; border-radius: 8px;
    font-size: 0.9rem; font-family: inherit; color: #1f2937; background: #fff;
}
.aum-control:focus { outline: none; border-color: #1a5c3a; box-shadow: 0 0 0 3px rgba(26,92,58,0.1); }
.aum-pills { display: flex; gap: 8px; flex-wrap: wrap; }
.aum-pill {
    padding: 7px 14px; border: 1px solid #e5e7eb; border-radius: 20px;
    font-size: 0.83rem; cursor: pointer; color: #4b5563; background: #fff; transition: all .15s;
}
.aum-pill:hover { border-color: #1a5c3a; }
.aum-pill.active { background: #1a5c3a; border-color: #1a5c3a; color: #fff; }
.aum-hint {
    background: #f8faf9; padding: 12px 14px; border-radius: 8px;
    font-size: 0.82rem; color: #6b7280; margin-bottom: 4px;
}
.aum-actions { display: flex; align-items: center; justify-content: space-between; margin-top: 18px; padding-top: 16px; border-top: 1px solid #e5e7eb; flex-wrap: wrap; gap: 12px; }
.aum-checkbox { display: flex; align-items: center; gap: 6px; font-size: 0.85rem; color: #4b5563; }
.aum-actions-right { display: flex; gap: 10px; margin-left: auto; }
.aum-btn-primary, .aum-btn-secondary {
    padding: 10px 18px; border-radius: 8px; font-size: 0.88rem; font-weight: 600;
    cursor: pointer; border: none; display: inline-flex; align-items: center; gap: 6px;
}
.aum-btn-primary { background: #f4a261; color: #1f2937; }
.aum-btn-primary:hover { background: #e8935a; }
.aum-btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
.aum-btn-secondary { background: #f0f4f1; color: #374151; }
.aum-btn-secondary:hover { background: #e5e7eb; }
@media (max-width: 600px) {
    .aum-row { flex-direction: column; }
}
</style>

<script>
(function () {
    var aumLockedRole = null;

    window.openAddUserModal = function (lockRole) {
        var overlay = document.getElementById('aumOverlay');
        aumResetForm();
        aumLockedRole = lockRole || null;

        var roleGroup = document.getElementById('aumRoleGroup');
        if (aumLockedRole) {
            roleGroup.style.display = 'none';
            var pill = document.querySelector('#aumOverlay .aum-pill[data-role="' + aumLockedRole + '"]');
            if (pill) aumSetRole(pill);
        } else {
            roleGroup.style.display = '';
        }

        overlay.style.display = 'flex';
    };

    window.closeAddUserModal = function () {
        document.getElementById('aumOverlay').style.display = 'none';
    };

    window.aumSetRole = function (el) {
        document.querySelectorAll('#aumOverlay .aum-pill[data-role]').forEach(function (p) { p.classList.remove('active'); });
        el.classList.add('active');
        var role = el.getAttribute('data-role');
        document.getElementById('aumRoleInput').value = role;

        var isStudent = role === 'student';
        document.getElementById('aumStudentFields').style.display = isStudent ? '' : 'none';
        document.getElementById('aumStaffFields').style.display = isStudent ? 'none' : '';
        document.querySelectorAll('#aumOverlay [data-student-required]').forEach(function (i) { i.required = isStudent; });
        document.querySelectorAll('#aumOverlay [data-staff-required]').forEach(function (i) { i.required = !isStudent; });

        if (!isStudent) aumToggleStaffSubFields(role);
    };

    window.aumSetStatus = function (el) {
        document.querySelectorAll('#aumOverlay .aum-pill[data-status]').forEach(function (p) { p.classList.remove('active'); });
        el.classList.add('active');
        document.getElementById('aumStatusInput').value = el.getAttribute('data-status');
    };

    function aumToggleStaffSubFields(role) {
        var isTeacher = role === 'teacher';
        document.getElementById('aumTeacherNameRow').style.display = isTeacher ? '' : 'none';
        document.getElementById('aumTeacherMiddleNameRow').style.display = isTeacher ? '' : 'none';
        document.getElementById('aumContactFieldGroup').style.display = isTeacher ? '' : 'none';
        document.getElementById('aumDepartmentRow').style.display = isTeacher ? '' : 'none';
        document.getElementById('aumTeacherOnlyFields').style.display = isTeacher ? '' : 'none';
        document.getElementById('aumAdminOnlyFields').style.display = isTeacher ? 'none' : '';
        document.getElementById('aumTeacherEmailHint').style.display = isTeacher ? '' : 'none';
        document.querySelectorAll('#aumOverlay [data-teacher-required]').forEach(function (i) { i.required = isTeacher; });
        document.querySelectorAll('#aumOverlay [data-admin-required]').forEach(function (i) { i.required = !isTeacher; });
    }

    function aumResetForm() {
        var form = document.getElementById('aumForm');
        form.reset();
        var alertBox = document.getElementById('aumAlert');
        alertBox.className = 'aum-alert';
        alertBox.innerHTML = '';
        aumSetRole(document.querySelector('#aumOverlay .aum-pill[data-role="student"]'));
        aumSetStatus(document.querySelector('#aumOverlay .aum-pill[data-status="active"]'));
    }

    function aumShowAlert(type, message) {
        var alertBox = document.getElementById('aumAlert');
        alertBox.className = 'aum-alert show ' + type;
        alertBox.innerHTML = '<i class="fas fa-' + (type === 'success' ? 'check-circle' : 'exclamation-circle') + '"></i> ' + message;
    }

    document.getElementById('aumForm').addEventListener('submit', function (e) {
        e.preventDefault();
        var form = e.target;
        var btn = document.getElementById('aumSubmitBtn');
        var endpoint = document.getElementById('aumOverlay').getAttribute('data-endpoint');

        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

        fetch(endpoint, {
            method: 'POST',
            body: new FormData(form),
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (data.success) {
                aumShowAlert('success', data.message || 'User created successfully.');
                if (typeof window.onUserAdded === 'function') {
                    window.onUserAdded(data.user || null);
                }
                setTimeout(function () { window.closeAddUserModal(); }, 1400);
            } else {
                var msg = Array.isArray(data.errors) ? data.errors.join(' ') : (data.message || 'Something went wrong.');
                aumShowAlert('error', msg);
            }
        })
        .catch(function () {
            aumShowAlert('error', 'Network error — please try again.');
        })
        .finally(function () {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-save"></i> Save User';
        });
    });

    // Close on backdrop click
    document.getElementById('aumOverlay').addEventListener('click', function (e) {
        if (e.target === this) window.closeAddUserModal();
    });
})();
</script>