<?php
include 'assets/api/course_view_functions.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($classInfo['subject_name']) ?> · My Courses · SUA IntelliLearn</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/courses.css">
    <link rel="stylesheet" href="assets/css/course_view.css">
</head>

<body>

    <?php include '../../includes/student_sidebar.php'; ?>

    <main class="main-content" id="dashMain">

        <?php include '../../includes/student_header.php'; ?>

        <a href="courses.php" class="breadcrumb-back">
            <i class="fas fa-arrow-left"></i> Back to My Courses
        </a>

        <?php if ($flash): ?>
            <div class="class-flash class-flash-<?= htmlspecialchars($flash['type']) ?>">
                <?= htmlspecialchars($flash['message']) ?>
            </div>
        <?php endif; ?>

        <div class="dash-page-title">
            <h1 class="dash-title">
                <?= htmlspecialchars($classInfo['subject_name']) ?>
                <span class="section-title-grade">
                    <?= htmlspecialchars($classInfo['section_name']) ?> · Grade
                    <?= (int) $classInfo['grade_level'] ?><?= !empty($classInfo['strand']) ? ' · ' . htmlspecialchars($classInfo['strand']) : '' ?>
                </span>
            </h1>
            <?php if ($schoolYearLabel): ?>
                <p class="dash-subtitle">School Year <?= htmlspecialchars($schoolYearLabel) ?></p>
            <?php endif; ?>
        </div>

        <!-- ===================== Course nav ===================== -->
        <nav class="class-nav">
            <a class="class-nav-item <?= $activeView === 'overview' ? 'active' : '' ?>"
                href="<?= courseViewUrl($classInfo['subject_id'], $activeTerm, 'overview') ?>">
                <i class="fas fa-chalkboard"></i> Overview
            </a>
            <a class="class-nav-item <?= $activeView === 'materials' ? 'active' : '' ?>"
                href="<?= courseViewUrl($classInfo['subject_id'], $activeTerm, 'materials') ?>">
                <i class="fas fa-book-open"></i> Materials
            </a>
            <a class="class-nav-item <?= $activeView === 'assignments' ? 'active' : '' ?>"
                href="<?= assignmentsUrlStudent($classInfo['subject_id'], $activeTerm) ?>">
                <i class="fas fa-marker"></i> Assignments
            </a>
            <a class="class-nav-item <?= $activeView === 'quizzes' ? 'active' : '' ?>"
                href="<?= quizzesUrlStudent($classInfo['subject_id'], $activeTerm) ?>">
                <i class="fas fa-file-circle-question"></i> Quizzes
            </a>
        </nav>

        <!-- Term Tabs -->
        <div class="term-tabs">
            <?php foreach ($terms as $key => $t): ?>
                <?php if ($t['offering']): ?>
                    <a class="term-tab <?= $activeTerm === $key ? 'active' : '' ?>"
                        href="<?= courseViewUrl($classInfo['subject_id'], $key, $activeView) ?>">
                        <?= htmlspecialchars($t['label']) ?>
                    </a>
                <?php else: ?>
                    <span class="term-tab term-tab-disabled" title="Not enrolled">
                        <?= htmlspecialchars($t['label']) ?>
                        <small>Not enrolled</small>
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
                    <h3>Not enrolled this term</h3>
                    <p>You don't have an active enrollment for this subject in the selected term.</p>
                </div>
            </section>

        <?php elseif ($activeView === 'overview'): ?>

            <!-- Stats Bar -->
            <section class="stats-bar">
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
                        <i class="fas fa-file-circle-question"></i>
                    </div>
                    <div class="stat-card__info">
                        <span class="stat-card__value"><?= $quizzesCount ?></span>
                        <span class="stat-card__label">Quizzes</span>
                    </div>
                </div>
            </section>

            <div class="term-summary">
                <span><i class="fas fa-clock"></i> <?= htmlspecialchars($activeOffering['schedule_days'] ?? 'TBA') ?>
                    <?php if ($activeOffering['start_time']): ?>
                        ·
                        <?= date('g:i A', strtotime($activeOffering['start_time'])) ?>–<?= date('g:i A', strtotime($activeOffering['end_time'])) ?>
                    <?php endif; ?>
                </span>
            </div>

            <!-- Teacher card -->
            <section class="panel panel--card">
                <div class="panel-header">
                    <h2><i class="fas fa-chalkboard-user"></i> Teacher</h2>
                </div>
                <div class="course-teacher-row">
                    <div class="course-teacher-avatar">
                        <?= htmlspecialchars(strtoupper(substr($classInfo['teacher_first'], 0, 1) . substr($classInfo['teacher_last'], 0, 1))) ?>
                    </div>
                    <div class="course-teacher-info">
                        <div class="course-teacher-name">
                            <?= htmlspecialchars($classInfo['teacher_first'] . ' ' . $classInfo['teacher_last']) ?>
                        </div>
                        <?php if (!empty($classInfo['teacher_email'])): ?>
                            <div class="course-teacher-email"><?= htmlspecialchars($classInfo['teacher_email']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

        <?php elseif ($activeView === 'materials'): ?>

            <section class="panel panel--card">
                <div class="panel-header">
                    <h2><i class="fas fa-book-open"></i> Learning Materials ·
                        <?= htmlspecialchars($terms[$activeTerm]['label']) ?></h2>
                </div>

                <?php if (empty($materials)): ?>
                    <div class="panel-empty panel-empty--enhanced">
                        <div class="panel-empty__icon">
                            <i class="fas fa-folder-open"></i>
                        </div>
                        <h3>No materials yet</h3>
                        <p>Anything your teacher uploads or links for this class will show up here.</p>
                    </div>
                <?php else: ?>
                    <ul class="material-list">
                        <?php foreach ($materials as $m):
                            $matIcon = materialIcon($m['type']);
                            $matColorSeed = md5($m['type']);
                            $matHue = hexdec(substr($matColorSeed, 0, 2)) % 360;
                            $matAccent = "hsl({$matHue}, 70%, 45%)";
                            $matAccentLight = "hsl({$matHue}, 70%, 95%)";
                            $matUrl = resolveFileUrl($m['file_path'], $m['external_url']);
                            ?>
                            <li class="material-item"
                                style="--mat-accent: <?= $matAccent ?>; --mat-accent-light: <?= $matAccentLight ?>;">
                                <div class="material-icon"><i class="fas <?= $matIcon ?>"></i></div>
                                <div class="material-info">
                                    <a class="material-title" href="<?= htmlspecialchars($matUrl) ?>" target="_blank"
                                        rel="noopener">
                                        <?= htmlspecialchars($m['title']) ?>
                                    </a>
                                    <div class="material-meta">
                                        <?= htmlspecialchars(ucfirst($m['type'])) ?>
                                        <?php if ($m['file_size']): ?> · <?= formatFileSize((int) $m['file_size']) ?><?php endif; ?>
                                        · Posted <?= date('M j, Y', strtotime($m['created_at'])) ?>
                                        by <?= htmlspecialchars($m['firstname'] . ' ' . $m['lastname']) ?>
                                    </div>
                                </div>
                                <div class="material-actions">
                                    <a href="<?= htmlspecialchars($matUrl) ?>" target="_blank" rel="noopener" class="qa-btn"
                                        title="Open">
                                        <i class="fas fa-external-link-alt"></i>
                                    </a>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

        <?php elseif ($activeView === 'assignments'): ?>

            <?php if (!$selectedAssignment): ?>
                <!-- Assignments list -->
                <section class="panel panel--card">
                    <div class="panel-header">
                        <h2><i class="fas fa-marker"></i> Assignments · <?= htmlspecialchars($terms[$activeTerm]['label']) ?>
                        </h2>
                    </div>

                    <?php if (empty($assignments)): ?>
                        <div class="panel-empty panel-empty--enhanced">
                            <div class="panel-empty__icon">
                                <i class="fas fa-marker"></i>
                            </div>
                            <h3>No assignments posted yet</h3>
                            <p>Anything your teacher posts for this class will show up here.</p>
                        </div>
                    <?php else: ?>
                        <ul class="material-list">
                            <?php foreach ($assignments as $a):
                                $due = dueDateLabel($a['due_date']);
                                $statusInfo = submissionStatusInfo($a['submission_status'], $a['submitted_at'], $a['due_date']);
                                // Feature merged from doc2: lock the assignment once its due date has passed.
                                $isDue = !empty($a['due_date']) && strtotime($a['due_date']) < time();
                                ?>
                                <li class="material-item">
                                    <div class="material-icon material-icon--assignment"><i class="fas fa-marker"></i></div>
                                    <div class="material-info">
                                        <?php if ($isDue): ?>
                                            <div class="material-title"><?= htmlspecialchars($a['title']) ?></div>
                                        <?php else: ?>
                                            <a class="material-title"
                                                href="<?= assignmentsUrlStudent($classInfo['subject_id'], $activeTerm, (int) $a['assignment_id']) ?>">
                                                <?= htmlspecialchars($a['title']) ?>
                                            </a>
                                        <?php endif; ?>
                                        <div class="material-meta">
                                            <?= rtrim(rtrim((string) $a['points'], '0'), '.') ?> pts
                                            · <span class="<?= $isDue || $due['overdue'] ? 'due-overdue' : '' ?>">
                                                <?= $isDue ? 'Assignment is due' : 'Due ' . htmlspecialchars($due['label']) ?>
                                            </span>
                                            · <?= (int) $a['attempts_used'] ?>/<?= (int) $a['max_attempts'] ?> attempts used
                                            · <span class="chip chip-<?= $statusInfo['class'] ?>"><?= $statusInfo['label'] ?></span>
                                            <?php if ($a['score'] !== null): ?>
                                                · Score:
                                                <?= rtrim(rtrim((string) $a['score'], '0'), '.') ?>/<?= rtrim(rtrim((string) $a['points'], '0'), '.') ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php if ($isDue): ?>
                                        <span class="btn-secondary btn-view-submissions"
                                            style="opacity:.6;cursor:not-allowed;pointer-events:none;">
                                            <i class="fas fa-lock"></i> Assignment is due
                                        </span>
                                    <?php else: ?>
                                        <a class="btn-secondary btn-view-submissions"
                                            href="<?= assignmentsUrlStudent($classInfo['subject_id'], $activeTerm, (int) $a['assignment_id']) ?>">
                                            View
                                        </a>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>

            <?php else: ?>
                <!-- Single assignment detail -->
                <?php
                $due = dueDateLabel($selectedAssignment['due_date']);
                // Feature merged from doc2: lock the detail view once due date has passed.
                $isDue = !empty($selectedAssignment['due_date']) && strtotime($selectedAssignment['due_date']) < time();
                ?>

                <?php if ($isDue): ?>
                    <a href="<?= assignmentsUrlStudent($classInfo['subject_id'], $activeTerm) ?>" class="breadcrumb-back">
                        <i class="fas fa-arrow-left"></i> Back to Assignments
                    </a>
                    <section class="panel panel--card">
                        <div class="panel-empty panel-empty--enhanced">
                            <div class="panel-empty__icon">
                                <i class="fas fa-lock"></i>
                            </div>
                            <h3>Assignment is due</h3>
                            <p>This assignment is no longer available because the deadline has passed.</p>
                            <a href="<?= assignmentsUrlStudent($classInfo['subject_id'], $activeTerm) ?>" class="btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Assignments
                            </a>
                        </div>
                    </section>

                <?php else: ?>
                    <?php
                    $statusInfo = submissionStatusInfo($selectedAssignment['submission_status'], $selectedAssignment['submitted_at'], $selectedAssignment['due_date']);
                    $attachmentUrl = resolveFileUrl($selectedAssignment['instructions_file_path']);
                    $submissionUrl = resolveFileUrl($selectedAssignment['submission_file_path'], $selectedAssignment['submission_url']);
                    $maxAttempts  = (int) $selectedAssignment['max_attempts'];
                    $attemptsUsed = (int) $selectedAssignment['attempts_used'];
                    $attemptsLeft = max(0, $maxAttempts - $attemptsUsed);
                    ?>
                    <a href="<?= assignmentsUrlStudent($classInfo['subject_id'], $activeTerm) ?>" class="breadcrumb-back">
                        <i class="fas fa-arrow-left"></i> Back to Assignments
                    </a>

                    <section class="panel panel--card">
                        <div class="panel-header">
                            <h2><i class="fas fa-marker"></i> <?= htmlspecialchars($selectedAssignment['title']) ?></h2>
                            <span class="enrolled-badge">
                                <?= rtrim(rtrim((string) $selectedAssignment['points'], '0'), '.') ?> pts
                                · <span class="<?= $due['overdue'] ? 'due-overdue' : '' ?>">Due
                                    <?= htmlspecialchars($due['label']) ?></span>
                                · <?= $maxAttempts ?> attempt<?= $maxAttempts === 1 ? '' : 's' ?> allowed
                            </span>
                        </div>

                        <?php if (!empty($selectedAssignment['description'])): ?>
                            <p class="assignment-description"><?= nl2br(htmlspecialchars($selectedAssignment['description'])) ?></p>
                        <?php endif; ?>
                        <?php if ($attachmentUrl): ?>
                            <a class="material-title attachment-link" href="<?= htmlspecialchars($attachmentUrl) ?>" target="_blank"
                                rel="noopener">
                                <i class="fas fa-paperclip"></i> View attachment
                            </a>
                        <?php endif; ?>

                        <div class="course-teacher-row" style="margin-top: 18px;">
                            <div class="course-teacher-info" style="width:100%;">
                                <div class="course-teacher-name">
                                    Your submissions
                                    <span class="chip chip-<?= $statusInfo['class'] ?>"
                                        style="margin-left:8px;"><?= $statusInfo['label'] ?></span>
                                </div>

                                <?php if (!empty($assignmentAttempts)): ?>
                                    <div class="attempt-history" style="margin-top:10px; display:flex; flex-direction:column; gap:12px;">
                                        <?php foreach ($assignmentAttempts as $attempt):
                                            $attemptSubmissionUrl = resolveFileUrl($attempt['submission_file_path'], $attempt['submission_url']);
                                            ?>
                                            <div class="attempt-card" style="padding:12px; border:1px solid var(--border-color, #e5e7eb); border-radius:8px;">
                                                <div class="material-meta">
                                                    <strong>Attempt <?= (int) $attempt['attempt_number'] ?> of <?= $maxAttempts ?></strong>
                                                    · Submitted <?= date('M j, Y g:i A', strtotime($attempt['submitted_at'])) ?>
                                                    <?php if ($attempt['score'] !== null): ?>
                                                        · Score:
                                                        <?= rtrim(rtrim((string) $attempt['score'], '0'), '.') ?>/<?= rtrim(rtrim((string) $selectedAssignment['points'], '0'), '.') ?>
                                                    <?php endif; ?>
                                                </div>

                                                <?php if (!empty($attempt['files'])): ?>
                                                    <ul class="attempt-file-list" style="margin-top:6px;">
                                                        <?php foreach ($attempt['files'] as $f): ?>
                                                            <li>
                                                                <a href="<?= htmlspecialchars(resolveFileUrl($f['file_path'])) ?>" target="_blank" rel="noopener" class="link-view">
                                                                    <?= htmlspecialchars($f['original_name']) ?>
                                                                </a>
                                                                <?php if ($f['file_size']): ?>
                                                                    <span class="field-hint">(<?= formatFileSize((int) $f['file_size']) ?>)</span>
                                                                <?php endif; ?>
                                                            </li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                <?php elseif ($attemptSubmissionUrl): ?>
                                                    <a href="<?= htmlspecialchars($attemptSubmissionUrl) ?>" target="_blank" rel="noopener"
                                                        class="link-view">View your submission</a>
                                                <?php endif; ?>

                                                <?php if (!empty($attempt['submission_text'])): ?>
                                                    <p class="assignment-description" style="margin-top:6px;">
                                                        <?= nl2br(htmlspecialchars($attempt['submission_text'])) ?></p>
                                                <?php endif; ?>

                                                <?php if (!empty($attempt['feedback'])): ?>
                                                    <div class="material-meta" style="margin-top:6px;"><strong>Feedback:</strong>
                                                        <?= htmlspecialchars($attempt['feedback']) ?></div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="field-hint" style="margin-top:6px;">You haven't submitted anything for this assignment
                                        yet. <?= $maxAttempts ?> attempt<?= $maxAttempts === 1 ? '' : 's' ?> allowed.</p>
                                <?php endif; ?>

                                <?php if ($selectedAssignment['score'] === null && $attemptsLeft > 0): ?>
                                    <!-- Submit / next-attempt form -->
                                    <form class="material-upload-form" id="assignmentSubmitForm"
                                          action="assets/api/assignment_submit.php" method="POST"
                                          enctype="multipart/form-data" style="margin-top:14px; padding-top:14px; border-top:1px solid var(--border-color, #e5e7eb);">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                        <input type="hidden" name="assignment_id" value="<?= (int) $selectedAssignment['assignment_id'] ?>">
                                        <input type="hidden" name="subject_id" value="<?= (int) $classInfo['subject_id'] ?>">
                                        <input type="hidden" name="term" value="<?= htmlspecialchars($activeTerm) ?>">

                                        <div class="form-row">
                                            <label for="submissionFile">Attach your work (PDF, image, Office doc, or ZIP — multiple files allowed)</label>
                                            <input type="file" id="submissionFile" name="submission_file[]" multiple
                                                   accept=".pdf,.png,.jpg,.jpeg,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt,.zip">
                                            <span class="field-hint">Max 25MB per file, up to 10 files.</span>
                                        </div>

                                        <div class="form-row">
                                            <label for="submissionText">Note (optional)</label>
                                            <textarea id="submissionText" name="submission_text" rows="3"
                                                      placeholder="Add any notes for your teacher"></textarea>
                                        </div>

                                        <div class="form-actions">
                                            <span class="field-hint" style="margin-right:auto;align-self:center;">
                                                <?= $attemptsLeft ?> attempt<?= $attemptsLeft === 1 ? '' : 's' ?> left
                                            </span>
                                            <button type="submit" class="btn-primary">
                                                <i class="fas fa-upload"></i>
                                                <?= $selectedAssignment['submission_id'] ? 'Submit attempt ' . ($attemptsUsed + 1) : 'Submit' ?>
                                            </button>
                                        </div>
                                    </form>
                                <?php elseif ($selectedAssignment['score'] === null && $attemptsLeft === 0): ?>
                                    <p class="field-hint" style="margin-top:14px; padding-top:14px; border-top:1px solid var(--border-color, #e5e7eb);">
                                        You've used all <?= $maxAttempts ?> allowed attempt<?= $maxAttempts === 1 ? '' : 's' ?> for this assignment.
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </section>
                <?php endif; ?>
            <?php endif; ?>

        <?php elseif ($activeView === 'quizzes'): ?>

            <?php if (!$selectedQuiz): ?>
                <!-- Quizzes list -->
                <section class="panel panel--card">
                    <div class="panel-header">
                        <h2><i class="fas fa-file-circle-question"></i> Quizzes ·
                            <?= htmlspecialchars($terms[$activeTerm]['label']) ?></h2>
                    </div>

                    <?php if (empty($quizzes)): ?>
                        <div class="panel-empty panel-empty--enhanced">
                            <div class="panel-empty__icon">
                                <i class="fas fa-file-circle-question"></i>
                            </div>
                            <h3>No quizzes yet</h3>
                            <p>Quizzes your teacher publishes for this class will show up here.</p>
                        </div>
                    <?php else: ?>
                        <ul class="material-list">
                            <?php foreach ($quizzes as $q):
                                $qStatus = quizStatusInfo($q['quiz_status']);
                                $attemptStatus = quizAttemptStatusInfo($q['attempt_status']);
                                ?>
                                <li class="material-item">
                                    <div class="material-icon material-icon--quiz"><i class="fas fa-file-circle-question"></i></div>
                                    <div class="material-info">
                                        <a class="material-title"
                                            href="<?= quizzesUrlStudent($classInfo['subject_id'], $activeTerm, (int) $q['quiz_id']) ?>">
                                            <?= htmlspecialchars($q['title']) ?>
                                        </a>
                                        <div class="material-meta">
                                            <span class="chip chip-<?= $qStatus['class'] ?>"><?= $qStatus['label'] ?></span>
                                            · <?= (int) $q['question_count'] ?>
                                            question<?= (int) $q['question_count'] === 1 ? '' : 's' ?>
                                            · <?= rtrim(rtrim((string) $q['total_points'], '0'), '.') ?> pts
                                            · <span
                                                class="chip chip-<?= $attemptStatus['class'] ?>"><?= $attemptStatus['label'] ?></span>
                                            <?php if ($q['score'] !== null): ?>
                                                · Score:
                                                <?= rtrim(rtrim((string) $q['score'], '0'), '.') ?>                    <?php if ($q['max_score'] !== null): ?>/<?= rtrim(rtrim((string) $q['max_score'], '0'), '.') ?><?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <a class="btn-secondary btn-view-submissions"
                                        href="<?= quizzesUrlStudent($classInfo['subject_id'], $activeTerm, (int) $q['quiz_id']) ?>">
                                        View
                                    </a>

                                    <a href="take_quiz.php?quiz_id=<?= (int) $q['quiz_id'] ?>" class="btn-primary">
                                        Take Quiz
                                    </a>

                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>

            <?php else: ?>
                <!-- Single quiz detail -->
                <a href="<?= quizzesUrlStudent($classInfo['subject_id'], $activeTerm) ?>" class="breadcrumb-back">
                    <i class="fas fa-arrow-left"></i> Back to Quizzes
                </a>

                <?php $qStatus = quizStatusInfo($selectedQuiz['quiz_status']); ?>
                <section class="panel panel--card">
                    <div class="panel-header">
                        <h2><i class="fas fa-file-circle-question"></i> <?= htmlspecialchars($selectedQuiz['title']) ?></h2>
                        <span class="enrolled-badge">
                            <span class="chip chip-<?= $qStatus['class'] ?>"><?= $qStatus['label'] ?></span>
                            · <?= rtrim(rtrim((string) $selectedQuiz['total_points'], '0'), '.') ?> pts total
                            <?php if ($selectedQuiz['time_limit_minutes']): ?>
                                · <?= (int) $selectedQuiz['time_limit_minutes'] ?> min limit
                            <?php endif; ?>
                            · <?= (int) $selectedQuiz['max_attempts'] ?>
                            attempt<?= (int) $selectedQuiz['max_attempts'] === 1 ? '' : 's' ?> allowed
                        </span>
                    </div>

                    <?php if (!empty($selectedQuiz['description'])): ?>
                        <p class="assignment-description"><?= nl2br(htmlspecialchars($selectedQuiz['description'])) ?></p>
                    <?php endif; ?>

                    <div class="panel-header" style="margin-top: 8px;">
                        <h2 style="font-size:1rem;"><i class="fas fa-list-check"></i> Your attempts</h2>
                    </div>

                    <?php if (empty($quizAttempts)): ?>
                        <div class="panel-empty panel-empty--enhanced">
                            <div class="panel-empty__icon">
                                <i class="fas fa-circle-question"></i>
                            </div>
                            <h3>No attempts yet</h3>
                            <p>You haven't attempted this quiz.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-scroll">
                            <table class="data-table data-table--modern">
                                <thead>
                                    <tr>
                                        <th>Attempt</th>
                                        <th>Status</th>
                                        <th>Submitted</th>
                                        <th>Score</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($quizAttempts as $row): ?>
                                        <?php $attemptStatus = quizAttemptStatusInfo($row['attempt_status']); ?>
                                        <tr>
                                            <td>#<?= (int) $row['attempt_number'] ?></td>
                                            <td><span
                                                    class="chip chip-<?= $attemptStatus['class'] ?>"><?= $attemptStatus['label'] ?></span>
                                            </td>
                                            <td><?= $row['submitted_at'] ? date('M j, Y g:i A', strtotime($row['submitted_at'])) : '—' ?>
                                            </td>
                                            <td><?= $row['score'] !== null ? rtrim(rtrim((string) $row['score'], '0'), '.') . ($row['max_score'] !== null ? '/' . rtrim(rtrim((string) $row['max_score'], '0'), '.') : '') : '—' ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

        <?php endif; ?>

    </main>

</body>

</html>