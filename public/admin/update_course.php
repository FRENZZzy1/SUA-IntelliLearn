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
$school_year_id = $_POST['school_year_id'] ?? '';
$capacity      = $_POST['capacity'] ?? 50;
$status        = $_POST['status'] ?? 'active';
$schedule_days_raw = $_POST['schedule_days'] ?? ''; 
$start_time_raw = trim($_POST['start_time'] ?? '');
$end_time_raw   = trim($_POST['end_time'] ?? '');

if (!ctype_digit((string) $offering_id)) $errors[] = 'Missing or invalid course reference.';
if (!ctype_digit((string) $subject_id)) $errors[] = 'Please choose a subject.';
if (!ctype_digit((string) $section_id)) $errors[] = 'Please choose a section.';
if (!ctype_digit((string) $teacher_id)) $errors[] = 'Please choose a teacher.';
if (!ctype_digit((string) $school_year_id)) $errors[] = 'Please choose a school year.';
if (!ctype_digit((string) $capacity) || (int) $capacity < 1) $errors[] = 'Capacity must be a positive number.';
if (!in_array($status, ['active', 'inactive'], true)) $errors[] = 'Invalid status.';
$schedule_days_raw = $_POST['schedule_days'] ?? '';
$schedule_days_raw = is_string($schedule_days_raw) ? trim($schedule_days_raw) : '';
$allowedScheduleDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
$schedule_days = null;
if ($schedule_days_raw !== '') {
    $requestedDays = array_values(array_unique(array_map('trim', explode(',', $schedule_days_raw))));
    if (count(array_diff($requestedDays, $allowedScheduleDays)) > 0) {
        $errors[] = 'Choose schedule days using the provided weekday buttons.';
    } else {
        $schedule_days = implode(', ', array_values(array_intersect($allowedScheduleDays, $requestedDays)));
    }
}

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
$check = $pdo->prepare("SELECT offering_id, subject_id, section_id FROM classofferings WHERE offering_id = ?");
$check->execute([(int) $offering_id]);
$existingOffering = $check->fetch();
if (!$existingOffering) {
    http_response_code(404);
    echo json_encode(['success' => false, 'errors' => ['That course no longer exists. Please refresh the page.']]);
    exit();
}

// Moving a class onto an archived subject / section isn't allowed (keeping its current one is fine).
$archErrors = [];
if ((int) $subject_id !== (int) $existingOffering['subject_id']) {
    $q = $pdo->prepare("SELECT is_archived FROM subjects WHERE subject_id = ?");
    $q->execute([(int) $subject_id]);
    if ((int) $q->fetchColumn() === 1) $archErrors[] = 'That subject is archived. Restore it first.';
}
if ((int) $section_id !== (int) $existingOffering['section_id']) {
    $q = $pdo->prepare("SELECT is_archived FROM sections WHERE section_id = ?");
    $q->execute([(int) $section_id]);
    if ((int) $q->fetchColumn() === 1) $archErrors[] = 'That section is archived. Restore it first.';
}
if ($archErrors) {
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => $archErrors]);
    exit();
}

try {
    // quarter is intentionally left out of this UPDATE — it's not a
    // per-class field. It's kept in sync school-wide by
    // syncCourseTermsToCurrent(), called whenever term intervals are
    // saved and whenever courses.php loads.
    $stmt = $pdo->prepare("
        UPDATE classofferings
        SET subject_id = ?, teacher_id = ?, section_id = ?, school_year_id = ?, schedule_days = ?, start_time = ?, end_time = ?, capacity = ?, status = ?
        WHERE offering_id = ?
    ");
    $stmt->execute([
        (int) $subject_id,
        (int) $teacher_id,
        (int) $section_id,
        (int) $school_year_id,
        $schedule_days,
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