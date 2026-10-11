<?php
/**
 * people_lookup.php?type=teacher|student&id=123  (student)
 *
 * Quick-view card data for a teacher or another student, opened from the
 * student header's search results.
 *
 * SECURITY NOTES
 *  - Same boundary as people_search.php: only role=teacher / role=student
 *    active accounts can be looked up, so admins / the principal can't be
 *    reached by guessing IDs.
 *  - Deliberately PUBLIC-SAFE fields only. Never add LRN, birthdate,
 *    gender, address, guardian details or contact numbers here — those are
 *    visible to a student's own teachers, not to other students.
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

$type = $_GET['type'] ?? '';
$id   = (int) ($_GET['id'] ?? 0);
if (!in_array($type, ['teacher', 'student'], true) || $id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'errors' => ['Missing or invalid request.']]);
    exit();
}

function fmt_schedule(array $row): string
{
    $s = $row['schedule_days'] ?: 'TBA';
    if (!empty($row['start_time']) && !empty($row['end_time'])) {
        $s .= ' · ' . date('g:i A', strtotime($row['start_time'])) . '–' . date('g:i A', strtotime($row['end_time']));
    }
    return $s;
}

if ($type === 'teacher') {
    $stmt = $pdo->prepare("
        SELECT t.teacher_id, t.firstname, t.lastname, t.department, t.specialization
        FROM teachers t
        JOIN users u ON u.id = t.user_id AND u.role = 'teacher' AND u.status = 'active'
        WHERE t.teacher_id = ? LIMIT 1
    ");
    $stmt->execute([$id]);
    $t = $stmt->fetch();
    if (!$t) {
        http_response_code(404);
        echo json_encode(['success' => false, 'errors' => ['Teacher not found.']]);
        exit();
    }

    // Classes this teacher runs that I'm actively enrolled in.
    $stmt = $pdo->prepare("
        SELECT DISTINCT sub.subject_name, sec.section_name, co.schedule_days, co.start_time, co.end_time
        FROM enrollments e
        JOIN classofferings co ON co.offering_id = e.offering_id
        JOIN subjects sub ON sub.subject_id = co.subject_id
        JOIN sections sec ON sec.section_id = co.section_id
        WHERE e.student_id = :me AND e.status = 'enrolled' AND co.teacher_id = :t
        ORDER BY sub.subject_name
    ");
    $stmt->execute([':me' => $myStudentId, ':t' => $id]);
    $withMe = [];
    foreach ($stmt->fetchAll() as $r) {
        $withMe[] = ['subject' => $r['subject_name'], 'detail' => fmt_schedule($r)];
    }

    // Everything else they teach (subject + section only, no schedules).
    $stmt = $pdo->prepare("
        SELECT DISTINCT sub.subject_name, sec.section_name, sec.grade_level
        FROM classofferings co
        JOIN subjects sub ON sub.subject_id = co.subject_id
        JOIN sections sec ON sec.section_id = co.section_id
        WHERE co.teacher_id = ? AND co.status = 'active'
        ORDER BY sec.grade_level, sub.subject_name, sec.section_name
    ");
    $stmt->execute([$id]);
    $handles = [];
    foreach ($stmt->fetchAll() as $r) {
        $handles[] = ['subject' => $r['subject_name'], 'detail' => 'Grade ' . (int) $r['grade_level'] . ' – ' . $r['section_name']];
    }

    echo json_encode([
        'success' => true,
        'type'    => 'teacher',
        'person'  => [
            'name'           => trim($t['firstname'] . ' ' . $t['lastname']),
            'department'     => $t['department'],
            'specialization' => $t['specialization'],
        ],
        'with_me' => $withMe,
        'handles' => $handles,
    ]);
    exit();
}

// ---- student ----
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
             ORDER BY e2.enrollment_id DESC LIMIT 1) AS grade_level
    FROM students s
    JOIN users u ON u.id = s.user_id AND u.role = 'student' AND u.status = 'active'
    WHERE s.student_id = ? LIMIT 1
");
$stmt->execute([$id]);
$s = $stmt->fetch();
if (!$s) {
    http_response_code(404);
    echo json_encode(['success' => false, 'errors' => ['Student not found.']]);
    exit();
}

// Classes we are BOTH actively enrolled in.
$stmt = $pdo->prepare("
    SELECT DISTINCT sub.subject_name, co.schedule_days, co.start_time, co.end_time,
           t.firstname AS t_first, t.lastname AS t_last
    FROM enrollments mine
    JOIN enrollments theirs ON theirs.offering_id = mine.offering_id
    JOIN classofferings co ON co.offering_id = mine.offering_id
    JOIN subjects sub ON sub.subject_id = co.subject_id
    LEFT JOIN teachers t ON t.teacher_id = co.teacher_id
    WHERE mine.student_id = :me AND mine.status = 'enrolled'
      AND theirs.student_id = :other AND theirs.status = 'enrolled'
    ORDER BY sub.subject_name
");
$stmt->execute([':me' => $myStudentId, ':other' => $id]);
$together = [];
foreach ($stmt->fetchAll() as $r) {
    $together[] = ['subject' => $r['subject_name'], 'detail' => fmt_schedule($r)];
}

$grade = $s['grade_level'] !== null ? (int) $s['grade_level'] : (int) $s['year_level'];
echo json_encode([
    'success' => true,
    'type'    => 'student',
    'person'  => [
        'name'    => trim($s['firstname'] . ' ' . $s['lastname']),
        'grade'   => $grade,
        'section' => $s['section_name'],
        'is_me'   => false,
    ],
    'together' => $together,
]);
exit();