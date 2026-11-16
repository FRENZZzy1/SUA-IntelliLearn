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

// Optional: start the new year with the sections and classes of an earlier year
// (no students). See copySchoolYearStructure() in config.php.
$copyFromId = null;
$copyFromRaw = trim($_POST['copy_from'] ?? '');
if ($copyFromRaw !== '') {
    if (ctype_digit($copyFromRaw)) {
        $copyFromId = (int) $copyFromRaw;
    } else {
        $errors[] = 'Invalid school year to copy from.';
    }
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

// A new school year can't start in the past, and it can't reuse any dates that an
// existing school year (current, past or archived) already covers.
if ($startTs !== false && $endTs !== false && $startTs < $endTs) {
    $newStart = date('Y-m-d', $startTs);
    $newEnd   = date('Y-m-d', $endTs);
    $today    = date('Y-m-d');

    if ($newStart < $today) {
        $errors[] = 'The start date has already passed (' . date('M j, Y', $startTs) . '). Please choose today or a future date.';
    }

    $overlapStmt = $pdo->prepare(
        "SELECT label, start_date, end_date FROM schoolyears
         WHERE start_date <= ? AND end_date >= ?
         ORDER BY start_date"
    );
    $overlapStmt->execute([$newEnd, $newStart]);
    foreach ($overlapStmt->fetchAll() as $existing) {
        $errors[] = sprintf(
            'These dates are not allowed: they overlap with %s (%s to %s). A new school year must start after the previous school year ends.',
            htmlspecialchars($existing['label'], ENT_QUOTES, 'UTF-8'),
            date('M j, Y', strtotime($existing['start_date'])),
            date('M j, Y', strtotime($existing['end_date']))
        );
    }
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
    $newId = (int) $pdo->lastInsertId();

    if ($makeCurrent) {
        $pdo->prepare("UPDATE schoolyears SET is_current = 0")->execute();
        $pdo->prepare("UPDATE schoolyears SET is_current = 1 WHERE school_year_id = ?")->execute([$newId]);
    }

    $copyStats = null;
    $copyLabel = '';
    if ($copyFromId !== null) {
        $labelStmt = $pdo->prepare("SELECT label FROM schoolyears WHERE school_year_id = ?");
        $labelStmt->execute([$copyFromId]);
        $copyLabel = trim((string) $labelStmt->fetchColumn());
        try {
            $copyStats = copySchoolYearStructure($pdo, $copyFromId, $newId);
        } catch (PDOException $e) {
            throw new RuntimeException('Could not copy sections and classes: ' . $e->getMessage());
        }
    }

    $pdo->commit();

    $message = 'School year added successfully.';
    if ($copyStats !== null) {
        $message = sprintf(
            'School year added. Copied %d section(s) and %d class(es) from %s as TRM 1 classes only; no students were carried over. TRM 2 and TRM 3 are created automatically once the year is current and its term dates (Classes & Subjects > Set Term Interval) reach them.',
            $copyStats['sections_created'] + $copyStats['sections_reused'],
            $copyStats['classes_created'],
            $copyLabel
        );

        // A year that has not started yet must not show its classes to admins,
        // teachers or students, and its class codes must not accept enrollments.
        // Same mechanism as "Set as Current" (which archives years that have not
        // started): archive the new year so its copied classes and sections are
        // hidden. autoAdvanceCurrentSchoolYear() restores them automatically once
        // the year has started and the previous year has ended, or an admin can
        // use "Set as Current". Skipped when the admin made the new year current.
        if (!$makeCurrent && date('Y-m-d', $startTs) > date('Y-m-d')) {
            try {
                archiveSchoolYear($pdo, $newId);
                $message .= sprintf(
                    ' Because this school year starts on %s, its classes stay hidden (it is archived) until then and are restored automatically when it starts.',
                    date('M j, Y', $startTs)
                );
            } catch (Throwable $e) {
                $message .= ' Warning: the copied classes could not be hidden until the start date. Please archive this school year from the list.';
            }
        }
    }
    setFlashMessage('success', $message);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(422);
    if ($e->getCode() === '23000') {
        echo json_encode(['success' => false, 'errors' => ['That school year label already exists.']]);
    } else {
        echo json_encode(['success' => false, 'errors' => ['Database error: ' . $e->getMessage()]]);
    }
} catch (Throwable $e) {
    // Copy problems (missing source year, class-code generation, ...): nothing was saved.
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => [$e->getMessage()]]);
}
