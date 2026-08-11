<?php
/**
 * courses_functions.php (student)
 *
 * Backend for public/student/courses.php ("My Courses").
 *
 * Mirrors the pattern used by the teacher "Classes" module
 * (public/teacher/assets/api/courses_functions.php /
 * course_section_functions.php): resolve the logged-in user's row,
 * scope everything to the current school year, and hand the view a
 * ready-to-render array.
 *
 * For a student, "My Courses" = one card per subject they are actively
 * enrolled in (via the `enrollments` table), regardless of which term
 * (quarter) that enrollment falls under. A subject can have up to 3
 * enrollment rows (one per quarter offering) — those are collapsed into
 * a single card here, same way the teacher's course_section.php
 * collapses per-term offering rows into one card per subject.
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
        e.enrollment_id, co.offering_id, co.quarter, co.schedule_days,
        co.start_time, co.end_time, co.capacity,
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

// ---- Collapse per-term rows into one card per subject -------------------
$myCourses = [];
foreach ($enrollmentRows as $row) {
    $subjectId = $row['subject_id'];
    if (!isset($myCourses[$subjectId])) {
        $myCourses[$subjectId] = $row;
        $myCourses[$subjectId]['term_count']  = 0;
        $myCourses[$subjectId]['offering_ids'] = [];
    }
    $myCourses[$subjectId]['term_count']++;
    $myCourses[$subjectId]['offering_ids'][] = (int) $row['offering_id'];
}
$myCourses = array_values($myCourses);

// ---- Materials / assignments / quizzes counts per subject ---------------
// One extra query per stat, scoped to all this subject's offering_ids,
// so the card can show a quick "what's inside" summary without a heavier
// per-card query loop.
foreach ($myCourses as &$course) {
    $offeringIds = $course['offering_ids'];
    $course['materials_count']   = 0;
    $course['assignments_count'] = 0;
    $course['quizzes_count']     = 0;

    if ($offeringIds) {
        $placeholders = implode(',', array_fill(0, count($offeringIds), '?'));

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM learning_materials WHERE offering_id IN ($placeholders)");
        $stmt->execute($offeringIds);
        $course['materials_count'] = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM assignments WHERE offering_id IN ($placeholders) AND status = 'published'");
        $stmt->execute($offeringIds);
        $course['assignments_count'] = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM quizzes WHERE offering_id IN ($placeholders) AND status = 'published'");
        $stmt->execute($offeringIds);
        $course['quizzes_count'] = (int) $stmt->fetchColumn();
    }
}
unset($course);

// ---- Sidebar/header context ---------------------------------------------
$studentGradeSection = !empty($myCourses)
    ? "Grade {$myCourses[0]['grade_level']} - {$myCourses[0]['section_name']}"
    : null;
