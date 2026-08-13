<?php
require_once '../../config/config.php';

// ---- Access control -------------------------------------------------
if (!isLoggedIn() || ($_SESSION['role'] ?? '') !== 'student') {
    header('Location: ../../public/login.php');
    exit();
}

// ---- Resolve the logged-in student's real name (session only holds the
// auto-generated STU-xxxx username, not a real name) --------------------
$userId = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT student_id, firstname, lastname FROM students WHERE user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$student = $stmt->fetch();

if (!$student) {
    die('Student record not found for this account.');
}
$studentId       = (int) $student['student_id'];
$studentFullName = trim($student['firstname'] . ' ' . $student['lastname']);

// ---- Current grade level / section (via this year's active enrollment) --
$stmt = $pdo->prepare("
    SELECT sec.grade_level, sec.section_name
    FROM enrollments e
    JOIN classofferings co ON co.offering_id = e.offering_id
    JOIN sections sec ON sec.section_id = co.section_id
    JOIN schoolyears sy ON sy.school_year_id = co.school_year_id
    WHERE e.student_id = ? AND e.status = 'active' AND sy.is_current = 1
    LIMIT 1
");
$stmt->execute([$studentId]);
$sectionRow = $stmt->fetch();

$studentGradeSection = $sectionRow
    ? "Grade {$sectionRow['grade_level']} - {$sectionRow['section_name']}"
    : null;

// ---- Real dashboard data: enrolled subjects, assignments, announcements
require_once __DIR__ . '/assets/api/dashboard_functions.php';

// ---- Still placeholder: no grades/attendance data exists yet to average.
// TODO: replace once the grades and attendance tables have real rows.
$overallAvgGrade    = 88;
$attendanceRate     = 95;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard · SUA IntelliLearn</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/dashboard.css">
</head>

<body>

    <?php include '../../includes/student_sidebar.php'; ?>

    <main class="main-content" id="dashMain">

        <?php include '../../includes/student_header.php'; ?>

        <!-- Welcome banner -->
        <section class="welcome-banner">
            <div class="welcome-text">
                <h2><?= $greeting ?>, <?= htmlspecialchars(explode(' ', $studentFullName)[0]) ?>! <span class="wave">👋</span></h2>
                <p>Here's a look at your classes and tasks for today — <?= $todayLabel ?></p>
            </div>
        </section>

        <!-- Metric cards -->
        <section class="metric-grid">
            <article class="metric-card">
                <div class="metric-head">
                    <span class="metric-label">Enrolled Subjects</span>
                    <i class="fas fa-book metric-icon"></i>
                </div>
                <div class="metric-value"><?= $enrolledSubjects ?></div>
                <div class="metric-foot"><?= $enrolledSubjects ?> active</div>
            </article>

            <article class="metric-card">
                <div class="metric-head">
                    <span class="metric-label">Overall Average Grade</span>
                    <i class="fas fa-file-lines metric-icon"></i>
                </div>
                <div class="metric-value"><?= $overallAvgGrade ?>%</div>
                <div class="metric-foot">▲ 8%</div>
            </article>

            <article class="metric-card">
                <div class="metric-head">
                    <span class="metric-label">Pending Assignments</span>
                    <i class="fas fa-square-check metric-icon"></i>
                </div>
                <div class="metric-value"><?= $pendingCount ?></div>
                <div class="metric-foot muted"><?= $pendingCount ?> left</div>
            </article>

            <article class="metric-card">
                <div class="metric-head">
                    <span class="metric-label">Attendance Rate</span>
                    <i class="fas fa-folder metric-icon"></i>
                </div>
                <div class="metric-value"><?= $attendanceRate ?>%</div>
                <div class="metric-foot">▲ 2%</div>
            </article>
        </section>

        <!-- Panels -->
        <section class="panel-grid">

            <!-- School Announcements -->
            <article class="panel">
                <div class="panel-head">
                    <h3><i class="fas fa-bullhorn"></i> School Announcements</h3>
                    <a href="announcements.php" class="panel-link">View All</a>
                </div>

                <?php if (empty($visibleAnnouncements)): ?>
                    <div class="panel-empty">
                        <i class="fas fa-bullhorn"></i>
                        <p>No announcements yet.</p>
                        <span>Anything your teachers or the administration post will show up here.</span>
                    </div>
                <?php else: ?>
                    <div class="announcement-list">
                        <?php foreach ($visibleAnnouncements as $a): ?>
                            <?php
                                [$badgeClass, $badgeLabel] = student_announcement_badge($a['priority']);
                                $postedBy = $a['t_first']
                                    ? trim($a['t_first'] . ' ' . $a['t_last'])
                                    : 'Administration';
                            ?>
                            <div class="announcement-item">
                                <div class="announcement-item-head">
                                    <h4><?= htmlspecialchars($a['title']) ?></h4>
                                    <span class="announcement-tag <?= $badgeClass ?>"><?= $badgeLabel ?></span>
                                </div>
                                <p><?= htmlspecialchars($a['body']) ?></p>
                                <div class="announcement-meta">
                                    <i class="fas fa-thumbtack"></i>
                                    Posted by <?= htmlspecialchars($postedBy) ?> · <?= date('F j, Y', strtotime($a['created_at'])) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </article>

            <!-- Due Today + To-Do -->
            <div style="display: flex; flex-direction: column; gap: 18px;">

                <article class="panel">
                    <div class="panel-head">
                        <h3><i class="fas fa-calendar-day"></i> Due Today</h3>
                        <span class="panel-link" style="color: var(--text-muted); font-weight: 500;"><?= date('F j') ?></span>
                    </div>

                    <?php if (empty($dueTodayTasks)): ?>
                        <div class="panel-empty">
                            <i class="fas fa-mug-hot"></i>
                            <p>Nothing due today.</p>
                            <span>Enjoy the breathing room.</span>
                        </div>
                    <?php else: ?>
                        <ul class="due-list">
                            <?php foreach ($dueTodayTasks as $item): ?>
                                <a class="due-item" href="<?= htmlspecialchars(student_task_link($item)) ?>">
                                    <div class="due-icon"><i class="fas <?= student_subject_icon($item['subject_name']) ?>"></i></div>
                                    <div class="due-info">
                                        <h5><?= htmlspecialchars($item['title']) ?></h5>
                                        <span><?= htmlspecialchars($item['subject_name']) ?> · <?= htmlspecialchars(trim($item['teacher_first'] . ' ' . $item['teacher_last'])) ?></span>
                                    </div>
                                    <div class="due-time"><?= student_due_time($item['due_date']) ?></div>
                                </a>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </article>

                <article class="panel">
                    <div class="panel-head">
                        <h3><i class="fas fa-list-check"></i> To-Do</h3>
                        <a href="courses.php" class="panel-link">View All</a>
                    </div>

                    <?php if (empty($todoTasks)): ?>
                        <div class="panel-empty">
                            <i class="fas fa-circle-check"></i>
                            <p>You're all caught up!</p>
                            <span>No pending assignments or quizzes right now.</span>
                        </div>
                    <?php else: ?>
                        <ul class="todo-list">
                            <?php foreach ($todoTasks as $item): ?>
                                <li class="todo-item">
                                    <a class="todo-link" href="<?= htmlspecialchars(student_task_link($item)) ?>">
                                        <i class="fas <?= student_task_type_icon($item['type']) ?> todo-type-icon"></i>
                                        <span class="todo-text">
                                            <span class="todo-chip"><?= student_subject_chip($item['subject_name']) ?></span>
                                            <?= htmlspecialchars($item['title']) ?>
                                        </span>
                                        <span class="todo-due"><?= student_due_label($item['due_date']) ?></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </article>

            </div>

        </section>

    </main>

</body>

</html>