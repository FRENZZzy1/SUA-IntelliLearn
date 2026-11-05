<?php
/**
 * Backend endpoint for the "Edit" action on a school year in settings.php.
 * Called via fetch() — always returns JSON.
 *
 * Lets an admin correct a school year's label and start / end dates. The
 * current / archived state is not touched here (Set as Current, Archive and
 * Restore have their own endpoints). Classes and sections point at the year by
 * id, so renaming it or moving its dates never detaches them.
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

$schoolYearId = $_POST['school_year_id'] ?? '';
$label        = trim($_POST['label'] ?? '');
$startDate    = trim($_POST['start_date'] ?? '');
$endDate      = trim($_POST['end_date'] ?? '');

if (!ctype_digit((string) $schoolYearId)) {
    $errors[] = 'Invalid school year.';
}

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

$schoolYearId = (int) $schoolYearId;
$newStart     = date('Y-m-d', $startTs);
$newEnd       = date('Y-m-d', $endTs);

try {
    $stmt = $pdo->prepare("SELECT school_year_id, is_archived, auto_archive_paused FROM schoolyears WHERE school_year_id = ?");
    $stmt->execute([$schoolYearId]);
    $year = $stmt->fetch();
    if (!$year) {
        http_response_code(404);
        echo json_encode(['success' => false, 'errors' => ['School year not found.']]);
        exit();
    }

    $pdo->prepare("UPDATE schoolyears SET label = ?, start_date = ?, end_date = ? WHERE school_year_id = ?")
        ->execute([$label, $newStart, $newEnd, $schoolYearId]);

    $message = 'School year updated.';
    // A year whose (new) end date has already passed is archived automatically.
    if ((int) $year['is_archived'] === 0 && (int) $year['auto_archive_paused'] === 0 && $newEnd < date('Y-m-d')) {
        $message .= ' Its end date has already passed, so it will be archived automatically.';
    }
    setFlashMessage('success', $message);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(422);
    if ($e->getCode() === '23000') {
        echo json_encode(['success' => false, 'errors' => ['That school year label already exists.']]);
    } else {
        echo json_encode(['success' => false, 'errors' => ['Database error: ' . $e->getMessage()]]);
    }
}
