<?php
include 'assets/api/dashboard_functions.php';
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