<?php
/**
 * Backend endpoint for the "View Students" modal in courses.php.
 * Called via fetch() — always returns JSON, never renders a page.
 * Read-only: lists the students currently enrolled in one classofferings row.
 */

require_once __DIR__ . '/../../../../config/config.php';

requireAdmin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'errors' => ['Invalid request method.']]);
    exit();
}

$offering_id = $_GET['offering_id'] ?? '';

if (!ctype_digit((string) $offering_id)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => ['Missing or invalid course reference.']]);
    exit();
}

// Pull the course header info (subject/section/teacher) so the modal can
// show a title even if the enrollment list ends up empty.
$courseStmt = $pdo->prepare("
    SELECT
        co.offering_id,
        co.capacity,
        s.subject_name,
        sec.section_name,
        sec.grade_level,
        sec.strand
    FROM classofferings co
    JOIN subjects s   ON s.subject_id = co.subject_id
    JOIN sections sec ON sec.section_id = co.section_id
    WHERE co.offering_id = ?
");
$courseStmt->execute([(int) $offering_id]);
$course = $courseStmt->fetch();

if (!$course) {
    http_response_code(404);
    echo json_encode(['success' => false, 'errors' => ['That course no longer exists. Please refresh the page.']]);
    exit();
}

$studentsStmt = $pdo->prepare("
    SELECT
        st.student_id,
        st.student_lrn,
        st.firstname,
        st.lastname,
        st.middlename,
        st.email,
        e.status,
        e.enrolled_at
    FROM enrollments e
    JOIN students st ON st.student_id = e.student_id
    WHERE e.offering_id = ?
    ORDER BY e.status = 'active' DESC, st.lastname ASC, st.firstname ASC
");
$studentsStmt->execute([(int) $offering_id]);
$students = $studentsStmt->fetchAll();

echo json_encode([
    'success' => true,
    'course'  => $course,
    'students' => $students,
]);