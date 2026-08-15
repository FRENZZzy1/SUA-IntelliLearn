<?php
/**
 * SUA IntelliLearn - Admin Settings
 * St. Uriel Academy Admin Portal
 *
 * Requires the `system_settings` table — see settings_migration.sql.
 */

require_once '../../config/config.php';

requireAdmin();

$csrfToken = generateCSRFToken();
$flash     = getFlashMessage();

// ---- Load key/value settings, falling back to defaults if a key is missing ----
$defaults = [
    'school_name'             => 'St. Uriel Academy',
    'default_class_capacity'  => '50',
    'auto_approve_enrollment' => '0',
    'enrollment_open'         => '1',
    'passing_grade'           => '75',
];

$settings = $defaults;
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    foreach ($stmt->fetchAll() as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    // Table likely doesn't exist yet — page still renders with defaults;
    // saving will surface the real error until the migration is run.
}

// ---- Load school years ----
$schoolYears = $pdo->query("SELECT * FROM schoolyears ORDER BY start_date DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - SUA IntelliLearn</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assests/css/dashboard.css">
    <link rel="stylesheet" href="assests/css/settings.css">
</head>
<body>

    <?php include '../../includes/admin_sidebar.php'; ?>

    <div class="main-content">
        <?php include '../../includes/admin_header.php'; ?>

        <div class="content-wrapper">

            <div class="deco-circle deco-circle-1"></div>
            <div class="deco-circle deco-circle-2"></div>

            <div class="welcome-banner fade-in">
                <div class="welcome-banner-content">
                    <h1><i class="fas fa-gear"></i> Settings</h1>
                    <p>Manage school years, enrollment rules, and system preferences</p>
                </div>
                <div class="welcome-banner-accent">
                    <i class="fas fa-sliders"></i>
                </div>
            </div>

            <?php if ($flash): ?>
            <div class="flash-message flash-<?= clean($flash['type']) ?>">
                <?= clean($flash['message']) ?>
            </div>
            <?php endif; ?>

            <!-- Tabs -->
            <div class="settings-tabs">
                <button class="settings-tab active" data-tab="school-year" onclick="switchTab(this)">
                    <i class="fas fa-calendar-days"></i> School Year & Terms
                </button>
                <button class="settings-tab" data-tab="enrollment" onclick="switchTab(this)">
                    <i class="fas fa-user-plus"></i> Enrollment Rules
                </button>
                <button class="settings-tab" data-tab="general" onclick="switchTab(this)">
                    <i class="fas fa-building-columns"></i> General
                </button>
            </div>

            <!-- ================= SCHOOL YEAR & TERMS ================= -->
            <div class="settings-panel active" id="panel-school-year">

                <div class="settings-card">
                    <div class="settings-card-header">
                        <h2><i class="fas fa-calendar-check"></i> School Years</h2>
                        <p>Only one school year can be active at a time. New sections and enrollments are created under the active year.</p>
                    </div>

                    <div class="year-list">
                        <?php foreach ($schoolYears as $sy): ?>
                        <div class="year-row <?= $sy['is_current'] ? 'is-current' : '' ?>">
                            <div class="year-row-info">
                                <div class="year-icon"><i class="fas fa-calendar"></i></div>
                                <div>
                                    <strong><?= clean($sy['label']) ?></strong>
                                    <span><?= clean(date('M j, Y', strtotime($sy['start_date']))) ?> &ndash; <?= clean(date('M j, Y', strtotime($sy['end_date']))) ?></span>
                                </div>
                            </div>
                            <?php if ($sy['is_current']): ?>
                                <span class="badge-current">Current</span>
                            <?php else: ?>
                                <button type="button" class="btn btn-outline btn-sm"
                                    onclick="setCurrentYear(<?= (int) $sy['school_year_id'] ?>, this)">
                                    Set as Current
                                </button>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>

                        <?php if (empty($schoolYears)): ?>
                        <p style="color: var(--text-muted); font-size: 0.88rem;">No school years yet. Add one below.</p>
                        <?php endif; ?>
                    </div>

                    <form id="addYearForm">
                        <input type="hidden" name="csrf" value="<?= clean($csrfToken) ?>">
                        <div class="form-alert" id="yearFormError" hidden></div>
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-tag"></i> Label</label>
                                <input type="text" name="label" placeholder="SY 2027" maxlength="9" required>
                                <small>Short label, e.g. "SY 2027" (max 9 characters).</small>
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-calendar-day"></i> Start Date</label>
                                <input type="date" name="start_date" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-calendar-day"></i> End Date</label>
                                <input type="date" name="end_date" required>
                            </div>
                            <div class="form-group" style="display: flex; align-items: flex-end;">
                                <label style="margin-bottom: 12px;">
                                    <input type="checkbox" name="make_current" style="width: auto;"> Make this the current school year
                                </label>
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary" id="addYearBtn">
                                <i class="fas fa-plus"></i> Add School Year
                            </button>
                        </div>
                    </form>
                </div>

                <div class="settings-card">
                    <div class="settings-card-header">
                        <h2><i class="fas fa-triangle-exclamation"></i> About Terms</h2>
                        <p>Class offerings are created per term (TRM 1 / TRM 2 / TRM 3) from Classes & Subjects. Carrying enrolled students forward from one term to the next is not automatic yet — each term's offering and enrollments are separate records today.</p>
                    </div>
                </div>
            </div>

            <!-- ================= ENROLLMENT RULES ================= -->
            <div class="settings-panel" id="panel-enrollment">
                <div class="settings-card">
                    <div class="settings-card-header">
                        <h2><i class="fas fa-clipboard-list"></i> Enrollment Rules</h2>
                        <p>Defaults applied when creating classes and reviewing enrollment requests.</p>
                    </div>

                    <form id="settingsForm">
                        <input type="hidden" name="csrf" value="<?= clean($csrfToken) ?>">
                        <input type="hidden" name="school_name" value="<?= clean($settings['school_name']) ?>">
                        <div class="form-alert" id="settingsFormError" hidden></div>

                        <div class="form-group">
                            <label><i class="fas fa-users"></i> Default Class Capacity</label>
                            <input type="number" name="default_class_capacity" min="1" max="500"
                                value="<?= clean($settings['default_class_capacity']) ?>" required>
                            <small>Used as the pre-filled capacity when a new class offering is created.</small>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-percent"></i> Passing Grade</label>
                            <input type="number" name="passing_grade" min="0" max="100" step="0.01"
                                value="<?= clean($settings['passing_grade']) ?>" required>
                            <small>Minimum final average considered passing for a subject.</small>
                        </div>

                        <div class="toggle-row">
                            <div class="toggle-row-text">
                                <strong>Auto-approve enrollment requests</strong>
                                <span>Skip manual admin review when the requested section has open seats.</span>
                            </div>
                            <label class="switch">
                                <input type="checkbox" name="auto_approve_enrollment" <?= $settings['auto_approve_enrollment'] === '1' ? 'checked' : '' ?>>
                                <span class="switch-slider"></span>
                            </label>
                        </div>

                        <div class="toggle-row">
                            <div class="toggle-row-text">
                                <strong>Enrollment open</strong>
                                <span>Turn off to hide the "Enroll Student" action for the current term.</span>
                            </div>
                            <label class="switch">
                                <input type="checkbox" name="enrollment_open" <?= $settings['enrollment_open'] === '1' ? 'checked' : '' ?>>
                                <span class="switch-slider"></span>
                            </label>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary" id="saveEnrollmentBtn">
                                <i class="fas fa-floppy-disk"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- ================= GENERAL ================= -->
            <div class="settings-panel" id="panel-general">
                <div class="settings-card">
                    <div class="settings-card-header">
                        <h2><i class="fas fa-school"></i> School Information</h2>
                        <p>Shown across the portal (sidebar, login page, exports).</p>
                    </div>

                    <form id="generalForm">
                        <input type="hidden" name="csrf" value="<?= clean($csrfToken) ?>">
                        <input type="hidden" name="default_class_capacity" value="<?= clean($settings['default_class_capacity']) ?>">
                        <input type="hidden" name="passing_grade" value="<?= clean($settings['passing_grade']) ?>">
                        <input type="hidden" name="auto_approve_enrollment" value="<?= $settings['auto_approve_enrollment'] === '1' ? '1' : '0' ?>">
                        <input type="hidden" name="enrollment_open" value="<?= $settings['enrollment_open'] === '1' ? '1' : '0' ?>">
                        <div class="form-alert" id="generalFormError" hidden></div>

                        <div class="form-group">
                            <label><i class="fas fa-signature"></i> School Name</label>
                            <input type="text" name="school_name" maxlength="150"
                                value="<?= clean($settings['school_name']) ?>" required>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary" id="saveGeneralBtn">
                                <i class="fas fa-floppy-disk"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>

    <script>
        function switchTab(btn) {
            document.querySelectorAll('.settings-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.settings-panel').forEach(p => p.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById('panel-' + btn.dataset.tab).classList.add('active');
        }

        // ---- Generic submit helper (mirrors courses.php's submitModalForm) ----
        function submitForm(form, url, submitBtn, errorBox, idleLabel) {
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
                    location.reload();
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

        document.getElementById('addYearForm').addEventListener('submit', function (e) {
            e.preventDefault();
            submitForm(
                this, 'add_school_year.php',
                document.getElementById('addYearBtn'),
                document.getElementById('yearFormError'),
                '<i class="fas fa-plus"></i> Add School Year'
            );
        });

        document.getElementById('settingsForm').addEventListener('submit', function (e) {
            e.preventDefault();
            submitForm(
                this, 'save_settings.php',
                document.getElementById('saveEnrollmentBtn'),
                document.getElementById('settingsFormError'),
                '<i class="fas fa-floppy-disk"></i> Save Changes'
            );
        });

        document.getElementById('generalForm').addEventListener('submit', function (e) {
            e.preventDefault();
            submitForm(
                this, 'save_settings.php',
                document.getElementById('saveGeneralBtn'),
                document.getElementById('generalFormError'),
                '<i class="fas fa-floppy-disk"></i> Save Changes'
            );
        });

        function setCurrentYear(id, btn) {
            const original = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

            const form = new FormData();
            form.append('csrf', document.querySelector('input[name="csrf"]').value);
            form.append('school_year_id', id);

            fetch('set_current_school_year.php', {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body: form
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.errors.join('\n'));
                    btn.disabled = false;
                    btn.innerHTML = original;
                }
            })
            .catch(() => {
                alert('Something went wrong. Please try again.');
                btn.disabled = false;
                btn.innerHTML = original;
            });
        }
    </script>

</body>
</html>
