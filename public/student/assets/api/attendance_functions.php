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

// ---- Term intervals, used to work out which term each attendance date
// actually fell in. A class's classofferings.quarter only reflects
// whichever term is *currently* active (it advances automatically as
// terms roll over — see syncCourseTermsToCurrent() in config.php), so it
// can't be used to label attendance taken in an earlier term. Instead,
// each attendance_date is resolved against the configured intervals
// independently, the same way "Set Term Interval" resolves today's date.
$termIntervals = getTermIntervals($pdo);

// ---- Term filter (mirrors public/teacher/attendance.php) ----------------
// Lets the student scope the whole page down to one term instead of always
// seeing every term stacked. Defaults to the currently active term, same
// as the teacher dashboard, with "All Terms" available to see everything.
$activeTerm = resolveCurrentTerm($termIntervals);
$termFilter = $_GET['term'] ?? ($activeTerm ?? 'all');
if (!in_array($termFilter, ['all', 'TRM 1', 'TRM 2', 'TRM 3'], true)) {
    $termFilter = 'all';
}

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

// ---- Attendance history + per-subject, per-term stats --------------------
// One query per subject, scoped to all its offering_ids, newest first.
// Records are then split into a TRM 1 / TRM 2 / TRM 3 (/ Unscheduled)
// bucket per subject, based on the date each record was taken.
$overallCounts = ['Present' => 0, 'Absent' => 0, 'Late' => 0, 'Excused' => 0];

foreach ($subjects as &$subject) {
    $offeringIds = $subject['offering_ids'];
    $subject['records'] = [];
    $subject['counts']  = ['Present' => 0, 'Absent' => 0, 'Late' => 0, 'Excused' => 0];
    $subject['terms']   = [];

    if ($offeringIds) {
        $placeholders = implode(',', array_fill(0, count($offeringIds), '?'));
        $stmt = $pdo->prepare("
            SELECT attendance_id, offering_id, attendance_date, status, remarks
            FROM attendance
            WHERE offering_id IN ($placeholders) AND student_id = ?
            ORDER BY attendance_date DESC
        ");
        $stmt->execute(array_merge($offeringIds, [$studentId]));
        $allRecords = $stmt->fetchAll();

        // Tag each record with the term its date actually fell in, then
        // scope everything below (counts, present rate, term buckets) to
        // the selected term filter — same as the teacher dashboard.
        foreach ($allRecords as &$r) {
            $r['term'] = attendanceTermForDate($termIntervals, $r['attendance_date']);
        }
        unset($r);

        $subject['records'] = $termFilter === 'all'
            ? $allRecords
            : array_values(array_filter($allRecords, fn($r) => $r['term'] === $termFilter));

        foreach ($subject['records'] as $r) {
            if (isset($subject['counts'][$r['status']])) {
                $subject['counts'][$r['status']]++;
                $overallCounts[$r['status']]++;
            }

            $term = $r['term'];
            if (!isset($subject['terms'][$term])) {
                $subject['terms'][$term] = [
                    'records' => [],
                    'counts'  => ['Present' => 0, 'Absent' => 0, 'Late' => 0, 'Excused' => 0],
                ];
            }
            $subject['terms'][$term]['records'][] = $r;
            if (isset($subject['terms'][$term]['counts'][$r['status']])) {
                $subject['terms'][$term]['counts'][$r['status']]++;
            }
        }

        // Order the per-term buckets TRM 1 -> TRM 2 -> TRM 3 -> Unscheduled,
        // and compute each bucket's own present rate.
        $ordered = [];
        foreach (ATTENDANCE_TERM_ORDER as $termLabel) {
            if (isset($subject['terms'][$termLabel])) {
                $ordered[$termLabel] = $subject['terms'][$termLabel];
            }
        }
        foreach ($ordered as $termLabel => &$bucket) {
            $bucketTotal = count($bucket['records']);
            $bucket['total_recorded'] = $bucketTotal;
            $bucket['present_rate'] = $bucketTotal > 0
                ? round((($bucket['counts']['Present'] + $bucket['counts']['Late'] + $bucket['counts']['Excused']) / $bucketTotal) * 100)
                : null;
        }
        unset($bucket);
        $subject['terms'] = $ordered;
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