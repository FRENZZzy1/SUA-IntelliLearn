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

        aumToggleStaffSubFields(role);
    };

    window.aumSetStatus = function (el) {
        document.querySelectorAll('#aumOverlay .aum-pill[data-status]').forEach(function (p) { p.classList.remove('active'); });
        el.classList.add('active');
        document.getElementById('aumStatusInput').value = el.getAttribute('data-status');
    };

    function aumToggleStaffSubFields(role) {
    var isTeacher = role === 'teacher';
    var isAdmin = role === 'admin';

    document.getElementById('aumTeacherNameRow').style.display = isTeacher ? '' : 'none';
    document.getElementById('aumTeacherMiddleNameRow').style.display = isTeacher ? '' : 'none';
    document.getElementById('aumContactFieldGroup').style.display = isTeacher ? '' : 'none';
    document.getElementById('aumDepartmentRow').style.display = isTeacher ? '' : 'none';
    document.getElementById('aumTeacherOnlyFields').style.display = isTeacher ? '' : 'none';
    document.getElementById('aumAdminOnlyFields').style.display = isAdmin ? '' : 'none';
    document.getElementById('aumTeacherEmailHint').style.display = isTeacher ? '' : 'none';

    var passwordRow = document.getElementById('aumPasswordRow');
    var passwordInput = document.getElementById('aumStaffPassword');
    var passwordLabel = document.getElementById('aumPasswordLabel');
    var passwordHint = document.getElementById('aumTeacherPasswordHint');
    if (passwordRow && passwordInput) {
        passwordRow.style.display = isTeacher ? 'none' : '';
        passwordInput.required = isAdmin;
        passwordInput.disabled = isTeacher;
        if (passwordLabel) passwordLabel.style.display = isTeacher ? 'none' : '';
        if (passwordHint) passwordHint.style.display = isTeacher ? '' : 'none';
    }
    var confirmRow = document.getElementById('aumPasswordConfirmRow');
    var confirmInput = document.getElementById('aumStaffPasswordConfirm');
    if (confirmRow && confirmInput) {
        confirmRow.style.display = isAdmin ? '' : 'none';
        confirmInput.required = isAdmin;
        confirmInput.disabled = !isAdmin;
    }

    document.querySelectorAll('#aumOverlay [data-teacher-required]').forEach(function (i) {
        i.required = isTeacher;
    });

    document.querySelectorAll('#aumOverlay [data-admin-required]').forEach(function (i) {
        i.required = isAdmin;
    });
    if (window.syncFullAccessDays) window.syncFullAccessDays('aumForm');
}

    function aumResetForm() {
        var form = document.getElementById('aumForm');
        form.reset();
        document.querySelectorAll('#aumOverlay .aum-eye-btn').forEach(function (b) { aumSetEye(b, false); });
        aumCheckPasswordMatch();
        var alertBox = document.getElementById('aumAlert');
        alertBox.className = 'aum-alert';
        alertBox.innerHTML = '';
        aumSetRole(document.querySelector('#aumOverlay .aum-pill[data-role="student"]'));
        aumSetStatus(document.querySelector('#aumOverlay .aum-pill[data-status="active"]'));
    }

    function aumShowAlert(type, message) {
        var alertBox = document.getElementById('aumAlert');
        alertBox.className = 'aum-alert show ' + type;
        alertBox.innerHTML = '<i class="fas fa-' + (type === 'success' ? 'check-circle' : 'exclamation-circle') + '"></i> <span>' + message + '</span>';
    }

    // Philippine mobile validation for the Add User modal.
    // Keep this validation local to the modal so it works even if the shared
    // user-management JavaScript is cached or loaded in a different order.
    function aumValidatePhilippineMobile(input) {
        if (!input) return true;
        var value = input.value.trim();
        if (value === '') {
            input.setCustomValidity('');
            return true;
        }
        var normalized = value.replace(/[\\s-]+/g, '');
        var valid = /^(?:09[0-9]{9}|\\+639[0-9]{9})$/.test(normalized);
        input.setCustomValidity(valid ? '' : 'Use 09XXXXXXXXX or +63 9XX XXX XXXX format.');
        return valid;
    }

    document.querySelectorAll('#aumOverlay input[data-ph-mobile]').forEach(function (input) {
        input.addEventListener('input', function () { aumValidatePhilippineMobile(input); });
        input.addEventListener('blur', function () { aumValidatePhilippineMobile(input); });
    });

    // Show / hide password (eye icon) for the password + confirm fields.
    function aumSetEye(btn, show) {
        var input = document.getElementById(btn.getAttribute('data-eye-for'));
        if (!input) return;
        input.type = show ? 'text' : 'password';
        btn.querySelector('i').className = 'fas ' + (show ? 'fa-eye-slash' : 'fa-eye');
        var t = show ? 'Hide password' : 'Show password';
        btn.title = t;
        btn.setAttribute('aria-label', t);
    }
    document.querySelectorAll('#aumOverlay .aum-eye-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = document.getElementById(btn.getAttribute('data-eye-for'));
            aumSetEye(btn, input && input.type === 'password');
        });
    });

    // Live "passwords match" check on the confirm field.
    function aumCheckPasswordMatch() {
        var pw = document.getElementById('aumStaffPassword');
        var cf = document.getElementById('aumStaffPasswordConfirm');
        var hint = document.getElementById('aumPasswordMatchHint');
        if (!pw || !cf || !hint) return true;
        if (cf.disabled || cf.value === '') {
            cf.setCustomValidity('');
            hint.style.display = 'none';
            hint.className = 'aum-help-text';
            return true;
        }
        var ok = pw.value === cf.value;
        cf.setCustomValidity(ok ? '' : 'Passwords do not match.');
        hint.style.display = '';
        hint.className = 'aum-help-text ' + (ok ? 'match-ok' : 'match-bad');
        hint.innerHTML = ok
            ? '<i class="fas fa-check-circle"></i> Passwords match.'
            : '<i class="fas fa-exclamation-circle"></i> Passwords do not match.';
        return ok;
    }
    ['aumStaffPassword', 'aumStaffPasswordConfirm'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) el.addEventListener('input', aumCheckPasswordMatch);
    });

    document.getElementById('aumForm').addEventListener('submit', function (e) {
        e.preventDefault();
        var form = e.target;
        aumCheckPasswordMatch();

        var mobileValid = true;
        document.querySelectorAll('#aumOverlay input[data-ph-mobile]').forEach(function (input) {
            if (!aumValidatePhilippineMobile(input)) mobileValid = false;
        });
        if (!mobileValid || !form.reportValidity()) {
            var invalidMobile = document.querySelector('#aumOverlay input[data-ph-mobile]:invalid');
            if (invalidMobile) invalidMobile.focus();
            return;
        }

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

    document.getElementById('aumOverlay').addEventListener('click', function (e) {
        if (e.target === this) window.closeAddUserModal();
    });
})();
