<?php
/**
 * Backend endpoint for the "Set Term Interval" modal on courses.php.
 * Called via fetch() — always returns JSON.
 *
 * Saves one JSON blob (TRM 1/2/3 -> start/end date range)
 * into system_settings.setting_value under key 'term_intervals'. Classes
 * no longer pick a term directly — add_course.php derives it from these
 * intervals against today's date (see resolveCurrentTerm() in config.php).
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

$year = $pdo->query("SELECT school_year_id, start_date, end_date FROM schoolyears WHERE is_current = 1 LIMIT 1")->fetch();
if (!$year) {
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => ['There is no current school year. Set one in Settings > School Year & Terms first.']]);
    exit();
}

[$intervals, $errors] = parseTermIntervalsInput($_POST, $year['start_date'], $year['end_date'], false);

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit();
}

try {
    saveSchoolYearTermIntervals($pdo, (int) $year['school_year_id'], $intervals);

    setFlashMessage('success', 'Term intervals saved.');
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => ['Database error: ' . $e->getMessage()]]);
}
