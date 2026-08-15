<?php
/**
 * Backend endpoint for the "Set as Current" action on settings.php.
 * Called via fetch() — always returns JSON.
 *
 * Only one school year may be current at a time, so this clears the
 * flag on every row before setting it on the chosen one.
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

$schoolYearId = $_POST['school_year_id'] ?? '';

if (!ctype_digit((string) $schoolYearId)) {
    echo json_encode(['success' => false, 'errors' => ['Invalid school year.']]);
    exit();
}
$schoolYearId = (int) $schoolYearId;

$stmt = $pdo->prepare("SELECT school_year_id FROM schoolyears WHERE school_year_id = ?");
$stmt->execute([$schoolYearId]);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'errors' => ['School year not found.']]);
    exit();
}

try {
    $pdo->beginTransaction();
    $pdo->prepare("UPDATE schoolyears SET is_current = 0")->execute();
    $pdo->prepare("UPDATE schoolyears SET is_current = 1 WHERE school_year_id = ?")->execute([$schoolYearId]);
    $pdo->commit();

    setFlashMessage('success', 'Active school year updated.');
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => ['Database error: ' . $e->getMessage()]]);
}
