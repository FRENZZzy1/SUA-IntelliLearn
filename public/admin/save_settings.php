<?php
/**
 * Backend endpoint for the General / Enrollment Rules / Branding forms
 * on settings.php. Called via fetch() — always returns JSON.
 *
 * Saves a fixed whitelist of keys into `system_settings` (key/value).
 * Checkboxes that are unchecked simply don't appear in $_POST, so
 * known boolean keys default to '0' when absent instead of being
 * skipped.
 */

require_once __DIR__ . '/../../config/config.php';

requireAdmin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'errors' => ['Invalid request method.']]);
    exit();
}

if (!validateCSRFToken($_POST['csrf'] ?? '')) {
    http_response_code(419);
    echo json_encode(['success' => false, 'errors' => ['Your session expired. Please refresh the page and try again.']]);
    exit();
}

$errors = [];

$schoolName        = trim($_POST['school_name'] ?? '');
$defaultCapacity   = $_POST['default_class_capacity'] ?? '';
$passingGrade      = $_POST['passing_grade'] ?? '';
$autoApprove       = isset($_POST['auto_approve_enrollment']) ? '1' : '0';
$enrollmentOpen    = isset($_POST['enrollment_open']) ? '1' : '0';

if ($schoolName === '' || mb_strlen($schoolName) > 150) {
    $errors[] = 'School name must be between 1 and 150 characters.';
}
if (!ctype_digit((string) $defaultCapacity) || (int) $defaultCapacity < 1 || (int) $defaultCapacity > 500) {
    $errors[] = 'Default class capacity must be a number between 1 and 500.';
}
if (!is_numeric($passingGrade) || (float) $passingGrade < 0 || (float) $passingGrade > 100) {
    $errors[] = 'Passing grade must be a number between 0 and 100.';
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit();
}

$values = [
    'school_name'             => $schoolName,
    'default_class_capacity'  => (string) (int) $defaultCapacity,
    'passing_grade'           => (string) (float) $passingGrade,
    'auto_approve_enrollment' => $autoApprove,
    'enrollment_open'         => $enrollmentOpen,
];

try {
    $stmt = $pdo->prepare("
        INSERT INTO system_settings (setting_key, setting_value, updated_by)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)
    ");

    $pdo->beginTransaction();
    foreach ($values as $key => $value) {
        $stmt->execute([$key, $value, $_SESSION['user_id'] ?? null]);
    }
    $pdo->commit();

    setFlashMessage('success', 'Settings saved successfully.');
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => ['Database error: ' . $e->getMessage()]]);
}
