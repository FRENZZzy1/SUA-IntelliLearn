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
    <link rel="stylesheet" href="/SUA-INTELLILEARN/includes/css/modern_dashboard.css">
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
                . ($assignmentsToGrade === null ? 'Unavailable' : ($assignmentsToGrade === 0 ? 'All caught up' : 'Tap to review <i class="fas fa-arrow-right"></i>'))
            . '</div>';
        ?>
        <?php if (!empty($pendingSubmissions)): ?>
            <!-- Work waiting: open the ungraded-submissions queue -->
            <button type="button" class="metric-card metric-card--link metric-card--grade" id="gradeQueueBtn"
                    aria-haspopup="dialog" aria-controls="gradeQueue">
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

    <?php if (!empty($pendingSubmissions)): ?>
    <!-- Grading queue: every submission still waiting for a score -->
    <dialog class="gq" id="gradeQueue" aria-labelledby="gqTitle">
        <div class="gq__sheet">
            <header class="gq__head">
                <div class="gq__grip" aria-hidden="true"></div>
                <div class="gq__title-row">
                    <div>
                        <p class="gq__eyebrow"><i class="fas fa-clipboard-check"></i> Grading queue</p>
                        <h3 id="gqTitle"><span id="gqCount"><?= count($pendingSubmissions) ?></span> waiting for a score</h3>
                    </div>
                    <button type="button" class="gq__close" id="gqClose" aria-label="Close"><i class="fas fa-xmark"></i></button>
                </div>

                <div class="gq__tools">
                    <label class="gq__search">
                        <i class="fas fa-magnifying-glass"></i>
                        <input type="search" id="gqSearch" placeholder="Search student or assignment" autocomplete="off">
                    </label>
                    <div class="gq__chips" id="gqChips" role="tablist" aria-label="Filter by class">
                        <button type="button" class="gq__chip is-active" data-class="all">All <b><?= count($pendingSubmissions) ?></b></button>
                        <?php
                        $chipSeen = [];
                        foreach ($pendingSubmissions as $ps) {
                            $chipSeen[$ps['class_key']]['label'] = $ps['subject'] . ' · ' . $ps['section'];
                            $chipSeen[$ps['class_key']]['n'] = ($chipSeen[$ps['class_key']]['n'] ?? 0) + 1;
                        }
                        foreach ($chipSeen as $k => $chip): ?>
                            <button type="button" class="gq__chip" data-class="<?= (int) $k ?>">
                                <?= htmlspecialchars($chip['label']) ?> <b><?= (int) $chip['n'] ?></b>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </header>

            <ul class="gq__list" id="gqList">
                <?php foreach ($pendingSubmissions as $ps): ?>
                <li class="gq__item" data-class="<?= (int) $ps['class_key'] ?>"
                    data-search="<?= htmlspecialchars(strtolower($ps['student'] . ' ' . $ps['assignment'] . ' ' . $ps['subject'])) ?>">
                    <a href="<?= htmlspecialchars($ps['url']) ?>" class="gq__card">
                        <span class="gq__avatar" aria-hidden="true"><?= htmlspecialchars($ps['initials']) ?></span>
                        <span class="gq__body">
                            <span class="gq__student"><?= htmlspecialchars($ps['student']) ?></span>
                            <span class="gq__assignment"><i class="fas fa-marker"></i> <?= htmlspecialchars($ps['assignment']) ?></span>
                            <span class="gq__meta">
                                <span class="gq__class"><?= htmlspecialchars($ps['subject']) ?></span>
                                <span class="gq__dot"></span>
                                <span>Gr. <?= (int) $ps['grade'] ?> · <?= htmlspecialchars($ps['section']) ?></span>
                            </span>
                        </span>
                        <span class="gq__side">
                            <?php if ($ps['is_late']): ?><span class="gq__tag gq__tag--late">Late</span><?php endif; ?>
                            <?php if ($ps['attempt'] > 1): ?><span class="gq__tag">Attempt <?= (int) $ps['attempt'] ?></span><?php endif; ?>
                            <span class="gq__time"><?= $ps['submitted_at'] ? time_ago($ps['submitted_at']) : '' ?></span>
                            <span class="gq__go"><i class="fas fa-chevron-right"></i></span>
                        </span>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
            <p class="gq__empty" id="gqEmpty" hidden><i class="fas fa-face-smile"></i> Nothing matches your filter.</p>
        </div>
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

<?php if (!empty($pendingSubmissions)): ?>
<script>
(function () {
    var btn = document.getElementById('gradeQueueBtn'),
        dlg = document.getElementById('gradeQueue');
    if (!btn || !dlg) return;
    var items = [].slice.call(dlg.querySelectorAll('.gq__item')),
        chips = [].slice.call(dlg.querySelectorAll('.gq__chip')),
        search = document.getElementById('gqSearch'),
        count = document.getElementById('gqCount'),
        empty = document.getElementById('gqEmpty'),
        cls = 'all';

    function apply() {
        var q = search.value.trim().toLowerCase(), n = 0;
        items.forEach(function (li) {
            var ok = (cls === 'all' || li.dataset.class === cls) && (!q || li.dataset.search.indexOf(q) > -1);
            li.hidden = !ok;
            if (ok) n++;
        });
        count.textContent = n;
        empty.hidden = n > 0;
    }
    function open() {
        if (typeof dlg.showModal === 'function') dlg.showModal(); else dlg.setAttribute('open', '');
        document.body.classList.add('gq-lock');
    }
    function close() {
        dlg.classList.add('is-closing');
        setTimeout(function () {
            dlg.classList.remove('is-closing');
            if (dlg.close) dlg.close(); else dlg.removeAttribute('open');
            document.body.classList.remove('gq-lock');
        }, 180);
    }
    btn.addEventListener('click', open);
    document.getElementById('gqClose').addEventListener('click', close);
    dlg.addEventListener('click', function (e) { if (e.target === dlg) close(); });
    dlg.addEventListener('cancel', function (e) { e.preventDefault(); close(); });
    search.addEventListener('input', apply);
    chips.forEach(function (c) {
        c.addEventListener('click', function () {
            chips.forEach(function (x) { x.classList.remove('is-active'); });
            c.classList.add('is-active');
            cls = c.dataset.class;
            apply();
        });
    });
})();
</script>
<?php endif; ?>

</body>
</html>