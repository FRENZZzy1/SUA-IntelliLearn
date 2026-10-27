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

    <!-- Metric cards (each one is clickable) -->
    <section class="metric-grid">
        <a class="metric-card metric-card--link" href="courses.php">
            <div class="metric-head">
                <span class="metric-label">Total Students Enrolled</span>
                <i class="fas fa-users metric-icon"></i>
            </div>
            <div class="metric-value"><?= $totalStudents ?></div>
            <div class="metric-foot">Across <?= $totalSubjects ?> active <?= $totalSubjects === 1 ? 'class' : 'classes' ?></div>
        </a>

        <?php
        $gradeCardInner = '
            <div class="metric-head">
                <span class="metric-label">Assignments to Grade</span>
                <i class="fas fa-clipboard-check metric-icon"></i>
            </div>
            <div class="metric-value">' . ($assignmentsToGrade ?? '—') . '</div>
            <div class="metric-foot muted">'
                . ($assignmentsToGrade === null ? 'Unavailable' : ($assignmentsToGrade === 0 ? 'All caught up' : 'Pending review'))
            . '</div>';
        ?>
        <?php if (count($gradeTargets) === 1): ?>
            <!-- Only one class has work waiting: go straight to its assignments -->
            <a class="metric-card metric-card--link" href="<?= htmlspecialchars($gradeTargets[0]['url']) ?>">
                <?= $gradeCardInner ?>
            </a>
        <?php elseif (count($gradeTargets) > 1): ?>
            <!-- Several classes have work waiting: let the teacher pick one -->
            <button type="button" class="metric-card metric-card--link" id="gradePickerBtn"
                    aria-haspopup="dialog" aria-controls="gradePickerDialog">
                <?= $gradeCardInner ?>
            </button>
        <?php else: ?>
            <a class="metric-card metric-card--link" href="courses.php">
                <?= $gradeCardInner ?>
            </a>
        <?php endif; ?>

        <a class="metric-card metric-card--warn metric-card--link" href="at_risk_students.php">
            <div class="metric-head">
                <span class="metric-label">Students at Risk of Failing</span>
                <i class="fas fa-triangle-exclamation metric-icon"></i>
            </div>
            <div class="metric-value"><?= $atRiskCount ?? '—' ?></div>
            <div class="metric-foot muted">
                <?= $atRiskCount === null ? 'Coming soon' : 'Needs intervention' ?>
            </div>
        </a>

        <a class="metric-card metric-card--link" href="attendance.php">
            <div class="metric-head">
                <span class="metric-label">Average Attendance Rate</span>
                <i class="fas fa-clipboard-list metric-icon"></i>
            </div>
            <div class="metric-value"><?= $attendanceRate !== null ? $attendanceRate . '%' : '—' ?></div>
            <div class="metric-foot muted">
                <?= $attendanceRate === null ? 'Coming soon' : 'This quarter' ?>
            </div>
        </a>
    </section>

    <?php if (count($gradeTargets) > 1): ?>
    <!-- Class picker for the "Assignments to Grade" card -->
    <dialog class="grade-picker" id="gradePickerDialog" aria-labelledby="gradePickerTitle">
        <div class="grade-picker__head">
            <h3 id="gradePickerTitle">Assignments to Grade</h3>
            <button type="button" class="grade-picker__close" id="gradePickerClose" aria-label="Close">
                <i class="fas fa-xmark"></i>
            </button>
        </div>
        <p class="grade-picker__sub">Pick a class to start grading.</p>
        <ul class="grade-picker__list">
            <?php foreach ($gradeTargets as $t): ?>
            <li>
                <a href="<?= htmlspecialchars($t['url']) ?>">
                    <span class="grade-picker__info">
                        <strong><?= htmlspecialchars($t['subject']) ?></strong>
                        <small>Grade <?= (int) $t['grade'] ?> · <?= htmlspecialchars($t['section']) ?> · <?= htmlspecialchars($t['term']) ?></small>
                    </span>
                    <span class="grade-picker__count"><?= (int) $t['pending'] ?> to grade</span>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
    </dialog>
    <?php endif; ?>

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
                                <td><?= htmlspecialchars($s['avg_grade']) ?><?= is_numeric($s['avg_grade']) ? '%' : '' ?></td>
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

    <!-- My Classes (ongoing term only; each card opens that class) -->
    <section class="panel">
        <div class="panel-head">
            <h3>My Classes<?php if ($currentTermLabel): ?> <span class="panel-term"><?= htmlspecialchars($currentTermLabel) ?> · Ongoing</span><?php endif; ?></h3>
            <a href="courses.php" class="panel-link">View All</a>
        </div>

        <?php if (empty($activeOfferings)): ?>
            <div class="panel-empty">
                <i class="fas fa-chalkboard"></i>
                <p>No active classes assigned yet.</p>
                <span>Once the admin assigns you a class, it will show up here.</span>
            </div>
        <?php elseif (empty($ongoingOfferings)): ?>
            <div class="panel-empty">
                <i class="fas fa-calendar-xmark"></i>
                <p><?= $currentTerm === null ? 'No term is running right now.' : 'No classes for the ongoing term.' ?></p>
                <span>Classes for the ongoing term will show up here. Use <strong>View All</strong> to browse every class.</span>
            </div>
        <?php else: ?>
            <div class="class-grid">
                <?php foreach ($ongoingOfferings as $c): ?>
                <a class="class-card class-card--link" href="<?= htmlspecialchars(dash_class_url($c)) ?>">
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
                </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

</main>

<?php if (count($gradeTargets) > 1): ?>
<script>
(function () {
    var btn = document.getElementById('gradePickerBtn');
    var dlg = document.getElementById('gradePickerDialog');
    var close = document.getElementById('gradePickerClose');
    if (!btn || !dlg) return;
    btn.addEventListener('click', function () {
        if (typeof dlg.showModal === 'function') { dlg.showModal(); } else { dlg.setAttribute('open', ''); }
    });
    close.addEventListener('click', function () { dlg.close(); });
    // click on the dark backdrop closes it
    dlg.addEventListener('click', function (e) { if (e.target === dlg) dlg.close(); });
})();
</script>
<?php endif; ?>

</body>
</html>