<?php
/**
 * Backend endpoint for the "Set Term Interval" modal on courses.php.
 * Called via fetch() — always returns JSON.
 *
 * Saves one JSON blob (TRM 1/2/3 -> mode + month range or date range)
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

$errors    = [];
$termLabels = [1 => 'TRM 1', 2 => 'TRM 2', 3 => 'TRM 3'];
$intervals  = [];

foreach ($termLabels as $n => $label) {
    $mode = $_POST["term_{$n}_mode"] ?? 'month';

    if (!in_array($mode, ['month', 'date'], true)) {
        $errors[] = "$label: invalid mode.";
        continue;
    }

    $entry = ['mode' => $mode, 'start_month' => null, 'end_month' => null, 'start_date' => null, 'end_date' => null];

    if ($mode === 'month') {
        $startMonth = $_POST["term_{$n}_start_month"] ?? '';
        $endMonth   = $_POST["term_{$n}_end_month"] ?? '';

        if (!ctype_digit((string) $startMonth) || (int) $startMonth < 1 || (int) $startMonth > 12
            || !ctype_digit((string) $endMonth) || (int) $endMonth < 1 || (int) $endMonth > 12) {
            $errors[] = "$label: choose a start and end month.";
        } else {
            $entry['start_month'] = (int) $startMonth;
            $entry['end_month']   = (int) $endMonth;
        }
    } else {
        $startDate = trim($_POST["term_{$n}_start_date"] ?? '');
        $endDate   = trim($_POST["term_{$n}_end_date"] ?? '');

        $startOk = (bool) DateTime::createFromFormat('Y-m-d', $startDate);
        $endOk   = (bool) DateTime::createFromFormat('Y-m-d', $endDate);

        if (!$startOk || !$endOk) {
            $errors[] = "$label: choose a valid start and end date.";
        } elseif ($startDate > $endDate) {
            $errors[] = "$label: start date must be on or before the end date.";
        } else {
            $entry['start_date'] = $startDate;
            $entry['end_date']   = $endDate;
        }
    }

    $intervals[$label] = $entry;
}

// Guard against overlapping date-mode ranges. resolveCurrentTerm() walks
// TRM 1 -> TRM 2 -> TRM 3 in order and returns the *first* range that
// contains today, using inclusive start/end bounds on both sides. If two
// terms share so much as a single day (e.g. TRM 1 ends 09/04 and TRM 2
// starts 09/04), that shared day will always resolve to the earlier term
// and the later term can never become active on it. Reject that here so
// it can't be saved in the first place.
if (empty($errors)) {
    $dateTerms = array_filter($intervals, static fn($entry) => $entry['mode'] === 'date');
    $labels    = array_keys($dateTerms);

    for ($i = 0; $i < count($labels); $i++) {
        for ($j = $i + 1; $j < count($labels); $j++) {
            $a = $dateTerms[$labels[$i]];
            $b = $dateTerms[$labels[$j]];

            // Standard inclusive-range overlap test: a and b overlap
            // unless one ends before the other begins.
            if ($a['start_date'] <= $b['end_date'] && $b['start_date'] <= $a['end_date']) {
                $errors[] = "{$labels[$i]} and {$labels[$j]} overlap ({$a['start_date']}–{$a['end_date']} vs {$b['start_date']}–{$b['end_date']}). Terms must not share any dates — for example, end {$labels[$i]} the day before {$labels[$j]} starts.";
            }
        }
    }
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit();
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO system_settings (setting_key, setting_value, updated_by)
        VALUES ('term_intervals', ?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)
    ");
    $stmt->execute([json_encode($intervals), $_SESSION['user_id'] ?? null]);

    // Every class follows the single school-wide current term — push all
    // of them onto whatever term these new intervals resolve to today,
    // whether they were created just now or last school year.
    syncCourseTermsToCurrent($pdo);

    setFlashMessage('success', 'Term intervals saved.');
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => ['Database error: ' . $e->getMessage()]]);
}