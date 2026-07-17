<?php
/**
 * Backend endpoint for the "Update Subject" modal in courses.php.
 * Called via fetch() — always returns JSON, never renders a page.
 * Mirrors add_subject.php but UPDATEs an existing subjects row instead
 * of inserting a new one.
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

$subject_id   = $_POST['subject_id'] ?? '';
$subject_name = trim($_POST['subject_name'] ?? '');
$description  = trim($_POST['description'] ?? '');

if (!ctype_digit((string) $subject_id)) $errors[] = 'Missing or invalid subject reference.';
if ($subject_name === '') $errors[] = 'Please enter a subject name.';
if (strlen($subject_name) > 255) $errors[] = 'Subject name is too long.';

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit();
}

// Confirm the subject actually exists before attempting the update.
$check = $pdo->prepare("SELECT subject_id FROM subjects WHERE subject_id = ?");
$check->execute([(int) $subject_id]);
if (!$check->fetch()) {
    http_response_code(404);
    echo json_encode(['success' => false, 'errors' => ['That subject no longer exists. Please refresh the page.']]);
    exit();
}

// Friendly duplicate check (no DB-level unique constraint on subject_name).
$dupStmt = $pdo->prepare("SELECT subject_id FROM subjects WHERE subject_name = ? AND subject_id != ?");
$dupStmt->execute([$subject_name, (int) $subject_id]);
if ($dupStmt->fetch()) {
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => ['A subject with that name already exists.']]);
    exit();
}

try {
    $stmt = $pdo->prepare("
        UPDATE subjects
        SET subject_name = ?, description = ?
        WHERE subject_id = ?
    ");
    $stmt->execute([
        $subject_name,
        $description !== '' ? $description : null,
        (int) $subject_id,
    ]);

    setFlashMessage('success', 'Subject updated successfully.');
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => ['Database error: ' . $e->getMessage()]]);
}
