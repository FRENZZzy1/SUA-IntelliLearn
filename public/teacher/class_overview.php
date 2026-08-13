<?php
include 'assets/api/class_overview_functions.php';

// Compute summary stats
$materialsCount    = count($materials ?? []);
$assignmentsCount  = count($assignments ?? []);
$enrolledCount     = (int) ($activeOffering['enrolled_count'] ?? 0);
$capacity          = (int) ($activeOffering['capacity'] ?? 0);

// Mock class average (replace with real DB query if available)
$classAverage = 0;
if (!empty($submissionRows)) {
    $scores = array_filter(array_column($submissionRows, 'score'), fn($s) => $s !== null);
    $classAverage = !empty($scores) ? round(array_sum($scores) / count($scores)) : 0;
}
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

    <!-- ===================== Class nav ===================== -->
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
        <a class="class-nav-item <?= $activeView === 'assignments' ? 'active' : '' ?>"
           href="<?= assignmentsUrl($classInfo['subject_id'], $classInfo['section_id'], $activeTerm) ?>">
            <i class="fas fa-marker"></i> Assignments
        </a>
        <a class="class-nav-item <?= $activeView === 'quizzes' ? 'active' : '' ?>"
           href="<?= quizzesUrl($classInfo['subject_id'], $classInfo['section_id'], $activeTerm) ?>">
            <i class="fas fa-file-circle-question"></i> Quizzes
        </a>
    </nav>

    <?php if (!$activeOfferingId && $activeView !== 'overview'): ?>
        <section class="panel">
            <div class="panel-empty panel-empty--enhanced">
                <div class="panel-empty__icon">
                    <i class="fas fa-calendar-xmark"></i>
                </div>
                <h3>No term is set up for this subject yet</h3>
                <p>Once a term has an active offering, its data will show up here.</p>
            </div>
        </section>

    <?php elseif ($activeView === 'overview'): ?>

        <!-- Term Tabs -->
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
                <div class="panel-empty panel-empty--enhanced">
                    <div class="panel-empty__icon">
                        <i class="fas fa-calendar-xmark"></i>
                    </div>
                    <h3>No term is set up for this subject yet</h3>
                    <p>Once a term has an active offering, you'll be able to upload materials here.</p>
                </div>
            </section>
        <?php else: ?>

            <!-- Stats Bar -->
            <section class="stats-bar">
                <div class="stat-card">
                    <div class="stat-card__icon stat-card__icon--green">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-card__info">
                        <span class="stat-card__value"><?= $enrolledCount ?></span>
                        <span class="stat-card__label">Enrolled</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card__icon stat-card__icon--blue">
                        <i class="fas fa-book-open"></i>
                    </div>
                    <div class="stat-card__info">
                        <span class="stat-card__value"><?= $materialsCount ?></span>
                        <span class="stat-card__label">Materials</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card__icon stat-card__icon--amber">
                        <i class="fas fa-marker"></i>
                    </div>
                    <div class="stat-card__info">
                        <span class="stat-card__value"><?= $assignmentsCount ?></span>
                        <span class="stat-card__label">Assignments</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card__icon stat-card__icon--purple">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-card__info">
                        <span class="stat-card__value"><?= $classAverage ?>%</span>
                        <span class="stat-card__label">Class Avg.</span>
                    </div>
                </div>
            </section>

            <div class="term-summary">
                <span><i class="fas fa-clock"></i> <?= htmlspecialchars($activeOffering['schedule_days'] ?? 'TBA') ?>
                    <?php if ($activeOffering['start_time']): ?>
                        · <?= date('g:i A', strtotime($activeOffering['start_time'])) ?>–<?= date('g:i A', strtotime($activeOffering['end_time'])) ?>
                    <?php endif; ?>
                </span>
                <span><i class="fas fa-users"></i> <?= (int) $activeOffering['enrolled_count'] ?>/<?= (int) $activeOffering['capacity'] ?> enrolled</span>
            </div>

            <!-- Learning Materials -->
            <section class="panel panel--card">
                <div class="panel-header">
                    <h2><i class="fas fa-book-open"></i> Learning Materials</h2>
                    <button type="button" class="btn-primary" id="btnShowUpload">
                        <i class="fas fa-plus"></i> Add Material
                    </button>
                </div>

                <!-- Upload form -->
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
                    <div class="panel-empty panel-empty--enhanced">
                        <div class="panel-empty__icon">
                            <i class="fas fa-folder-open"></i>
                        </div>
                        <h3>No materials uploaded yet</h3>
                        <p>Click "Add Material" to upload a file or share a link with your students.</p>
                    </div>
                <?php else: ?>
                    <ul class="material-list">
                        <?php foreach ($materials as $m): 
                            $matIcon = materialIcon($m['type']);
                            $matColorSeed = md5($m['type']);
                            $matHue = hexdec(substr($matColorSeed, 0, 2)) % 360;
                            $matAccent = "hsl({$matHue}, 70%, 45%)";
                            $matAccentLight = "hsl({$matHue}, 70%, 95%)";
                        ?>
                            <li class="material-item" style="--mat-accent: <?= $matAccent ?>; --mat-accent-light: <?= $matAccentLight ?>;">
                                <div class="material-icon"><i class="fas <?= $matIcon ?>"></i></div>
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
                                <div class="material-actions">
                                    <a href="<?= htmlspecialchars($m['external_url'] ?: $m['file_path']) ?>" target="_blank" rel="noopener" class="qa-btn" title="Open">
                                        <i class="fas fa-external-link-alt"></i>
                                    </a>
                                    <form action="assets/api/material_delete.php" method="POST" class="material-delete-form"
                                          onsubmit="return confirm('Remove this material? This cannot be undone.');">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                        <input type="hidden" name="material_id" value="<?= (int) $m['material_id'] ?>">
                                        <input type="hidden" name="offering_id" value="<?= (int) $activeOfferingId ?>">
                                        <input type="hidden" name="subject_id" value="<?= (int) $classInfo['subject_id'] ?>">
                                        <input type="hidden" name="section_id" value="<?= (int) $classInfo['section_id'] ?>">
                                        <input type="hidden" name="term" value="<?= htmlspecialchars($activeTerm) ?>">
                                        <button type="submit" class="qa-btn qa-btn--danger" title="Remove">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

        <?php endif; ?>

    <?php elseif ($activeView === 'students'): ?>

        <section class="panel panel--card">
            <div class="panel-header">
                <h2><i class="fas fa-users"></i> Students · <?= htmlspecialchars($terms[$activeTerm]['label']) ?></h2>
                <span class="enrolled-badge"><?= count($students) ?>/<?= (int) $activeOffering['capacity'] ?> enrolled</span>
            </div>

            <?php if (empty($students)): ?>
                <div class="panel-empty panel-empty--enhanced">
                    <div class="panel-empty__icon">
                        <i class="fas fa-user-slash"></i>
                    </div>
                    <h3>No students enrolled yet</h3>
                    <p>Students will appear here once they are enrolled in this class.</p>
                </div>
            <?php else: ?>
                <div class="table-scroll">
                    <table class="data-table data-table--modern">
                        <thead>
                            <tr>
                                <th>LRN</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $s): ?>
                                <tr>
                                    <td><span class="lrn-badge"><?= htmlspecialchars($s['student_lrn']) ?></span></td>
                                    <td class="student-name"><?= htmlspecialchars($s['lastname'] . ', ' . $s['firstname'] . ' ' . ($s['middlename'] ?? '')) ?></td>
                                    <td><?= htmlspecialchars($s['email'] ?? '—') ?></td>
                                    <td><span class="chip chip--active">Active</span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

    <?php elseif ($activeView === 'assignments'): ?>

        <?php if (!$selectedAssignment): ?>
            <!-- Assignments list -->
            <section class="panel panel--card">
                <div class="panel-header">
                    <h2><i class="fas fa-marker"></i> Assignments · <?= htmlspecialchars($terms[$activeTerm]['label']) ?></h2>
                    <button type="button" class="btn-primary" id="btnShowAssignmentForm">
                        <i class="fas fa-plus"></i> New Assignment
                    </button>
                </div>

                <!-- New assignment form -->
                <form class="material-upload-form" id="assignmentCreateForm"
                      action="assets/api/assignment_create.php" method="POST" enctype="multipart/form-data" hidden>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="offering_id" value="<?= (int) $activeOfferingId ?>">
                    <input type="hidden" name="subject_id" value="<?= (int) $classInfo['subject_id'] ?>">
                    <input type="hidden" name="section_id" value="<?= (int) $classInfo['section_id'] ?>">
                    <input type="hidden" name="term" value="<?= htmlspecialchars($activeTerm) ?>">

                    <div class="form-row">
                        <label for="assignmentTitle">Title</label>
                        <input type="text" id="assignmentTitle" name="title" maxlength="255" required
                               placeholder="e.g. Problem Set 3: Quadratic Equations">
                    </div>

                    <div class="form-row">
                        <label for="assignmentDescription">Instructions (optional)</label>
                        <textarea id="assignmentDescription" name="description" rows="3"
                                  placeholder="What should students do for this assignment?"></textarea>
                    </div>

                    <div class="form-row form-row-inline">
                        <div>
                            <label for="assignmentDueDate">Due date (optional)</label>
                            <input type="datetime-local" id="assignmentDueDate" name="due_date">
                        </div>
                        <div>
                            <label for="assignmentPoints">Points</label>
                            <input type="number" id="assignmentPoints" name="points" min="1" step="0.01" value="100">
                        </div>
                        <div>
                            <label for="assignmentMaxAttempts">Attempts allowed</label>
                            <input type="number" id="assignmentMaxAttempts" name="max_attempts" min="1" step="1" value="1">
                        </div>
                    </div>

                    <div class="form-row">
                        <label for="assignmentFile">Attachment (optional)</label>
                        <input type="file" id="assignmentFile" name="instructions_file"
                               accept=".pdf,.ppt,.pptx,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.zip">
                        <span class="field-hint">Max 25MB.</span>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn-secondary" id="btnCancelAssignmentForm">Cancel</button>
                        <button type="submit" class="btn-primary"><i class="fas fa-upload"></i> Post Assignment</button>
                    </div>
                </form>

                <?php if (empty($assignments)): ?>
                    <div class="panel-empty panel-empty--enhanced">
                        <div class="panel-empty__icon">
                            <i class="fas fa-marker"></i>
                        </div>
                        <h3>No assignments posted yet</h3>
                        <p>Click "New Assignment" to post work for your students.</p>
                    </div>
                <?php else: ?>
                    <ul class="material-list">
                        <?php foreach ($assignments as $a): ?>
                            <?php $due = dueDateLabel($a['due_date']); ?>
                            <li class="material-item">
                                <div class="material-icon material-icon--assignment"><i class="fas fa-marker"></i></div>
                                <div class="material-info">
                                    <a class="material-title"
                                       href="<?= assignmentsUrl($classInfo['subject_id'], $classInfo['section_id'], $activeTerm, (int) $a['assignment_id']) ?>">
                                        <?= htmlspecialchars($a['title']) ?>
                                    </a>
                                    <div class="material-meta">
                                        <?= (int) $a['points'] ?> pts
                                        · <span class="<?= $due['overdue'] ? 'due-overdue' : '' ?>">Due <?= htmlspecialchars($due['label']) ?></span>
                                        · <?= (int) $a['max_attempts'] ?> attempt<?= (int) $a['max_attempts'] === 1 ? '' : 's' ?> allowed
                                        · <?= (int) $a['graded_count'] ?>/<?= (int) $a['submitted_count'] ?> graded
                                    </div>
                                </div>
                                <a class="btn-secondary btn-view-submissions"
                                   href="<?= assignmentsUrl($classInfo['subject_id'], $classInfo['section_id'], $activeTerm, (int) $a['assignment_id']) ?>">
                                    View Submissions
                                </a>
                                <form action="assets/api/assignment_delete.php" method="POST" class="material-delete-form"
                                      onsubmit="return confirm('Remove this assignment and all of its submissions? This cannot be undone.');">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                    <input type="hidden" name="assignment_id" value="<?= (int) $a['assignment_id'] ?>">
                                    <input type="hidden" name="offering_id" value="<?= (int) $activeOfferingId ?>">
                                    <input type="hidden" name="subject_id" value="<?= (int) $classInfo['subject_id'] ?>">
                                    <input type="hidden" name="section_id" value="<?= (int) $classInfo['section_id'] ?>">
                                    <input type="hidden" name="term" value="<?= htmlspecialchars($activeTerm) ?>">
                                    <button type="submit" class="qa-btn qa-btn--danger" title="Remove">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

        <?php else: ?>
            <!-- Submissions / grading grid -->
            <?php $due = dueDateLabel($selectedAssignment['due_date']); ?>
            <a href="<?= assignmentsUrl($classInfo['subject_id'], $classInfo['section_id'], $activeTerm) ?>" class="breadcrumb-back">
                <i class="fas fa-arrow-left"></i> Back to Assignments
            </a>

            <section class="panel panel--card">
                <div class="panel-header">
                    <h2><i class="fas fa-marker"></i> <?= htmlspecialchars($selectedAssignment['title']) ?></h2>
                    <span class="enrolled-badge">
                        <?= (int) $selectedAssignment['points'] ?> pts
                        · <span class="<?= $due['overdue'] ? 'due-overdue' : '' ?>">Due <?= htmlspecialchars($due['label']) ?></span>
                        · <?= (int) $selectedAssignment['max_attempts'] ?> attempt<?= (int) $selectedAssignment['max_attempts'] === 1 ? '' : 's' ?> allowed
                    </span>
                </div>

                <?php if (!empty($selectedAssignment['description'])): ?>
                    <p class="assignment-description"><?= nl2br(htmlspecialchars($selectedAssignment['description'])) ?></p>
                <?php endif; ?>
                <?php if (!empty($selectedAssignment['instructions_file_path'])): ?>
                    <a class="material-title attachment-link" href="<?= htmlspecialchars($selectedAssignment['instructions_file_path']) ?>" target="_blank" rel="noopener">
                        <i class="fas fa-paperclip"></i> View attachment
                    </a>
                <?php endif; ?>

                <?php if (empty($submissionRows)): ?>
                    <div class="panel-empty panel-empty--enhanced">
                        <div class="panel-empty__icon">
                            <i class="fas fa-user-slash"></i>
                        </div>
                        <h3>No students enrolled yet</h3>
                        <p>Students will appear here once they are enrolled in this class.</p>
                    </div>
                <?php else: ?>
                    <form action="assets/api/submissions_grade_save.php" method="POST" class="grading-form" id="gradingForm">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="assignment_id" value="<?= (int) $selectedAssignment['assignment_id'] ?>">
                        <input type="hidden" name="offering_id" value="<?= (int) $activeOfferingId ?>">
                        <input type="hidden" name="subject_id" value="<?= (int) $classInfo['subject_id'] ?>">
                        <input type="hidden" name="section_id" value="<?= (int) $classInfo['section_id'] ?>">
                        <input type="hidden" name="term" value="<?= htmlspecialchars($activeTerm) ?>">

                        <div class="table-scroll">
                            <table class="data-table data-table--modern grading-table">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Status</th>
                                        <th>Attempts</th>
                                        <th>Submission</th>
                                        <th>Score (/ <?= (int) $selectedAssignment['points'] ?>)</th>
                                        <th>Feedback</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($submissionRows as $row): ?>
                                        <?php
                                            $statusInfo = submissionStatusInfo(
                                                $row['submission_status'],
                                                $row['submitted_at'],
                                                $selectedAssignment['due_date']
                                            );
                                            $studentAttempts = $attemptsByStudent[(int) $row['student_id']] ?? [];
                                        ?>
                                        <tr>
                                            <td class="student-name"><?= htmlspecialchars($row['lastname'] . ', ' . $row['firstname'] . ' ' . ($row['middlename'] ?? '')) ?></td>
                                            <td><span class="chip chip-<?= $statusInfo['class'] ?>"><?= $statusInfo['label'] ?></span></td>
                                            <td><?= (int) $row['attempts_used'] ?> / <?= (int) $selectedAssignment['max_attempts'] ?></td>
                                            <td>
                                                <?php if (empty($studentAttempts)): ?>
                                                    <span class="field-hint">—</span>
                                                <?php else: ?>
                                                    <ul class="attempt-file-list">
                                                        <?php foreach ($studentAttempts as $attempt): ?>
                                                            <li>
                                                                <span class="attempt-file-list__label">Attempt <?= (int) $attempt['attempt_number'] ?>:</span>
                                                                <?php if (!empty($attempt['files'])): ?>
                                                                    <span class="attempt-file-list__files">
                                                                        <?php foreach ($attempt['files'] as $i => $f): ?><?= $i > 0 ? ', ' : '' ?><a href="<?= htmlspecialchars($f['file_path']) ?>" target="_blank" rel="noopener" class="link-view"><?= htmlspecialchars($f['original_name']) ?></a><?php endforeach; ?>
                                                                    </span>
                                                                <?php elseif ($attempt['external_url'] ?: $attempt['file_path']): ?>
                                                                    <a href="<?= htmlspecialchars($attempt['external_url'] ?: $attempt['file_path']) ?>" target="_blank" rel="noopener" class="link-view">View</a>
                                                                <?php elseif (!empty($attempt['submission_text'])): ?>
                                                                    <span title="<?= htmlspecialchars($attempt['submission_text']) ?>" class="link-view link-view--text">Text answer</span>
                                                                <?php else: ?>
                                                                    <span class="field-hint">—</span>
                                                                <?php endif; ?>
                                                            </li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <input type="number" class="grade-input" name="score[<?= (int) $row['student_id'] ?>]"
                                                       min="0" max="<?= (float) $selectedAssignment['points'] ?>" step="0.01"
                                                       value="<?= $row['score'] !== null ? htmlspecialchars($row['score']) : '' ?>"
                                                       placeholder="—">
                                            </td>
                                            <td>
                                                <input type="text" class="feedback-input" name="feedback[<?= (int) $row['student_id'] ?>]"
                                                       maxlength="2000" placeholder="Optional feedback"
                                                       value="<?= htmlspecialchars($row['feedback'] ?? '') ?>">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="form-actions grading-save-bar">
                            <button type="submit" class="btn-primary"><i class="fas fa-check"></i> Save Grades</button>
                        </div>
                    </form>
                <?php endif; ?>
            </section>
        <?php endif; ?>

    <?php elseif ($activeView === 'quizzes'): ?>

        <?php if (!$selectedQuiz): ?>
            <!-- Quizzes list -->
            <section class="panel panel--card">
                <div class="panel-header">
                    <h2><i class="fas fa-file-circle-question"></i> Quizzes · <?= htmlspecialchars($terms[$activeTerm]['label']) ?></h2>
                    <a href="quiz_generator.php?subject_id=<?= (int) $classInfo['subject_id'] ?>&section_id=<?= (int) $classInfo['section_id'] ?>&term=<?= htmlspecialchars($activeTerm) ?>"
                       class="btn-primary">
                        <i class="fas fa-plus"></i> New Quiz
                    </a>
                </div>

                <?php if (empty($quizzes)): ?>
                    <div class="panel-empty panel-empty--enhanced">
                        <div class="panel-empty__icon">
                            <i class="fas fa-file-circle-question"></i>
                        </div>
                        <h3>No quizzes yet</h3>
                        <p>Quizzes you create show up here, along with student scores.</p>
                    </div>
                <?php else: ?>
                    <ul class="material-list">
                        <?php foreach ($quizzes as $q): ?>
                            <?php $qStatus = quizStatusInfo($q['status']); ?>
                            <li class="material-item">
                                <div class="material-icon material-icon--quiz"><i class="fas fa-file-circle-question"></i></div>
                                <div class="material-info">
                                    <a class="material-title"
                                       href="<?= quizzesUrl($classInfo['subject_id'], $classInfo['section_id'], $activeTerm, (int) $q['quiz_id']) ?>">
                                        <?= htmlspecialchars($q['title']) ?>
                                    </a>
                                    <div class="material-meta">
                                        <span class="chip chip-<?= $qStatus['class'] ?>"><?= $qStatus['label'] ?></span>
                                        · <?= (int) $q['question_count'] ?> question<?= (int) $q['question_count'] === 1 ? '' : 's' ?>
                                        · <?= rtrim(rtrim((string) $q['total_points'], '0'), '.') ?> pts
                                        · <?= (int) $q['attempts_submitted'] ?> attempt<?= (int) $q['attempts_submitted'] === 1 ? '' : 's' ?> submitted
                                        <?php if ($q['avg_score'] !== null): ?>
                                            · Avg <?= htmlspecialchars($q['avg_score']) ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <a class="btn-secondary btn-view-submissions"
                                   href="<?= quizzesUrl($classInfo['subject_id'], $classInfo['section_id'], $activeTerm, (int) $q['quiz_id']) ?>">
                                    View Scores
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

        <?php else: ?>
            <!-- Per-student scores / manual override grid -->
            <a href="<?= quizzesUrl($classInfo['subject_id'], $classInfo['section_id'], $activeTerm) ?>" class="breadcrumb-back">
                <i class="fas fa-arrow-left"></i> Back to Quizzes
            </a>

            <?php $qStatus = quizStatusInfo($selectedQuiz['status']); ?>
            <section class="panel panel--card">
                <div class="panel-header">
                    <h2><i class="fas fa-file-circle-question"></i> <?= htmlspecialchars($selectedQuiz['title']) ?></h2>
                    <span class="enrolled-badge">
                        <span class="chip chip-<?= $qStatus['class'] ?>"><?= $qStatus['label'] ?></span>
                        · <?= rtrim(rtrim((string) $selectedQuiz['total_points'], '0'), '.') ?> pts total
                        <?php if ($selectedQuiz['time_limit_minutes']): ?>
                            · <?= (int) $selectedQuiz['time_limit_minutes'] ?> min limit
                        <?php endif; ?>
                    </span>
                </div>

                <?php if (!empty($selectedQuiz['description'])): ?>
                    <p class="assignment-description"><?= nl2br(htmlspecialchars($selectedQuiz['description'])) ?></p>
                <?php endif; ?>

                <p class="field-hint" style="margin: 0 0 16px;">
                    Scores below come from auto-checked quiz attempts. If the system graded something
                    incorrectly, you can override a student's score directly here — changes are saved as final.
                </p>

                <?php if (empty($quizAttemptRows)): ?>
                    <div class="panel-empty panel-empty--enhanced">
                        <div class="panel-empty__icon">
                            <i class="fas fa-user-slash"></i>
                        </div>
                        <h3>No students enrolled yet</h3>
                        <p>Students will appear here once they are enrolled in this class.</p>
                    </div>
                <?php else: ?>
                    <form action="assets/api/quiz_score_save.php" method="POST" class="grading-form" id="quizScoreForm">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="quiz_id" value="<?= (int) $selectedQuiz['quiz_id'] ?>">
                        <input type="hidden" name="offering_id" value="<?= (int) $activeOfferingId ?>">
                        <input type="hidden" name="subject_id" value="<?= (int) $classInfo['subject_id'] ?>">
                        <input type="hidden" name="section_id" value="<?= (int) $classInfo['section_id'] ?>">
                        <input type="hidden" name="term" value="<?= htmlspecialchars($activeTerm) ?>">

                        <div class="table-scroll">
                            <table class="data-table data-table--modern grading-table">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Status</th>
                                        <th>Attempt</th>
                                        <th>Submitted</th>
                                        <th>Score</th>
                                        <th>Answers</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($quizAttemptRows as $row): ?>
                                        <?php $attemptStatus = quizAttemptStatusInfo($row['attempt_status']); ?>
                                        <tr>
                                            <td class="student-name"><?= htmlspecialchars($row['lastname'] . ', ' . $row['firstname'] . ' ' . ($row['middlename'] ?? '')) ?></td>
                                            <td><span class="chip chip-<?= $attemptStatus['class'] ?>"><?= $attemptStatus['label'] ?></span></td>
                                            <td><?= $row['attempt_number'] ? '#' . (int) $row['attempt_number'] : '—' ?></td>
                                            <td><?= $row['submitted_at'] ? date('M j, Y g:i A', strtotime($row['submitted_at'])) : '—' ?></td>
                                            <td>
                                                <?php if ($row['attempt_id']): ?>
                                                    <input type="number" class="grade-input" name="score[<?= (int) $row['attempt_id'] ?>]"
                                                           min="0" max="<?= $row['max_score'] !== null ? (float) $row['max_score'] : (float) $selectedQuiz['total_points'] ?>"
                                                           step="0.01"
                                                           value="<?= $row['score'] !== null ? htmlspecialchars($row['score']) : '' ?>"
                                                           placeholder="—">
                                                    <?php if ($row['max_score'] !== null): ?>
                                                        <span class="field-hint">/ <?= rtrim(rtrim((string) $row['max_score'], '0'), '.') ?></span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="field-hint">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($row['attempt_id'] && in_array($row['attempt_status'], ['submitted', 'graded'], true)): ?>
                                                    <a class="btn-secondary btn-view-answers"
                                                       href="<?= quizzesUrl($classInfo['subject_id'], $classInfo['section_id'], $activeTerm, (int) $selectedQuiz['quiz_id'], (int) $row['attempt_id']) ?>">
                                                        <i class="fas fa-eye"></i> View Answers
                                                    </a>
                                                <?php else: ?>
                                                    <span class="field-hint">—</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="form-actions grading-save-bar">
                            <button type="submit" class="btn-primary"><i class="fas fa-check"></i> Save Scores</button>
                        </div>
                    </form>
                <?php endif; ?>
            </section>

            <?php if ($selectedAttempt): ?>
                <!-- Answer review modal: question-by-question breakdown of one student's attempt,
                     editable so the teacher can correct auto-grading or grade short answers. -->
                <div class="modal-overlay" id="answerReviewModal">
                    <div class="modal-box modal-box--wide">
                        <div class="modal-header">
                            <div>
                                <h3>
                                    <i class="fas fa-list-check"></i>
                                    <?= htmlspecialchars($selectedAttempt['lastname'] . ', ' . $selectedAttempt['firstname'] . ' ' . ($selectedAttempt['middlename'] ?? '')) ?>
                                </h3>
                                <p class="modal-subtitle">
                                    <?= htmlspecialchars($selectedQuiz['title']) ?>
                                    · Attempt #<?= (int) $selectedAttempt['attempt_number'] ?>
                                    <?php if ($selectedAttempt['submitted_at']): ?>
                                        · Submitted <?= date('M j, Y g:i A', strtotime($selectedAttempt['submitted_at'])) ?>
                                    <?php endif; ?>
                                    <?php if ($selectedAttempt['score'] !== null): ?>
                                        · Score <span id="answerReviewScoreDisplay"><?= htmlspecialchars($selectedAttempt['score']) ?></span><?= $selectedAttempt['max_score'] !== null ? ' / ' . rtrim(rtrim((string) $selectedAttempt['max_score'], '0'), '.') : '' ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <a href="<?= quizzesUrl($classInfo['subject_id'], $classInfo['section_id'], $activeTerm, (int) $selectedQuiz['quiz_id']) ?>"
                               class="modal-close" aria-label="Close">
                                <i class="fas fa-xmark"></i>
                            </a>
                        </div>

                        <form action="assets/api/quiz_answer_save.php" method="POST" id="answerReviewForm">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            <input type="hidden" name="attempt_id" value="<?= (int) $selectedAttempt['attempt_id'] ?>">
                            <input type="hidden" name="quiz_id" value="<?= (int) $selectedQuiz['quiz_id'] ?>">
                            <input type="hidden" name="offering_id" value="<?= (int) $activeOfferingId ?>">
                            <input type="hidden" name="subject_id" value="<?= (int) $classInfo['subject_id'] ?>">
                            <input type="hidden" name="section_id" value="<?= (int) $classInfo['section_id'] ?>">
                            <input type="hidden" name="term" value="<?= htmlspecialchars($activeTerm) ?>">

                            <div class="modal-body">
                                <?php if (empty($attemptQuestions)): ?>
                                    <p class="field-hint">This quiz has no questions.</p>
                                <?php else: ?>
                                    <?php foreach ($attemptQuestions as $i => $q): ?>
                                        <?php
                                            $answerStatus = quizAnswerStatusInfo($q);
                                            $wasAnswered  = $q['answer_id'] !== null;
                                            $qPoints      = rtrim(rtrim((string) $q['points'], '0'), '.');
                                        ?>
                                        <div class="answer-review-item answer-review-item--<?= $answerStatus['class'] ?>">
                                            <div class="answer-review-item__head">
                                                <span class="answer-review-num">Q<?= $i + 1 ?></span>
                                                <p class="answer-review-text"><?= nl2br(htmlspecialchars($q['question_text'])) ?></p>
                                                <span class="chip chip-<?= $answerStatus['class'] ?>" data-answer-status-chip><?= $answerStatus['label'] ?></span>
                                            </div>

                                            <?php if (in_array($q['question_type'], ['mcq', 'true_false'], true)): ?>
                                                <ul class="answer-review-choices">
                                                    <?php foreach ($q['choices'] as $choice): ?>
                                                        <?php
                                                            $isSelected = $choice['choice_id'] == $q['selected_choice_id'];
                                                            $isCorrect  = (bool) $choice['is_correct'];
                                                            $choiceClasses = [];
                                                            if ($isCorrect) {
                                                                $choiceClasses[] = 'answer-review-choice--correct';
                                                            }
                                                            if ($isSelected && !$isCorrect) {
                                                                $choiceClasses[] = 'answer-review-choice--wrong';
                                                            }
                                                            if ($isSelected) {
                                                                $choiceClasses[] = 'answer-review-choice--selected';
                                                            }
                                                        ?>
                                                        <li class="answer-review-choice <?= implode(' ', $choiceClasses) ?>">
                                                            <?php if ($isSelected): ?>
                                                                <i class="fas fa-circle-dot"></i>
                                                            <?php else: ?>
                                                                <i class="fa-regular fa-circle"></i>
                                                            <?php endif; ?>
                                                            <span><?= htmlspecialchars($choice['choice_text']) ?></span>
                                                            <?php if ($isCorrect): ?>
                                                                <i class="fas fa-check answer-review-correct-mark" title="Correct answer"></i>
                                                            <?php endif; ?>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php else: ?>
                                                <div class="answer-review-shortanswer">
                                                    <?php if ($q['answer_text'] !== null && $q['answer_text'] !== ''): ?>
                                                        <?= nl2br(htmlspecialchars($q['answer_text'])) ?>
                                                    <?php else: ?>
                                                        <span class="field-hint">No answer submitted</span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>

                                            <?php if ($wasAnswered): ?>
                                                <div class="answer-grade-row" data-max-points="<?= $qPoints ?>">
                                                    <div class="answer-grade-toggle">
                                                        <label class="answer-grade-pill answer-grade-pill--correct <?= $q['is_correct'] === 1 || $q['is_correct'] === '1' ? 'checked' : '' ?>">
                                                            <input type="radio" name="is_correct[<?= (int) $q['question_id'] ?>]" value="1"
                                                                   <?= $q['is_correct'] === 1 || $q['is_correct'] === '1' ? 'checked' : '' ?>>
                                                            <i class="fas fa-check"></i> Correct
                                                        </label>
                                                        <label class="answer-grade-pill answer-grade-pill--incorrect <?= $q['is_correct'] === 0 || $q['is_correct'] === '0' ? 'checked' : '' ?>">
                                                            <input type="radio" name="is_correct[<?= (int) $q['question_id'] ?>]" value="0"
                                                                   <?= $q['is_correct'] === 0 || $q['is_correct'] === '0' ? 'checked' : '' ?>>
                                                            <i class="fas fa-xmark"></i> Incorrect
                                                        </label>
                                                    </div>
                                                    <div class="answer-grade-points">
                                                        <input type="number" class="grade-input" name="points[<?= (int) $q['question_id'] ?>]"
                                                               min="0" max="<?= $qPoints ?>" step="0.01"
                                                               value="<?= $q['points_awarded'] !== null ? htmlspecialchars($q['points_awarded']) : '' ?>"
                                                               placeholder="0">
                                                        <span class="field-hint">/ <?= $qPoints ?> pts</span>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <div class="answer-review-points">— / <?= $qPoints ?> pts (skipped)</div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($attemptQuestions) && array_filter($attemptQuestions, fn($q) => $q['answer_id'] !== null)): ?>
                                <div class="form-actions grading-save-bar modal-save-bar">
                                    <button type="submit" class="btn-primary"><i class="fas fa-check"></i> Save Corrections</button>
                                </div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

    <?php elseif ($activeView === 'attendance'): ?>

        <?php
            $attendanceDate      = $attendanceDate ?? date('Y-m-d');
            $attendanceByStudent = $attendanceByStudent ?? [];
            $attendanceSummary   = $attendanceSummary ?? ['Present' => 0, 'Absent' => 0, 'Late' => 0, 'Excused' => 0, 'Unmarked' => 0];

            $prevDate = date('Y-m-d', strtotime($attendanceDate . ' -1 day'));
            $nextDate = date('Y-m-d', strtotime($attendanceDate . ' +1 day'));
            $isToday  = $attendanceDate >= date('Y-m-d');
        ?>
        <section class="panel panel--card attendance-panel">
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
                <div class="panel-empty panel-empty--enhanced">
                    <div class="panel-empty__icon">
                        <i class="fas fa-user-slash"></i>
                    </div>
                    <h3>No students enrolled yet</h3>
                    <p>Students will appear here once they are enrolled in this class.</p>
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



<script src="assets/js/class_overview.js"></script>

</body>
</html>