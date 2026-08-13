<?php
include 'assets/api/attendance_functions.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance · SUA IntelliLearn</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/course_view.css">
    <link rel="stylesheet" href="assets/css/attendance.css">
</head>
<body>

<?php include '../../includes/student_sidebar.php'; ?>

<main class="main-content" id="dashMain">

    <?php include '../../includes/student_header.php'; ?>

    <div class="dash-page-title">
        <h1 class="dash-title">Attendance</h1>
        <?php if ($schoolYearLabel): ?>
            <p class="dash-subtitle">School Year <?= htmlspecialchars($schoolYearLabel) ?></p>
        <?php endif; ?>
    </div>

    <?php if (empty($subjects)): ?>
        <section class="panel">
            <div class="panel-empty panel-empty--enhanced">
                <div class="panel-empty__icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <h3>You're not enrolled in any classes yet</h3>
                <p>Once your enrollment is approved, your attendance records will show up here.</p>
            </div>
        </section>
    <?php else: ?>

        <!-- Overall summary bar -->
        <section class="stats-bar">
            <div class="stat-card">
                <div class="stat-card__icon stat-card__icon--green">
                    <i class="fas fa-check"></i>
                </div>
                <div class="stat-card__info">
                    <span class="stat-card__value"><?= (int) $overallCounts['Present'] ?></span>
                    <span class="stat-card__label">Present</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-card__icon stat-card__icon--amber">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-card__info">
                    <span class="stat-card__value"><?= (int) $overallCounts['Late'] ?></span>
                    <span class="stat-card__label">Late</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-card__icon" style="background:#fdeceb; color:#b3261e;">
                    <i class="fas fa-xmark"></i>
                </div>
                <div class="stat-card__info">
                    <span class="stat-card__value"><?= (int) $overallCounts['Absent'] ?></span>
                    <span class="stat-card__label">Absent</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-card__icon stat-card__icon--blue">
                    <i class="fas fa-file-shield"></i>
                </div>
                <div class="stat-card__info">
                    <span class="stat-card__value"><?= (int) $overallCounts['Excused'] ?></span>
                    <span class="stat-card__label">Excused</span>
                </div>
            </div>
        </section>

        <!-- One panel per enrolled subject -->
        <?php foreach ($subjects as $i => $subject):
            $teacherName = trim($subject['teacher_first'] . ' ' . $subject['teacher_last']);
            ?>
            <section class="panel panel--card attendance-subject <?= $i === 0 ? 'expanded' : '' ?>" data-attendance-subject>
                <div class="panel-header attendance-subject-header">
                    <div class="attendance-subject-title">
                        <i class="fas fa-book" style="color: var(--primary, #10b981);"></i>
                        <div>
                            <h2><?= htmlspecialchars($subject['subject_name']) ?></h2>
                            <div class="attendance-subject-meta">
                                <?= htmlspecialchars($subject['section_name']) ?> · Grade
                                <?= (int) $subject['grade_level'] ?>
                                · <?= htmlspecialchars($teacherName) ?>
                            </div>
                        </div>
                    </div>

                    <div style="display:flex; align-items:center; gap:16px;">
                        <?php if ($subject['present_rate'] !== null): ?>
                            <div class="attendance-rate">
                                <span class="attendance-rate-value"><?= $subject['present_rate'] ?>%</span>
                                <span class="attendance-rate-label">attendance rate</span>
                            </div>
                        <?php endif; ?>
                        <button type="button" class="attendance-toggle" data-attendance-toggle>
                            <span><?= $subject['total_recorded'] ?> record<?= $subject['total_recorded'] === 1 ? '' : 's' ?></span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                    </div>
                </div>

                <div class="attendance-mini-counts">
                    <span class="chip chip-present"><?= (int) $subject['counts']['Present'] ?> Present</span>
                    <span class="chip chip-late"><?= (int) $subject['counts']['Late'] ?> Late</span>
                    <span class="chip chip-absent"><?= (int) $subject['counts']['Absent'] ?> Absent</span>
                    <span class="chip chip-excused"><?= (int) $subject['counts']['Excused'] ?> Excused</span>
                </div>

                <div class="attendance-subject-body">
                    <?php if (empty($subject['records'])): ?>
                        <div class="panel-empty panel-empty--enhanced">
                            <div class="panel-empty__icon">
                                <i class="fas fa-calendar-xmark"></i>
                            </div>
                            <h3>No attendance recorded yet</h3>
                            <p>Your teacher hasn't taken attendance for this class yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-scroll">
                            <table class="data-table data-table--modern">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th>Remarks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($subject['records'] as $record):
                                        $statusInfo = attendanceStatusInfo($record['status']);
                                        ?>
                                        <tr>
                                            <td><?= date('M j, Y (D)', strtotime($record['attendance_date'])) ?></td>
                                            <td><span class="chip chip-<?= $statusInfo['class'] ?>"><?= htmlspecialchars($statusInfo['label']) ?></span></td>
                                            <td class="attendance-remarks-cell"><?= $record['remarks'] ? htmlspecialchars($record['remarks']) : '—' ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        <?php endforeach; ?>

    <?php endif; ?>

</main>

<script>
(function () {
    document.querySelectorAll('[data-attendance-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            btn.closest('[data-attendance-subject]').classList.toggle('expanded');
        });
    });
})();
</script>

</body>
</html>