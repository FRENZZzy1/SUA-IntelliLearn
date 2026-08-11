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
            <a class="class-nav-item <?= $activeView === 'attendance' ? 'active' : '' ?>"
                href="<?= courseViewUrl($classInfo['subject_id'], $activeTerm, 'attendance') ?>">
                <i class="fas fa-calendar-check"></i> Attendance
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
                                ?>
                                <li class="material-item">
                                    <div class="material-icon material-icon--assignment"><i class="fas fa-marker"></i></div>
                                    <div class="material-info">
                                        <a class="material-title"
                                            href="<?= assignmentsUrlStudent($classInfo['subject_id'], $activeTerm, (int) $a['assignment_id']) ?>">
                                            <?= htmlspecialchars($a['title']) ?>
                                        </a>
                                        <div class="material-meta">
                                            <?= rtrim(rtrim((string) $a['points'], '0'), '.') ?> pts
                                            · <span class="<?= $due['overdue'] ? 'due-overdue' : '' ?>">Due
                                                <?= htmlspecialchars($due['label']) ?></span>
                                            · <span class="chip chip-<?= $statusInfo['class'] ?>"><?= $statusInfo['label'] ?></span>
                                            <?php if ($a['score'] !== null): ?>
                                                · Score:
                                                <?= rtrim(rtrim((string) $a['score'], '0'), '.') ?>/<?= rtrim(rtrim((string) $a['points'], '0'), '.') ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <a class="btn-secondary btn-view-submissions"
                                        href="<?= assignmentsUrlStudent($classInfo['subject_id'], $activeTerm, (int) $a['assignment_id']) ?>">
                                        View
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>

            <?php else: ?>
                <!-- Single assignment detail -->
                <?php
                $due = dueDateLabel($selectedAssignment['due_date']);
                $statusInfo = submissionStatusInfo($selectedAssignment['submission_status'], $selectedAssignment['submitted_at'], $selectedAssignment['due_date']);
                $attachmentUrl = resolveFileUrl($selectedAssignment['instructions_file_path']);
                $submissionUrl = resolveFileUrl($selectedAssignment['submission_file_path'], $selectedAssignment['submission_url']);
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
                                Your submission
                                <span class="chip chip-<?= $statusInfo['class'] ?>"
                                    style="margin-left:8px;"><?= $statusInfo['label'] ?></span>
                            </div>

                            <?php if ($selectedAssignment['submission_id']): ?>
                                <div class="material-meta" style="margin-top:6px;">
                                    Submitted <?= date('M j, Y g:i A', strtotime($selectedAssignment['submitted_at'])) ?>
                                    <?php if ($selectedAssignment['score'] !== null): ?>
                                        · Score:
                                        <?= rtrim(rtrim((string) $selectedAssignment['score'], '0'), '.') ?>/<?= rtrim(rtrim((string) $selectedAssignment['points'], '0'), '.') ?>
                                    <?php endif; ?>
                                </div>
                                <?php if ($submissionUrl): ?>
                                    <a href="<?= htmlspecialchars($submissionUrl) ?>" target="_blank" rel="noopener"
                                        class="link-view">View your submission</a>
                                <?php elseif (!empty($selectedAssignment['submission_text'])): ?>
                                    <p class="assignment-description">
                                        <?= nl2br(htmlspecialchars($selectedAssignment['submission_text'])) ?></p>
                                <?php endif; ?>
                                <?php if (!empty($selectedAssignment['feedback'])): ?>
                                    <div class="material-meta" style="margin-top:6px;"><strong>Feedback:</strong>
                                        <?= htmlspecialchars($selectedAssignment['feedback']) ?></div>
                                <?php endif; ?>
                            <?php else: ?>
                                <p class="field-hint" style="margin-top:6px;">You haven't submitted anything for this assignment
                                    yet.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>
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

        <?php elseif ($activeView === 'attendance'): ?>

            <!-- Attendance intentionally left blank for now -->
            <section class="panel panel--card">
                <div class="panel-empty panel-empty--enhanced">
                    <div class="panel-empty__icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <h3>Attendance coming soon</h3>
                    <p>Your attendance record for this class isn't available here yet.</p>
                </div>
            </section>

        <?php endif; ?>

    </main>

</body>

</html>