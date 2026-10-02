<?php
/**
 * Save teacher-entered quiz scores from the quiz grading grid.
 *
 * Direct POST target:
 * public/teacher/class_overview.php
 *     -> assets/api/quiz_score_save.php
 *
 * This endpoint intentionally only updates quiz_attempts.score. Detailed
 * per-question corrections are handled separately by quiz_answer_save.php.
 */

require_once __DIR__ . '/../../../../config/config.php';

// ---- Access control ---------------------------------------------------------
if (!isLoggedIn() || ($_SESSION['role'] ?? '') !== 'teacher') {
    header('Location: ../../login.php');
    exit();
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: ../../courses.php');
    exit();
}

// ---- Read request data ------------------------------------------------------
$subjectId = filter_input(INPUT_POST, 'subject_id', FILTER_VALIDATE_INT);
$sectionId = filter_input(INPUT_POST, 'section_id', FILTER_VALIDATE_INT);
$quizId    = filter_input(INPUT_POST, 'quiz_id', FILTER_VALIDATE_INT);
$offeringId = filter_input(INPUT_POST, 'offering_id', FILTER_VALIDATE_INT);
$term      = $_POST['term'] ?? null;

$backUrl = '../../class_overview.php?' . http_build_query([
    'subject_id' => $subjectId,
    'section_id' => $sectionId,
    'term'       => $term,
    'view'       => 'quizzes',
    'quiz_id'    => $quizId,
]);

// ---- CSRF -------------------------------------------------------------------
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    setFlashMessage('error', 'Your session expired. Please try again.');
    header('Location: ' . $backUrl);
    exit();
}

if (!$quizId || !$offeringId) {
    setFlashMessage('error', 'Invalid quiz grading request.');
    header('Location: ../../courses.php');
    exit();
}

// ---- Resolve the logged-in teacher -----------------------------------------
$userId = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare(
    'SELECT teacher_id FROM teachers WHERE user_id = ? LIMIT 1'
);
$stmt->execute([$userId]);
$teacher = $stmt->fetch();

if (!$teacher) {
    setFlashMessage('error', 'Teacher record not found for this account.');
    header('Location: ../../courses.php');
    exit();
}

$teacherId = (int) $teacher['teacher_id'];

// ---- Verify quiz ownership --------------------------------------------------
// Never trust quiz_id/offering_id from the form. The quiz must belong to the
// supplied offering, and that offering must belong to the logged-in teacher.
$stmt = $pdo->prepare(
    'SELECT
        q.quiz_id,
        q.offering_id,
        (
            SELECT COALESCE(SUM(qq.points), 0)
            FROM quiz_questions qq
            WHERE qq.quiz_id = q.quiz_id
        ) AS total_points
     FROM quizzes q
     JOIN classofferings co ON co.offering_id = q.offering_id
     WHERE q.quiz_id = ?
       AND q.offering_id = ?
       AND co.teacher_id = ?
     LIMIT 1'
);
$stmt->execute([$quizId, $offeringId, $teacherId]);
$quiz = $stmt->fetch();

if (!$quiz) {
    setFlashMessage('error', 'Quiz not found or you do not have access to it.');
    header('Location: ../../courses.php');
    exit();
}

$quizTotalPoints = (float) $quiz['total_points'];

// ---- Read submitted scores --------------------------------------------------
// Form shape:
// score[attempt_id] = score
$scores = $_POST['score'] ?? [];

if (!is_array($scores)) {
    setFlashMessage('error', 'Invalid score data.');
    header('Location: ' . $backUrl);
    exit();
}

// ---- Load the attempts that are actually part of this teacher's quiz -------
// This prevents a crafted POST from changing another quiz's attempt.
$attemptIds = [];
foreach (array_keys($scores) as $attemptId) {
    $attemptId = filter_var($attemptId, FILTER_VALIDATE_INT);
    if ($attemptId !== false && $attemptId > 0) {
        $attemptIds[] = (int) $attemptId;
    }
}

if (!$attemptIds) {
    setFlashMessage('error', 'No grades were entered.');
    header('Location: ' . $backUrl);
    exit();
}

$placeholders = implode(',', array_fill(0, count($attemptIds), '?'));

$stmt = $pdo->prepare(
    "SELECT
        qa.attempt_id,
        qa.max_score,
        qa.status
     FROM quiz_attempts qa
     WHERE qa.quiz_id = ?
       AND qa.attempt_id IN ($placeholders)"
);
$stmt->execute(array_merge([$quizId], $attemptIds));

$validAttempts = [];
foreach ($stmt->fetchAll() as $row) {
    $validAttempts[(int) $row['attempt_id']] = $row;
}

// ---- Update scores ----------------------------------------------------------
// A blank score means "leave unchanged", matching the assignment grading flow.
// Scores are clamped server-side so the browser's max attribute cannot be
// bypassed to save an impossible value.
$update = $pdo->prepare(
    "UPDATE quiz_attempts
     SET score = ?,
         max_score = ?,
         status = 'graded'
     WHERE attempt_id = ?
       AND quiz_id = ?"
);

$savedCount = 0;

$pdo->beginTransaction();

try {
    foreach ($scores as $attemptId => $rawScore) {
        $attemptId = filter_var($attemptId, FILTER_VALIDATE_INT);

        if ($attemptId === false || $attemptId <= 0 || !isset($validAttempts[$attemptId])) {
            continue;
        }

        $rawScore = trim((string) $rawScore);

        // Blank inputs do not erase an existing grade.
        if ($rawScore === '') {
            continue;
        }

        $score = filter_var($rawScore, FILTER_VALIDATE_FLOAT);

        if ($score === false || !is_finite((float) $score) || $score < 0) {
            continue;
        }

        // Prefer the attempt's existing max_score when available. Otherwise
        // fall back to the current quiz total.
        $maxScore = $validAttempts[$attemptId]['max_score'] !== null
            ? (float) $validAttempts[$attemptId]['max_score']
            : $quizTotalPoints;

        // If an old/incomplete attempt has no max_score and the quiz currently
        // has no points, still keep the value bounded at zero.
        $maxScore = max(0.0, $maxScore);
        $score = min((float) $score, $maxScore);
        $score = round($score, 2);

        $update->execute([$score, $maxScore, $attemptId, $quizId]);
        $savedCount++;
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    setFlashMessage('error', 'Could not save quiz scores. Please try again.');
    header('Location: ' . $backUrl);
    exit();
}

if ($savedCount > 0) {
    setFlashMessage('success', "Saved scores for {$savedCount} student(s).");
} else {
    setFlashMessage('error', 'No valid grades were entered.');
}

header('Location: ' . $backUrl);
exit();
