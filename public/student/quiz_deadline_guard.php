<?php
/**
 * Server-side guard for student quiz access.
 *
 * This file is reached through public/student/.htaccess before
 * take_quiz.php. It checks the quiz availability window using the
 * database clock so students cannot bypass the deadline by opening
 * take_quiz.php directly or submitting a crafted POST request.
 */
require_once __DIR__ . '/../../config/config.php';

if (!isLoggedIn() || ($_SESSION['role'] ?? '') !== 'student') {
    header('Location: ../../login.php');
    exit();
}

$quizId = filter_input(INPUT_GET, 'quiz_id', FILTER_VALIDATE_INT);
if (!$quizId) {
    http_response_code(400);
    die('Invalid Quiz ID.');
}

$stmt = $pdo->prepare("\n    SELECT quiz_id, status, available_from, available_until\n    FROM quizzes\n    WHERE quiz_id = ?\n    LIMIT 1\n");
$stmt->execute([$quizId]);
$quiz = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$quiz || $quiz['status'] !== 'published') {
    http_response_code(404);
    die('Quiz not found or is not available.');
}

// Use the database server's current time so the check is not dependent on
// the student's browser clock or PHP timezone configuration.
$stmt = $pdo->prepare("\n    SELECT\n        CASE\n            WHEN ? IS NOT NULL AND ? > NOW() THEN 'not_started'\n            WHEN ? IS NOT NULL AND ? <= NOW() THEN 'expired'\n            ELSE 'available'\n        END AS availability\n");
$stmt->execute([
    $quiz['available_from'],
    $quiz['available_from'],
    $quiz['available_until'],
    $quiz['available_until'],
]);
$availability = $stmt->fetchColumn();

if ($availability === 'not_started') {
    http_response_code(403);
    die('This quiz is not available yet.');
}

if ($availability === 'expired') {
    http_response_code(403);
    die('This quiz is closed. The deadline has passed and late attempts are not allowed.');
}

// The request is allowed. Include the existing quiz page directly so its
// current UI, timer, attempt limits, and grading logic remain unchanged.
require __DIR__ . '/take_quiz.php';
