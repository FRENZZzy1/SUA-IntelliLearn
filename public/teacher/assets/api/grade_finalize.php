<?php
// Path: public/teacher/assets/api/ -> up 4 levels -> project root -> config/config.php
require_once __DIR__ . '/../../../../config/config.php';

// ---- Access control -------------------------------------------------
if (!isLoggedIn() || ($_SESSION['role'] ?? '') !== 'teacher') {
    header('Location: ../../login.php');
    exit();
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../grade_book.php');
    exit();
}

$userId = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT teacher_id FROM teachers WHERE user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$teacher = $stmt->fetch();
if (!$teacher) {
    die('Teacher record not found for this account.');
}
$teacherId = (int) $teacher['teacher_id'];

$offeringId = filter_input(INPUT_POST, 'offering_id', FILTER_VALIDATE_INT);
$backUrl = '../../grade_book.php?offering_id=' . (int) $offeringId;

// ---- CSRF -----------------------------------------------------------
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    setFlashMessage('error', 'Your session expired. Please try again.');
    header('Location: ' . $backUrl);
    exit();
}

// ---- Validate the offering belongs to this teacher (authorization) --------
if (!$offeringId) {
    setFlashMessage('error', 'Missing or invalid class.');
    header('Location: ../../grade_book.php');
    exit();
}
$stmt = $pdo->prepare("SELECT offering_id FROM classofferings WHERE offering_id = ? AND teacher_id = ? AND status = 'active' LIMIT 1");
$stmt->execute([$offeringId, $teacherId]);
if (!$stmt->fetch()) {
    setFlashMessage('error', 'You do not have access to that class.');
    header('Location: ../../grade_book.php');
    exit();
}

// ---- Active students enrolled in this offering ------------------------
$stmt = $pdo->prepare("SELECT enrollment_id, student_id FROM enrollments WHERE offering_id = ? AND status = 'active'");
$stmt->execute([$offeringId]);
$enrollments = $stmt->fetchAll();
if (!$enrollments) {
    setFlashMessage('error', 'No enrolled students to finalize grades for.');
    header('Location: ' . $backUrl);
    exit();
}

function avgGrade($v) {
    $v = array_values(array_filter($v, fn($x) => $x !== null));
    return $v ? round(array_sum($v) / count($v), 1) : null;
}

// ---- Written Work: every quiz's latest attempt, as a percentage -----------
$stmt = $pdo->prepare("
    SELECT qa.student_id, qa.score, qa.max_score
    FROM quizzes q
    JOIN quiz_attempts qa ON qa.quiz_id = q.quiz_id
        AND qa.attempt_number = (
            SELECT MAX(x.attempt_number) FROM quiz_attempts x
            WHERE x.quiz_id = q.quiz_id AND x.student_id = qa.student_id
        )
    WHERE q.offering_id = ?
");
$stmt->execute([$offeringId]);
$quizPercents = [];
foreach ($stmt->fetchAll() as $r) {
    $max = (float) ($r['max_score'] ?? 0);
    if ($r['score'] !== null && $max > 0) {
        $quizPercents[(int) $r['student_id']][] = ((float) $r['score'] / $max) * 100;
    }
}

// ---- Performance Task (Activity) and Exam: every assignment's latest submission ----
$stmt = $pdo->prepare("
    SELECT a.type, a.points, sub.student_id, sub.score
    FROM assignments a
    LEFT JOIN submissions sub ON sub.assignment_id = a.assignment_id
        AND sub.attempt_number = (
            SELECT MAX(x.attempt_number) FROM submissions x
            WHERE x.assignment_id = a.assignment_id AND x.student_id = sub.student_id
        )
    WHERE a.offering_id = ?
");
$stmt->execute([$offeringId]);
$ptPercents = [];
$examPercents = [];
foreach ($stmt->fetchAll() as $r) {
    if ($r['student_id'] === null || $r['score'] === null) continue;
    $points = (float) $r['points'];
    if ($points <= 0) continue;
    $pct = ((float) $r['score'] / $points) * 100;
    if (($r['type'] ?? 'Activity') === 'Exam') {
        $examPercents[(int) $r['student_id']][] = $pct;
    } else {
        $ptPercents[(int) $r['student_id']][] = $pct;
    }
}

// ---- Compute and upsert one final grade per enrollment ---------------------
$upsert = $pdo->prepare("
    INSERT INTO grades (enrollment_id, quarter, grade, graded_by)
    VALUES (?, 'Final', ?, ?)
    ON DUPLICATE KEY UPDATE grade = VALUES(grade), graded_by = VALUES(graded_by)
");

$finalizedCount = 0;
$skippedCount = 0;
try {
    $pdo->beginTransaction();
    foreach ($enrollments as $e) {
        $sid = (int) $e['student_id'];
        $ww = avgGrade($quizPercents[$sid] ?? []);
        $pt = avgGrade($ptPercents[$sid] ?? []);
        $exam = avgGrade($examPercents[$sid] ?? []);
        $final = computeWeightedFinalGrade($ww, $pt, $exam);
        if ($final === null) {
            // No graded work at all yet for this student — nothing to finalize.
            $skippedCount++;
            continue;
        }
        $upsert->execute([(int) $e['enrollment_id'], $final, $teacherId]);
        $finalizedCount++;
    }
    $pdo->commit();
} catch (PDOException $ex) {
    $pdo->rollBack();
    setFlashMessage('error', 'Could not finalize grades. Please try again.');
    header('Location: ' . $backUrl);
    exit();
}

if ($finalizedCount > 0) {
    $msg = "Finalized grades for $finalizedCount student" . ($finalizedCount === 1 ? '' : 's') . '.';
    if ($skippedCount > 0) {
        $msg .= " Skipped $skippedCount with no graded work yet.";
    }
    setFlashMessage('success', $msg);
} else {
    setFlashMessage('error', 'No students had graded work to finalize yet.');
}
header('Location: ' . $backUrl);
exit();
