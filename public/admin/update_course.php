<?php
/**
 * Backend endpoint for the "Update Course" modal in courses.php.
 * Called via fetch() — always returns JSON, never renders a page.
 * Mirrors add_course.php but UPDATEs an existing classofferings row
 * instead of inserting a new one.
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

$offering_id   = $_POST['offering_id'] ?? '';
$subject_id    = $_POST['subject_id'] ?? '';
$section_id    = $_POST['section_id'] ?? '';
$teacher_id    = $_POST['teacher_id'] ?? '';
$quarter       = $_POST['quarter'] ?? '';
$school_year_id = $_POST['school_year_id'] ?? '';
$capacity      = $_POST['capacity'] ?? 50;
$status        = $_POST['status'] ?? 'active';
$schedule_days  = trim($_POST['schedule_days'] ?? '');
$start_time_raw = trim($_POST['start_time'] ?? '');
$end_time_raw   = trim($_POST['end_time'] ?? '');

if (!ctype_digit((string) $offering_id)) $errors[] = 'Missing or invalid course reference.';
if (!ctype_digit((string) $subject_id)) $errors[] = 'Please choose a subject.';
if (!ctype_digit((string) $section_id)) $errors[] = 'Please choose a section.';
if (!ctype_digit((string) $teacher_id)) $errors[] = 'Please choose a teacher.';
if (!in_array((string) $quarter, ['1', '2', '3', '4'], true)) $errors[] = 'Please choose a quarter.';
if (!ctype_digit((string) $school_year_id)) $errors[] = 'Please choose a school year.';
if (!ctype_digit((string) $capacity) || (int) $capacity < 1) $errors[] = 'Capacity must be a positive number.';
if (!in_array($status, ['active', 'inactive'], true)) $errors[] = 'Invalid status.';
if ($schedule_days !== '' && mb_strlen($schedule_days) > 20) $errors[] = 'Schedule days must be 20 characters or fewer.';

// Accept freely typed times like "7:00 AM", "07:00", "7am" and normalize
// them to HH:MM:SS for storage in the TIME column.
$start_time = null;
if ($start_time_raw !== '') {
    $ts = strtotime($start_time_raw);
    if ($ts === false) {
        $errors[] = 'Could not understand the start time. Try formats like 7:00 AM or 07:00.';
    } else {
        $start_time = date('H:i:s', $ts);
    }
}

$end_time = null;
if ($end_time_raw !== '') {
    $ts = strtotime($end_time_raw);
    if ($ts === false) {
        $errors[] = 'Could not understand the end time. Try formats like 10:00 AM or 22:00.';
    } else {
        $end_time = date('H:i:s', $ts);
    }
}

if ($start_time !== null && $end_time !== null && $start_time >= $end_time) $errors[] = 'End time must be after start time.';

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit();
}

// Confirm the course actually exists before attempting the update.
$check = $pdo->prepare("SELECT offering_id FROM classofferings WHERE offering_id = ?");
$check->execute([(int) $offering_id]);
if (!$check->fetch()) {
    http_response_code(404);
    echo json_encode(['success' => false, 'errors' => ['That course no longer exists. Please refresh the page.']]);
    exit();
}

try {
    $stmt = $pdo->prepare("
        UPDATE classofferings
        SET subject_id = ?, teacher_id = ?, section_id = ?, quarter = ?, school_year_id = ?, schedule_days = ?, start_time = ?, end_time = ?, capacity = ?, status = ?
        WHERE offering_id = ?
    ");
    $stmt->execute([
        (int) $subject_id,
        (int) $teacher_id,
        (int) $section_id,
        (int) $quarter,
        (int) $school_year_id,
        $schedule_days !== '' ? $schedule_days : null,
        $start_time,
        $end_time,
        (int) $capacity,
        $status,
        (int) $offering_id,
    ]);

    setFlashMessage('success', 'Course updated successfully.');
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(422);
    if ($e->getCode() === '23000') {
        echo json_encode(['success' => false, 'errors' => ['That subject is already offered to this section for this quarter and school year.']]);
    } else {
        echo json_encode(['success' => false, 'errors' => ['Database error: ' . $e->getMessage()]]);
    }
}