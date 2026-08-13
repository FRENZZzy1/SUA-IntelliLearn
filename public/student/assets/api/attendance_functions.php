<?php
/**
 * attendance_functions.php (student)
 *
 * Backend for public/student/attendance.php ("Attendance").
 *
 * Attendance used to live as a per-class tab inside course_view.php
 * (always showed "coming soon"). It's moved here instead: one page,
 * one list per subject the student is actively enrolled in, covering
 * every term/offering of that subject in the current school year.
 *
 * Mirrors the pattern used by courses_functions.php: resolve the
 * logged-in student, scope to the current school year, collapse
 * per-term offering rows into one entry per subject, then attach the
 * attendance history for each subject's offering(s).
 */
require_once __DIR__ . '/../../../../config/config.php';

// ---- Access control -------------------------------------------------
if (!isLoggedIn() || ($_SESSION['role'] ?? '') !== 'student') {
    header('Location: ../../login.php');
    exit();
}

$userId = (int) $_SESSION['user_id'];

// ---- Resolve the logged-in student row --------------------------------
$stmt = $pdo->prepare("SELECT student_id, firstname, lastname FROM students WHERE user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$student = $stmt->fetch();

if (!$student) {
    die('Student record not found for this account.');
}
$studentId       = (int) $student['student_id'];
$studentFullName = trim($student['firstname'] . ' ' . $student['lastname']);

// ---- Current school year ---------------------------------------------
$stmt = $pdo->query("SELECT school_year_id, label FROM schoolyears WHERE is_current = 1 LIMIT 1");
$schoolYear      = $stmt->fetch();
$schoolYearId    = $schoolYear['school_year_id'] ?? null;
$schoolYearLabel = $schoolYear['label'] ?? null;

// ---- This student's active enrollments, one row per term-offering ------
$stmt = $pdo->prepare("
    SELECT
        e.enrollment_id, co.offering_id, co.quarter,
        sub.subject_id, sub.subject_name,
        sec.section_id, sec.section_name, sec.grade_level, sec.strand,
        t.teacher_id, t.firstname AS teacher_first, t.lastname AS teacher_last
    FROM enrollments e
    JOIN classofferings co ON co.offering_id = e.offering_id
    JOIN subjects sub      ON sub.subject_id = co.subject_id
    JOIN sections sec      ON sec.section_id = co.section_id
    JOIN teachers t        ON t.teacher_id = co.teacher_id
    WHERE e.student_id = ?
      AND e.status = 'active'
      AND co.status = 'active'
      AND (co.school_year_id = ? OR ? IS NULL)
    ORDER BY sub.subject_name, co.quarter
");
$stmt->execute([$studentId, $schoolYearId, $schoolYearId]);
$enrollmentRows = $stmt->fetchAll();

// ---- Collapse per-term rows into one entry per subject -------------------
$subjects = [];
foreach ($enrollmentRows as $row) {
    $subjectId = $row['subject_id'];
    if (!isset($subjects[$subjectId])) {
        $subjects[$subjectId] = $row;
        $subjects[$subjectId]['offering_ids'] = [];
        $subjects[$subjectId]['quarters']     = [];
    }
    $subjects[$subjectId]['offering_ids'][] = (int) $row['offering_id'];
    $subjects[$subjectId]['quarters'][(int) $row['offering_id']] = $row['quarter'];
}
$subjects = array_values($subjects);

// ---- Attendance history + per-subject stats -----------------------------
// One query per subject, scoped to all its offering_ids, newest first.
$overallCounts = ['Present' => 0, 'Absent' => 0, 'Late' => 0, 'Excused' => 0];

foreach ($subjects as &$subject) {
    $offeringIds = $subject['offering_ids'];
    $subject['records'] = [];
    $subject['counts']  = ['Present' => 0, 'Absent' => 0, 'Late' => 0, 'Excused' => 0];

    if ($offeringIds) {
        $placeholders = implode(',', array_fill(0, count($offeringIds), '?'));
        $stmt = $pdo->prepare("
            SELECT attendance_id, offering_id, attendance_date, status, remarks
            FROM attendance
            WHERE offering_id IN ($placeholders) AND student_id = ?
            ORDER BY attendance_date DESC
        ");
        $stmt->execute(array_merge($offeringIds, [$studentId]));
        $subject['records'] = $stmt->fetchAll();

        foreach ($subject['records'] as $r) {
            if (isset($subject['counts'][$r['status']])) {
                $subject['counts'][$r['status']]++;
                $overallCounts[$r['status']]++;
            }
        }
    }

    $subject['total_recorded'] = count($subject['records']);
    $subject['present_rate']   = $subject['total_recorded'] > 0
        ? round((($subject['counts']['Present'] + $subject['counts']['Late'] + $subject['counts']['Excused']) / $subject['total_recorded']) * 100)
        : null;
}
unset($subject);

$overallTotal = array_sum($overallCounts);

// ---- Sidebar/header context ---------------------------------------------
$studentGradeSection = !empty($subjects)
    ? "Grade {$subjects[0]['grade_level']} - {$subjects[0]['section_name']}"
    : null;

/**
 * Human label + chip class for an attendance status.
 * Mirrors the chip-present/absent/late/excused classes already defined
 * in course_view.css / class_overview.css.
 */
function attendanceStatusInfo(string $status): array
{
    $map = [
        'Present' => ['label' => 'Present', 'class' => 'present'],
        'Absent'  => ['label' => 'Absent',  'class' => 'absent'],
        'Late'    => ['label' => 'Late',    'class' => 'late'],
        'Excused' => ['label' => 'Excused', 'class' => 'excused'],
    ];
    return $map[$status] ?? ['label' => $status, 'class' => 'unmarked'];
}