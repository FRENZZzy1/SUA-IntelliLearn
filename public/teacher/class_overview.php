<?php
include 'assets/api/class_overview_functions.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($classInfo['subject_name']) ?> · <?= htmlspecialchars($classInfo['section_name']) ?> · SUA IntelliLearn</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/courses.css">
    <link rel="stylesheet" href="assets/css/class_overview.css">
</head>
<body>

<?php include '../../includes/teachers_sidebar.php'; ?>

<main class="main-content" id="dashMain">

    <?php include '../../includes/teacher_header.php'; ?>

    <a href="course_section.php?section_id=<?= (int) $classInfo['section_id'] ?>" class="breadcrumb-back">
        <i class="fas fa-arrow-left"></i> Back to <?= htmlspecialchars($classInfo['section_name']) ?>
    </a>

    <div class="dash-page-title">
        <h1 class="dash-title">
            <?= htmlspecialchars($classInfo['subject_name']) ?>
            <span class="section-title-grade">
                <?= htmlspecialchars($classInfo['section_name']) ?> · Grade <?= (int) $classInfo['grade_level'] ?><?= !empty($classInfo['strand']) ? ' · ' . htmlspecialchars($classInfo['strand']) : '' ?>
            </span>
        </h1>
        <?php if ($schoolYearLabel): ?>
            <p class="dash-subtitle">School Year <?= htmlspecialchars($schoolYearLabel) ?></p>
        <?php endif; ?>
    </div>

    <?php if ($flash): ?>
        <div class="class-flash class-flash-<?= htmlspecialchars($flash['type']) ?>">
            <?= htmlspecialchars($flash['message']) ?>
        </div>
    <?php endif; ?>

    <!-- ===================== Class nav: Overview / Students / Attendance / Grading ===================== -->
    <nav class="class-nav">
        <a class="class-nav-item <?= $activeView === 'overview' ? 'active' : '' ?>"
           href="<?= classOverviewUrl($classInfo['subject_id'], $classInfo['section_id'], $activeTerm, 'overview') ?>">
            <i class="fas fa-chalkboard"></i> Overview
        </a>
        <a class="class-nav-item <?= $activeView === 'students' ? 'active' : '' ?>"
           href="<?= classOverviewUrl($classInfo['subject_id'], $classInfo['section_id'], $activeTerm, 'students') ?>">
            <i class="fas fa-users"></i> Students
        </a>
        <a class="class-nav-item <?= $activeView === 'attendance' ? 'active' : '' ?>"
           href="<?= classOverviewUrl($classInfo['subject_id'], $classInfo['section_id'], $activeTerm, 'attendance') ?>">
            <i class="fas fa-calendar-check"></i> Attendance
        </a>
        <span class="class-nav-item class-nav-disabled" title="Coming soon">
            <i class="fas fa-marker"></i> Grading <span class="soon-chip">Soon</span>
        </span>
    </nav>

    <?php if (!$activeOfferingId && $activeView !== 'overview'): ?>
        <!-- No term selected/available and the view needs an offering to load data -->
        <section class="panel">
            <div class="panel-empty">
                <i class="fas fa-calendar-xmark"></i>
                <p>No term is set up for this subject yet.</p>
                <span>Once a term has an active offering, its data will show up here.</span>
            </div>
        </section>

    <?php elseif ($activeView === 'overview'): ?>

        <!-- ===================== Term 1 / Term 2 / Term 3 tabs ===================== -->
        <div class="term-tabs">
            <?php foreach ($terms as $key => $t): ?>
                <?php if ($t['offering']): ?>
                    <a class="term-tab <?= $activeTerm === $key ? 'active' : '' ?>"
                       href="<?= classOverviewUrl($classInfo['subject_id'], $classInfo['section_id'], $key, 'overview') ?>">
                        <?= htmlspecialchars($t['label']) ?>
                    </a>
                <?php else: ?>
                    <span class="term-tab term-tab-disabled" title="Not set up yet">
                        <?= htmlspecialchars($t['label']) ?>
                        <small>Not set up yet</small>
                    </span>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <?php if (!$activeOfferingId): ?>
            <section class="panel">
                <div class="panel-empty">
                    <i class="fas fa-calendar-xmark"></i>
                    <p>No term is set up for this subject yet.</p>
                    <span>Once a term has an active offering, you'll be able to upload materials here.</span>
                </div>
            </section>
        <?php else: ?>

            <div class="term-summary">
                <span><i class="fas fa-clock"></i> <?= htmlspecialchars($activeOffering['schedule_days'] ?? 'TBA') ?>
                    <?php if ($activeOffering['start_time']): ?>
                        · <?= date('g:i A', strtotime($activeOffering['start_time'])) ?>–<?= date('g:i A', strtotime($activeOffering['end_time'])) ?>
                    <?php endif; ?>
                </span>
                <span><i class="fas fa-users"></i> <?= (int) $activeOffering['enrolled_count'] ?>/<?= (int) $activeOffering['capacity'] ?> enrolled</span>
            </div>

            <!-- ===================== Learning materials ===================== -->
            <section class="panel">
                <div class="panel-header">
                    <h2><i class="fas fa-book-open"></i> Learning Materials</h2>
                    <button type="button" class="btn-primary" id="btnShowUpload">
                        <i class="fas fa-plus"></i> Add Material
                    </button>
                </div>

                <!-- Upload / add-link form (hidden until "Add Material" is clicked) -->
                <form class="material-upload-form" id="materialUploadForm"
                      action="assets/api/material_upload.php" method="POST" enctype="multipart/form-data" hidden>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="offering_id" value="<?= (int) $activeOfferingId ?>">
                    <input type="hidden" name="subject_id" value="<?= (int) $classInfo['subject_id'] ?>">
                    <input type="hidden" name="section_id" value="<?= (int) $classInfo['section_id'] ?>">
                    <input type="hidden" name="term" value="<?= htmlspecialchars($activeTerm) ?>">

                    <div class="form-row">
                        <label for="materialTitle">Title</label>
                        <input type="text" id="materialTitle" name="title" maxlength="255" required
                               placeholder="e.g. Module 1: Introduction to Cells">
                    </div>

                    <div class="form-row material-source-toggle">
                        <label><input type="radio" name="source" value="file" checked> Upload a file</label>
                        <label><input type="radio" name="source" value="link"> Add a link</label>
                    </div>

                    <div class="form-row material-source-file">
                        <label for="materialType">File type</label>
                        <select id="materialType" name="material_type">
                            <option value="pdf">PDF</option>
                            <option value="slides">Slides</option>
                            <option value="video">Video</option>
                            <option value="other">Other</option>
                        </select>
                        <label for="materialFile">File</label>
                        <input type="file" id="materialFile" name="material_file"
                               accept=".pdf,.ppt,.pptx,.doc,.docx,.xls,.xlsx,.mp4,.mov,.jpg,.jpeg,.png,.zip">
                        <span class="field-hint">Max 25MB.</span>
                    </div>

                    <div class="form-row material-source-link" hidden>
                        <label for="materialUrl">URL</label>
                        <input type="url" id="materialUrl" name="external_url"
                               placeholder="https://drive.google.com/... or https://youtube.com/...">
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn-secondary" id="btnCancelUpload">Cancel</button>
                        <button type="submit" class="btn-primary"><i class="fas fa-upload"></i> Save Material</button>
                    </div>
                </form>

                <?php if (empty($materials)): ?>
                    <div class="panel-empty">
                        <i class="fas fa-folder-open"></i>
                        <p>No materials uploaded for <?= htmlspecialchars($terms[$activeTerm]['label']) ?> yet.</p>
                        <span>Click "Add Material" to upload a file or share a link with your students.</span>
                    </div>
                <?php else: ?>
                    <ul class="material-list">
                        <?php foreach ($materials as $m): ?>
                            <li class="material-item">
                                <div class="material-icon"><i class="fas <?= materialIcon($m['type']) ?>"></i></div>
                                <div class="material-info">
                                    <a class="material-title"
                                       href="<?= htmlspecialchars($m['external_url'] ?: $m['file_path']) ?>"
                                       target="_blank" rel="noopener">
                                        <?= htmlspecialchars($m['title']) ?>
                                    </a>
                                    <div class="material-meta">
                                        <?= htmlspecialchars(ucfirst($m['type'])) ?>
                                        <?php if ($m['file_size']): ?> · <?= formatFileSize((int) $m['file_size']) ?><?php endif; ?>
                                        · Uploaded <?= date('M j, Y', strtotime($m['created_at'])) ?>
                                        by <?= htmlspecialchars($m['firstname'] . ' ' . $m['lastname']) ?>
                                    </div>
                                </div>
                                <form action="assets/api/material_delete.php" method="POST" class="material-delete-form"
                                      onsubmit="return confirm('Remove this material? This cannot be undone.');">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                    <input type="hidden" name="material_id" value="<?= (int) $m['material_id'] ?>">
                                    <input type="hidden" name="offering_id" value="<?= (int) $activeOfferingId ?>">
                                    <input type="hidden" name="subject_id" value="<?= (int) $classInfo['subject_id'] ?>">
                                    <input type="hidden" name="section_id" value="<?= (int) $classInfo['section_id'] ?>">
                                    <input type="hidden" name="term" value="<?= htmlspecialchars($activeTerm) ?>">
                                    <button type="submit" class="btn-icon-danger" title="Remove">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

            <!-- Assignments will go here later -->

        <?php endif; ?>

    <?php elseif ($activeView === 'students'): ?>

        <section class="panel">
            <div class="panel-header">
                <h2><i class="fas fa-users"></i> Students · <?= htmlspecialchars($terms[$activeTerm]['label']) ?></h2>
                <span class="subject-enrolled"><?= count($students) ?>/<?= (int) $activeOffering['capacity'] ?> enrolled</span>
            </div>

            <?php if (empty($students)): ?>
                <div class="panel-empty">
                    <i class="fas fa-user-slash"></i>
                    <p>No students enrolled yet.</p>
                </div>
            <?php else: ?>
                <div class="table-scroll">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>LRN</th>
                                <th>Name</th>
                                <th>Email</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $s): ?>
                                <tr>
                                    <td><?= htmlspecialchars($s['student_lrn']) ?></td>
                                    <td><?= htmlspecialchars($s['lastname'] . ', ' . $s['firstname'] . ' ' . ($s['middlename'] ?? '')) ?></td>
                                    <td><?= htmlspecialchars($s['email'] ?? '—') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

    <?php elseif ($activeView === 'attendance'): ?>

        <?php
            // Defensive fallback: these are normally set by class_overview_functions.php.
            // If they're missing, the version of that file on the server is out of date.
            $attendanceDate      = $attendanceDate ?? date('Y-m-d');
            $attendanceByStudent = $attendanceByStudent ?? [];
            $attendanceSummary   = $attendanceSummary ?? ['Present' => 0, 'Absent' => 0, 'Late' => 0, 'Excused' => 0, 'Unmarked' => 0];

            $prevDate = date('Y-m-d', strtotime($attendanceDate . ' -1 day'));
            $nextDate = date('Y-m-d', strtotime($attendanceDate . ' +1 day'));
            $isToday  = $attendanceDate >= date('Y-m-d');
        ?>
        <section class="panel attendance-panel">
            <div class="panel-header">
                <h2><i class="fas fa-calendar-check"></i> Attendance · <?= htmlspecialchars($terms[$activeTerm]['label']) ?></h2>
            </div>

            <!-- Date navigation -->
            <div class="attendance-date-bar">
                <a class="date-nav-btn"
                   href="<?= attendanceUrl($classInfo['subject_id'], $classInfo['section_id'], $activeTerm, $prevDate) ?>"
                   aria-label="Previous day">
                    <i class="fas fa-chevron-left"></i>
                </a>

                <form method="GET" action="class_overview.php" class="date-picker-form">
                    <input type="hidden" name="subject_id" value="<?= (int) $classInfo['subject_id'] ?>">
                    <input type="hidden" name="section_id" value="<?= (int) $classInfo['section_id'] ?>">
                    <input type="hidden" name="view" value="attendance">
                    <?php if ($activeTerm): ?><input type="hidden" name="term" value="<?= htmlspecialchars($activeTerm) ?>"><?php endif; ?>
                    <input type="date" name="date" value="<?= htmlspecialchars($attendanceDate) ?>"
                           max="<?= date('Y-m-d') ?>" onchange="this.form.submit()">
                </form>

                <?php if (!$isToday): ?>
                    <a class="date-nav-btn"
                       href="<?= attendanceUrl($classInfo['subject_id'], $classInfo['section_id'], $activeTerm, $nextDate) ?>"
                       aria-label="Next day">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php else: ?>
                    <span class="date-nav-btn disabled" aria-hidden="true"><i class="fas fa-chevron-right"></i></span>
                <?php endif; ?>

                <a class="btn-secondary btn-today"
                   href="<?= attendanceUrl($classInfo['subject_id'], $classInfo['section_id'], $activeTerm, date('Y-m-d')) ?>">Today</a>
            </div>

            <!-- Day summary -->
            <div class="attendance-summary">
                <span class="chip chip-present"><?= $attendanceSummary['Present'] ?> Present</span>
                <span class="chip chip-absent"><?= $attendanceSummary['Absent'] ?> Absent</span>
                <span class="chip chip-late"><?= $attendanceSummary['Late'] ?> Late</span>
                <span class="chip chip-excused"><?= $attendanceSummary['Excused'] ?> Excused</span>
                <?php if ($attendanceSummary['Unmarked'] > 0): ?>
                    <span class="chip chip-unmarked"><?= $attendanceSummary['Unmarked'] ?> Unmarked</span>
                <?php endif; ?>
            </div>

            <?php if (empty($students)): ?>
                <div class="panel-empty">
                    <i class="fas fa-user-slash"></i>
                    <p>No students enrolled yet.</p>
                </div>
            <?php else: ?>
                <form action="assets/api/attendance_save.php" method="POST" class="attendance-form" id="attendanceForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="offering_id" value="<?= (int) $activeOfferingId ?>">
                    <input type="hidden" name="subject_id" value="<?= (int) $classInfo['subject_id'] ?>">
                    <input type="hidden" name="section_id" value="<?= (int) $classInfo['section_id'] ?>">
                    <input type="hidden" name="term" value="<?= htmlspecialchars($activeTerm) ?>">
                    <input type="hidden" name="attendance_date" value="<?= htmlspecialchars($attendanceDate) ?>">

                    <div class="attendance-bulk-actions">
                        <span>Mark all:</span>
                        <button type="button" class="chip-btn chip-present" data-mark-all="Present">Present</button>
                        <button type="button" class="chip-btn chip-absent" data-mark-all="Absent">Absent</button>
                    </div>

                    <div class="attendance-list">
                        <?php foreach ($students as $s): ?>
                            <?php
                                $existingStatus  = $attendanceByStudent[$s['student_id']]['status'] ?? 'Present';
                                $existingRemarks = $attendanceByStudent[$s['student_id']]['remarks'] ?? '';
                            ?>
                            <div class="attendance-row">
                                <div class="attendance-student">
                                    <span class="attendance-student-name"><?= htmlspecialchars($s['lastname'] . ', ' . $s['firstname']) ?></span>
                                    <span class="attendance-student-lrn"><?= htmlspecialchars($s['student_lrn']) ?></span>
                                </div>
                                <div class="attendance-status-group">
                                    <?php foreach (['Present', 'Absent', 'Late', 'Excused'] as $statusOpt): ?>
                                        <label class="status-pill status-<?= strtolower($statusOpt) ?> <?= $existingStatus === $statusOpt ? 'checked' : '' ?>">
                                            <input type="radio" name="status[<?= (int) $s['student_id'] ?>]"
                                                   value="<?= $statusOpt ?>" <?= $existingStatus === $statusOpt ? 'checked' : '' ?>>
                                            <?= htmlspecialchars($statusOpt) ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <input type="text" class="attendance-remarks" name="remarks[<?= (int) $s['student_id'] ?>]"
                                       placeholder="Remarks (optional)" value="<?= htmlspecialchars($existingRemarks) ?>" maxlength="255">
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="form-actions attendance-save-bar">
                        <button type="submit" class="btn-primary"><i class="fas fa-check"></i> Save Attendance</button>
                    </div>
                </form>
            <?php endif; ?>
        </section>

    <?php endif; ?>

</main>

<script>
// Toggle the "Add Material" form open/closed
const btnShowUpload = document.getElementById('btnShowUpload');
const btnCancelUpload = document.getElementById('btnCancelUpload');
const uploadForm = document.getElementById('materialUploadForm');

if (btnShowUpload && uploadForm) {
    btnShowUpload.addEventListener('click', () => {
        uploadForm.hidden = !uploadForm.hidden;
    });
}
if (btnCancelUpload && uploadForm) {
    btnCancelUpload.addEventListener('click', () => {
        uploadForm.hidden = true;
        uploadForm.reset();
    });
}

// Toggle file-vs-link fields in the upload form
document.querySelectorAll('input[name="source"]').forEach((radio) => {
    radio.addEventListener('change', () => {
        const isFile = document.querySelector('input[name="source"]:checked').value === 'file';
        document.querySelector('.material-source-file').hidden = !isFile;
        document.querySelector('.material-source-link').hidden = isFile;
    });
});

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
</script>

</body>
</html>