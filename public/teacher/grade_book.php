<?php
require_once __DIR__ . '/../../config/config.php';

if (!isLoggedIn() || ($_SESSION['role'] ?? '') !== 'teacher') {
    header('Location: ../login.php');
    exit();
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$stmt = $pdo->prepare("SELECT teacher_id, firstname, lastname FROM teachers WHERE user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$teacher) {
    die('Teacher record not found for this account.');
}

$teacherId = (int)$teacher['teacher_id'];

// One row per term offering. Teachers only see classes assigned to them.
$stmt = $pdo->prepare("\n    SELECT co.offering_id, co.quarter, co.schedule_days, co.start_time, co.end_time,\n           sub.subject_name, sec.section_name, sec.grade_level, sec.strand, sy.label AS school_year,\n           COUNT(DISTINCT CASE WHEN e.status = 'active' THEN e.student_id END) AS student_count\n    FROM classofferings co\n    JOIN subjects sub ON sub.subject_id = co.subject_id\n    JOIN sections sec ON sec.section_id = co.section_id\n    JOIN schoolyears sy ON sy.school_year_id = co.school_year_id\n    LEFT JOIN enrollments e ON e.offering_id = co.offering_id\n    WHERE co.teacher_id = ? AND co.status = 'active'\n    GROUP BY co.offering_id, co.quarter, co.schedule_days, co.start_time, co.end_time,\n             sub.subject_name, sec.section_name, sec.grade_level, sec.strand, sy.label\n    ORDER BY sy.start_date DESC, sub.subject_name, sec.section_name, co.quarter\n");
$stmt->execute([$teacherId]);
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$selectedOfferingId = filter_input(INPUT_GET, 'offering_id', FILTER_VALIDATE_INT);
$selectedClass = null;
foreach ($classes as $class) {
    if ((int)$class['offering_id'] === (int)$selectedOfferingId) {
        $selectedClass = $class;
        break;
    }
}
if (!$selectedClass && $classes) {
    $selectedClass = $classes[0];
    $selectedOfferingId = (int)$selectedClass['offering_id'];
}

$students = [];
$assignments = [];
$quizzes = [];
$assignmentScores = [];
$quizScores = [];

if ($selectedOfferingId) {
    // Enrolled students in the selected class.
    $stmt = $pdo->prepare("\n        SELECT s.student_id, s.student_lrn, s.firstname, s.lastname, s.middlename\n        FROM enrollments e\n        JOIN students s ON s.student_id = e.student_id\n        WHERE e.offering_id = ? AND e.status = 'active'\n        ORDER BY s.lastname, s.firstname\n    ");
    $stmt->execute([$selectedOfferingId]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Assignments and each student's latest attempt. Scores are normalized to percentage.
    $stmt = $pdo->prepare("\n        SELECT a.assignment_id, a.title, a.points,\n               sub.student_id, sub.score, sub.attempt_number, sub.status\n        FROM assignments a\n        LEFT JOIN submissions sub ON sub.assignment_id = a.assignment_id\n          AND sub.attempt_number = (\n              SELECT MAX(s2.attempt_number)\n              FROM submissions s2\n              WHERE s2.assignment_id = a.assignment_id AND s2.student_id = sub.student_id\n          )\n        WHERE a.offering_id = ?\n        ORDER BY a.created_at ASC, a.assignment_id ASC\n    ");
    $stmt->execute([$selectedOfferingId]);
    $assignmentRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $assignmentIds = [];
    foreach ($assignmentRows as $row) {
        $id = (int)$row['assignment_id'];
        if (!isset($assignments[$id])) {
            $assignments[$id] = [
                'id' => $id,
                'title' => $row['title'],
                'points' => (float)$row['points'],
            ];
            $assignmentIds[] = $id;
        }
        if ($row['student_id'] !== null) {
            $assignmentScores[(int)$row['student_id']][$id] = [
                'score' => $row['score'] !== null ? (float)$row['score'] : null,
                'status' => $row['status'] ?? 'missing',
            ];
        }
    }
    $assignments = array_values($assignments);

    // Quizzes and each student's latest attempt. score/max_score is normalized to percentage.
    $stmt = $pdo->prepare("\n        SELECT q.quiz_id, q.title,\n               qa.student_id, qa.score, qa.max_score, qa.attempt_number, qa.status\n        FROM quizzes q\n        LEFT JOIN quiz_attempts qa ON qa.quiz_id = q.quiz_id\n          AND qa.attempt_number = (\n              SELECT MAX(q2.attempt_number)\n              FROM quiz_attempts q2\n              WHERE q2.quiz_id = q.quiz_id AND q2.student_id = qa.student_id\n          )\n        WHERE q.offering_id = ?\n        ORDER BY q.created_at ASC, q.quiz_id ASC\n    ");
    $stmt->execute([$selectedOfferingId]);
    $quizRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $quizMap = [];
    foreach ($quizRows as $row) {
        $id = (int)$row['quiz_id'];
        if (!isset($quizMap[$id])) {
            $quizMap[$id] = ['id' => $id, 'title' => $row['title']];
        }
        if ($row['student_id'] !== null) {
            $max = (float)($row['max_score'] ?? 0);
            $score = $row['score'] !== null ? (float)$row['score'] : null;
            $quizScores[(int)$row['student_id']][$id] = [
                'score' => ($score !== null && $max > 0) ? ($score / $max) * 100 : null,
                'status' => $row['status'] ?? 'missing',
            ];
        }
    }
    $quizzes = array_values($quizMap);
}

function averagePercent(array $scores): ?float {
    $valid = array_values(array_filter($scores, static fn($v) => $v !== null));
    if (!$valid) return null;
    return round(array_sum($valid) / count($valid), 1);
}

function gradeClass(?float $value): string {
    if ($value === null) return 'grade-none';
    if ($value >= 90) return 'grade-excellent';
    if ($value >= 80) return 'grade-good';
    if ($value >= 75) return 'grade-fair';
    return 'grade-low';
}

$rows = [];
$quizTotal = 0;
$assignmentTotal = 0;
$allOverall = [];
$quizDistribution = ['90+' => 0, '80–89' => 0, '75–79' => 0, '<75' => 0];
$assignmentDistribution = ['90+' => 0, '80–89' => 0, '75–79' => 0, '<75' => 0];

foreach ($students as $student) {
    $sid = (int)$student['student_id'];
    $aValues = [];
    foreach ($assignments as $a) {
        $value = $assignmentScores[$sid][$a['id']]['score'] ?? null;
        $aValues[] = $value !== null && $a['points'] > 0 ? ($value / $a['points']) * 100 : null;
    }
    $qValues = [];
    foreach ($quizzes as $q) {
        $qValues[] = $quizScores[$sid][$q['id']]['score'] ?? null;
    }
    $aAvg = averagePercent($aValues);
    $qAvg = averagePercent($qValues);
    $categoryValues = array_values(array_filter([$aAvg, $qAvg], static fn($v) => $v !== null));
    $overall = $categoryValues ? round(array_sum($categoryValues) / count($categoryValues), 1) : null;

    if ($qAvg !== null) {
        $quizTotal += 1;
        if ($qAvg >= 90) $quizDistribution['90+']++;
        elseif ($qAvg >= 80) $quizDistribution['80–89']++;
        elseif ($qAvg >= 75) $quizDistribution['75–79']++;
        else $quizDistribution['<75']++;
    }
    if ($aAvg !== null) {
        $assignmentTotal += 1;
        if ($aAvg >= 90) $assignmentDistribution['90+']++;
        elseif ($aAvg >= 80) $assignmentDistribution['80–89']++;
        elseif ($aAvg >= 75) $assignmentDistribution['75–79']++;
        else $assignmentDistribution['<75']++;
    }
    if ($overall !== null) $allOverall[] = $overall;

    $rows[] = [
        'student' => $student,
        'assignment_avg' => $aAvg,
        'quiz_avg' => $qAvg,
        'overall' => $overall,
        'assignment_values' => $aValues,
        'quiz_values' => $qValues,
    ];
}

$classAverage = $allOverall ? round(array_sum($allOverall) / count($allOverall), 1) : null;
$quizAverage = [];
$assignmentAverage = [];
foreach ($rows as $row) {
    if ($row['quiz_avg'] !== null) $quizAverage[] = $row['quiz_avg'];
    if ($row['assignment_avg'] !== null) $assignmentAverage[] = $row['assignment_avg'];
}
$quizClassAverage = $quizAverage ? round(array_sum($quizAverage) / count($quizAverage), 1) : null;
$assignmentClassAverage = $assignmentAverage ? round(array_sum($assignmentAverage) / count($assignmentAverage), 1) : null;

$gradedQuizCells = 0;
$totalQuizCells = count($students) * count($quizzes);
$gradedAssignmentCells = 0;
$totalAssignmentCells = count($students) * count($assignments);
foreach ($rows as $row) {
    $gradedQuizCells += count(array_filter($row['quiz_values'], static fn($v) => $v !== null));
    $gradedAssignmentCells += count(array_filter($row['assignment_values'], static fn($v) => $v !== null));
}
$quizCompletion = $totalQuizCells ? round(($gradedQuizCells / $totalQuizCells) * 100) : 0;
$assignmentCompletion = $totalAssignmentCells ? round(($gradedAssignmentCells / $totalAssignmentCells) * 100) : 0;

$firstName = htmlspecialchars($teacher['firstname']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grade Book · SUA IntelliLearn</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/grade_book.css">
</head>
<body>
<?php include '../../includes/teachers_sidebar.php'; ?>

<main class="main-content" id="gradeBookMain">
    <?php include '../../includes/teacher_header.php'; ?>

    <div class="page-wrap">
        <section class="page-heading">
            <div>
                <p class="eyebrow">TEACHER ANALYTICS</p>
                <h1>Grade Book</h1>
                <p class="page-subtitle">View student performance across quizzes and assignments.</p>
            </div>
            <div class="teacher-chip"><i class="fas fa-user-tie"></i> <?= $firstName ?></div>
        </section>

        <?php if (!$classes): ?>
            <section class="empty-state">
                <i class="fas fa-chalkboard"></i>
                <h2>No classes available</h2>
                <p>You currently have no active classes assigned to you.</p>
            </section>
        <?php else: ?>
            <section class="classes-section">
                <div class="section-title-row">
                    <div>
                        <h2>Your Classes</h2>
                        <span><?= count($classes) ?> active class<?= count($classes) === 1 ? '' : 'es' ?></span>
                    </div>
                </div>
                <div class="class-grid">
                    <?php foreach ($classes as $class): ?>
                        <a class="class-card <?= (int)$class['offering_id'] === (int)$selectedOfferingId ? 'selected' : '' ?>" href="?offering_id=<?= (int)$class['offering_id'] ?>">
                            <div class="class-icon"><i class="fas fa-book-open"></i></div>
                            <div class="class-info">
                                <strong><?= htmlspecialchars($class['subject_name']) ?></strong>
                                <span><?= htmlspecialchars($class['section_name']) ?> · <?= htmlspecialchars($class['quarter']) ?></span>
                                <small>Grade <?= htmlspecialchars($class['grade_level']) ?><?= $class['strand'] ? ' · ' . htmlspecialchars($class['strand']) : '' ?></small>
                            </div>
                            <div class="class-count"><strong><?= (int)$class['student_count'] ?></strong><span>students</span></div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="selected-class-head">
                <div>
                    <span class="eyebrow">SELECTED CLASS</span>
                    <h2><?= htmlspecialchars($selectedClass['subject_name']) ?> <span>· <?= htmlspecialchars($selectedClass['section_name']) ?></span></h2>
                    <p><?= htmlspecialchars($selectedClass['quarter']) ?> · School Year <?= htmlspecialchars($selectedClass['school_year']) ?></p>
                </div>
                <div class="mobile-class-select">
                    <label for="classSelect">Switch class</label>
                    <select id="classSelect" onchange="if(this.value) window.location='?offering_id='+this.value">
                        <?php foreach ($classes as $class): ?>
                            <option value="<?= (int)$class['offering_id'] ?>" <?= (int)$class['offering_id'] === (int)$selectedOfferingId ? 'selected' : '' ?>>
                                <?= htmlspecialchars($class['subject_name'] . ' · ' . $class['section_name'] . ' · ' . $class['quarter']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </section>

            <section class="analytics-grid">
                <article class="metric-card primary"><span>Class Average</span><strong><?= $classAverage !== null ? number_format($classAverage, 1) . '%' : '—' ?></strong><small>Quizzes + assignments</small></article>
                <article class="metric-card"><span>Quiz Average</span><strong><?= $quizClassAverage !== null ? number_format($quizClassAverage, 1) . '%' : '—' ?></strong><small><?= count($quizzes) ?> quiz<?= count($quizzes) === 1 ? '' : 'zes' ?></small></article>
                <article class="metric-card"><span>Assignment Average</span><strong><?= $assignmentClassAverage !== null ? number_format($assignmentClassAverage, 1) . '%' : '—' ?></strong><small><?= count($assignments) ?> assignment<?= count($assignments) === 1 ? '' : 's' ?></small></article>
                <article class="metric-card"><span>Students</span><strong><?= count($students) ?></strong><small><?= $quizCompletion ?>% quiz grading coverage</small></article>
            </section>

            <section class="analytics-panels">
                <article class="panel">
                    <div class="panel-heading"><div><h3>Performance Overview</h3><p>Average percentage by assessment type.</p></div></div>
                    <div class="bar-chart">
                        <div class="bar-row"><span>Quizzes</span><div class="bar-track"><div class="bar-fill" style="width: <?= $quizClassAverage !== null ? min(100, max(0, $quizClassAverage)) : 0 ?>%"></div></div><strong><?= $quizClassAverage !== null ? number_format($quizClassAverage, 1) . '%' : '—' ?></strong></div>
                        <div class="bar-row"><span>Assignments</span><div class="bar-track"><div class="bar-fill secondary" style="width: <?= $assignmentClassAverage !== null ? min(100, max(0, $assignmentClassAverage)) : 0 ?>%"></div></div><strong><?= $assignmentClassAverage !== null ? number_format($assignmentClassAverage, 1) . '%' : '—' ?></strong></div>
                        <div class="coverage"><span>Assignment grading coverage</span><strong><?= $assignmentCompletion ?>%</strong></div>
                    </div>
                </article>
                <article class="panel">
                    <div class="panel-heading"><div><h3>Grade Distribution</h3><p>Students grouped by average score.</p></div></div>
                    <div class="distribution">
                        <?php foreach ($quizDistribution as $label => $count): ?>
                            <div class="distribution-row"><span>Quiz <?= htmlspecialchars($label) ?></span><div class="dist-track"><div style="width: <?= $quizTotal ? round(($count / $quizTotal) * 100) : 0 ?>%"></div></div><strong><?= $count ?></strong></div>
                        <?php endforeach; ?>
                    </div>
                </article>
            </section>

            <section class="gradebook-section">
                <div class="section-title-row">
                    <div><h2>Student Grades</h2><span>Latest quiz attempt and latest assignment submission are used.</span></div>
                </div>
                <div class="table-wrap">
                    <table class="grade-table">
                        <thead>
                            <tr>
                                <th class="student-col">Student</th>
                                <th>Quizzes</th>
                                <th>Assignments</th>
                                <th>Overall</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($rows as $row): $s = $row['student']; ?>
                            <tr>
                                <td class="student-cell">
                                    <div class="avatar"><?= strtoupper(substr($s['firstname'], 0, 1) . substr($s['lastname'], 0, 1)) ?></div>
                                    <div><strong><?= htmlspecialchars($s['lastname'] . ', ' . $s['firstname']) ?></strong><small><?= htmlspecialchars($s['student_lrn']) ?></small></div>
                                </td>
                                <td><span class="grade-pill <?= gradeClass($row['quiz_avg']) ?>"><?= $row['quiz_avg'] !== null ? number_format($row['quiz_avg'], 1) . '%' : 'N/A' ?></span></td>
                                <td><span class="grade-pill <?= gradeClass($row['assignment_avg']) ?>"><?= $row['assignment_avg'] !== null ? number_format($row['assignment_avg'], 1) . '%' : 'N/A' ?></span></td>
                                <td><strong class="overall-grade <?= gradeClass($row['overall']) ?>"><?= $row['overall'] !== null ? number_format($row['overall'], 1) . '%' : 'N/A' ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$rows): ?><tr><td colspan="4" class="no-data">No enrolled students in this class.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>
    </div>
</main>
</body>
</html>
