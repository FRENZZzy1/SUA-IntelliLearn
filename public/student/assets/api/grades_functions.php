<?php
/**
 * grades_functions.php (student)
 *
 * Backend for public/student/grades.php ("My Grades").
 *
 * Mirrors the pattern used by courses_functions.php: resolve the
 * logged-in student's row, then build a ready-to-render structure.
 *
 * A student's grades are organized the way enrollment actually works
 * in this system: one enrollment row per class offering, one class
 * offering per (subject, term/quarter, school year). So "My Grades"
 * is grouped:
 *
 *   School Year -> Term (TRM 1 / TRM 2 / TRM 3) -> one row per
 *   class offering the student is/was enrolled in that term.
 *
 * For each enrollment we show:
 *   - The official final grade from the `grades` table (quarter =
 *     'Final'), the same value teachers post via grade_finalize.php.
 *   - When no final grade has been posted yet, a live "current
 *     standing" estimate computed the same way grade_finalize.php
 *     computes it (Written Work / Performance Task / Exam, via
 *     computeWeightedFinalGrade() in config.php), clearly labeled as
 *     unofficial/in-progress so it's never confused with the posted
 *     grade.
 */
require_once __DIR__ . '/../../../../config/config.php';

// ---- Access control -------------------------------------------------
if (!isLoggedIn() || ($_SESSION['role'] ?? '') !== 'student') {
    header('Location: ../../login.php');
    exit();
}

const PASSING_GRADE = 75.0;
const TERM_ORDER = ['TRM 1', 'TRM 2', 'TRM 3'];

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

