<?php
/**
 * people_search.php?q=Rose  (student)
 *
 * Backs the student header's search bar. Lets a logged-in student find
 * TEACHERS and OTHER STUDENTS.
 *
 * SECURITY NOTES
 *  - Only the `teachers` and `students` tables are queried, joined to
 *    `users` with an explicit role check, so admins / the principal can
 *    never show up in results. Do not add an admin branch here.
 *  - Only ACTIVE accounts are searchable.
 *  - The searching student never appears in their own results.
 *  - Students are matched by NAME only (not LRN) and the response carries
 *    no LRN, birthdate, guardian, address or contact details.
 */

require_once __DIR__ . '/../../../../config/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!isLoggedIn() || ($_SESSION['role'] ?? '') !== 'student') {
    http_response_code(401);
    echo json_encode(['success' => false, 'errors' => ['Your session has expired. Please log in again.']]);
    exit();
}

$stmt = $pdo->prepare("SELECT student_id FROM students WHERE user_id = ? LIMIT 1");
$stmt->execute([$_SESSION['user_id']]);
$myStudentId = (int) $stmt->fetchColumn();
if (!$myStudentId) {
    http_response_code(403);
    echo json_encode(['success' => false, 'errors' => ['No student record linked to this account.']]);
    exit();
}

$q = trim((string) ($_GET['q'] ?? ''));
if (mb_strlen($q) < 2) {
    echo json_encode(['success' => true, 'teachers' => [], 'students' => []]);
    exit();
}
// Treat % and _ typed by the user as literal characters.
$like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';

// ---- Teachers ----------------------------------------------------------
$stmt = $pdo->prepare("
    SELECT t.teacher_id, t.firstname, t.lastname, t.department,
           (SELECT COUNT(*)
              FROM enrollments e
              JOIN classofferings co ON co.offering_id = e.offering_id
             WHERE e.student_id = :me AND e.status = 'enrolled'
               AND co.teacher_id = t.teacher_id) AS teaches_me
    FROM teachers t
    JOIN users u ON u.id = t.user_id AND u.role = 'teacher' AND u.status = 'active'
    WHERE CONCAT(t.firstname, ' ', t.lastname) LIKE :q1
       OR t.firstname LIKE :q2
       OR t.lastname  LIKE :q3
    ORDER BY teaches_me DESC, t.lastname, t.firstname
    LIMIT 5
");
$stmt->execute([':me' => $myStudentId, ':q1' => $like, ':q2' => $like, ':q3' => $like]);
$teachers = [];
foreach ($stmt->fetchAll() as $t) {
    $teachers[] = [
        'id'         => (int) $t['teacher_id'],
        'name'       => trim($t['firstname'] . ' ' . $t['lastname']),
        'department' => $t['department'],
        'is_mine'    => (int) $t['teaches_me'] > 0,
    ];
}

// ---- Other students ----------------------------------------------------
$stmt = $pdo->prepare("
    SELECT s.student_id, s.firstname, s.lastname, s.year_level,
           (SELECT sec.section_name FROM enrollments e2
              JOIN classofferings co2 ON co2.offering_id = e2.offering_id
              JOIN sections sec ON sec.section_id = co2.section_id
             WHERE e2.student_id = s.student_id AND e2.status = 'enrolled'
             ORDER BY e2.enrollment_id DESC LIMIT 1) AS section_name,
           (SELECT sec.grade_level FROM enrollments e2
              JOIN classofferings co2 ON co2.offering_id = e2.offering_id
              JOIN sections sec ON sec.section_id = co2.section_id
             WHERE e2.student_id = s.student_id AND e2.status = 'enrolled'
             ORDER BY e2.enrollment_id DESC LIMIT 1) AS grade_level,
           (SELECT COUNT(DISTINCT mine.offering_id)
              FROM enrollments mine
              JOIN enrollments theirs ON theirs.offering_id = mine.offering_id
             WHERE mine.student_id = :me2 AND mine.status = 'enrolled'
               AND theirs.student_id = s.student_id AND theirs.status = 'enrolled') AS shared_classes
    FROM students s
    JOIN users u ON u.id = s.user_id AND u.role = 'student' AND u.status = 'active'
    WHERE s.student_id <> :me
      AND ( CONCAT(s.firstname, ' ', s.lastname) LIKE :q1
         OR s.firstname LIKE :q2
         OR s.lastname  LIKE :q3 )
    ORDER BY shared_classes DESC, s.lastname, s.firstname
    LIMIT 6
");
$stmt->execute([':me' => $myStudentId, ':me2' => $myStudentId, ':q1' => $like, ':q2' => $like, ':q3' => $like]);
$students = [];
foreach ($stmt->fetchAll() as $s) {
    $grade = $s['grade_level'] !== null ? (int) $s['grade_level'] : (int) $s['year_level'];
    $students[] = [
        'id'        => (int) $s['student_id'],
        'name'      => trim($s['firstname'] . ' ' . $s['lastname']),
        'grade'     => $grade,
        'section'   => $s['section_name'],
        'classmate' => (int) $s['shared_classes'] > 0,
    ];
}

echo json_encode(['success' => true, 'teachers' => $teachers, 'students' => $students]);
exit();