<?php
require_once '../../config/config.php'; // adjust path to your actual config.php location

// ---- Access control -------------------------------------------------
if (!isLoggedIn() || ($_SESSION['role'] ?? '') !== 'teacher') {
    header('Location: ../login.php');
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
        COUNT(DISTINCT co.subject_id) AS subject_count,
        COUNT(DISTINCT e.student_id)  AS student_count
    FROM classofferings co
    JOIN sections sec ON sec.section_id = co.section_id
    LEFT JOIN enrollments e
           ON e.offering_id = co.offering_id AND e.status = 'enrolled'
    WHERE co.section_id NOT IN (SELECT section_id FROM sections WHERE is_archived = 1) AND co.teacher_id = ?
      AND co.status = 'active'
      AND (co.school_year_id = ? OR ? IS NULL)
    GROUP BY sec.section_id, sec.section_name, sec.grade_level
    ORDER BY sec.grade_level, sec.section_name
");
$stmt->execute([$teacherId, $schoolYearId, $schoolYearId]);
$mySections = $stmt->fetchAll();

// ---- Detail lists behind the three summary cards -----------------------------
// Subjects: one row per subject+section (the 3 term offerings collapse into one).
// Students: one row per student per section, with the subjects they take.
$currentTerm = resolveCurrentTerm(getTermIntervals($pdo)); // e.g. 'TRM 2' or null
$stmt = $pdo->prepare("
    SELECT co.offering_id, co.quarter, co.subject_id, co.section_id,
           sub.subject_name, sec.section_name, sec.grade_level
    FROM classofferings co
    JOIN subjects sub ON sub.subject_id = co.subject_id
    JOIN sections sec ON sec.section_id = co.section_id
    WHERE co.section_id NOT IN (SELECT section_id FROM sections WHERE is_archived = 1) AND co.teacher_id = ?
      AND co.status = 'active'
      AND (co.school_year_id = ? OR ? IS NULL)
    ORDER BY sec.grade_level, sec.section_name, sub.subject_name, co.quarter
");
$stmt->execute([$teacherId, $schoolYearId, $schoolYearId]);
$allOfferings = $stmt->fetchAll();

// Preferred offering per subject+section: the ongoing term, else the earliest term.
$subjectList = [];
$preferredOffering = []; // offering_id => preferred offering_id of the same subject+section
foreach ($allOfferings as $o) {
    $key = $o['section_id'] . '-' . $o['subject_id'];
    if (!isset($subjectList[$key])) {
        $subjectList[$key] = $o;
    } elseif ($currentTerm !== null && $o['quarter'] === $currentTerm && $subjectList[$key]['quarter'] !== $currentTerm) {
        $subjectList[$key] = $o;
    }
}
foreach ($allOfferings as $o) {
    $preferredOffering[(int) $o['offering_id']] = (int) $subjectList[$o['section_id'] . '-' . $o['subject_id']]['offering_id'];
}
$subjectList = array_values($subjectList);

$studentList = [];
if ($allOfferings) {
    $offeringMeta = [];
    foreach ($allOfferings as $o) {
        $offeringMeta[(int) $o['offering_id']] = $o;
    }
    $ph = implode(',', array_fill(0, count($offeringMeta), '?'));
    $stmt = $pdo->prepare("
        SELECT e.offering_id, s.student_id, s.firstname, s.lastname
        FROM enrollments e
        JOIN students s ON s.student_id = e.student_id
        WHERE e.status = 'enrolled' AND e.offering_id IN ($ph)
        ORDER BY s.lastname, s.firstname
    ");
    $stmt->execute(array_keys($offeringMeta));
    foreach ($stmt->fetchAll() as $r) {
        $o   = $offeringMeta[(int) $r['offering_id']];
        $key = $o['section_id'] . '-' . $r['student_id'];
        if (!isset($studentList[$key])) {
            $studentList[$key] = [
                'name'    => trim($r['lastname'] . ', ' . $r['firstname']),
                'section' => $o['section_name'],
                'grade'   => (int) $o['grade_level'],
                'subjects' => [],
            ];
        }
        $prefId = $preferredOffering[(int) $r['offering_id']];
        // one chip per subject, linking to the student's page for the preferred term
        $studentList[$key]['subjects'][$o['subject_id']] = [
            'name' => $o['subject_name'],
            'url'  => 'student_scores.php?' . http_build_query(['student_id' => (int) $r['student_id'], 'offering_id' => $prefId]),
        ];
    }
    $studentList = array_values($studentList);
    usort($studentList, fn($a, $b) => strcasecmp($a['name'], $b['name']));
}

// ---- At-risk count for the sidebar nav badge (kept in sync with dashboard) ----
// TODO: replace once at-risk detection exists (REQ018–REQ021).
$atRiskCount = null;