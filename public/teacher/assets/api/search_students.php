<?php
/**
 * assests/api/search_students.php?q=Rose
 *
 * Teacher-scoped student search backing the teacher header's search bar.
 *
 * SECURITY NOTE: this intentionally does NOT reuse the admin
 * search_entities.php `type=student` branch, because that one searches
 * the entire student body. A teacher must only ever be able to find
 * students who are actively enrolled in one of THEIR OWN classes —
 * the WHERE co.teacher_id = ? below is what enforces that boundary.
 * Do not remove it / do not add a "search all students" fallback here.
 */

require_once __DIR__ . '/../../../../config/config.php';

header('Content-Type: application/json');

// ---- Auth guard: teachers only -----------------------------------------
if (empty($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(401);
    echo json_encode(['success' => false, 'errors' => ['Your session has expired. Please log in again.']]);
    exit();
}

$stmt = $pdo->prepare("SELECT teacher_id FROM teachers WHERE user_id = ? LIMIT 1");
$stmt->execute([$_SESSION['user_id']]);
$teacherId = $stmt->fetchColumn();

if (!$teacherId) {
    http_response_code(403);
    echo json_encode(['success' => false, 'errors' => ['No teacher record linked to this account.']]);
    exit();
}

$q = trim((string) ($_GET['q'] ?? ''));

if (mb_strlen($q) < 2) {
    echo json_encode(['success' => true, 'results' => []]);
    exit();
}

$like = '%' . $q . '%';

$stmt = $pdo->prepare("
    SELECT DISTINCT
        s.student_id,
        s.firstname,
        s.lastname,
        s.student_lrn,
        sec.section_name,
        sec.grade_level,
        (
            SELECT COUNT(DISTINCT co2.offering_id)
            FROM enrollments e2
            JOIN classofferings co2 ON co2.offering_id = e2.offering_id
            WHERE e2.student_id = s.student_id
              AND co2.teacher_id = :teacher_id2
              AND e2.status = 'active'
        ) AS classes_with_you
    FROM students s
    JOIN enrollments e       ON e.student_id = s.student_id
    JOIN classofferings co   ON co.offering_id = e.offering_id
    JOIN sections sec        ON sec.section_id = co.section_id
    WHERE co.teacher_id = :teacher_id
      AND e.status = 'active'
      AND (
            CONCAT(s.firstname, ' ', s.lastname) LIKE :q1
         OR s.firstname LIKE :q2
         OR s.lastname  LIKE :q3
         OR s.student_lrn LIKE :q4
      )
    ORDER BY s.lastname, s.firstname
    LIMIT 8
");
$stmt->execute([
    ':teacher_id'  => $teacherId,
    ':teacher_id2' => $teacherId,
    ':q1' => $like,
    ':q2' => $like,
    ':q3' => $like,
    ':q4' => $like,
]);

$results = [];
foreach ($stmt->fetchAll() as $s) {
    $results[] = [
        'student_id' => (int) $s['student_id'],
        'name'       => trim($s['firstname'] . ' ' . $s['lastname']),
        'lrn'        => (string) $s['student_lrn'],
        'section'    => $s['section_name'],
        'grade'      => (int) $s['grade_level'],
        'classes'    => (int) $s['classes_with_you'],
    ];
}

echo json_encode(['success' => true, 'results' => $results]);
exit();