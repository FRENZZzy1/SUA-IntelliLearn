<?php
/**
 * Backend endpoint for the "Enroll Student" modal in enrollment.php.
 * Creates a new pending enrollment request. Called via fetch() —
 * always returns JSON, never renders a page.
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

$student_id  = $_POST['student_id'] ?? '';
$grade_level = $_POST['grade_level'] ?? '';
$subject_id  = $_POST['subject_id'] ?? '';
$strand      = trim($_POST['strand'] ?? '');
$offering_id = $_POST['offering_id'] ?? '';

if (!ctype_digit((string) $student_id)) $errors[] = 'Please choose a student.';
if (!in_array((string) $grade_level, ['7', '8', '9', '10', '11', '12'], true)) $errors[] = 'Please choose a grade level.';
if (!ctype_digit((string) $subject_id)) $errors[] = 'Please choose a course/subject.';
if ($strand !== '' && !in_array($strand, ['STEM', 'ABM', 'HUMSS', 'TVL'], true)) $errors[] = 'Invalid strand.';
if (!ctype_digit((string) $offering_id)) $errors[] = 'Please choose a section.';

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit();
}

$student_id  = (int) $student_id;
$grade_level = (int) $grade_level;
$subject_id  = (int) $subject_id;
$offering_id = (int) $offering_id;

// Re-validate the chosen section server-side: it must actually be an
// active offering for this subject + grade (+ strand) with an open seat,
// in case the form was tampered with or the class filled up meanwhile.
$sectionSql = "
    SELECT co.offering_id, co.capacity,
        (SELECT COUNT(*) FROM enrollments e WHERE e.offering_id = co.offering_id AND e.status = 'active') AS enrolled_count
    FROM classofferings co
    JOIN sections sec ON sec.section_id = co.section_id
    WHERE co.offering_id = ? AND co.subject_id = ? AND sec.grade_level = ? AND co.status = 'active'
";
$sectionParams = [$offering_id, $subject_id, $grade_level];
if ($strand !== '') {
    $sectionSql .= " AND sec.strand = ?";
    $sectionParams[] = $strand;
}

$sectionStmt = $pdo->prepare($sectionSql);
$sectionStmt->execute($sectionParams);
$offering = $sectionStmt->fetch();

if (!$offering) {
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => ['That section no longer matches this request. Please refresh and pick again.']]);
    exit();
}

if ((int) $offering['enrolled_count'] >= (int) $offering['capacity']) {
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => ['That section just filled up. Please choose another section.']]);
    exit();
}

// Don't create a duplicate pending request for the same student+subject+grade.
$dupStmt = $pdo->prepare("
    SELECT COUNT(*) FROM enrollment_requests
    WHERE student_id = ? AND subject_id = ? AND grade_level = ? AND status = 'pending'
");
$dupStmt->execute([$student_id, $subject_id, $grade_level]);

if ((int) $dupStmt->fetchColumn() > 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => ['This student already has a pending request for that course at this grade level.']]);
    exit();
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO enrollment_requests (student_id, grade_level, subject_id, strand, offering_id, status)
        VALUES (?, ?, ?, ?, ?, 'pending')
    ");
    $stmt->execute([
        $student_id,
        $grade_level,
        $subject_id,
        $strand !== '' ? $strand : null,
        $offering_id,
    ]);

    setFlashMessage('success', 'Enrollment request submitted.');
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => ['Database error: ' . $e->getMessage()]]);
}