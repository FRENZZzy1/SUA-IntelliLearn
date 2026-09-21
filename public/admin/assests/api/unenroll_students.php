<?php
/**
 * Backend endpoint for the "Unenroll" action in the View Students modal (courses.php).
 * Called via fetch() — always returns JSON, never renders a page.
 *
 * A "class" here is one subject + section + school year, which has one
 * classofferings row per term (TRM 1 / TRM 2 / TRM 3). Unenrolling a student
 * from any of those rows drops them from ALL of the class's term offerings, so
 * they lose access to the whole class (courses, materials, assignments,
 * grades, attendance, calendar), not just the term the admin happened to open.
 *
 * Unenrolling marks the enrollments as 'dropped' instead of deleting the rows,
 * so the student's grades (which cascade off enrollments) are preserved and
 * every teacher/student query that filters on status = 'active' stops
 * showing them in the class right away.
 * If the student is enrolled again later, the dropped rows are re-activated
 * (see approve_enrollment.php / add_enrollment_request.php).
 *
 * POST: offering_id, student_ids[] (one or many), csrf
 */

require_once __DIR__ . '/../../../../config/config.php';

requireAdmin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'errors' => ['Invalid request method.']]);
    exit();
}

if (!validateCSRFToken($_POST['csrf'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'errors' => ['Your session expired. Please refresh the page and try again.']]);
    exit();
}

$offering_id = $_POST['offering_id'] ?? '';
$rawIds      = $_POST['student_ids'] ?? [];

if (!ctype_digit((string) $offering_id)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => ['Missing or invalid course reference.']]);
    exit();
}

if (!is_array($rawIds)) {
    $rawIds = [$rawIds];
}

$studentIds = [];
foreach ($rawIds as $id) {
    if (ctype_digit((string) $id)) {
        $studentIds[(int) $id] = (int) $id;
    }
}
$studentIds = array_values($studentIds);

if (empty($studentIds)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => ['Select at least one student to unenroll.']]);
    exit();
}

try {
    $placeholders = implode(',', array_fill(0, count($studentIds), '?'));

    $pdo->beginTransaction();

    // The offering the admin opened.
    $stmt = $pdo->prepare("
        UPDATE enrollments
        SET status = 'dropped'
        WHERE offering_id = ?
          AND status = 'active'
          AND student_id IN ($placeholders)
    ");
    $stmt->execute(array_merge([(int) $offering_id], $studentIds));
    $unenrolled = $stmt->rowCount();

    // Every other term offering of the same class (same subject + section +
    // school year).
    $stmt = $pdo->prepare("
        UPDATE enrollments e
        JOIN classofferings co     ON co.offering_id = e.offering_id
        JOIN classofferings target ON target.offering_id = ?
        SET e.status = 'dropped'
        WHERE e.status = 'active'
          AND e.student_id IN ($placeholders)
          AND co.subject_id     = target.subject_id
          AND co.section_id     = target.section_id
          AND co.school_year_id = target.school_year_id
    ");
    $stmt->execute(array_merge([(int) $offering_id], $studentIds));

    $pdo->commit();

    echo json_encode([
        'success'   => true,
        'unenrolled' => $unenrolled,
        'student_ids' => $studentIds,
    ]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'errors' => ['Database error: ' . $e->getMessage()]]);
}