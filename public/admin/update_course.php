<?php
/**
 * Backend endpoint for the "Update Course" modal in courses.php.
 * Called via fetch() — always returns JSON, never renders a page.
 * Mirrors add_course.php but UPDATEs an existing classofferings row
 * instead of inserting a new one.
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

$offering_id = $_POST['offering_id'] ?? '';
$subject_id  = $_POST['subject_id'] ?? '';
$section_id  = $_POST['section_id'] ?? '';
$teacher_id  = $_POST['teacher_id'] ?? '';
$quarter     = $_POST['quarter'] ?? '';
$capacity    = $_POST['capacity'] ?? 50;
$status      = $_POST['status'] ?? 'active';

if (!ctype_digit((string) $offering_id)) $errors[] = 'Missing or invalid course reference.';
if (!ctype_digit((string) $subject_id)) $errors[] = 'Please choose a subject.';
if (!ctype_digit((string) $section_id)) $errors[] = 'Please choose a section.';
if (!ctype_digit((string) $teacher_id)) $errors[] = 'Please choose a teacher.';
if (!in_array((string) $quarter, ['1', '2', '3', '4'], true)) $errors[] = 'Please choose a quarter.';
if (!ctype_digit((string) $capacity) || (int) $capacity < 1) $errors[] = 'Capacity must be a positive number.';
if (!in_array($status, ['active', 'inactive'], true)) $errors[] = 'Invalid status.';

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit();
}

// Confirm the course actually exists before attempting the update.
$check = $pdo->prepare("SELECT offering_id FROM classofferings WHERE offering_id = ?");
$check->execute([(int) $offering_id]);
if (!$check->fetch()) {
    http_response_code(404);
    echo json_encode(['success' => false, 'errors' => ['That course no longer exists. Please refresh the page.']]);
    exit();
}

try {
    $stmt = $pdo->prepare("
        UPDATE classofferings
        SET subject_id = ?, teacher_id = ?, section_id = ?, quarter = ?, capacity = ?, status = ?
        WHERE offering_id = ?
    ");
    $stmt->execute([
        (int) $subject_id,
        (int) $teacher_id,
        (int) $section_id,
        (int) $quarter,
        (int) $capacity,
        $status,
        (int) $offering_id,
    ]);

    setFlashMessage('success', 'Course updated successfully.');
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(422);
    if ($e->getCode() === '23000') {
        echo json_encode(['success' => false, 'errors' => ['That subject is already offered to this section for this quarter.']]);
    } else {
        echo json_encode(['success' => false, 'errors' => ['Database error: ' . $e->getMessage()]]);
    }
}
