<?php
/**
 * Backend endpoint for the "Add Course" modal in courses.php.
 * Called via fetch() — always returns JSON, never renders a page.
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

$subject_id = $_POST['subject_id'] ?? '';
$section_id = $_POST['section_id'] ?? '';
$teacher_id = $_POST['teacher_id'] ?? '';
$quarter    = $_POST['quarter'] ?? '';
$capacity   = $_POST['capacity'] ?? 50;
$status     = $_POST['status'] ?? 'active';

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

try {
    $stmt = $pdo->prepare("
        INSERT INTO classofferings (subject_id, teacher_id, section_id, quarter, capacity, status)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        (int) $subject_id,
        (int) $teacher_id,
        (int) $section_id,
        (int) $quarter,
        (int) $capacity,
        $status,
    ]);

    setFlashMessage('success', 'Course added successfully.');
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(422);
    if ($e->getCode() === '23000') {
        echo json_encode(['success' => false, 'errors' => ['That subject is already offered to this section for this quarter.']]);
    } else {
        echo json_encode(['success' => false, 'errors' => ['Database error: ' . $e->getMessage()]]);
    }
}
