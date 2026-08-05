<?php
/**
 * Teacher Dashboard
 * public/teacher/dashboard.php
 *
 * Central hub for teacher activities: enrollment stats, active classes,
 * at-risk snapshot, and a recent-activity feed.
 *
 * NOTE ON DATA SOURCES
 * ---------------------------------------------------------------
 * The current schema (students_PINAKAAAABAGOO.sql) has: users, teachers,
 * students, classofferings, enrollments, sections, subjects,
 * announcements, learning_materials.
 *
 * It does NOT yet have tables for grades, attendance, quizzes, or
 * at-risk scoring. The three metric cards that depend on those
 * (Assignments to Grade, Students at Risk, Attendance Rate) are wired
 * to placeholder queries below — clearly marked TODO — so wiring in
 * the real logic later is a one-block edit, not a rewrite.
 * ---------------------------------------------------------------
 */

require_once '../../config/config.php'; // adjust path to your actual config.php location

// ---- Access control -------------------------------------------------
if (!isLoggedIn() || ($_SESSION['role'] ?? '') !== 'teacher') {
    header('Location: ../../login.php');
    exit();
}

$userId = (int) $_SESSION['user_id'];

// ---- Resolve the logged-in teacher row -------------------------------
$stmt = $pdo->prepare("SELECT teacher_id, firstname, lastname FROM teachers WHERE user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$teacher = $stmt->fetch();

if (!$teacher) {
    die('Teacher record not found for this account.');
}
$teacherId   = (int) $teacher['teacher_id'];
$teacherName = trim($teacher['firstname'] . ' ' . $teacher['lastname']);

// ---- Current school year ---------------------------------------------
$stmt = $pdo->query("SELECT school_year_id, label FROM schoolyears WHERE is_current = 1 LIMIT 1");
$schoolYear   = $stmt->fetch();
$schoolYearId = $schoolYear['school_year_id'] ?? null;

// ---- Active classes / subjects for this teacher -----------------------
$stmt = $pdo->prepare("
    SELECT co.offering_id, co.schedule_days, co.start_time, co.end_time,
           sub.subject_name, sec.section_name, sec.grade_level
    FROM classofferings co
    JOIN subjects sub  ON sub.subject_id = co.subject_id
    JOIN sections sec  ON sec.section_id = co.section_id
    WHERE co.teacher_id = ?
      AND co.status = 'active'
      AND (co.school_year_id = ? OR ? IS NULL)
    ORDER BY sec.grade_level, sub.subject_name
");
$stmt->execute([$teacherId, $schoolYearId, $schoolYearId]);
$activeOfferings = $stmt->fetchAll();
$offeringIds     = array_column($activeOfferings, 'offering_id');
$totalSubjects   = count($activeOfferings);

// ---- Total students enrolled across this teacher's active classes -----
$totalStudents = 0;
if ($offeringIds) {
    $placeholders = implode(',', array_fill(0, count($offeringIds), '?'));
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT student_id) AS total
        FROM enrollments
        WHERE status = 'active'
          AND offering_id IN ($placeholders)
    ");
    $stmt->execute($offeringIds);
    $totalStudents = (int) $stmt->fetchColumn();
}

// ---- Placeholder metrics (no backing table yet) ------------------------
// TODO: replace with a real query once an `assignments` table exists.
$assignmentsToGrade = null;
// TODO: replace with a real query once grades/attendance-based risk
// scoring is implemented (REQ018–REQ021 in the proposal).
$atRiskCount = null;
$atRiskStudents = []; // TODO: populate once at-risk detection exists.
// TODO: replace once an `attendance` table exists.
$attendanceRate = null;

// ---- Recent activity feed (announcements + materials + enrollments) ---
$recentActivity = [];

if ($offeringIds) {
    $placeholders = implode(',', array_fill(0, count($offeringIds), '?'));

    // Materials this teacher uploaded
    $stmt = $pdo->prepare("
        SELECT title AS label, type, created_at
        FROM learning_materials
        WHERE uploaded_by = ?
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$teacherId]);
    foreach ($stmt->fetchAll() as $row) {
        $recentActivity[] = [
            'icon' => 'fa-file-arrow-up',
            'text' => 'Uploaded material — ' . $row['label'],
            'time' => $row['created_at'],
        ];
    }

    // Recent enrollments into this teacher's classes
    $stmt = $pdo->prepare("
        SELECT s.firstname, s.lastname, e.enrolled_at, sub.subject_name
        FROM enrollments e
        JOIN students s   ON s.student_id = e.student_id
        JOIN classofferings co ON co.offering_id = e.offering_id
        JOIN subjects sub ON sub.subject_id = co.subject_id
        WHERE e.offering_id IN ($placeholders)
          AND e.status = 'active'
        ORDER BY e.enrolled_at DESC
        LIMIT 5
    ");
    $stmt->execute($offeringIds);
    foreach ($stmt->fetchAll() as $row) {
        $recentActivity[] = [
            'icon' => 'fa-user-plus',
            'text' => $row['firstname'] . ' ' . $row['lastname'] . ' enrolled in ' . $row['subject_name'],
            'time' => $row['enrolled_at'],
        ];
    }
}

// Announcements this teacher posted
$stmt = $pdo->prepare("
    SELECT title, created_at
    FROM announcements
    WHERE posted_by = ?
    ORDER BY created_at DESC
    LIMIT 5
");
$stmt->execute([$userId]);
foreach ($stmt->fetchAll() as $row) {
    $recentActivity[] = [
        'icon' => 'fa-bullhorn',
        'text' => 'Posted announcement — ' . $row['title'],
        'time' => $row['created_at'],
    ];
}

// Sort combined feed by timestamp, newest first, cap at 6
usort($recentActivity, fn($a, $b) => strtotime($b['time']) <=> strtotime($a['time']));
$recentActivity = array_slice($recentActivity, 0, 6);

/** Small helper: "2 hours ago" style relative time. */
function time_ago(string $datetime): string
{
    $diff = time() - strtotime($datetime);
    if ($diff < 60)     return 'Just now';
    if ($diff < 3600)   return floor($diff / 60) . ' min ago';
    if ($diff < 86400)  return floor($diff / 3600) . ' hr ago';
    if ($diff < 604800) return floor($diff / 86400) . ' day' . (floor($diff / 86400) > 1 ? 's' : '') . ' ago';
    return date('M j', strtotime($datetime));
}

$greetingHour = (int) date('H');
$greeting = $greetingHour < 12 ? 'Good morning' : ($greetingHour < 18 ? 'Good afternoon' : 'Good evening');
$todayLabel = date('l, F j, Y');
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

<?php include '../../includes/teachers_sidebar.php'; ?>

<main class="main-content" id="dashMain">

    <?php include '../../includes/teacher_header.php'; ?>

    <div class="dash-page-title">
        <h1 class="dash-title">Dashboard</h1>
    </div>

    <!-- Welcome banner -->
    <section class="welcome-banner">
        <div class="welcome-text">
            <h2><?= $greeting ?>, <?= htmlspecialchars($teacherName) ?>! <span class="wave">👋</span></h2>
            <p>Here's what's happening across your classes today — <?= $todayLabel ?></p>
        </div>
        <div class="welcome-stats">
            <div class="welcome-stat">
                <span class="num"><?= $totalStudents ?></span>
                <span class="lbl">Students</span>
            </div>
            <div class="welcome-stat">
                <span class="num"><?= $totalSubjects ?></span>
                <span class="lbl">Subjects</span>
            </div>
            <div class="welcome-stat">
                <span class="num"><?= $atRiskCount ?? '—' ?></span>
                <span class="lbl">At Risk</span>
            </div>
        </div>
    </section>

    <!-- Metric cards -->
    <section class="metric-grid">
        <article class="metric-card">
            <div class="metric-head">
                <span class="metric-label">Total Students Enrolled</span>
                <i class="fas fa-users metric-icon"></i>
            </div>
            <div class="metric-value"><?= $totalStudents ?></div>
            <div class="metric-foot">Across <?= $totalSubjects ?> active <?= $totalSubjects === 1 ? 'class' : 'classes' ?></div>
        </article>

        <article class="metric-card">
            <div class="metric-head">
                <span class="metric-label">Assignments to Grade</span>
                <i class="fas fa-clipboard-check metric-icon"></i>
            </div>
            <div class="metric-value"><?= $assignmentsToGrade ?? '—' ?></div>
            <div class="metric-foot muted">
                <?= $assignmentsToGrade === null ? 'Coming soon' : 'Pending review' ?>
            </div>
        </article>

        <article class="metric-card metric-card--warn">
            <div class="metric-head">
                <span class="metric-label">Students at Risk of Failing</span>
                <i class="fas fa-triangle-exclamation metric-icon"></i>
            </div>
            <div class="metric-value"><?= $atRiskCount ?? '—' ?></div>
            <div class="metric-foot muted">
                <?= $atRiskCount === null ? 'Coming soon' : 'Needs intervention' ?>
            </div>
        </article>

        <article class="metric-card">
            <div class="metric-head">
                <span class="metric-label">Average Attendance Rate</span>
                <i class="fas fa-clipboard-list metric-icon"></i>
            </div>
            <div class="metric-value"><?= $attendanceRate !== null ? $attendanceRate . '%' : '—' ?></div>
            <div class="metric-foot muted">
                <?= $attendanceRate === null ? 'Coming soon' : 'This quarter' ?>
            </div>
        </article>
    </section>

    <!-- Panels -->
    <section class="panel-grid">

        <article class="panel">
            <div class="panel-head">
                <h3>At-Risk Students</h3>
                <a href="at_risk_students.php" class="panel-link">View All</a>
            </div>

            <?php if (empty($atRiskStudents)): ?>
                <div class="panel-empty">
                    <i class="fas fa-shield-heart"></i>
                    <p>At-risk detection isn't wired up yet.</p>
                    <span>Once grade and attendance data are tracked, students who need attention will show up here automatically.</span>
                </div>
            <?php else: ?>
                <div class="table-scroll">
                    <table class="panel-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Subject</th>
                                <th>Avg. Grade</th>
                                <th>Risk</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($atRiskStudents as $s): ?>
                            <tr>
                                <td><?= htmlspecialchars($s['name']) ?></td>
                                <td><span class="chip"><?= htmlspecialchars($s['subject']) ?></span></td>
                                <td><?= htmlspecialchars($s['avg_grade']) ?>%</td>
                                <td><span class="risk risk--<?= strtolower($s['risk']) ?>"><?= htmlspecialchars($s['risk']) ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </article>

        <article class="panel">
            <div class="panel-head">
                <h3>Recent Activity</h3>
            </div>

            <?php if (empty($recentActivity)): ?>
                <div class="panel-empty">
                    <i class="fas fa-inbox"></i>
                    <p>Nothing here yet.</p>
                    <span>Uploaded materials, new enrollments, and announcements you post will appear here.</span>
                </div>
            <?php else: ?>
                <ul class="activity-list">
                    <?php foreach ($recentActivity as $item): ?>
                    <li>
                        <span class="activity-icon"><i class="fas <?= htmlspecialchars($item['icon']) ?>"></i></span>
                        <span class="activity-text"><?= htmlspecialchars($item['text']) ?></span>
                        <span class="activity-time"><?= time_ago($item['time']) ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </article>

    </section>

    <!-- My Classes -->
    <section class="panel">
        <div class="panel-head">
            <h3>My Classes</h3>
            <a href="courses.php" class="panel-link">View All</a>
        </div>

        <?php if (empty($activeOfferings)): ?>
            <div class="panel-empty">
                <i class="fas fa-chalkboard"></i>
                <p>No active classes assigned yet.</p>
                <span>Once the admin assigns you a class, it will show up here.</span>
            </div>
        <?php else: ?>
            <div class="class-grid">
                <?php foreach ($activeOfferings as $c): ?>
                <div class="class-card">
                    <div class="class-card-top">
                        <span class="class-grade">Grade <?= (int) $c['grade_level'] ?></span>
                        <span class="class-section"><?= htmlspecialchars($c['section_name']) ?></span>
                    </div>
                    <h4><?= htmlspecialchars($c['subject_name']) ?></h4>
                    <p class="class-schedule">
                        <i class="fas fa-clock"></i>
                        <?= htmlspecialchars($c['schedule_days'] ?? 'TBA') ?>
                        <?php if ($c['start_time']): ?>
                            · <?= date('g:i A', strtotime($c['start_time'])) ?>–<?= date('g:i A', strtotime($c['end_time'])) ?>
                        <?php endif; ?>
                    </p>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

</main>

</body>
</html>