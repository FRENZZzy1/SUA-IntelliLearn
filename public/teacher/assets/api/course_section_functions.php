<?php
require_once '../../config/config.php'; // adjust path to your actual config.php location

// ---- Access control -------------------------------------------------
if (!isLoggedIn() || ($_SESSION['role'] ?? '') !== 'teacher') {
    header('Location: ../../login.php');
    exit();
}

$userId = (int) $_SESSION['user_id'];

// ---- Resolve the logged-in teacher row -------------------------------
$stmt = $pdo->prepare("SELECT teacher_id, firstname, lastname FROM teachers WHERE user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$teacher = $stmt->fetch();

if (!$teacher) {
    die('Teacher record not found for this account.');
}
$teacherId = (int) $teacher['teacher_id'];

// ---- Validate section_id from the query string -------------------------
$sectionId = filter_input(INPUT_GET, 'section_id', FILTER_VALIDATE_INT);
if (!$sectionId) {
    header('Location: courses.php');
    exit();
}

// ---- Current school year ---------------------------------------------
$stmt = $pdo->query("SELECT school_year_id, label FROM schoolyears WHERE is_current = 1 LIMIT 1");
$schoolYear      = $stmt->fetch();
$schoolYearId    = $schoolYear['school_year_id'] ?? null;
$schoolYearLabel = $schoolYear['label'] ?? null;

// ---- Section info ------------------------------------------------------
// Scoped to (teacher_id, section_id) together: this doubles as the
// authorization check. If this teacher doesn't actually have an active
// offering in the requested section, $section comes back empty and we
// bounce them back to the courses list rather than leaking another
// teacher's section by URL guessing.
$stmt = $pdo->prepare("
    SELECT DISTINCT sec.section_id, sec.section_name, sec.grade_level, sec.strand
    FROM classofferings co
    JOIN sections sec ON sec.section_id = co.section_id
    WHERE co.teacher_id = ?
      AND co.section_id = ?
      AND co.status = 'active'
      AND (co.school_year_id = ? OR ? IS NULL)
    LIMIT 1
");
$stmt->execute([$teacherId, $sectionId, $schoolYearId, $schoolYearId]);
$section = $stmt->fetch();

if (!$section) {
    header('Location: courses.php');
    exit();
}

// ---- Subjects this teacher teaches within this section -----------------
$stmt = $pdo->prepare("
    SELECT
        co.offering_id,
        sub.subject_name,
        co.quarter,
        co.schedule_days,
        co.start_time,
        co.end_time,
        co.capacity,
        COUNT(e.student_id) AS enrolled_count
    FROM classofferings co
    JOIN subjects sub ON sub.subject_id = co.subject_id
    LEFT JOIN enrollments e
           ON e.offering_id = co.offering_id AND e.status = 'active'
    WHERE co.teacher_id = ?
      AND co.section_id = ?
      AND co.status = 'active'
      AND (co.school_year_id = ? OR ? IS NULL)
    GROUP BY co.offering_id, sub.subject_name, co.quarter, co.schedule_days,
             co.start_time, co.end_time, co.capacity
    ORDER BY sub.subject_name
");
$stmt->execute([$teacherId, $sectionId, $schoolYearId, $schoolYearId]);
$sectionSubjects = $stmt->fetchAll();

// ---- At-risk count for the sidebar nav badge (kept in sync with dashboard) ----
// TODO: replace once at-risk detection exists (REQ018–REQ021).
$atRiskCount = null;