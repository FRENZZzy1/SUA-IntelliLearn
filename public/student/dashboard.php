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

// ---- STATIC placeholder data (swap for real queries later) -----------
$greeting            = 'Good morning';
$todayLabel          = 'Wednesday, March 25, 2026';

$enrolledSubjects   = 6;
$pendingCount       = 5;
$dueTodayCount      = 3;
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

                <div class="announcement-list">
                    <div class="announcement-item">
                        <div class="announcement-item-head">
                            <h4>Quarter 3 Examination Schedule Released</h4>
                            <span class="announcement-tag announcement-tag--urgent">Urgent</span>
                        </div>
                        <p>The Q3 exam schedule has been finalized. Exams begin April 7–11, 2026. Please review the full schedule on the bulletin board and prepare accordingly.</p>
                        <div class="announcement-meta"><i class="fas fa-thumbtack"></i> Posted by Administration · March 24, 2026</div>
                    </div>

                    <div class="announcement-item">
                        <div class="announcement-item-head">
                            <h4>Acquaintance Party – April 3, 2026</h4>
                            <span class="announcement-tag announcement-tag--event">Event</span>
                        </div>
                        <p>The Student Council invites all Grade 10 students to the annual Acquaintance Party at the school gymnasium. Dress code: smart casual.</p>
                        <div class="announcement-meta"><i class="fas fa-thumbtack"></i> Posted by Student Council · March 22, 2026</div>
                    </div>

                    <div class="announcement-item">
                        <div class="announcement-item-head">
                            <h4>No Classes on March 28 (Holy Thursday)</h4>
                            <span class="announcement-tag announcement-tag--general">General</span>
                        </div>
                        <p>In observance of Holy Week, there will be no classes from March 27–31, 2026. Classes resume on April 1.</p>
                        <div class="announcement-meta"><i class="fas fa-thumbtack"></i> Posted by Principal's Office · March 20, 2026</div>
                    </div>

                    <div class="announcement-item">
                        <div class="announcement-item-head">
                            <h4>Science Fair Project Submission Reminder</h4>
                            <span class="announcement-tag announcement-tag--academic">Academic</span>
                        </div>
                        <p>All Grade 10 participants must submit their Science Fair abstracts by March 27. Submit to your Science teacher or the Science Department office.</p>
                        <div class="announcement-meta"><i class="fas fa-thumbtack"></i> Posted by Science Dept. · March 19, 2026</div>
                    </div>
                </div>
            </article>

            <!-- Due Today + To-Do -->
            <div style="display: flex; flex-direction: column; gap: 18px;">

                <article class="panel">
                    <div class="panel-head">
                        <h3><i class="fas fa-calendar-day"></i> Due Today</h3>
                        <span class="panel-link" style="color: var(--text-muted); font-weight: 500;">March 25</span>
                    </div>

                    <ul class="due-list">
                        <li class="due-item">
                            <div class="due-icon"><i class="fas fa-file-pen"></i></div>
                            <div class="due-info">
                                <h5>Math Long Quiz – Ch. 5 Polynomials</h5>
                                <span>Mathematics 10 · Mr. Dela Cruz</span>
                            </div>
                            <div class="due-time">8:00 AM</div>
                        </li>
                        <li class="due-item">
                            <div class="due-icon"><i class="fas fa-file-lines"></i></div>
                            <div class="due-info">
                                <h5>English Essay – Argumentative Writing</h5>
                                <span>English 10 · Ms. Aquino</span>
                            </div>
                            <div class="due-time">2:00 PM</div>
                        </li>
                        <li class="due-item">
                            <div class="due-icon"><i class="fas fa-flask"></i></div>
                            <div class="due-info">
                                <h5>Science Lab Report – Osmosis</h5>
                                <span>Science 10 · Ms. Villanueva</span>
                            </div>
                            <div class="due-time">End of Day</div>
                        </li>
                    </ul>
                </article>

                <article class="panel">
                    <div class="panel-head">
                        <h3><i class="fas fa-list-check"></i> Assignments To-Do</h3>
                        <a href="assignments.php" class="panel-link">View All</a>
                    </div>

                    <ul class="todo-list">
                        <li class="todo-item">
                            <input type="checkbox">
                            <span class="todo-text">
                                <span class="todo-chip">MATH</span>
                                Math Long Quiz – Ch. 5 Polynomials
                            </span>
                            <span class="todo-due">Due today, 8:00 AM</span>
                        </li>
                    </ul>
                </article>

            </div>

        </section>

    </main>

</body>

</html>