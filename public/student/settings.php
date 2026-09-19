<?php
/**
 * SUA IntelliLearn - Student Profile Settings
 */

require_once '../../config/config.php';

if (!isLoggedIn() || ($_SESSION['role'] ?? '') !== 'student') {
    header('Location: ../../public/login.php');
    exit();
}

$userId = (int) ($_SESSION['user_id'] ?? 0);

$stmt = $pdo->prepare("SELECT u.id AS user_id, u.username, u.role, u.status, u.created_at,
                              s.student_id, s.student_lrn, s.firstname, s.lastname, s.middlename,
                              s.email
                       FROM users u
                       JOIN students s ON s.user_id = u.id
                       WHERE u.id = ?
                       LIMIT 1");
$stmt->execute([$userId]);
$profile = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$profile) {
    die('Student profile not found.');
}

$studentFullName = trim($profile['firstname'] . ' ' . ($profile['middlename'] ? $profile['middlename'] . ' ' : '') . $profile['lastname']);
$displayName = trim($profile['firstname'] . ' ' . $profile['lastname']);
$initials = function_exists('get_initials') ? get_initials($displayName) : strtoupper(substr($profile['firstname'], 0, 1) . substr($profile['lastname'], 0, 1));
$memberSince = date('F j, Y', strtotime($profile['created_at']));
$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Settings - SUA IntelliLearn</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/profile.css">
</head>
<body>

<?php include '../../includes/student_sidebar.php'; ?>

