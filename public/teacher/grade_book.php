<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/assets/api/gradebook_status_functions.php';
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
$stmt = $pdo->prepare("SELECT co.offering_id,co.quarter,co.subject_id,co.section_id,co.school_year_id,sy.is_current school_year_is_current,sub.subject_name,sec.section_name,sec.grade_level,sy.label school_year,COUNT(DISTINCT CASE WHEN e.status='enrolled' THEN e.student_id END) student_count FROM classofferings co JOIN subjects sub ON sub.subject_id=co.subject_id JOIN sections sec ON sec.section_id=co.section_id JOIN schoolyears sy ON sy.school_year_id=co.school_year_id LEFT JOIN enrollments e ON e.offering_id=co.offering_id WHERE co.section_id NOT IN (SELECT section_id FROM sections WHERE is_archived = 1) AND co.teacher_id=? AND co.status='active' GROUP BY co.offering_id,co.quarter,co.subject_id,co.section_id,co.school_year_id,sy.is_current,sub.subject_name,sec.section_name,sec.grade_level,sy.label ORDER BY sy.start_date DESC,sub.subject_name,sec.section_name,co.quarter");
$stmt->execute([$teacherId]);
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
$offeringId = (int) (filter_input(INPUT_GET, 'offering_id', FILTER_VALIDATE_INT) ?: 0);
$selectedClass = null;
foreach ($classes as $c)
    if ((int) $c['offering_id'] === $offeringId)
        $selectedClass = $c;
// ---- Group offerings into CLASSES (subject + section). Each class has one
// offering per term; the term picker switches between them. ----------------
$currentTerm = resolveCurrentTerm(getTermIntervals($pdo)); // e.g. 'TRM 2' or null
$groups = [];
foreach ($classes as $c) {
    $gk = $c['subject_id'] . '-' . $c['section_id'];
    if (!isset($groups[$gk]))
        $groups[$gk] = ['info' => $c, 'terms' => []];
    $c['term_no'] = (int) substr($c['quarter'], -1);
    $groups[$gk]['terms'][] = $c;
}
foreach ($groups as &$g) {
    // Oldest -> newest (school year, then term) so the picker reads Term 1, 2, 3.
    usort($g['terms'], fn($a, $b) => [$a['school_year_id'], $a['term_no']] <=> [$b['school_year_id'], $b['term_no']]);
    // Default term: the one running now in the current school year; else the newest.
    $g['primary'] = end($g['terms']);
    foreach ($g['terms'] as $t) {
        if ($t['school_year_is_current'] && $currentTerm !== null && $t['quarter'] === $currentTerm) {
            $g['primary'] = $t;
            break;
        }
    }
    $g['multi_year'] = count(array_unique(array_column($g['terms'], 'school_year_id'))) > 1;
}
unset($g);
if (!$selectedClass && $groups) {
    $selectedClass = reset($groups)['primary'];
    $offeringId = (int) $selectedClass['offering_id'];
}
$selectedGroup = null;
if ($selectedClass)
    $selectedGroup = $groups[$selectedClass['subject_id'] . '-' . $selectedClass['section_id']] ?? null;
