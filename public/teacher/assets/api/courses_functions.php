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
$teacherId   = (int) $teacher['teacher_id'];
$teacherName = trim($teacher['firstname'] . ' ' . $teacher['lastname']);

// ---- Current school year ---------------------------------------------
$stmt = $pdo->query("SELECT school_year_id, label FROM schoolyears WHERE is_current = 1 LIMIT 1");
$schoolYear      = $stmt->fetch();
$schoolYearId    = $schoolYear['school_year_id'] ?? null;
$schoolYearLabel = $schoolYear['label'] ?? null;

// ---- Sections this teacher has active classes in ----------------------
// One row per section, with a rollup of how many subjects the teacher
// teaches there and how many distinct students are enrolled across
// those offerings.
$stmt = $pdo->prepare("
    SELECT
        sec.section_id,
        sec.section_name,
        sec.grade_level,
        sec.strand,
        COUNT(DISTINCT co.subject_id) AS subject_count,
        COUNT(DISTINCT e.student_id)  AS student_count
    FROM classofferings co
    JOIN sections sec ON sec.section_id = co.section_id
    LEFT JOIN enrollments e
           ON e.offering_id = co.offering_id AND e.status = 'active'
    WHERE co.teacher_id = ?
      AND co.status = 'active'
      AND (co.school_year_id = ? OR ? IS NULL)
    GROUP BY sec.section_id, sec.section_name, sec.grade_level, sec.strand
    ORDER BY sec.grade_level, sec.section_name
");
$stmt->execute([$teacherId, $schoolYearId, $schoolYearId]);
$mySections = $stmt->fetchAll();

// ---- At-risk count for the sidebar nav badge (kept in sync with dashboard) ----
// TODO: replace once at-risk detection exists (REQ018–REQ021).
$atRiskCount = null;