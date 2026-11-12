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

        <?php if ($isArchivedView): ?>
        <a href="courses.php?view=archived&amp;sy=<?= (int) $archiveYearId ?>" class="breadcrumb-back">
            <i class="fas fa-arrow-left"></i> Back to Archived Courses
        </a>
        <?php else: ?>
        <a href="courses.php" class="breadcrumb-back">
            <i class="fas fa-arrow-left"></i> Back to My Courses
        </a>
        <?php endif; ?>

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
                    <?= (int) $classInfo['grade_level'] ?>
                </span>
            </h1>
            <?php if ($schoolYearLabel): ?>
                <p class="dash-subtitle">School Year <?= htmlspecialchars($schoolYearLabel) ?><?php if ($isArchivedView): ?> <span class="archive-chip"><i class="fas fa-box-archive"></i> Archived</span><?php endif; ?></p>
            <?php endif; ?>
        </div>

        <?php if ($isArchivedView): ?>
            <div class="archive-banner" role="note">
                <i class="fas fa-box-archive"></i>
                <div>
                    <strong>This class is archived.</strong>
                    You can still review its materials, assignments, quizzes and your results, but nothing can be submitted or taken anymore.
                </div>
            </div>
        <?php endif; ?>

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
                <span class="nav-badge" data-nav-badge="assignments" data-todo="<?= (int) $assignmentTodoCount ?>"
                    data-graded="<?= htmlspecialchars(implode('|', $assignmentGradedKeys)) ?>"
                    <?= $assignmentTodoCount > 0 ? '' : 'hidden' ?>><?= (int) $assignmentTodoCount ?></span>
            </a>
            <a class="class-nav-item <?= $activeView === 'quizzes' ? 'active' : '' ?>"
                href="<?= quizzesUrlStudent($classInfo['subject_id'], $activeTerm) ?>">
                <i class="fas fa-file-circle-question"></i> Quizzes
                <span class="nav-badge" data-nav-badge="quizzes" data-todo="<?= (int) $quizTodoCount ?>"
                    data-graded="<?= htmlspecialchars(implode('|', $quizGradedKeys)) ?>"
                    <?= $quizTodoCount > 0 ? '' : 'hidden' ?>><?= (int) $quizTodoCount ?></span>
            </a>
        </nav>
        <script>
            // Tab badges: to-do items (from the server) + teacher-graded items not seen yet.
            (function () {
                var KEY = 'sua_course_seen_<?= (int) $userId ?>';
                var activeView = <?= json_encode($activeView) ?>;
                var seen = [];
                try { seen = JSON.parse(localStorage.getItem(KEY) || '[]') || []; } catch (e) { seen = []; }
                var changed = false;
                document.querySelectorAll('.nav-badge[data-nav-badge]').forEach(function (badge) {
                    var ids = (badge.dataset.graded || '').split('|').filter(Boolean);
                    // Opening a tab counts as seeing its graded items.
                    if (badge.dataset.navBadge === activeView) {
                        ids.forEach(function (id) { if (seen.indexOf(id) === -1) { seen.push(id); changed = true; } });
                    }
                    var unseen = ids.filter(function (id) { return seen.indexOf(id) === -1; }).length;
                    var todo = parseInt(badge.dataset.todo, 10) || 0;
                    var total = todo + unseen;
                    var parts = [];
                    if (todo) parts.push(todo + ' to do');
                    if (unseen) parts.push(unseen + ' newly graded');
                    badge.textContent = total;
                    badge.title = parts.join(' · ');
                    badge.hidden = total === 0;
                });
                if (changed) {
                    try { localStorage.setItem(KEY, JSON.stringify(seen.slice(-400))); } catch (e) {}
                }
            })();
        </script>

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
                        <ul class="work-list">
                            <?php foreach ($assignments as $a):
                                $due = dueDateLabel($a['due_date']);
                                $statusInfo = submissionStatusInfo($a['submission_status'], $a['submitted_at'], $a['due_date']);
                                // Lock the assignment once its due date has passed.
                                $isDue = !empty($a['due_date']) && strtotime($a['due_date']) < time();
                                $aPts  = rtrim(rtrim((string) $a['points'], '0'), '.') ?: '0';
                                $aMax  = (int) $a['max_attempts'];
                                $aUrl  = assignmentsUrlStudent($classInfo['subject_id'], $activeTerm, (int) $a['assignment_id']);
                                $aOver = $isDue || $due['overdue'];
                                $aDone = $a['score'] !== null;
                                $aScorePct = null;
                                if ($aDone && (float) $a['points'] > 0) {
                                    $aScorePct = (int) round(((float) $a['score'] / (float) $a['points']) * 100);
                                }
                                $aTier = $aScorePct === null ? '' : ($aScorePct >= 80 ? 'good' : ($aScorePct >= 50 ? 'mid' : 'low'));
                                ?>
                                <li class="work-card work-card--assignment<?= $aOver && !$aDone ? ' is-overdue' : '' ?><?= $aDone ? ' is-done' : '' ?><?= $isDue ? ' is-locked' : '' ?>">
                                    <div class="work-card__icon"><i class="fas fa-marker"></i></div>

                                    <div class="work-card__main">
                                        <div class="work-card__top">
                                            <?php if ($isDue): ?>
                                                <span class="work-card__title work-card__title--static"><?= htmlspecialchars($a['title']) ?></span>
                                            <?php else: ?>
                                                <a class="work-card__title" href="<?= $aUrl ?>"><?= htmlspecialchars($a['title']) ?></a>
                                            <?php endif; ?>
                                            <span class="chip chip-<?= htmlspecialchars($statusInfo['class']) ?>"><?= htmlspecialchars($statusInfo['label']) ?></span>
                                        </div>

                                        <div class="work-meta">
                                            <span class="work-pill"><i class="fas fa-star"></i><b><?= $aPts ?></b> pt<?= $aPts === '1' ? '' : 's' ?></span>
                                            <span class="work-pill"><i class="fas fa-rotate-right"></i><b><?= (int) $a['attempts_used'] ?>/<?= $aMax ?></b> attempt<?= $aMax === 1 ? '' : 's' ?> used</span>
                                            <?php if ($aDone): ?>
                                                <span class="work-pill work-pill--score<?= $aTier ? ' work-pill--' . $aTier : '' ?>">
                                                    <i class="fas fa-trophy"></i>Score <b><?= rtrim(rtrim((string) $a['score'], '0'), '.') ?: '0' ?>/<?= $aPts ?></b><?= $aScorePct !== null ? ' · <b>' . $aScorePct . '%</b>' : '' ?>
                                                </span>
                                            <?php endif; ?>
                                            <span class="work-pill work-pill--due<?= $aOver ? ' work-pill--overdue' : '' ?>">
                                                <i class="fas fa-calendar-xmark"></i>Due <b><?= htmlspecialchars($due['label']) ?></b>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="work-card__actions">
                                        <?php if ($isDue): ?>
                                            <span class="btn-secondary work-btn work-btn--disabled">
                                                <i class="fas fa-lock"></i> Assignment is due
                                            </span>
                                        <?php else: ?>
                                            <a class="btn-secondary work-btn" href="<?= $aUrl ?>">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        <?php endif; ?>
                                    </div>
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
                            <?php $saPts = rtrim(rtrim((string) $selectedAssignment['points'], '0'), '.') ?: '0'; ?>
                            <div class="quiz-facts quiz-facts--detail">
                                <span class="quiz-fact"><i class="fas fa-star"></i> <b><?= $saPts ?></b> pt<?= $saPts === '1' ? '' : 's' ?></span>
                                <span class="quiz-fact"><i class="fas fa-rotate-right"></i> <b><?= $attemptsUsed ?>/<?= $maxAttempts ?></b> attempt<?= $maxAttempts === 1 ? '' : 's' ?> used</span>
                                <span class="quiz-schedule__item <?= $due['overdue'] ? 'quiz-schedule__item--overdue' : '' ?>">
                                    <i class="fas fa-calendar-xmark"></i>
                                    <span class="quiz-schedule__label">Due</span>
                                    <span class="quiz-schedule__value"><?= htmlspecialchars($due['label']) ?></span>
                                </span>
                            </div>
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

                                <?php if ($isArchivedView && $selectedAssignment['score'] === null): ?>
                                    <p class="field-hint" style="margin-top:14px; padding-top:14px; border-top:1px solid var(--border-color, #e5e7eb);">
                                        <i class="fas fa-lock"></i> Submissions are closed because this class is archived.
                                    </p>
                                <?php elseif ($selectedAssignment['score'] === null && $attemptsLeft > 0): ?>
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
                        <ul class="work-list">
                            <?php foreach ($quizzes as $q):
                                $qStatus = quizStatusInfo($q['quiz_status']);
                                $attemptStatus = quizAttemptStatusInfo($q['attempt_status']);
                                $qNow = time();
                                $qNotOpen = !empty($q['available_from']) && $qNow < strtotime($q['available_from']);
                                $qClosed  = !empty($q['available_until']) && $qNow > strtotime($q['available_until']);
                                $qNoAttempts = (int) $q['attempts_used'] >= (int) $q['max_attempts'];
                                $qScorePct = null;
                                if ($q['score'] !== null && $q['max_score'] !== null && (float) $q['max_score'] > 0) {
                                    $qScorePct = (int) round(((float) $q['score'] / (float) $q['max_score']) * 100);
                                }
                                $qScoreTier = $qScorePct === null ? '' : ($qScorePct >= 80 ? 'good' : ($qScorePct >= 50 ? 'mid' : 'low'));
                                $qPts       = rtrim(rtrim((string) $q['total_points'], '0'), '.') ?: '0';
                                $qQuestions = (int) $q['question_count'];
                                $qMax       = (int) $q['max_attempts'];
                                $qUsed      = (int) $q['attempts_used'];
                                $qUrl       = quizzesUrlStudent($classInfo['subject_id'], $activeTerm, (int) $q['quiz_id']);
                                $qDone      = $q['score'] !== null;
                                ?>
                                <li class="work-card work-card--quiz<?= $qClosed && !$qDone ? ' is-overdue' : '' ?><?= $qDone ? ' is-done' : '' ?>">
                                    <div class="work-card__icon"><i class="fas fa-file-circle-question"></i></div>

                                    <div class="work-card__main">
                                        <div class="work-card__top">
                                            <a class="work-card__title" href="<?= $qUrl ?>"><?= htmlspecialchars($q['title']) ?></a>
                                            <span class="chip chip-<?= htmlspecialchars($attemptStatus['class']) ?>"><?= htmlspecialchars($attemptStatus['label']) ?></span>
                                        </div>

                                        <div class="work-meta">
                                            <span class="work-pill"><i class="fas fa-circle-question"></i><b><?= $qQuestions ?></b> question<?= $qQuestions === 1 ? '' : 's' ?></span>
                                            <span class="work-pill"><i class="fas fa-star"></i><b><?= $qPts ?></b> pt<?= $qPts === '1' ? '' : 's' ?></span>
                                            <span class="work-pill"><i class="fas fa-rotate-right"></i><b><?= $qUsed ?>/<?= $qMax ?></b> attempt<?= $qMax === 1 ? '' : 's' ?> used</span>
                                            <?php if ($q['score'] !== null): ?>
                                                <span class="work-pill work-pill--score<?= $qScoreTier ? ' work-pill--' . $qScoreTier : '' ?>">
                                                    <i class="fas fa-trophy"></i>Score <b><?= rtrim(rtrim((string) $q['score'], '0'), '.') ?><?php if ($q['max_score'] !== null): ?>/<?= rtrim(rtrim((string) $q['max_score'], '0'), '.') ?><?php endif; ?></b><?= $qScorePct !== null ? ' · <b>' . $qScorePct . '%</b>' : '' ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                        <div class="work-meta work-meta--schedule">
                                            <span class="work-pill work-pill--soft">
                                                <i class="fas fa-calendar-check"></i>Opens <b><?= !empty($q['available_from']) ? date('M j, Y g:i A', strtotime($q['available_from'])) : 'Immediately' ?></b>
                                            </span>
                                            <span class="work-pill work-pill--due<?= $qClosed ? ' work-pill--overdue' : '' ?>">
                                                <i class="fas fa-calendar-xmark"></i>Due <b><?= !empty($q['available_until']) ? date('M j, Y g:i A', strtotime($q['available_until'])) : 'No due date' ?></b>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="work-card__actions">
                                        <a class="btn-secondary work-btn" href="<?= $qUrl ?>">
                                            <i class="fas fa-chart-simple"></i> View
                                        </a>
                                        <?php if ($isArchivedView): ?>
                                            <span class="btn-secondary work-btn work-btn--disabled"><i class="fas fa-lock"></i> Archived</span>
                                        <?php elseif ($qNotOpen): ?>
                                            <span class="btn-secondary work-btn work-btn--disabled"><i class="fas fa-lock"></i> Not open yet</span>
                                        <?php elseif ($qClosed || $qNoAttempts): ?>
                                            <span class="btn-secondary work-btn work-btn--disabled"><i class="fas fa-lock"></i> Closed</span>
                                        <?php else: ?>
                                            <a href="take_quiz.php?quiz_id=<?= (int) $q['quiz_id'] ?>" class="btn-primary work-btn">
                                                <i class="fas fa-play"></i> Take Quiz
                                            </a>
                                        <?php endif; ?>
                                    </div>
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
                        <?php
                            $sqPts    = rtrim(rtrim((string) $selectedQuiz['total_points'], '0'), '.') ?: '0';
                            $sqMax    = (int) $selectedQuiz['max_attempts'];
                            $sqUsed   = count(array_filter($quizAttempts, fn($r) => $r['attempt_status'] !== 'in_progress'));
                            $sqClosed = !empty($selectedQuiz['available_until']) && time() > strtotime($selectedQuiz['available_until']);
                        ?>
                        <div class="quiz-facts quiz-facts--detail">
                            <span class="quiz-fact"><span class="chip chip-<?= $qStatus['class'] ?>"><?= $qStatus['label'] ?></span></span>
                            <span class="quiz-fact"><i class="fas fa-star"></i> <b><?= $sqPts ?></b> pt<?= $sqPts === '1' ? '' : 's' ?> total</span>
                            <span class="quiz-fact"><i class="fas fa-rotate-right"></i> <b><?= $sqUsed ?>/<?= $sqMax ?></b> attempt<?= $sqMax === 1 ? '' : 's' ?> used</span>
                            <?php if ($selectedQuiz['time_limit_minutes']): ?>
                                <span class="quiz-fact"><i class="fas fa-hourglass-half"></i> <b><?= (int) $selectedQuiz['time_limit_minutes'] ?></b> min limit</span>
                            <?php endif; ?>
                            <span class="quiz-schedule__item">
                                <i class="fas fa-calendar-check"></i>
                                <span class="quiz-schedule__label">Opens</span>
                                <span class="quiz-schedule__value"><?= !empty($selectedQuiz['available_from']) ? date('M j, Y g:i A', strtotime($selectedQuiz['available_from'])) : 'Immediately' ?></span>
                            </span>
                            <span class="quiz-schedule__item <?= $sqClosed ? 'quiz-schedule__item--overdue' : '' ?>">
                                <i class="fas fa-calendar-xmark"></i>
                                <span class="quiz-schedule__label">Due</span>
                                <span class="quiz-schedule__value"><?= !empty($selectedQuiz['available_until']) ? date('M j, Y g:i A', strtotime($selectedQuiz['available_until'])) : 'No due date' ?></span>
                            </span>
                        </div>
                    </div>

                    <?php if (!empty($selectedQuiz['description'])): ?>
                        <p class="assignment-description"><?= nl2br(htmlspecialchars($selectedQuiz['description'])) ?></p>
                    <?php endif; ?>

                    <?php
                        // Find the best graded attempt so the student's headline score is
                        // front and center, instead of buried in a table they have to scan.
                        $bestAttempt = null;
                        $bestPct = -1;
                        foreach ($quizAttempts as $row) {
                            if ($row['score'] === null || $row['max_score'] === null || (float) $row['max_score'] <= 0) {
                                continue;
                            }
                            $pct = ((float) $row['score'] / (float) $row['max_score']) * 100;
                            if ($pct > $bestPct) {
                                $bestPct = $pct;
                                $bestAttempt = $row;
                            }
                        }
                        $bestPctRounded = $bestAttempt ? (int) round($bestPct) : null;
                        $bestTier = $bestPctRounded === null ? 'neutral' : ($bestPctRounded >= 80 ? 'good' : ($bestPctRounded >= 50 ? 'mid' : 'low'));
                    ?>

                    <?php if ($bestAttempt): ?>
                        <div class="quiz-score-summary quiz-score-summary--<?= $bestTier ?>">
                            <div class="quiz-score-summary__icon"><i class="fas fa-trophy"></i></div>
                            <div class="quiz-score-summary__text">
                                <span class="quiz-score-summary__label">
                                    Your best score<?= count($quizAttempts) > 1 ? ' (Attempt #' . (int) $bestAttempt['attempt_number'] . ')' : '' ?>
                                </span>
                                <span class="quiz-score-summary__value">
                                    <span class="quiz-score-summary__frac"><?= rtrim(rtrim((string) $bestAttempt['score'], '0'), '.') ?>/<?= rtrim(rtrim((string) $bestAttempt['max_score'], '0'), '.') ?></span>
                                    <span class="quiz-score-summary__pct">(<?= $bestPctRounded ?>%)</span>
                                </span>
                            </div>
                        </div>
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
                            <table class="data-table data-table--modern quiz-attempts-table">
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
                                        <?php
                                            $attemptStatus = quizAttemptStatusInfo($row['attempt_status']);
                                            $rowPct = null;
                                            if ($row['score'] !== null && $row['max_score'] !== null && (float) $row['max_score'] > 0) {
                                                $rowPct = (int) round(((float) $row['score'] / (float) $row['max_score']) * 100);
                                            }
                                            $rowTier = $rowPct === null ? 'neutral' : ($rowPct >= 80 ? 'good' : ($rowPct >= 50 ? 'mid' : 'low'));
                                        ?>
                                        <tr>
                                            <td data-label="Attempt">#<?= (int) $row['attempt_number'] ?></td>
                                            <td data-label="Status"><span
                                                    class="chip chip-<?= $attemptStatus['class'] ?>"><?= $attemptStatus['label'] ?></span>
                                            </td>
                                            <td data-label="Submitted"><?= $row['submitted_at'] ? date('M j, Y g:i A', strtotime($row['submitted_at'])) : '—' ?>
                                            </td>
                                            <td data-label="Score">
                                                <?php if ($row['score'] !== null): ?>
                                                    <span class="score-badge score-badge--<?= $rowTier ?>">
                                                        <span class="score-badge__frac"><?= rtrim(rtrim((string) $row['score'], '0'), '.') ?><?php if ($row['max_score'] !== null): ?>/<?= rtrim(rtrim((string) $row['max_score'], '0'), '.') ?><?php endif; ?></span>
                                                        <?php if ($rowPct !== null): ?><em><?= $rowPct ?>%</em><?php endif; ?>
                                                    </span>
                                                <?php else: ?>
                                                    —
                                                <?php endif; ?>
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