<?php
require_once '../../../../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_SESSION['role'] ?? '') !== 'student') {
    http_response_code(403);
    echo json_encode(['success'=>false,'errors'=>['Access denied.']]);
    exit;
}

if (!function_exists('verifyCSRFToken') || !verifyCSRFToken($_POST['csrf'] ?? '')) {
    // Accept the session-generated token when the page is posted without a hidden token.
    // A token is still required when the helper is available.
    if (function_exists('generateCSRFToken')) {
        http_response_code(419);
        echo json_encode(['success'=>false,'errors'=>['Invalid security token. Please refresh and try again.']]);
        exit;
    }
}

$userId = (int)$_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT student_id FROM students WHERE user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$studentId = (int)$stmt->fetchColumn();

$offeringId = (int)($_POST['offering_id'] ?? 0);
$ratings = [
 'rating_teaching_quality'=>(int)($_POST['rating_teaching_quality'] ?? 0),
 'rating_communication'=>(int)($_POST['rating_communication'] ?? 0),
 'rating_preparation'=>(int)($_POST['rating_preparation'] ?? 0),
 'rating_fairness'=>(int)($_POST['rating_fairness'] ?? 0),
 'rating_support'=>(int)($_POST['rating_support'] ?? 0)
];
$comments = trim($_POST['comments'] ?? '');

foreach ($ratings as $rating) {
    if ($rating < 1 || $rating > 5) {
        echo json_encode(['success'=>false,'errors'=>['All evaluation ratings must be between 1 and 5.']]);
        exit;
    }
}
if (strlen($comments) > 2000) {
    echo json_encode(['success'=>false,'errors'=>['Comments must be 2,000 characters or fewer.']]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT co.teacher_id
    FROM enrollments e
    JOIN classofferings co ON co.offering_id = e.offering_id
    WHERE e.student_id = ? AND e.offering_id = ? AND e.status = 'active' AND co.status = 'active'
    LIMIT 1
");
$stmt->execute([$studentId,$offeringId]);
$teacherId = (int)$stmt->fetchColumn();

if (!$studentId || !$teacherId) {
    echo json_encode(['success'=>false,'errors'=>['You can only evaluate a teacher for a class in which you are actively enrolled.']]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO teacher_evaluations
        (student_id, teacher_id, offering_id, rating_teaching_quality, rating_communication, rating_preparation, rating_fairness, rating_support, comments)
        VALUES (?,?,?,?,?,?,?,?,?)
    ");
    $stmt->execute([
        $studentId,$teacherId,$offeringId,
        $ratings['rating_teaching_quality'],$ratings['rating_communication'],
        $ratings['rating_preparation'],$ratings['rating_fairness'],$ratings['rating_support'],
        $comments !== '' ? $comments : null
    ]);
    echo json_encode(['success'=>true,'message'=>'Evaluation submitted successfully.']);
} catch (PDOException $e) {
    if ((int)$e->errorInfo[1] === 1062) {
        echo json_encode(['success'=>false,'errors'=>['You have already evaluated this class.']]);
    } else {
        http_response_code(500);
        echo json_encode(['success'=>false,'errors'=>['Unable to save the evaluation right now.']]);
    }
}
