<?php
/**
 * Backend endpoint for the "Archive" / "Restore" buttons on a school year in
 * settings.php. Called via fetch() - always returns JSON.
 *
 * Archiving a school year hides all of its classes and sections from students
 * and teachers. Nothing is deleted; Restore brings back exactly what archiving
 * hid. A year is archived automatically once its end date has passed; Restore
 * pauses that for the restored year and this Archive button archives it again.
 * The logic lives in archiveSchoolYear() / restoreSchoolYear() /
 * autoArchiveEndedSchoolYears() in config.php.
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
$action       = $_POST['action'] ?? '';

if ($action === 'restore') {
    // The Restore button was removed: a year only comes back by being set as current.
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => ['Restoring a school year is no longer available. Set it as current instead.']]);
    exit();
}

if (!ctype_digit((string) $schoolYearId) || !in_array($action, ['archive'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => ['Invalid request.']]);
    exit();
}
$schoolYearId = (int) $schoolYearId;

try {
    $labelStmt = $pdo->prepare("SELECT label FROM schoolyears WHERE school_year_id = ?");
    $labelStmt->execute([$schoolYearId]);
    $label = trim((string) $labelStmt->fetchColumn());

    if ($action === 'archive') {
        $counts  = archiveSchoolYear($pdo, $schoolYearId);
        $message = sprintf(
            '%s archived: %d class(es) and %d section(s) are now hidden. Nothing was deleted.',
            $label, $counts['classes'], $counts['sections']
        );
    } else {
        $counts  = restoreSchoolYear($pdo, $schoolYearId);
        $message = sprintf(
            '%s restored: %d class(es) and %d section(s) are visible again. It will not be archived automatically again; use Archive when you want to archive it.',
            $label, $counts['classes'], $counts['sections']
        );
    }

    setFlashMessage('success', $message);
    echo json_encode(['success' => true]);
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => [$e->getMessage()]]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'errors' => ['Database error: ' . $e->getMessage()]]);
}
