<?php
/**
 * teacher_evaluation_submit.php
 * -----------------------------------------------------------------
 * POST endpoint behind the evaluation form on student/teacher_evaluation.php.
 * Always returns JSON.
 *
 * Form fields: csrf, offering_id, ratings[question_key] = 1..5,
 *              strengths (optional), improvements (optional)
 *
 * Anonymity: the ANSWERS are stored without any student_id. A separate
 * "submission" row records only that this student finished this class (so
 * they can't submit twice and the completion rate can be computed). The two
 * are inserted in one transaction but share no identifying column.
 * -----------------------------------------------------------------
 */

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../includes/teacher_evaluation.php';

header('Content-Type: application/json');

function teval_submit_fail(int $code, string $message): void
{
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message]);
    exit();
}

if (!isLoggedIn() || ($_SESSION['role'] ?? '') !== 'student') {
    teval_submit_fail(401, 'Your session has expired. Please refresh the page and log in again.');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    teval_submit_fail(405, 'Invalid request method.');
}
if (!validateCSRFToken($_POST['csrf'] ?? '')) {
    teval_submit_fail(419, 'Your session expired. Please refresh the page and try again.');
}
if (!teval_tables_ready($pdo)) {
    teval_submit_fail(500, 'Teacher evaluation isn\'t set up yet. Please tell your administrator.');
}

$stmt = $pdo->prepare("SELECT student_id FROM students WHERE user_id = ? LIMIT 1");
$stmt->execute([(int) $_SESSION['user_id']]);
$studentId = (int) $stmt->fetchColumn();
if ($studentId <= 0) {
    teval_submit_fail(403, 'Student record not found for this account.');
}

$round = teval_get_open_round($pdo);
if (!$round) {
    teval_submit_fail(409, 'This survey is closed, so evaluations are no longer being accepted.');
}

// ---- Which class? Must be one this student can evaluate in this round ----
$offeringId = (int) ($_POST['offering_id'] ?? 0);
$target = null;
foreach (teval_student_classes($pdo, $studentId, $round) as $row) {
    if ((int) $row['offering_id'] === $offeringId) { $target = $row; break; }
}
if (!$target) {
    teval_submit_fail(403, 'You can only evaluate teachers of classes you are enrolled in for this survey.');
}
if ((int) $target['done'] > 0) {
    teval_submit_fail(409, 'You have already evaluated this class.');
}

// ---- Ratings: every question answered, integers 1-5 ----
$posted  = $_POST['ratings'] ?? [];
$ratings = [];
foreach (array_keys(teval_questions_flat()) as $key) {
    $v = is_array($posted) ? ($posted[$key] ?? null) : null;
    if (!is_string($v) || !preg_match('/^[1-5]$/', $v)) {
        teval_submit_fail(422, 'Please answer every rating question before submitting.');
    }
    $ratings[$key] = (int) $v;
}

$strengths    = teval_clean_comment($_POST['strengths'] ?? '');
$improvements = teval_clean_comment($_POST['improvements'] ?? '');

// ---- Save ---------------------------------------------------------------
try {
    $pdo->beginTransaction();

    // Unique (round, student, class): a double-click or a second tab fails here.
    $pdo->prepare("INSERT INTO teacher_evaluation_submissions (round_id, student_id, offering_id) VALUES (?, ?, ?)")
        ->execute([(int) $round['round_id'], $studentId, $offeringId]);

    $pdo->prepare("INSERT INTO teacher_evaluation_responses (round_id, offering_id, teacher_id, strengths, improvements) VALUES (?, ?, ?, ?, ?)")
        ->execute([(int) $round['round_id'], $offeringId, (int) $target['teacher_id'], $strengths, $improvements]);
    $responseId = (int) $pdo->lastInsertId();

    $ins = $pdo->prepare("INSERT INTO teacher_evaluation_answers (response_id, question_key, rating) VALUES (?, ?, ?)");
    foreach ($ratings as $key => $rating) {
        $ins->execute([$responseId, $key, $rating]);
    }

    $pdo->commit();
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if ($e->getCode() === '23000') {
        teval_submit_fail(409, 'You have already evaluated this class.');
    }
    teval_submit_fail(500, 'Something went wrong while saving your evaluation. Please try again.');
}

$remaining = 0;
foreach (teval_student_classes($pdo, $studentId, $round) as $row) {
    if (!(int) $row['done']) $remaining++;
}

echo json_encode(['success' => true, 'remaining' => $remaining]);
