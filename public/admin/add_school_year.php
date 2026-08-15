<?php
/**
 * Backend endpoint for the "Add School Year" form on settings.php.
 * Called via fetch() — always returns JSON.
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

$label      = trim($_POST['label'] ?? '');
$startDate  = trim($_POST['start_date'] ?? '');
$endDate    = trim($_POST['end_date'] ?? '');
$makeCurrent = isset($_POST['make_current']);

if ($label === '' || mb_strlen($label) > 9) {
    $errors[] = 'Label must be 1-9 characters (e.g. "SY 2027").';
}

$startTs = strtotime($startDate);
$endTs   = strtotime($endDate);

if ($startTs === false) $errors[] = 'Please enter a valid start date.';
if ($endTs === false) $errors[] = 'Please enter a valid end date.';
if ($startTs !== false && $endTs !== false && $startTs >= $endTs) {
    $errors[] = 'End date must be after the start date.';
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit();
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("INSERT INTO schoolyears (label, start_date, end_date, is_current) VALUES (?, ?, ?, 0)");
    $stmt->execute([$label, date('Y-m-d', $startTs), date('Y-m-d', $endTs)]);

    if ($makeCurrent) {
        $pdo->prepare("UPDATE schoolyears SET is_current = 0")->execute();
        $newId = (int) $pdo->lastInsertId();
        $pdo->prepare("UPDATE schoolyears SET is_current = 1 WHERE school_year_id = ?")->execute([$newId]);
    }

    $pdo->commit();

    setFlashMessage('success', 'School year added successfully.');
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(422);
    if ($e->getCode() === '23000') {
        echo json_encode(['success' => false, 'errors' => ['That school year label already exists.']]);
    } else {
        echo json_encode(['success' => false, 'errors' => ['Database error: ' . $e->getMessage()]]);
    }
}