// ---- School years this student has an enrollment in ---------------------
// Enrollments the admin has unenrolled ('dropped') are excluded everywhere on
// this page, so a school year that only contains dropped classes isn't listed.
$stmt = $pdo->prepare("
    SELECT DISTINCT sy.school_year_id, sy.label, sy.is_current
    FROM enrollments e
    JOIN classofferings co ON co.offering_id = e.offering_id
    JOIN schoolyears sy    ON sy.school_year_id = co.school_year_id
    WHERE e.student_id = ?
      AND e.status <> 'dropped'
    ORDER BY sy.start_date DESC
");
$stmt->execute([$studentId]);
$schoolYears = $stmt->fetchAll();

// ---- Which school year is selected? (defaults to the current one, or
//      the most recent one this student has records for) -----------------
$requestedYearId = filter_input(INPUT_GET, 'school_year_id', FILTER_VALIDATE_INT) ?: null;
$selectedYearId  = null;
foreach ($schoolYears as $sy) {
    if ($requestedYearId && (int) $sy['school_year_id'] === $requestedYearId) {
        $selectedYearId = (int) $sy['school_year_id'];
        break;
    }
}
if ($selectedYearId === null) {
    foreach ($schoolYears as $sy) {
        if ((int) $sy['is_current'] === 1) {
            $selectedYearId = (int) $sy['school_year_id'];
            break;
        }
    }
}
if ($selectedYearId === null && !empty($schoolYears)) {
    $selectedYearId = (int) $schoolYears[0]['school_year_id'];
}
$selectedYearLabel = null;
foreach ($schoolYears as $sy) {
    if ((int) $sy['school_year_id'] === $selectedYearId) {
        $selectedYearLabel = $sy['label'];
        break;
    }
}

// ---- Enrollments (except unenrolled/dropped ones) for the selected school
//      year, with grade ---------------------------------------------------
$enrollmentRows = [];
if ($selectedYearId !== null) {
    $stmt = $pdo->prepare("
        SELECT
            e.enrollment_id, e.status AS enrollment_status,
            co.offering_id, co.quarter, co.schedule_days, co.start_time, co.end_time,
            sub.subject_id, sub.subject_name,
            sec.section_name, sec.grade_level, sec.strand,
            t.firstname AS teacher_first, t.lastname AS teacher_last
        FROM enrollments e
        JOIN classofferings co ON co.offering_id = e.offering_id
        JOIN subjects sub      ON sub.subject_id = co.subject_id
        JOIN sections sec      ON sec.section_id = co.section_id
        JOIN teachers t        ON t.teacher_id = co.teacher_id
        WHERE e.student_id = ? AND co.school_year_id = ?
          AND e.status <> 'dropped'
        ORDER BY sub.subject_name, co.quarter
    ");
    $stmt->execute([$studentId, $selectedYearId]);
    $enrollmentRows = $stmt->fetchAll();
}

// ---- All posted grades for these enrollments -----------------------------
$enrollmentIds = array_column($enrollmentRows, 'enrollment_id');
$gradesByEnrollment = [];
if ($enrollmentIds) {
    $placeholders = implode(',', array_fill(0, count($enrollmentIds), '?'));
    $stmt = $pdo->prepare("
        SELECT enrollment_id, quarter, grade, remarks, updated_at
        FROM grades
        WHERE enrollment_id IN ($placeholders)
    ");
    $stmt->execute($enrollmentIds);
    foreach ($stmt->fetchAll() as $g) {
        $gradesByEnrollment[(int) $g['enrollment_id']][$g['quarter']] = $g;
    }
}

// ---- Live "current standing" helpers (mirrors grade_finalize.php) --------
function avgPercent($values) {
    $values = array_values(array_filter($values, fn($x) => $x !== null));
    return $values ? round(array_sum($values) / count($values), 2) : null;
}

function computeCurrentStanding(PDO $pdo, int $offeringId, int $studentId): ?float {
    $stmt = $pdo->prepare("
        SELECT qa.score, qa.max_score
        FROM quizzes q
        JOIN quiz_attempts qa ON qa.quiz_id = q.quiz_id AND qa.student_id = ?
            AND qa.attempt_number = (
                SELECT MAX(x.attempt_number) FROM quiz_attempts x
                WHERE x.quiz_id = q.quiz_id AND x.student_id = ?
            )
        WHERE q.offering_id = ?
    ");
    $stmt->execute([$studentId, $studentId, $offeringId]);
    $writtenWorkPercents = [];
    foreach ($stmt->fetchAll() as $r) {
        $max = (float) ($r['max_score'] ?? 0);
        if ($r['score'] !== null && $max > 0) {
            $writtenWorkPercents[] = ((float) $r['score'] / $max) * 100;
        }
    }

    $stmt = $pdo->prepare("
        SELECT a.type, a.points, sub.score
        FROM assignments a
        LEFT JOIN submissions sub ON sub.assignment_id = a.assignment_id AND sub.student_id = ?
            AND sub.attempt_number = (
                SELECT MAX(x.attempt_number) FROM submissions x
                WHERE x.assignment_id = a.assignment_id AND x.student_id = ?
            )
        WHERE a.offering_id = ?
    ");
    $stmt->execute([$studentId, $studentId, $offeringId]);
    $ptPercents = [];
    $examPercents = [];
    foreach ($stmt->fetchAll() as $r) {
        if ($r['score'] === null) continue;
        $points = (float) $r['points'];
        if ($points <= 0) continue;
        $pct = ((float) $r['score'] / $points) * 100;
        if (($r['type'] ?? 'Activity') === 'Exam') {
            $examPercents[] = $pct;
        } else {
            $ptPercents[] = $pct;
        }
    }

    return computeWeightedFinalGrade(
        avgPercent($writtenWorkPercents),
        avgPercent($ptPercents),
        avgPercent($examPercents)
    );
}

// ---- Assemble one row per enrollment, with final grade or live estimate --
$myGrades = [];
foreach ($enrollmentRows as $row) {
    $enrollmentId = (int) $row['enrollment_id'];
    $postedFinal  = $gradesByEnrollment[$enrollmentId]['Final'] ?? null;

    $finalGrade   = $postedFinal ? (float) $postedFinal['grade'] : null;
    $remarks      = $postedFinal['remarks'] ?? null;
    $postedAt     = $postedFinal['updated_at'] ?? null;
    $isPosted     = $postedFinal !== null;

    $currentStanding = null;
    if (!$isPosted) {
        $currentStanding = computeCurrentStanding($pdo, (int) $row['offering_id'], $studentId);
    }

    $myGrades[] = [
        'enrollment_id'     => $enrollmentId,
        'enrollment_status' => $row['enrollment_status'],
        'offering_id'       => (int) $row['offering_id'],
        'quarter'           => $row['quarter'],
        'subject_id'        => (int) $row['subject_id'],
        'subject_name'      => $row['subject_name'],
        'teacher_name'      => trim($row['teacher_first'] . ' ' . $row['teacher_last']),
        'section_name'      => $row['section_name'],
        'grade_level'       => $row['grade_level'],
        'strand'            => $row['strand'],
        'is_posted'         => $isPosted,
        'final_grade'       => $finalGrade,
        'remarks'           => $remarks,
        'posted_at'         => $postedAt,
        'current_standing'  => $currentStanding,
        'is_passing'        => $finalGrade !== null ? ($finalGrade >= PASSING_GRADE) : null,
    ];
}

// ---- Group rows by term, in TRM 1 -> TRM 2 -> TRM 3 order ----------------
$termGroups = [];
foreach (TERM_ORDER as $term) {
    $termGroups[$term] = [];
}
// PHP auto-vivifies array keys, so any legacy/unexpected quarter label
// (e.g. 'Prelim') simply creates its own group here and lands after the
// TRM 1/2/3 groups already seeded above, instead of being dropped.
foreach ($myGrades as $g) {
    $termGroups[$g['quarter']][] = $g;
}

// ---- Summary stats (based on posted/final grades only) -------------------
$postedGrades   = array_values(array_filter($myGrades, fn($g) => $g['is_posted']));
$finalValues    = array_column($postedGrades, 'final_grade');
$gwa            = $finalValues ? round(array_sum($finalValues) / count($finalValues), 2) : null;
$passingCount   = count(array_filter($postedGrades, fn($g) => $g['is_passing']));
$atRiskCount    = count(array_filter($postedGrades, fn($g) => $g['is_passing'] === false));
$pendingCount   = count($myGrades) - count($postedGrades);
$totalClasses   = count($myGrades);

// ---- Sidebar/header context ---------------------------------------------
$firstWithSection = null;
foreach ($myGrades as $g) {
    if (!empty($g['section_name'])) {
        $firstWithSection = $g;
        break;
    }
}
$studentGradeSection = $firstWithSection
    ? "Grade {$firstWithSection['grade_level']} - {$firstWithSection['section_name']}"
    : null;