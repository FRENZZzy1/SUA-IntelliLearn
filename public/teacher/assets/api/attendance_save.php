<?php
// This file is a direct POST target (the <form action="...">), so use
// __DIR__ rather than a cwd-relative path.
// Path: public/teacher/assets/api/ -> up 4 -> project root -> config/config.php
require_once __DIR__ . '/../../../../config/config.php';

// ---- Access control -------------------------------------------------
if (!isLoggedIn() || ($_SESSION['role'] ?? '') !== 'teacher') {
    header('Location: ../../login.php');
    exit();
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../courses.php');
    exit();
}

$userId = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT teacher_id FROM teachers WHERE user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$teacher = $stmt->fetch();
if (!$teacher) {
    die('Teacher record not found for this account.');
}
$teacherId = (int) $teacher['teacher_id'];

// ---- Params carried through so we can redirect back to the right place ----
$subjectId = filter_input(INPUT_POST, 'subject_id', FILTER_VALIDATE_INT);
$sectionId = filter_input(INPUT_POST, 'section_id', FILTER_VALIDATE_INT);
$term      = $_POST['term'] ?? null;
$date      = $_POST['attendance_date'] ?? date('Y-m-d');
if (DateTime::createFromFormat('Y-m-d', $date) === false) {
    $date = date('Y-m-d');
}
$backUrl = '../../class_overview.php?' . http_build_query([
    'subject_id' => $subjectId,
    'section_id' => $sectionId,
    'term'       => $term,
    'view'       => 'attendance',
    'date'       => $date,
]);

// ---- CSRF -----------------------------------------------------------
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    setFlashMessage('error', 'Your session expired. Please try again.');
    header('Location: ' . $backUrl);
    exit();
}

// ---- Validate the offering belongs to this teacher (authorization) --------
$offeringId = filter_input(INPUT_POST, 'offering_id', FILTER_VALIDATE_INT);
if (!$offeringId) {
    setFlashMessage('error', 'Missing or invalid class.');
    header('Location: ' . $backUrl);
    exit();
}
$stmt = $pdo->prepare("SELECT offering_id FROM classofferings WHERE offering_id = ? AND teacher_id = ? AND status = 'active' LIMIT 1");
$stmt->execute([$offeringId, $teacherId]);
if (!$stmt->fetch()) {
    setFlashMessage('error', 'You do not have access to that class.');
    header('Location: ../../courses.php');
    exit();
}

// ---- Only accept students actually enrolled in this offering --------------
$stmt = $pdo->prepare("SELECT student_id FROM enrollments WHERE offering_id = ? AND status = 'active'");
$stmt->execute([$offeringId]);
$validStudentIds = array_column($stmt->fetchAll(), 'student_id');
$validStudentIds = array_map('intval', $validStudentIds);

$allowedStatuses = ['Present', 'Absent', 'Late', 'Excused'];
$statusInput  = $_POST['status'] ?? [];   // [student_id => status]
$remarksInput = $_POST['remarks'] ?? [];  // [student_id => remarks]

if (!is_array($statusInput) || empty($statusInput)) {
    setFlashMessage('error', 'No attendance data submitted.');
    header('Location: ' . $backUrl);
    exit();
}

$selectStmt = $pdo->prepare("
    SELECT attendance_id FROM attendance
    WHERE student_id = ? AND offering_id = ? AND attendance_date = ?
    LIMIT 1
");
$updateStmt = $pdo->prepare("
    UPDATE attendance SET status = ?, remarks = ?, recorded_by = ?
    WHERE attendance_id = ?
");
$insertStmt = $pdo->prepare("
    INSERT INTO attendance (student_id, offering_id, attendance_date, status, remarks, recorded_by)
    VALUES (?, ?, ?, ?, ?, ?)
");

$saved = 0;
$pdo->beginTransaction();
try {
    foreach ($statusInput as $studentId => $status) {
        $studentId = (int) $studentId;
        if (!in_array($studentId, $validStudentIds, true)) {
            continue; // not enrolled in this offering — ignore
        }
        if (!in_array($status, $allowedStatuses, true)) {
            continue; // left unmarked / invalid — skip, don't record a blank status
        }
        $remarks = trim($remarksInput[$studentId] ?? '');
        $remarks = $remarks !== '' ? mb_substr($remarks, 0, 255) : null;

        $selectStmt->execute([$studentId, $offeringId, $date]);
        $existing = $selectStmt->fetch();

        if ($existing) {
            $updateStmt->execute([$status, $remarks, $teacherId, $existing['attendance_id']]);
        } else {
            $insertStmt->execute([$studentId, $offeringId, $date, $status, $remarks, $teacherId]);
        }
        $saved++;
    }
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    setFlashMessage('error', 'Something went wrong while saving attendance. Please try again.');
    header('Location: ' . $backUrl);
    exit();
}

setFlashMessage('success', "Attendance saved for $saved student(s).");
header('Location: ' . $backUrl);
exit();