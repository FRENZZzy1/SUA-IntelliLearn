<?php
require_once __DIR__ . '/../../config/config.php';
if (!isLoggedIn() || ($_SESSION['role'] ?? '') !== 'teacher') {
    header('Location: ../login.php');
    exit();
}
$userId = (int) ($_SESSION['user_id'] ?? 0);
$stmt = $pdo->prepare("SELECT teacher_id,firstname,lastname FROM teachers WHERE user_id=? LIMIT 1");
$stmt->execute([$userId]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$teacher)
    die('Teacher record not found.');
$teacherId = (int) $teacher['teacher_id'];
$stmt = $pdo->prepare("SELECT co.offering_id,co.quarter,sub.subject_name,sec.section_name,sec.grade_level,sec.strand,sy.label school_year,COUNT(DISTINCT CASE WHEN e.status='active' THEN e.student_id END) student_count FROM classofferings co JOIN subjects sub ON sub.subject_id=co.subject_id JOIN sections sec ON sec.section_id=co.section_id JOIN schoolyears sy ON sy.school_year_id=co.school_year_id LEFT JOIN enrollments e ON e.offering_id=co.offering_id WHERE co.teacher_id=? AND co.status='active' GROUP BY co.offering_id,co.quarter,sub.subject_name,sec.section_name,sec.grade_level,sec.strand,sy.label ORDER BY sy.start_date DESC,sub.subject_name,sec.section_name,co.quarter");
$stmt->execute([$teacherId]);
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
$offeringId = (int) (filter_input(INPUT_GET, 'offering_id', FILTER_VALIDATE_INT) ?: 0);
$selectedClass = null;
foreach ($classes as $c)
    if ((int) $c['offering_id'] === $offeringId)
        $selectedClass = $c;
if (!$selectedClass && $classes) {
    $selectedClass = $classes[0];
    $offeringId = (int) $selectedClass['offering_id'];
}
$students = [];
$assignments = [];
$quizzes = [];
$aScores = [];
$qScores = [];
$finalized = [];
if ($offeringId) {
    $stmt = $pdo->prepare("SELECT e.enrollment_id,s.student_id,s.student_lrn,s.firstname,s.lastname,s.middlename FROM enrollments e JOIN students s ON s.student_id=e.student_id WHERE e.offering_id=? AND e.status='active' ORDER BY s.lastname,s.firstname");
    $stmt->execute([$offeringId]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $pdo->prepare("SELECT a.assignment_id,a.title,a.points,a.type,sub.student_id,sub.score FROM assignments a LEFT JOIN submissions sub ON sub.assignment_id=a.assignment_id AND sub.attempt_number=(SELECT MAX(x.attempt_number) FROM submissions x WHERE x.assignment_id=a.assignment_id AND x.student_id=sub.student_id) WHERE a.offering_id=? ORDER BY a.created_at,a.assignment_id");
    $stmt->execute([$offeringId]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $id = (int) $r['assignment_id'];
        if (!isset($assignments[$id]))
            $assignments[$id] = ['id' => $id, 'title' => $r['title'], 'points' => (float) $r['points'], 'type' => $r['type'] ?: 'Activity'];
        if ($r['student_id'] !== null)
            $aScores[(int) $r['student_id']][$id] = $r['score'] !== null ? (float) $r['score'] : null;
    }
    $assignments = array_values($assignments);
    $stmt = $pdo->prepare("SELECT q.quiz_id,q.title,qa.student_id,qa.score,qa.max_score FROM quizzes q LEFT JOIN quiz_attempts qa ON qa.quiz_id=q.quiz_id AND qa.attempt_number=(SELECT MAX(x.attempt_number) FROM quiz_attempts x WHERE x.quiz_id=q.quiz_id AND x.student_id=qa.student_id) WHERE q.offering_id=? ORDER BY q.created_at,q.quiz_id");
    $stmt->execute([$offeringId]);
    $qm = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $id = (int) $r['quiz_id'];
        if (!isset($qm[$id]))
            $qm[$id] = ['id' => $id, 'title' => $r['title']];
        if ($r['student_id'] !== null) {
            $max = (float) ($r['max_score'] ?? 0);
            $qScores[(int) $r['student_id']][$id] = ($r['score'] !== null && $max > 0) ? ((float) $r['score'] / $max) * 100 : null;
        }
    }
    $quizzes = array_values($qm);
    // Already-finalized official grades (grades.quarter='Final' = the one
    // computed, DepEd-weighted final grade of record for this offering/term).
    $stmt = $pdo->prepare("SELECT g.enrollment_id,g.grade,g.updated_at FROM grades g JOIN enrollments e ON e.enrollment_id=g.enrollment_id WHERE e.offering_id=? AND g.quarter='Final'");
    $stmt->execute([$offeringId]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r)
        $finalized[(int) $r['enrollment_id']] = ['grade' => (float) $r['grade'], 'updated_at' => $r['updated_at']];
}
function avgGrade($v)
{
    $v = array_values(array_filter($v, fn($x) => $x !== null));
    return $v ? round(array_sum($v) / count($v), 1) : null;
}
function gradeClass($v)
{
    if ($v === null)
        return 'grade-none';
    return $v >= 90 ? 'grade-excellent' : ($v >= 80 ? 'grade-good' : ($v >= 75 ? 'grade-fair' : 'grade-low'));
}
$rows = [];
$all = [];
$qa = [];
$aa = [];
$dist = ['90+' => 0, '80–89' => 0, '75–79' => 0, '<75' => 0];
foreach ($students as $s) {
    $sid = (int) $s['student_id'];
    $eid = (int) $s['enrollment_id'];
    $av = [];
    $ptv = [];
    $examv = [];
    $qv = [];
    foreach ($assignments as $a) {
        $x = $aScores[$sid][$a['id']] ?? null;
        $pct = $x !== null && $a['points'] > 0 ? ($x / $a['points']) * 100 : null;
        $av[] = $pct;
        if ($a['type'] === 'Exam')
            $examv[] = $pct;
        else
            $ptv[] = $pct;
    }
    foreach ($quizzes as $q)
        $qv[] = $qScores[$sid][$q['id']] ?? null;
    $a = avgGrade($av);
    $q = avgGrade($qv);
    $pt = avgGrade($ptv);
    $exam = avgGrade($examv);
    $o = avgGrade(array_filter([$a, $q], fn($x) => $x !== null));
    $computedFinal = computeWeightedFinalGrade($q, $pt, $exam);
    if ($a !== null)
        $aa[] = $a;
    if ($q !== null) {
        $qa[] = $q;
        $dist[$q >= 90 ? '90+' : ($q >= 80 ? '80–89' : ($q >= 75 ? '75–79' : '<75'))]++;
    }
    if ($o !== null)
        $all[] = $o;
    $rows[] = [
        'student' => $s,
        'a' => $a,
        'q' => $q,
        'o' => $o,
        'pt' => $pt,
        'exam' => $exam,
        'computed_final' => $computedFinal,
        'official' => $finalized[$eid]['grade'] ?? null,
        'official_at' => $finalized[$eid]['updated_at'] ?? null,
    ];
}
$classAvg = avgGrade($all);
$quizAvg = avgGrade($qa);
$assignmentAvg = avgGrade($aa);
$first = htmlspecialchars($teacher['firstname']);
$csrfToken = generateCSRFToken();
$flash = getFlashMessage();
?><!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Grade Book · SUA IntelliLearn</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/grade_book.css">
</head>

