<?php
/**
 * quiz_generator_functions.php
 *
 * Data loader for public/teacher/quiz_generator.php — same contract as
 * dashboard_functions.php: session already started by config.php,
 * this file just guards access and prepares the variables the view uses.
 *
 * NOTE: adjust the require_once path below if config.php lives somewhere
 * else in your project — this assumes the same depth used elsewhere
 * under public/teacher/assets/api/.
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/SUA-IntelliLearn/config/config.php';

requireTeacher();

// ------------------------------------------------------------------
// Resolve the logged-in user to a teachers.teacher_id
// ------------------------------------------------------------------
$stmt = $pdo->prepare("SELECT teacher_id, firstname, lastname FROM teachers WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$teacherRow = $stmt->fetch();

if (!$teacherRow) {
    // Logged in as 'teacher' role but no matching teachers row — bail safely.
    setFlashMessage('error', 'Your teacher profile could not be found. Please contact the administrator.');
    header('Location: dashboard.php');
    exit();
}

$teacherId   = (int) $teacherRow['teacher_id'];
$teacherName = trim($teacherRow['firstname'] . ' ' . $teacherRow['lastname']);

// ------------------------------------------------------------------
// Classes/subjects this teacher is actually assigned to (active only).
// This is what populates the "Subject / Class" dropdown — a teacher can
// only generate quizzes for offerings they own.
// ------------------------------------------------------------------
$stmt = $pdo->prepare("
    SELECT
        co.offering_id,
        co.quarter,
        s.subject_id,
        s.subject_name,
        sec.section_name,
        sec.grade_level
    FROM classofferings co
    JOIN subjects s ON s.subject_id = co.subject_id
    JOIN sections sec ON sec.section_id = co.section_id
    WHERE co.teacher_id = ? AND co.status = 'active'
    ORDER BY sec.grade_level ASC, s.subject_name ASC, sec.section_name ASC
");
$stmt->execute([$teacherId]);
$teacherOfferings = $stmt->fetchAll();

// ------------------------------------------------------------------
// Recent quizzes this teacher has generated/created, for the sidebar list
// on the quiz generator page.
// ------------------------------------------------------------------
$stmt = $pdo->prepare("
    SELECT
        q.quiz_id,
        q.title,
        q.status,
        q.generation_source,
        q.created_at,
        s.subject_name,
        sec.section_name,
        (SELECT COUNT(*) FROM quiz_questions qq WHERE qq.quiz_id = q.quiz_id) AS question_count
    FROM quizzes q
    JOIN classofferings co ON co.offering_id = q.offering_id
    JOIN subjects s ON s.subject_id = co.subject_id
    JOIN sections sec ON sec.section_id = co.section_id
    WHERE q.created_by = ?
    ORDER BY q.created_at DESC
    LIMIT 8
");
$stmt->execute([$teacherId]);
$recentQuizzes = $stmt->fetchAll();

$csrfToken = generateCSRFToken();