<main class="main-content" id="dashMain">
    <?php include '../../includes/student_header.php'; ?>

    <div class="content-wrapper student-profile-page">
        <div class="welcome-banner profile-banner fade-in">
            <div class="welcome-text">
                <h1><i class="fas fa-user-gear"></i> Profile Settings</h1>
                <p>Manage your account information and password</p>
            </div>
        </div>

        <div class="profile-grid fade-in">
            <aside class="profile-card profile-summary-card">
                <div class="profile-avatar-wrap">
                    <div class="profile-avatar"><?php echo htmlspecialchars($initials); ?></div>
                    <div class="profile-avatar-ring"></div>
                </div>
                <h2><?php echo htmlspecialchars($displayName); ?></h2>
                <span class="status-badge">
                    <span class="status-dot"></span>
                    <?php echo htmlspecialchars(ucfirst($profile['status'])); ?>
                </span>

                <div class="profile-divider"></div>

                <div class="profile-meta-list">
                    <div class="profile-meta-item">
                        <div class="meta-icon"><i class="fas fa-user-graduate"></i></div>
                        <div class="meta-content">
                            <span class="label">Role</span>
                            <span class="value">Student</span>
                        </div>
                    </div>
                    <div class="profile-meta-item">
                        <div class="meta-icon"><i class="fas fa-id-card"></i></div>
                        <div class="meta-content">
                            <span class="label">Student LRN</span>
                            <span class="value"><?php echo htmlspecialchars((string) $profile['student_lrn']); ?></span>
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
            </aside>

            <div class="profile-forms-col">
                <section class="profile-card form-card">
                    <div class="card-header">
                        <div class="card-header-icon"><i class="fas fa-id-card"></i></div>
                        <div>
                            <h2>Account Information</h2>
                            <p>Update the contact information linked to your student account.</p>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="infoErrors" class="form-alert error" role="alert" hidden></div>
                        <form id="infoForm">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="firstname">First Name</label>
                                    <input type="text" id="firstname" value="<?php echo htmlspecialchars($profile['firstname']); ?>" disabled>
                                </div>
                                <div class="form-group">
                                    <label for="lastname">Last Name</label>
                                    <input type="text" id="lastname" value="<?php echo htmlspecialchars($profile['lastname']); ?>" disabled>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="username"><i class="fas fa-user-tag"></i> Username</label>
                                <div class="input-wrap">
                                    <input type="text" id="username" value="<?php echo htmlspecialchars($profile['username']); ?>" disabled>
                                    <span class="input-icon"><i class="fas fa-lock"></i></span>
                                </div>
                                <small>Username is assigned by the school and cannot be changed here.</small>
                            </div>

                            <div class="form-group">
                                <label for="student_lrn"><i class="fas fa-id-card"></i> Student LRN</label>
                                <div class="input-wrap">
                                    <input type="text" id="student_lrn" value="<?php echo htmlspecialchars((string) $profile['student_lrn']); ?>" disabled>
                                    <span class="input-icon"><i class="fas fa-lock"></i></span>
                                </div>
                                <small>Your LRN is managed by the school.</small>
                            </div>

                            <div class="form-group">
                                <label for="email"><i class="fas fa-envelope"></i> Email Address</label>
                                <div class="input-wrap">
                                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($profile['email'] ?? ''); ?>" autocomplete="email" placeholder="student@example.com">
                                    <span class="input-icon"><i class="fas fa-at"></i></span>
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn-primary" id="infoSubmitBtn">
                                    <span class="btn-text"><i class="fas fa-save"></i> Save Changes</span>
                                    <span class="btn-loader" hidden><i class="fas fa-circle-notch fa-spin"></i> Saving...</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </section>

                <section class="profile-card form-card">
                    <div class="card-header">
                        <div class="card-header-icon lock-icon"><i class="fas fa-lock"></i></div>
                        <div>
                            <h2>Change Password</h2>
                            <p>Use a strong password that you do not reuse on other accounts.</p>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="passwordErrors" class="form-alert error" role="alert" hidden></div>
                        <form id="passwordForm">
                            <div class="form-group">
                                <label for="current_password"><i class="fas fa-key"></i> Current Password</label>
                                <div class="input-wrap">
                                    <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
                                    <button type="button" class="input-icon toggle-password" data-target="current_password" aria-label="Show current password"><i class="fas fa-eye"></i></button>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="new_password"><i class="fas fa-shield-halved"></i> New Password</label>
                                <div class="input-wrap">
                                    <input type="password" id="new_password" name="new_password" required minlength="8" pattern="(?=.*[A-Z])(?=.*[^A-Za-z0-9]).{8,}" autocomplete="new-password">
                                    <button type="button" class="input-icon toggle-password" data-target="new_password" aria-label="Show new password"><i class="fas fa-eye"></i></button>
                                </div>
                                <small>Minimum 8 characters, including at least one uppercase letter and one special character.</small>
                                <div class="password-strength" id="passwordStrength">
                                    <div class="strength-bar"><span></span></div>
                                    <span class="strength-text">Password strength</span>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="confirm_password"><i class="fas fa-check-double"></i> Confirm New Password</label>
                                <div class="input-wrap">
                                    <input type="password" id="confirm_password" name="confirm_password" required minlength="8" pattern="(?=.*[A-Z])(?=.*[^A-Za-z0-9]).{8,}" autocomplete="new-password">
                                    <button type="button" class="input-icon toggle-password" data-target="confirm_password" aria-label="Show password confirmation"><i class="fas fa-eye"></i></button>
                                </div>
                                <div class="match-indicator" id="matchIndicator"></div>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn-primary" id="passSubmitBtn">
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
</main>

<div id="profileToast" class="profile-toast" role="status" aria-live="polite">
    <i class="fas fa-circle-check"></i>
    <span id="profileToastMsg">Saved</span>
</div>

<script>
const PROFILE_CSRF_TOKEN = <?php echo json_encode($csrfToken); ?>;

function showErrors(id, errors) {
    const box = document.getElementById(id);
    if (!errors || !errors.length) {
        box.hidden = true;
        box.innerHTML = '';
        return;
    }
    box.innerHTML = errors.map(error => `<div>${escapeHtml(error)}</div>`).join('');
    box.hidden = false;
}

function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = value;
    return div.innerHTML;
}

function showToast(message, error = false) {
    const toast = document.getElementById('profileToast');
    document.getElementById('profileToastMsg').textContent = message;
    toast.classList.toggle('toast-error', error);
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 3500);
}

function setLoading(id, loading) {
    const button = document.getElementById(id);
    const text = button.querySelector('.btn-text');
    const loader = button.querySelector('.btn-loader');
    button.disabled = loading;
    text.hidden = loading;
    loader.hidden = !loading;
}

