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
    'enrollment_open'         => '1'

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

// ---- Teacher evaluation survey state ----
require_once '../../includes/teacher_evaluation.php';
$isFullAdmin   = adminAccessLevel() === 'full';   // only Full Access admins may send/close a survey
$tevalReady    = teval_tables_ready($pdo);
$tevalOpen     = $tevalReady ? teval_get_open_round($pdo) : null;
$tevalRounds   = $tevalReady ? teval_get_rounds($pdo, 6) : [];
$tevalProgress = $tevalOpen ? teval_round_results($pdo, $tevalOpen)['overview'] : null;
$tevalDefaultTerm = resolveCurrentTerm(getTermIntervals($pdo)) ?? 'TRM 1';
$currentYearLabel = '';
foreach ($schoolYears as $sy) {
    if ($sy['is_current']) { $currentYearLabel = trim($sy['label']); break; }
}
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
                <button class="settings-tab" data-tab="teacher-evaluation" onclick="switchTab(this)">
                    <i class="fas fa-chalkboard-user"></i> Teacher Evaluation
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
                        <p>Defaults applied when creating classes and letting students join with a class code.</p>
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


                        <div class="toggle-row">
                            <div class="toggle-row-text">
                                <strong>Enrollment open</strong>
                                <span>Turn off to stop students from joining classes with a class code. Teachers approve or deny each request.</span>
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

            <!-- ================= TEACHER EVALUATION ================= -->
            <div class="settings-panel" id="panel-teacher-evaluation">

                <?php if (!$tevalReady): ?>
                <div class="settings-card">
                    <div class="settings-card-header">
                        <h2><i class="fas fa-database"></i> Setup required</h2>
                        <p>The teacher evaluation tables don't exist yet. Run <code>teacher_evaluation_migration.sql</code> against the <code>lms</code> database, then reload this page.</p>
                    </div>
                </div>
                <?php else: ?>

                <?php if (!$isFullAdmin): ?>
                <div class="eval-notice">
                    <i class="fas fa-lock"></i>
                    <span>Only administrators with <strong>Full Access</strong> can send or close a teacher evaluation survey. You can still review the status here.</span>
                </div>
                <?php endif; ?>

                <?php if ($tevalOpen): ?>
                <!-- A survey is live -->
                <div class="settings-card">
                    <div class="settings-card-header">
                        <h2><i class="fas fa-paper-plane"></i> Survey in progress</h2>
                        <p>Students can see this survey on their dashboard and under <em>Teacher Evaluation</em>.</p>
                    </div>

                    <div class="eval-status">
                        <div class="eval-status-main">
                            <strong><?= clean($tevalOpen['title']) ?></strong>
                            <span>
                                <?= clean($tevalOpen['school_year_label']) ?> &middot; <?= clean($tevalOpen['term']) ?>
                                &middot;
                                <?= $tevalOpen['closes_on']
                                    ? 'Closes ' . clean(date('M j, Y', strtotime($tevalOpen['closes_on'])))
                                    : 'No deadline' ?>
                            </span>
                        </div>
                        <span class="badge-current">Open</span>
                    </div>

                    <div class="eval-progress" aria-label="Survey completion">
                        <div class="eval-progress-head">
                            <span><?= (int) $tevalProgress['submitted'] ?> of <?= (int) $tevalProgress['expected'] ?> class evaluations submitted</span>
                            <strong><?= (int) $tevalProgress['rate'] ?>%</strong>
                        </div>
                        <div class="eval-progress-track"><div class="eval-progress-fill" style="width: <?= (int) $tevalProgress['rate'] ?>%"></div></div>
                    </div>

                    <div class="form-alert" id="closeSurveyError" hidden></div>
                    <div class="form-actions eval-actions">
                        <a class="btn btn-outline" href="analytics.php?round=<?= (int) $tevalOpen['round_id'] ?>">
                            <i class="fas fa-chart-line"></i> View results
                        </a>
                        <?php if ($isFullAdmin): ?>
                        <button type="button" class="btn btn-primary" id="closeSurveyBtn"
                            onclick="closeSurvey(<?= (int) $tevalOpen['round_id'] ?>, this)">
                            <i class="fas fa-circle-stop"></i> Close survey
                        </button>
                        <?php endif; ?>
                    </div>
                </div>

                <?php else: ?>
                <!-- No live survey: form to send one -->
                <div class="settings-card">
                    <div class="settings-card-header">
                        <h2><i class="fas fa-paper-plane"></i> Send a teacher evaluation survey</h2>
                        <p>
                            Students rate each teacher they're enrolled with for the chosen term.
                            Answers are saved <strong>anonymously</strong> (no student name or ID is attached), and
                            results appear under <em>System Analytics</em> with an AI-generated summary.
                        </p>
                    </div>

                    <form id="sendSurveyForm">
                        <input type="hidden" name="csrf" value="<?= clean($csrfToken) ?>">
                        <input type="hidden" name="action" value="open">
                        <div class="form-alert" id="sendSurveyError" hidden></div>

                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-heading"></i> Survey title</label>
                                <input type="text" name="title" maxlength="150"
                                    placeholder="Teacher Evaluation - <?= clean($currentYearLabel) ?> <?= clean($tevalDefaultTerm) ?>"
                                    <?= $isFullAdmin ? '' : 'disabled' ?>>
                                <small>Optional. Leave blank to use the default.</small>
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-layer-group"></i> Term to evaluate</label>
                                <select name="term" required <?= $isFullAdmin ? '' : 'disabled' ?>>
                                    <?php foreach (TEACHER_EVAL_TERMS as $term): ?>
                                    <option value="<?= clean($term) ?>" <?= $term === $tevalDefaultTerm ? 'selected' : '' ?>><?= clean($term) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <small>Applies to the current school year<?= $currentYearLabel !== '' ? ' (' . clean($currentYearLabel) . ')' : '' ?>.</small>
                            </div>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-calendar-day"></i> Deadline</label>
                            <input type="date" name="closes_on" min="<?= date('Y-m-d') ?>" <?= $isFullAdmin ? '' : 'disabled' ?>>
                            <small>Optional. Students can't submit after this date.</small>
                        </div>

                        <?php if ($isFullAdmin): ?>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary" id="sendSurveyBtn">
                                <i class="fas fa-paper-plane"></i> Send survey to students
                            </button>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
                <?php endif; ?>

                <!-- Past surveys -->
                <div class="settings-card">
                    <div class="settings-card-header">
                        <h2><i class="fas fa-clock-rotate-left"></i> Recent surveys</h2>
                        <p>Open any survey's results in System Analytics.</p>
                    </div>
                    <div class="year-list">
                        <?php foreach ($tevalRounds as $r): $live = teval_round_is_live($r); ?>
                        <div class="year-row <?= $live ? 'is-current' : '' ?>">
                            <div class="year-row-info">
                                <div class="year-icon"><i class="fas fa-clipboard-list"></i></div>
                                <div>
                                    <strong><?= clean($r['title']) ?></strong>
                                    <span><?= clean($r['school_year_label']) ?> &middot; <?= clean($r['term']) ?> &middot; Sent <?= clean(date('M j, Y', strtotime($r['opened_at']))) ?></span>
                                </div>
                            </div>
                            <a class="btn btn-outline btn-sm" href="analytics.php?round=<?= (int) $r['round_id'] ?>">
                                <?= $live ? 'Open' : 'Results' ?>
                            </a>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($tevalRounds)): ?>
                        <p style="color: var(--text-muted); font-size: 0.88rem;">No surveys have been sent yet.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <?php endif; ?>
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

        // ---- Teacher evaluation tab ----
        // Re-open the tab named in the URL hash (the page reloads after a save).
        (function () {
            const wanted = location.hash.replace('#', '');
            const tab = wanted ? document.querySelector('.settings-tab[data-tab="' + wanted + '"]') : null;
            if (tab) switchTab(tab);
        })();

        const sendSurveyForm = document.getElementById('sendSurveyForm');
        if (sendSurveyForm) {
            sendSurveyForm.addEventListener('submit', function (e) {
                e.preventDefault();
                if (!confirm('Send this survey to students now? They will be asked to evaluate each of their teachers.')) return;
                location.hash = 'teacher-evaluation';
                submitForm(
                    this, 'teacher_evaluation_round.php',
                    document.getElementById('sendSurveyBtn'),
                    document.getElementById('sendSurveyError'),
                    '<i class="fas fa-paper-plane"></i> Send survey to students'
                );
            });
        }

        function closeSurvey(roundId, btn) {
            if (!confirm('Close this survey? Students will no longer be able to submit evaluations.')) return;
            const errorBox = document.getElementById('closeSurveyError');
            const original = btn.innerHTML;
            errorBox.hidden = true;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Closing...';

            const form = new FormData();
            form.append('csrf', document.querySelector('input[name="csrf"]').value);
            form.append('action', 'close');
            form.append('round_id', roundId);

            fetch('teacher_evaluation_round.php', {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body: form
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    location.hash = 'teacher-evaluation';
                    location.reload();
                } else {
                    errorBox.innerHTML = data.errors.map(err => '<div>' + err + '</div>').join('');
                    errorBox.hidden = false;
                    btn.disabled = false;
                    btn.innerHTML = original;
                }
            })
            .catch(() => {
                errorBox.innerHTML = '<div>Something went wrong. Please try again.</div>';
                errorBox.hidden = false;
                btn.disabled = false;
                btn.innerHTML = original;
            });
        }

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