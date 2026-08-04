<?php
/**
 * SUA IntelliLearn - Admin Profile Settings
 * St. Uriel Academy Admin Portal
 */

require_once '../../config/config.php';
require_once 'assests/api/dashboard_functions.php';

requireAdmin();

$profile = get_admin_profile($pdo, (int) $_SESSION['user_id']);
if (!$profile) {
    die('Profile not found.');
}

$csrfToken   = generateCSRFToken();
$initials    = get_initials($profile['username']);
$avatarColor = get_avatar_color($profile['username']);
$memberSince = date('F j, Y', strtotime($profile['created_at']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Settings - SUA IntelliLearn</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assests/css/dashboard.css">
    <link rel="stylesheet" href="assests/css/profile.css">
</head>
<body>

    <?php include '../../includes/admin_sidebar.php'; ?>

    <div class="main-content">
        <?php include '../../includes/admin_header.php'; ?>

        <div class="content-wrapper">

            <!-- Decorative Background Elements -->
            <div class="deco-circle deco-circle-1"></div>
            <div class="deco-circle deco-circle-2"></div>

            <!-- Page Header -->
            <div class="welcome-banner fade-in">
                <div class="welcome-banner-content">
                    <h1><i class="fas fa-user-gear"></i> Profile Settings</h1>
                    <p>Manage your account information and password</p>
                </div>
                <div class="welcome-banner-accent">
                    <i class="fas fa-shield-halved"></i>
                </div>
            </div>

            <!-- Profile Grid -->
            <div class="profile-grid fade-in">

                <!-- Profile Summary -->
                <aside class="card profile-summary-card">
                    <div class="card-body profile-summary">
                        <div class="profile-avatar-wrap">
                            <div class="profile-avatar" style="background: <?php echo $avatarColor; ?>;">
                                <?php echo htmlspecialchars($initials); ?>
                            </div>
                            <div class="profile-avatar-ring"></div>
                        </div>
                        <h3><?php echo htmlspecialchars($profile['username']); ?></h3>
                        <span class="status-badge status-<?php echo htmlspecialchars($profile['status']); ?>">
                            <span class="status-dot"></span>
                            <?php echo htmlspecialchars(ucfirst($profile['status'])); ?>
                        </span>

                        <div class="profile-divider">
                            <span><i class="fas fa-id-badge"></i></span>
                        </div>

                        <div class="profile-meta-list">
                            <div class="profile-meta-item">
                                <div class="meta-icon"><i class="fas fa-user-shield"></i></div>
                                <div class="meta-content">
                                    <span class="label">Role</span>
                                    <span class="value"><?php echo htmlspecialchars(ucfirst($profile['role'])); ?></span>
                                </div>
                            </div>
                            <div class="profile-meta-item">
                                <div class="meta-icon"><i class="fas fa-layer-group"></i></div>
                                <div class="meta-content">
                                    <span class="label">Access Level</span>
                                    <span class="value"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $profile['access_level']))); ?></span>
                                </div>
                            </div>
                            <div class="profile-meta-item">
                                <div class="meta-icon"><i class="fas fa-calendar-check"></i></div>
                                <div class="meta-content">
                                    <span class="label">Member Since</span>
                                    <span class="value"><?php echo htmlspecialchars($memberSince); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </aside>

                <!-- Profile Forms -->
                <div class="profile-forms-col">

                    <!-- Account Information -->
                    <section class="card form-card">
                        <div class="card-header">
                            <div class="card-header-icon">
                                <i class="fas fa-id-card"></i>
                            </div>
                            <h2>Account Information</h2>
                        </div>
                        <div class="card-body">
                            <div id="infoErrors" class="form-alert" role="alert" aria-live="assertive" hidden></div>
                            <form id="infoForm">
                                <div class="form-group">
                                    <label for="username">
                                        <i class="fas fa-user-tag"></i> Username
                                    </label>
                                    <div class="input-wrap">
                                        <input type="text" id="username" value="<?php echo htmlspecialchars($profile['username']); ?>" disabled aria-describedby="username-help">
                                        <span class="input-icon"><i class="fas fa-lock"></i></span>
                                    </div>
                                    <small id="username-help">Username can't be changed here.</small>
                                </div>
                                <div class="form-group">
                                    <label for="email">
                                        <i class="fas fa-envelope"></i> Email Address
                                    </label>
                                    <div class="input-wrap">
                                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($profile['email']); ?>" required autocomplete="email">
                                        <span class="input-icon"><i class="fas fa-at"></i></span>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="position">
                                        <i class="fas fa-briefcase"></i> Position
                                    </label>
                                    <div class="input-wrap">
                                        <select id="position" name="position" required>
                                            <option value="principal" <?php echo $profile['position'] === 'principal' ? 'selected' : ''; ?>>Principal</option>
                                            <option value="registrar" <?php echo $profile['position'] === 'registrar' ? 'selected' : ''; ?>>Registrar</option>
                                            <option value="staff" <?php echo $profile['position'] === 'staff' ? 'selected' : ''; ?>>Staff</option>
                                        </select>
                                        <span class="input-icon"><i class="fas fa-chevron-down"></i></span>
                                    </div>
                                </div>
                                <div class="form-actions">
                                    <button type="submit" class="btn-sm btn-primary" id="infoSubmitBtn">
                                        <span class="btn-text"><i class="fas fa-save"></i> Save Changes</span>
                                        <span class="btn-loader" hidden><i class="fas fa-circle-notch fa-spin"></i> Saving...</span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </section>

                    <!-- Change Password -->
                    <section class="card form-card">
                        <div class="card-header">
                            <div class="card-header-icon lock-icon">
                                <i class="fas fa-lock"></i>
                            </div>
                            <h2>Change Password</h2>
                        </div>
                        <div class="card-body">
                            <div id="passwordErrors" class="form-alert" role="alert" aria-live="assertive" hidden></div>
                            <form id="passwordForm">
                                <div class="form-group">
                                    <label for="current_password">
                                        <i class="fas fa-key"></i> Current Password
                                    </label>
                                    <div class="input-wrap">
                                        <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
                                        <span class="input-icon toggle-password" data-target="current_password">
                                            <i class="fas fa-eye"></i>
                                        </span>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="new_password">
                                        <i class="fas fa-shield-halved"></i> New Password
                                    </label>
                                    <div class="input-wrap">
                                        <input type="password" id="new_password" name="new_password" required minlength="8" autocomplete="new-password" aria-describedby="password-help">
                                        <span class="input-icon toggle-password" data-target="new_password">
                                            <i class="fas fa-eye"></i>
                                        </span>
                                    </div>
                                    <small id="password-help">At least 8 characters.</small>
                                    <!-- Password Strength Meter -->
                                    <div class="password-strength" id="passwordStrength">
                                        <div class="strength-bar"><span></span></div>
                                        <span class="strength-text">Password strength</span>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="confirm_password">
                                        <i class="fas fa-check-double"></i> Confirm New Password
                                    </label>
                                    <div class="input-wrap">
                                        <input type="password" id="confirm_password" name="confirm_password" required minlength="8" autocomplete="new-password">
                                        <span class="input-icon toggle-password" data-target="confirm_password">
                                            <i class="fas fa-eye"></i>
                                        </span>
                                    </div>
                                    <div class="match-indicator" id="matchIndicator">
                                        <i class="fas fa-circle-check"></i> <span>Passwords match</span>
                                    </div>
                                </div>
                                <div class="form-actions">
                                    <button type="submit" class="btn-sm btn-primary" id="passSubmitBtn">
                                        <span class="btn-text"><i class="fas fa-key"></i> Update Password</span>
                                        <span class="btn-loader" hidden><i class="fas fa-circle-notch fa-spin"></i> Updating...</span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </section>

                </div>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div id="profileToast" class="profile-toast" role="status" aria-live="polite" aria-atomic="true">
        <div class="toast-icon"><i class="fas fa-info-circle"></i></div>
        <span id="profileToastMsg">Notification</span>
    </div>

    <script>
        /* ============================================
           Shared Dashboard Utilities
           ============================================ */
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('collapsed');
        }

        function setActive(el) {
            document.querySelectorAll('.nav-item').forEach(item => item.classList.remove('active'));
            el.classList.add('active');
        }

        function showToast(message) {
            const toast = document.getElementById('toast');
            const msg = document.getElementById('toast-msg');
            if (!toast || !msg) return;
            msg.textContent = message;
            toast.style.transform = 'translateY(0)';
            toast.style.opacity = '1';
            setTimeout(() => {
                toast.style.transform = 'translateY(100px)';
                toast.style.opacity = '0';
            }, 2500);
        }

        /* ============================================
           Profile Page Logic
           ============================================ */
        const PROFILE_CSRF_TOKEN = <?php echo json_encode($csrfToken); ?>;

        function profileShowToast(message, isError) {
            const toast = document.getElementById('profileToast');
            const msg = document.getElementById('profileToastMsg');
            msg.textContent = message;
            toast.classList.toggle('toast-error', !!isError);
            toast.classList.add('show');
            setTimeout(() => toast.classList.remove('show'), 3500);
        }

        function profileShowErrors(boxId, errors) {
            const box = document.getElementById(boxId);
            if (!errors || errors.length === 0) {
                box.hidden = true;
                box.innerHTML = '';
                return;
            }
            box.innerHTML = errors.map(e => '<div>' + e + '</div>').join('');
            box.hidden = false;
        }

        async function profilePost(action, formData) {
            formData.append('action', action);
            formData.append('csrf_token', PROFILE_CSRF_TOKEN);

            const res = await fetch('assests/api/profile_handler.php', {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            });
            return res.json();
        }

        /* ---- Loading State Helpers ---- */
        function setLoading(btnId, loading) {
            const btn = document.getElementById(btnId);
            if (!btn) return;
            const text = btn.querySelector('.btn-text');
            const loader = btn.querySelector('.btn-loader');
            btn.disabled = loading;
            if (text) text.hidden = loading;
            if (loader) loader.hidden = !loading;
        }

        /* ---- Info Form ---- */
        document.getElementById('infoForm').addEventListener('submit', async function (e) {
            e.preventDefault();
            profileShowErrors('infoErrors', null);
            setLoading('infoSubmitBtn', true);

            const formData = new FormData();
            formData.append('email', document.getElementById('email').value.trim());
            formData.append('position', document.getElementById('position').value);

            try {
                const data = await profilePost('update_info', formData);
                if (data.success) {
                    profileShowToast('Profile updated successfully.', false);
                } else {
                    profileShowErrors('infoErrors', data.errors || ['Something went wrong.']);
                }
            } catch (err) {
                profileShowErrors('infoErrors', ['Something went wrong. Please try again.']);
            } finally {
                setLoading('infoSubmitBtn', false);
            }
        });

        /* ---- Password Form ---- */
        document.getElementById('passwordForm').addEventListener('submit', async function (e) {
            e.preventDefault();
            profileShowErrors('passwordErrors', null);
            setLoading('passSubmitBtn', true);

            const newPass = document.getElementById('new_password').value;
            const confirmPass = document.getElementById('confirm_password').value;
            if (newPass !== confirmPass) {
                profileShowErrors('passwordErrors', ['New password and confirmation do not match.']);
                setLoading('passSubmitBtn', false);
                return;
            }

            const formData = new FormData();
            formData.append('current_password', document.getElementById('current_password').value);
            formData.append('new_password', newPass);
            formData.append('confirm_password', confirmPass);

            try {
                const data = await profilePost('change_password', formData);
                if (data.success) {
                    profileShowToast('Password updated successfully.', false);
                    document.getElementById('passwordForm').reset();
                    updatePasswordStrength('');
                    updateMatchIndicator();
                } else {
                    profileShowErrors('passwordErrors', data.errors || ['Something went wrong.']);
                }
            } catch (err) {
                profileShowErrors('passwordErrors', ['Something went wrong. Please try again.']);
            } finally {
                setLoading('passSubmitBtn', false);
            }
        });

        /* ---- Password Visibility Toggle ---- */
        document.querySelectorAll('.toggle-password').forEach(toggle => {
            toggle.addEventListener('click', function () {
                const targetId = this.getAttribute('data-target');
                const input = document.getElementById(targetId);
                const icon = this.querySelector('i');
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.replace('fa-eye', 'fa-eye-slash');
                } else {
                    input.type = 'password';
                    icon.classList.replace('fa-eye-slash', 'fa-eye');
                }
            });
        });

        /* ---- Password Strength Meter ---- */
        const newPassInput = document.getElementById('new_password');
        const strengthBar = document.querySelector('#passwordStrength .strength-bar span');
        const strengthText = document.querySelector('#passwordStrength .strength-text');

        function updatePasswordStrength(password) {
            let strength = 0;
            if (password.length >= 8) strength++;
            if (password.length >= 12) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;

            const bar = document.querySelector('#passwordStrength .strength-bar span');
            const txt = document.querySelector('#passwordStrength .strength-text');
            if (!bar || !txt) return;

            const colors = ['#e5e7eb', '#ef4444', '#f97316', '#eab308', '#22c55e', '#10b981'];
            const labels = ['Too short', 'Weak', 'Fair', 'Good', 'Strong', 'Very Strong'];
            const widths = ['0%', '20%', '40%', '60%', '80%', '100%'];

            const idx = Math.min(strength, 5);
            bar.style.width = widths[idx];
            bar.style.background = colors[idx];
            txt.textContent = password.length === 0 ? 'Password strength' : labels[idx];
            txt.style.color = colors[idx];
        }

        if (newPassInput) {
            newPassInput.addEventListener('input', (e) => updatePasswordStrength(e.target.value));
        }

        /* ---- Password Match Indicator ---- */
        const confirmInput = document.getElementById('confirm_password');
        const matchIndicator = document.getElementById('matchIndicator');

        function updateMatchIndicator() {
            if (!matchIndicator || !confirmInput || !newPassInput) return;
            const p1 = newPassInput.value;
            const p2 = confirmInput.value;
            if (p2.length === 0) {
                matchIndicator.classList.remove('show', 'match', 'mismatch');
                return;
            }
            matchIndicator.classList.add('show');
            if (p1 === p2) {
                matchIndicator.classList.add('match');
                matchIndicator.classList.remove('mismatch');
                matchIndicator.querySelector('span').textContent = 'Passwords match';
                matchIndicator.querySelector('i').className = 'fas fa-circle-check';
            } else {
                matchIndicator.classList.add('mismatch');
                matchIndicator.classList.remove('match');
                matchIndicator.querySelector('span').textContent = 'Passwords do not match';
                matchIndicator.querySelector('i').className = 'fas fa-circle-xmark';
            }
        }

        if (confirmInput) confirmInput.addEventListener('input', updateMatchIndicator);
        if (newPassInput) newPassInput.addEventListener('input', updateMatchIndicator);
    </script>

</body>
</html>