$students = [];
$items = ['ww' => [], 'pt' => [], 'exam' => []];
$done = [];
if ($offeringId) {
    $stmt = $pdo->prepare("SELECT e.enrollment_id,s.student_id,s.student_lrn,s.firstname,s.lastname,s.middlename FROM enrollments e JOIN students s ON s.student_id=e.student_id WHERE e.offering_id=? AND e.status='enrolled' ORDER BY s.lastname,s.firstname");
    $stmt->execute([$offeringId]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $loaded = gb_load_items($pdo, $offeringId);
    $items = $loaded['items'];
    $done = $loaded['done'];
}
$rows = [];
$completeCount = ['ww' => 0, 'pt' => 0, 'exam' => 0];
foreach ($students as $s) {
    $sid = (int) $s['student_id'];
    $st = [];
    foreach (GB_CATEGORIES as $key => $label) {
        $st[$key] = gb_category_status($items[$key], $done[$sid] ?? []);
        if ($st[$key] === 'complete')
            $completeCount[$key]++;
    }
    $rows[] = ['student' => $s, 'status' => $st];
}
$first = htmlspecialchars($teacher['firstname']);
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
                            <h2>Your Classes</h2><span><?= count($groups) ?> active
                                class<?= count($groups) == 1 ? '' : 'es' ?></span>
                        </div>
                    </div>
                    <div class="class-grid"><?php foreach ($groups as $gk => $g):
                        $isSel = $selectedGroup && $selectedClass['subject_id'] == $g['info']['subject_id'] && $selectedClass['section_id'] == $g['info']['section_id'];
                        $target = $isSel ? $selectedClass : $g['primary'];
                    ?><a class="class-card <?= $isSel ? 'selected' : '' ?>"
                                href="?offering_id=<?= $target['offering_id'] ?>" <?= $isSel ? 'aria-current="true"' : '' ?>>
                                <div class="class-icon"><i class="fas fa-book-open"></i></div>
                                <div class="class-info">
                                    <strong><?= htmlspecialchars($g['info']['subject_name']) ?></strong><span><?= htmlspecialchars($g['info']['section_name']) ?></span><small>Grade
                                        <?= htmlspecialchars($g['info']['grade_level']) ?>
                                        · <?= count($g['terms']) ?> term<?= count($g['terms']) == 1 ? '' : 's' ?></small>
                                </div>
                                <div class="class-count"><strong><?= $target['student_count'] ?></strong><span>students</span></div>
                            </a><?php endforeach; ?></div>
                </section>
                <section class="selected-class-head">
                    <div><span class="eyebrow">SELECTED CLASS</span>
                        <h2><?= htmlspecialchars($selectedClass['subject_name']) ?> <span>·
                                <?= htmlspecialchars($selectedClass['section_name']) ?></span></h2>
                        <p>Term <?= (int) substr($selectedClass['quarter'], -1) ?> · School Year
                            <?= htmlspecialchars($selectedClass['school_year']) ?></p>
                    </div>
                    <div class="selected-class-controls">
                        <div class="term-switch" role="tablist" aria-label="Term">
                            <?php foreach ($selectedGroup['terms'] as $t): $on = (int) $t['offering_id'] === $offeringId; ?>
                                <a class="term-seg <?= $on ? 'active' : '' ?>" role="tab" aria-selected="<?= $on ? 'true' : 'false' ?>"
                                    href="?offering_id=<?= $t['offering_id'] ?>">
                                    Term <?= $t['term_no'] ?>
                                    <?php if ($selectedGroup['multi_year']): ?><small><?= htmlspecialchars($t['school_year']) ?></small><?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                        <a class="btn-export<?= $rows ? '' : ' disabled' ?>" href="assets/api/export_gradebook.php?offering_id=<?= (int) $offeringId ?>" <?= $rows ? '' : 'aria-disabled="true" tabindex="-1"' ?>><i class="fas fa-file-excel"></i> Export Gradebook</a>
                        <div class="mobile-class-select"><label>Switch class</label><select
                                onchange="if(this.value)location='?offering_id='+this.value"><?php foreach ($groups as $g):
                                    $isSel = $selectedClass['subject_id'] == $g['info']['subject_id'] && $selectedClass['section_id'] == $g['info']['section_id'];
                                    $target = $isSel ? $selectedClass : $g['primary']; ?>
                                    <option value="<?= $target['offering_id'] ?>" <?= $isSel ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($g['info']['subject_name'] . ' · ' . $g['info']['section_name']) ?>
                                    </option><?php endforeach; ?>
                            </select></div>
                    </div>
                </section>
                <section class="analytics-grid">
                    <article class="metric-card primary"><span>Students</span><strong><?= count($students) ?></strong><small>Enrolled in this class</small></article>
                    <?php foreach (GB_CATEGORIES as $key => $label): ?>
                        <article class="metric-card"><span><?= htmlspecialchars($label) ?></span><strong><?= $completeCount[$key] ?> / <?= count($students) ?></strong><small><?= count($items[$key]) ?> item<?= count($items[$key]) == 1 ? '' : 's' ?> · students complete</small></article>
                    <?php endforeach; ?>
                </section>
                <section class="gradebook-section">
                    <div class="section-title-row">
                        <div>
                            <h2>Student Grades</h2><span>Click <b>View Scores</b> to see every quiz and activity and whether it was submitted.</span>
                        </div>
                    </div>
                    <div class="table-wrap">
                        <table class="grade-table">
                            <thead>
                                <tr>
                                    <th class="student-col">Student</th>
                                    <th>Written Works</th>
                                    <th>Performance Tasks</th>
                                    <th>Exam</th>
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
                                        <?php foreach (GB_CATEGORIES as $key => $label): ?>
                                            <td><span class="status-pill status-<?= $r['status'][$key] ?>"><?= gb_status_label($r['status'][$key]) ?></span></td>
                                        <?php endforeach; ?>
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
                </section><?php endif; ?>
        </div>
    </main>
</body>

</html>