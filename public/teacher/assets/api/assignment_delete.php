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

$subjectId = filter_input(INPUT_POST, 'subject_id', FILTER_VALIDATE_INT);
$sectionId = filter_input(INPUT_POST, 'section_id', FILTER_VALIDATE_INT);
$term      = $_POST['term'] ?? null;
$backUrl   = '../../class_overview.php?' . http_build_query([
    'subject_id' => $subjectId,
    'section_id' => $sectionId,
    'term'       => $term,
    'view'       => 'assignments',
]);

// ---- CSRF -----------------------------------------------------------
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    setFlashMessage('error', 'Your session expired. Please try again.');
    header('Location: ' . $backUrl);
    exit();
}

$assignmentId = filter_input(INPUT_POST, 'assignment_id', FILTER_VALIDATE_INT);
$offeringId   = filter_input(INPUT_POST, 'offering_id', FILTER_VALIDATE_INT);
if (!$assignmentId || !$offeringId) {
    setFlashMessage('error', 'Invalid request.');
    header('Location: ' . $backUrl);
    exit();
}

// ---- Verify the assignment belongs to an offering owned by this teacher -----
$stmt = $pdo->prepare("
    SELECT a.assignment_id, a.instructions_file_path
    FROM assignments a
    JOIN classofferings co ON co.offering_id = a.offering_id
    WHERE a.assignment_id = ? AND a.offering_id = ? AND co.teacher_id = ?
    LIMIT 1
");
$stmt->execute([$assignmentId, $offeringId, $teacherId]);
$assignment = $stmt->fetch();

if (!$assignment) {
    setFlashMessage('error', 'Assignment not found or you do not have access to it.');
    header('Location: ' . $backUrl);
    exit();
}

// ---- Delete the DB row (submissions cascade via FK), then the attachment on disk (if any) ----
$stmt = $pdo->prepare("DELETE FROM assignments WHERE assignment_id = ?");
$stmt->execute([$assignmentId]);

if (!empty($assignment['instructions_file_path'])) {
    $fullPath = __DIR__ . '/../../' . $assignment['instructions_file_path'];
    if (is_file($fullPath)) {
        @unlink($fullPath);
    }
}

setFlashMessage('success', 'Assignment removed.');
header('Location: ' . $backUrl);
exit();
