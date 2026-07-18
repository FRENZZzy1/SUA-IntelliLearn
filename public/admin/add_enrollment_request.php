<?php
/**
 * Backend endpoint for the "Enroll Student" modal in enrollment.php.
 * Creates a new pending enrollment request. Called via fetch() —
 * always returns JSON, never renders a page.
 */

require_once __DIR__ . '/../../config/config.php';

requireAdmin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'errors' => ['Invalid request method.']]);
    exit();
}

$errors = [];

if (!validateCSRFToken($_POST['csrf'] ?? '')) {
    $errors[] = 'Your session expired. Please refresh the page and try again.';
}

$student_id  = $_POST['student_id'] ?? '';
$grade_level = $_POST['grade_level'] ?? '';
$subject_id  = $_POST['subject_id'] ?? '';
$strand      = trim($_POST['strand'] ?? '');

if (!ctype_digit((string) $student_id)) $errors[] = 'Please choose a student.';
if (!in_array((string) $grade_level, ['7', '8', '9', '10', '11', '12'], true)) $errors[] = 'Please choose a grade level.';
if (!ctype_digit((string) $subject_id)) $errors[] = 'Please choose a course/subject.';
if ($strand !== '' && !in_array($strand, ['STEM', 'ABM', 'HUMSS', 'TVL'], true)) $errors[] = 'Invalid strand.';

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit();
}

// Don't create a duplicate pending request for the same student+subject+grade.
$dupStmt = $pdo->prepare("
    SELECT COUNT(*) FROM enrollment_requests
    WHERE student_id = ? AND subject_id = ? AND grade_level = ? AND status = 'pending'
");
$dupStmt->execute([(int) $student_id, (int) $subject_id, (int) $grade_level]);

if ((int) $dupStmt->fetchColumn() > 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => ['This student already has a pending request for that course at this grade level.']]);
    exit();
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO enrollment_requests (student_id, grade_level, subject_id, strand, status)
        VALUES (?, ?, ?, ?, 'pending')
    ");
    $stmt->execute([
        (int) $student_id,
        (int) $grade_level,
        (int) $subject_id,
        $strand !== '' ? $strand : null,
    ]);

    setFlashMessage('success', 'Enrollment request submitted.');
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => ['Database error: ' . $e->getMessage()]]);
}