<body><?php include '../../includes/teachers_sidebar.php'; ?>
    <main class="main-content"><?php include '../../includes/teacher_header.php'; ?>
        <div class="page-wrap">
            <?php if ($flash): ?>
                <div class="flash-message flash-<?= htmlspecialchars($flash["type"]) ?>"><?= htmlspecialchars($flash["message"]) ?></div>
            <?php endif; ?>
            <section class="page-heading">
                <div>
                    <p class="eyebrow">TEACHER ANALYTICS</p>
                    <h1>Grade Book</h1>
                    <p class="page-subtitle">View class performance and drill down into every student's scores.</p>
                </div>
                <div class="teacher-chip"><i class="fas fa-user-tie"></i> <?= $first ?></div>
            </section>
            <?php if (!$classes): ?>
                <section class="empty-state"><i class="fas fa-chalkboard"></i>
                    <h2>No classes available</h2>
                    <p>You currently have no active classes assigned to you.</p>
                </section><?php else: ?>
                <section class="classes-section">
                    <div class="section-title-row">
                        <div>
                            <h2>Your Classes</h2><span><?= count($classes) ?> active
                                class<?= count($classes) == 1 ? '' : 'es' ?></span>
                        </div>
                    </div>
                    <div class="class-grid"><?php foreach ($classes as $c): ?><a
                                class="class-card <?= $c['offering_id'] == $offeringId ? 'selected' : '' ?>"
                                href="?offering_id=<?= $c['offering_id'] ?>">
                                <div class="class-icon"><i class="fas fa-book-open"></i></div>
                                <div class="class-info">
                                    <strong><?= htmlspecialchars($c['subject_name']) ?></strong><span><?= htmlspecialchars($c['section_name']) ?>
                                        · <?= htmlspecialchars($c['quarter']) ?></span><small>Grade
                                        <?= htmlspecialchars($c['grade_level']) ?>        <?= $c['strand'] ? ' · ' . htmlspecialchars($c['strand']) : '' ?></small>
                                </div>
                                <div class="class-count"><strong><?= $c['student_count'] ?></strong><span>students</span></div>
                            </a><?php endforeach; ?></div>
                </section>
                <section class="selected-class-head">
                    <div><span class="eyebrow">SELECTED CLASS</span>
                        <h2><?= htmlspecialchars($selectedClass['subject_name']) ?> <span>·
                                <?= htmlspecialchars($selectedClass['section_name']) ?></span></h2>
                        <p><?= htmlspecialchars($selectedClass['quarter']) ?> · School Year
                            <?= htmlspecialchars($selectedClass['school_year']) ?></p>
                    </div>
                    <div class="mobile-class-select"><label>Switch class</label><select
                            onchange="if(this.value)location='?offering_id='+this.value"><?php foreach ($classes as $c): ?>
                                <option value="<?= $c['offering_id'] ?>" <?= $c['offering_id'] == $offeringId ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['subject_name'] . ' · ' . $c['section_name'] . ' · ' . $c['quarter']) ?>
                                </option><?php endforeach; ?>
                        </select></div>
                </section>
                <section class="analytics-grid">
                    <article class="metric-card primary"><span>Class
                            Average</span><strong><?= $classAvg !== null ? $classAvg . '%' : '—' ?></strong><small>Quiz + assignment
                            averages</small></article>
                    <article class="metric-card"><span>Quiz
                            Average</span><strong><?= $quizAvg !== null ? $quizAvg . '%' : '—' ?></strong><small><?= count($quizzes) ?>
                            quizzes</small></article>
                    <article class="metric-card"><span>Assignment
                            Average</span><strong><?= $assignmentAvg !== null ? $assignmentAvg . '%' : '—' ?></strong><small><?= count($assignments) ?>
                            assignments</small></article>
                    <article class="metric-card"><span>Students</span><strong><?= count($students) ?></strong><small>Click a
                            student to view scores</small></article>
                </section>
                <section class="analytics-panels">
                    <article class="panel">
                        <div class="panel-heading">
                            <div>
                                <h3>Performance Overview</h3>
                                <p>Average percentage by assessment type.</p>
                            </div>
                        </div>
                        <div class="bar-chart">
                            <div class="bar-row"><span>Quizzes</span>
                                <div class="bar-track">
                                    <div class="bar-fill" style="width:<?= min(100, $quizAvg ?? 0) ?>%"></div>
                                </div><strong><?= $quizAvg !== null ? $quizAvg . '%' : '—' ?></strong>
                            </div>
                            <div class="bar-row"><span>Assignments</span>
                                <div class="bar-track">
                                    <div class="bar-fill secondary" style="width:<?= min(100, $assignmentAvg ?? 0) ?>%"></div>
                                </div><strong><?= $assignmentAvg !== null ? $assignmentAvg . '%' : '—' ?></strong>
                            </div>
                        </div>
                    </article>
                    <article class="panel">
                        <div class="panel-heading">
                            <div>
                                <h3>Quiz Distribution</h3>
                                <p>Students grouped by quiz average.</p>
                            </div>
                        </div><?php $den = max(1, count($qa));
                        foreach ($dist as $label => $count): ?>
                            <div class="distribution-row"><span><?= htmlspecialchars($label) ?></span>
                                <div class="dist-track">
                                    <div style="width:<?= round($count / $den * 100) ?>%"></div>
                                </div><strong><?= $count ?></strong>
                            </div><?php endforeach; ?>
                    </article>
                </section>
                <section class="gradebook-section">
                    <div class="section-title-row">
                        <div>
                            <h2>Student Grades</h2><span>Click <b>View Scores</b> to see every quiz and assignment
                                score.</span>
                        </div>
                    </div>
                    <div class="table-wrap">
                        <table class="grade-table">
                            <thead>
                                <tr>
                                    <th class="student-col">Student</th>
                                    <th>Quiz Average</th>
                                    <th>Assignment Average</th>
                                    <th>Overall</th>
                                    <th>Details</th>
                                </tr>
                            </thead>
                            <tbody><?php foreach ($rows as $r):
                                $s = $r['student']; ?>
                                    <tr>
                                        <td class="student-cell">
                                            <div class="avatar">
                                                <?= strtoupper(substr($s['firstname'], 0, 1) . substr($s['lastname'], 0, 1)) ?></div>
                                            <div>
                                                <strong><?= htmlspecialchars($s['lastname'] . ', ' . $s['firstname']) ?></strong><small><?= htmlspecialchars($s['student_lrn']) ?></small>
                                            </div>
                                        </td>
                                        <td><span
                                                class="grade-pill <?= gradeClass($r['q']) ?>"><?= $r['q'] !== null ? $r['q'] . '%' : 'N/A' ?></span>
                                        </td>
                                        <td><span
                                                class="grade-pill <?= gradeClass($r['a']) ?>"><?= $r['a'] !== null ? $r['a'] . '%' : 'N/A' ?></span>
                                        </td>
                                        <td><strong
                                                class="overall-grade <?= gradeClass($r['o']) ?>"><?= $r['o'] !== null ? $r['o'] . '%' : 'N/A' ?></strong>
                                        </td>
                                        <td><a class="view-scores"
                                                href="student_scores.php?offering_id=<?= $offeringId ?>&student_id=<?= $s['student_id'] ?>"><i
                                                    class="fas fa-list-check"></i> View Scores</a></td>
                                    </tr><?php endforeach; ?><?php if (!$rows): ?>
                                    <tr>
                                        <td colspan="5" class="no-data">No enrolled students in this class.</td>
                                    </tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
                <section class="gradebook-section final-grades-section">
                    <div class="section-title-row">
                        <div>
                            <h2>Final Grades</h2><span>Written Work (quizzes) · Performance Task (activities) · Exam,
                                weighted <?= (int) round(GRADE_COMPONENT_WEIGHTS['written_work'] * 100) ?>/<?= (int) round(GRADE_COMPONENT_WEIGHTS['performance_task'] * 100) ?>/<?= (int) round(GRADE_COMPONENT_WEIGHTS['exam'] * 100) ?>.</span>
                        </div>
                        <form action="assets/api/grade_finalize.php" method="POST" onsubmit="return confirm('Finalize grades for every active student in this class? This will overwrite any previously finalized grade for this term.');">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            <input type="hidden" name="offering_id" value="<?= (int) $offeringId ?>">
                            <button type="submit" class="btn-primary" <?= $rows ? '' : 'disabled' ?>><i class="fas fa-check-double"></i> Finalize Grades for This Class</button>
                        </form>
                    </div>
                    <div class="table-wrap">
                        <table class="grade-table">
                            <thead>
                                <tr>
                                    <th class="student-col">Student</th>
                                    <th>Written Work</th>
                                    <th>Performance Task</th>
                                    <th>Exam</th>
                                    <th>Computed Final</th>
                                    <th>Official Grade</th>
                                </tr>
                            </thead>
                            <tbody><?php foreach ($rows as $r):
                                $s = $r['student'];
                                $needsUpdate = $r['official'] !== null && $r['computed_final'] !== null && abs($r['official'] - $r['computed_final']) >= 0.01; ?>
                                    <tr>
                                        <td class="student-cell">
                                            <div class="avatar">
                                                <?= strtoupper(substr($s['firstname'], 0, 1) . substr($s['lastname'], 0, 1)) ?></div>
                                            <div>
                                                <strong><?= htmlspecialchars($s['lastname'] . ', ' . $s['firstname']) ?></strong><small><?= htmlspecialchars($s['student_lrn']) ?></small>
                                            </div>
                                        </td>
                                        <td><span
                                                class="grade-pill <?= gradeClass($r['q']) ?>"><?= $r['q'] !== null ? $r['q'] . '%' : 'N/A' ?></span>
                                        </td>
                                        <td><span
                                                class="grade-pill <?= gradeClass($r['pt']) ?>"><?= $r['pt'] !== null ? $r['pt'] . '%' : 'N/A' ?></span>
                                        </td>
                                        <td><span
                                                class="grade-pill <?= gradeClass($r['exam']) ?>"><?= $r['exam'] !== null ? $r['exam'] . '%' : 'N/A' ?></span>
                                        </td>
                                        <td><strong
                                                class="overall-grade <?= gradeClass($r['computed_final']) ?>"><?= $r['computed_final'] !== null ? $r['computed_final'] . '%' : 'N/A' ?></strong>
                                        </td>
                                        <td><?php if ($r['official'] !== null): ?><strong
                                                    class="overall-grade <?= gradeClass($r['official']) ?>"><?= $r['official'] ?>%</strong>
                                                <span class="finalize-status <?= $needsUpdate ? 'stale' : 'current' ?>"><?= $needsUpdate ? 'Needs re-finalizing' : 'Up to date' ?></span>
                                            <?php else: ?><span class="finalize-status pending">Not finalized</span><?php endif; ?>
                                        </td>
                                    </tr><?php endforeach; ?><?php if (!$rows): ?>
                                    <tr>
                                        <td colspan="6" class="no-data">No enrolled students in this class.</td>
                                    </tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section><?php endif; ?>
        </div>
    </main>
</body>

</html>