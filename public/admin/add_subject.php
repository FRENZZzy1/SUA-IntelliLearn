<?php
/**
 * Backend endpoint for the "New Subject" modal in courses.php.
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

$subject_name = trim($_POST['subject_name'] ?? '');
$description  = trim($_POST['description'] ?? '');

if ($subject_name === '') $errors[] = 'Please enter a subject name.';
if (strlen($subject_name) > 255) $errors[] = 'Subject name is too long.';

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit();
}

// Friendly duplicate check (no DB-level unique constraint on subject_name).
$dupStmt = $pdo->prepare("SELECT subject_id FROM subjects WHERE subject_name = ?");
$dupStmt->execute([$subject_name]);
if ($dupStmt->fetch()) {
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => ['A subject with that name already exists.']]);
    exit();
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO subjects (subject_name, description)
        VALUES (?, ?)
    ");
    $stmt->execute([
        $subject_name,
        $description !== '' ? $description : null,
    ]);

    setFlashMessage('success', 'Subject added successfully.');
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => ['Database error: ' . $e->getMessage()]]);
}