async function postProfile(action, fields) {
    const formData = new FormData();
    Object.entries(fields).forEach(([key, value]) => formData.append(key, value));
    formData.append('action', action);
    formData.append('csrf_token', PROFILE_CSRF_TOKEN);

    const response = await fetch('assets/api/profile_handler.php', {
        method: 'POST',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    });

    const data = await response.json();
    if (!response.ok && !data) throw new Error('Request failed');
    return data;
}

document.getElementById('infoForm').addEventListener('submit', async event => {
    event.preventDefault();
    showErrors('infoErrors', null);
    setLoading('infoSubmitBtn', true);

    try {
        const data = await postProfile('update_info', {
            email: document.getElementById('email').value.trim()
        });
        if (data.success) {
            showToast('Profile updated successfully.');
        } else {
            showErrors('infoErrors', data.errors || ['Unable to update your profile.']);
        }
    } catch (error) {
        showErrors('infoErrors', ['Unable to update your profile. Please try again.']);
    } finally {
        setLoading('infoSubmitBtn', false);
    }
});

document.getElementById('passwordForm').addEventListener('submit', async event => {
    event.preventDefault();
    showErrors('passwordErrors', null);

    const currentPassword = document.getElementById('current_password').value;
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;

    if (newPassword !== confirmPassword) {
        showErrors('passwordErrors', ['New password and confirmation do not match.']);
        return;
    }

    setLoading('passSubmitBtn', true);
    try {
        const data = await postProfile('change_password', {
            current_password: currentPassword,
            new_password: newPassword,
            confirm_password: confirmPassword
        });
        if (data.success) {
            document.getElementById('passwordForm').reset();
            updatePasswordStrength('');
            updateMatchIndicator();
            showToast('Password updated successfully.');
        } else {
            showErrors('passwordErrors', data.errors || ['Unable to update your password.']);
        }
    } catch (error) {
        showErrors('passwordErrors', ['Unable to update your password. Please try again.']);
    } finally {
        setLoading('passSubmitBtn', false);
    }
});

document.querySelectorAll('.toggle-password').forEach(button => {
    button.addEventListener('click', () => {
        const input = document.getElementById(button.dataset.target);
        const icon = button.querySelector('i');
        const showing = input.type === 'text';
        input.type = showing ? 'password' : 'text';
        icon.classList.toggle('fa-eye', showing);
        icon.classList.toggle('fa-eye-slash', !showing);
    });
});

const newPasswordInput = document.getElementById('new_password');
const confirmPasswordInput = document.getElementById('confirm_password');

function updatePasswordStrength(password) {
    const bar = document.querySelector('#passwordStrength .strength-bar span');
    const text = document.querySelector('#passwordStrength .strength-text');
    let strength = 0;
    if (password.length >= 8) strength++;
    if (password.length >= 12) strength++;
    if (/[A-Z]/.test(password)) strength++;
    if (/[0-9]/.test(password)) strength++;
    if (/[^A-Za-z0-9]/.test(password)) strength++;
    const labels = ['Password strength', 'Weak', 'Fair', 'Good', 'Strong', 'Very strong'];
    bar.style.width = `${strength * 20}%`;
    text.textContent = labels[strength];
}

function updateMatchIndicator() {
    const indicator = document.getElementById('matchIndicator');
    const confirm = confirmPasswordInput.value;
    if (!confirm) {
        indicator.innerHTML = '';
        indicator.className = 'match-indicator';
        return;
    }
    const match = confirm === newPasswordInput.value;
    indicator.className = `match-indicator ${match ? 'match' : 'no-match'}`;
    indicator.innerHTML = match
        ? '<i class="fas fa-circle-check"></i> Passwords match'
        : '<i class="fas fa-circle-xmark"></i> Passwords do not match';
}

newPasswordInput.addEventListener('input', event => {
    updatePasswordStrength(event.target.value);
    updateMatchIndicator();
});
confirmPasswordInput.addEventListener('input', updateMatchIndicator);
</script>
</body>
</html>
