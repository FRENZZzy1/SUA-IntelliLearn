<?php
/**
 * assests/api/student_lookup.php?student_id=123
 *
 * Teacher-scoped single-student lookup backing the quick-view modal
 * opened from the teacher header's search results.
 *
 * SECURITY NOTE: same boundary as search_students.php — a teacher may
 * only look up a student who is ACTIVELY enrolled in one of THEIR OWN
 * classes. The WHERE co.teacher_id = ? (enforced via the EXISTS check
 * below) is what enforces that boundary. Do not remove it / do not add
 * a "look up any student" fallback here.
 *
 * NOTE ON COLUMNS: gender / birthdate / guardian_name / guardian_contact
 * are assumed to live on the `students` table. If your schema names
 * them differently (or keeps guardian info in a separate table), adjust
 * the SELECT below — the rest of the file doesn't need to change.
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

$studentId = (int) ($_GET['student_id'] ?? 0);

if ($studentId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'errors' => ['Missing or invalid student_id.']]);
    exit();
}

// ---- Enforce boundary: student must be actively enrolled in one of ----
// ---- THIS teacher's classes, and pull section/grade off that class ----
$stmt = $pdo->prepare("
    SELECT
        s.student_id,
        s.firstname,
        s.lastname,
        s.student_lrn,
        s.gender,
        s.birthdate,
        s.guardian_name,
        s.guardian_contact,
        sec.section_name,
        sec.grade_level
    FROM students s
    JOIN enrollments e     ON e.student_id = s.student_id
    JOIN classofferings co ON co.offering_id = e.offering_id
    JOIN sections sec      ON sec.section_id = co.section_id
    WHERE s.student_id = :student_id
      AND co.teacher_id = :teacher_id
      AND e.status = 'active'
    LIMIT 1
");
$stmt->execute([
    ':student_id' => $studentId,
    ':teacher_id' => $teacherId,
]);
$student = $stmt->fetch();

if (!$student) {
    // Either the student doesn't exist, or isn't enrolled with this
    // teacher — same response either way so we don't leak which.
    http_response_code(404);
    echo json_encode(['success' => false, 'errors' => ['Student not found in your classes.']]);
    exit();
}

// ---- Subjects this student takes WITH this teacher ---------------------
$stmt = $pdo->prepare("
    SELECT
        sub.subject_name,
        co.schedule_days,
        co.start_time,
        co.end_time
    FROM enrollments e
    JOIN classofferings co ON co.offering_id = e.offering_id
    JOIN subjects sub      ON sub.subject_id = co.subject_id
    WHERE e.student_id = :student_id
      AND co.teacher_id = :teacher_id
      AND e.status = 'active'
    ORDER BY sub.subject_name
");
$stmt->execute([
    ':student_id' => $studentId,
    ':teacher_id' => $teacherId,
]);

$subjects = [];
foreach ($stmt->fetchAll() as $row) {
    $schedule = $row['schedule_days'] ?? 'TBA';
    if ($row['start_time']) {
        $schedule .= ' · ' . date('g:i A', strtotime($row['start_time']))
                   . '–' . date('g:i A', strtotime($row['end_time']));
    }
    $subjects[] = [
        'subject'  => $row['subject_name'],
        'schedule' => $schedule,
    ];
}

// ---- Performance snapshot (placeholders — same reasoning as ------------
// ---- dashboard.php: no grades/attendance tables yet) --------------------
// TODO: replace once grades table exists.
$avgGrade = null;
// TODO: replace once attendance table exists.
$attendanceRate = null;

echo json_encode([
    'success' => true,
    'student' => [
        'student_id'       => (int) $student['student_id'],
        'name'             => trim($student['firstname'] . ' ' . $student['lastname']),
        'lrn'              => (string) $student['student_lrn'],
        'section'          => $student['section_name'],
        'grade'            => (int) $student['grade_level'],
        'gender'           => $student['gender'],
        'birthdate'        => $student['birthdate'] ? date('M j, Y', strtotime($student['birthdate'])) : null,
        'guardian_name'    => $student['guardian_name'],
        'guardian_contact' => $student['guardian_contact'],
    ],
    'subjects' => $subjects,
    'performance' => [
        'avg_grade'       => $avgGrade,
        'attendance_rate' => $attendanceRate,
    ],
]);
exit();