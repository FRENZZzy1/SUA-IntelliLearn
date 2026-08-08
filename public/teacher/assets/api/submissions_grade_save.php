<?php
// This file is a direct POST target (the <form action="...">), so PHP's
// relative-path resolution for require/include is NOT reliable here — it
// depends on the calling script's cwd, not this file's location. __DIR__
// always points at this file's real folder on disk, so use that instead.
// Path: public/teacher/assets/api/ -> up 4 levels -> project root -> config/config.php
require_once __DIR__ . '/../../../../config/config.php';

// ---- Access control -------------------------------------------------
if (!isLoggedIn() || ($_SESSION['role'] ?? '') !== 'teacher') {
    header('Location: ../../login.php');
    exit();
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../courses.php');
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

$subjectId    = filter_input(INPUT_POST, 'subject_id', FILTER_VALIDATE_INT);
$sectionId    = filter_input(INPUT_POST, 'section_id', FILTER_VALIDATE_INT);
$term         = $_POST['term'] ?? null;
$assignmentId = filter_input(INPUT_POST, 'assignment_id', FILTER_VALIDATE_INT);
$offeringId   = filter_input(INPUT_POST, 'offering_id', FILTER_VALIDATE_INT);
$backUrl      = '../../class_overview.php?' . http_build_query([
    'subject_id'    => $subjectId,
    'section_id'    => $sectionId,
    'term'          => $term,
    'view'          => 'assignments',
    'assignment_id' => $assignmentId,
]);

// ---- CSRF -----------------------------------------------------------
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    setFlashMessage('error', 'Your session expired. Please try again.');
    header('Location: ' . $backUrl);
    exit();
}

if (!$assignmentId || !$offeringId) {
    setFlashMessage('error', 'Invalid request.');
    header('Location: ' . $backUrl);
    exit();
}

// ---- Verify the assignment belongs to an offering owned by this teacher -----
$stmt = $pdo->prepare("
    SELECT a.assignment_id, a.points
    FROM assignments a
    JOIN classofferings co ON co.offering_id = a.offering_id
    WHERE a.assignment_id = ? AND a.offering_id = ? AND co.teacher_id = ?
    LIMIT 1
");
$stmt->execute([$assignmentId, $offeringId, $teacherId]);
$assignment = $stmt->fetch();

if (!$assignment) {
    setFlashMessage('error', 'Assignment not found or you do not have access to it.');
    header('Location: ../../courses.php');
    exit();
}
$maxPoints = (float) $assignment['points'];

// ---- Only students actually enrolled in this offering can be graded --------
$stmt = $pdo->prepare("
    SELECT s.student_id
    FROM enrollments e
    JOIN students s ON s.student_id = e.student_id
    WHERE e.offering_id = ? AND e.status = 'active'
");
$stmt->execute([$offeringId]);
$validStudentIds = array_map('intval', array_column($stmt->fetchAll(), 'student_id'));

$scores    = $_POST['score'] ?? [];
$feedbacks = $_POST['feedback'] ?? [];

$upsert = $pdo->prepare("
    INSERT INTO submissions (assignment_id, student_id, attempt_number, status, score, feedback, graded_by, graded_at, submitted_at)
    VALUES (?, ?, 1, 'graded', ?, ?, ?, NOW(), NOW())
    ON DUPLICATE KEY UPDATE
        score = VALUES(score),
        feedback = VALUES(feedback),
        status = 'graded',
        graded_by = VALUES(graded_by),
        graded_at = NOW()
");

$savedCount = 0;
foreach ($scores as $studentId => $rawScore) {
    $studentId = (int) $studentId;
    if (!in_array($studentId, $validStudentIds, true)) {
        continue; // not enrolled here — ignore, don't trust client-side field names
    }

    $rawScore = trim((string) $rawScore);
    if ($rawScore === '') {
        continue; // blank score = leave this student's grade untouched
    }

    $score = filter_var($rawScore, FILTER_VALIDATE_FLOAT);
    if ($score === false || $score < 0) {
        continue; // skip invalid input rather than failing the whole batch
    }
    if ($score > $maxPoints) {
        $score = $maxPoints;
    }
    $score = round($score, 2);

    $feedback = trim((string) ($feedbacks[$studentId] ?? ''));
    $feedback = $feedback === '' ? null : mb_substr($feedback, 0, 2000);

    $upsert->execute([$assignmentId, $studentId, $score, $feedback, $teacherId]);
    $savedCount++;
}

if ($savedCount > 0) {
    setFlashMessage('success', "Saved grades for {$savedCount} student(s).");
} else {
    setFlashMessage('error', 'No grades were entered.');
}
header('Location: ' . $backUrl);
exit();
