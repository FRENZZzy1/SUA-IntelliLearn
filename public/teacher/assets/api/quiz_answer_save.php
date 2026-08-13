<?php
// Path: public/teacher/assets/api/ -> up 4 -> project root -> config/config.php
// (same depth as class_overview_functions.php, which lives alongside this file)
require_once __DIR__ . '/../../../../config/config.php';

// ---- Access control ---------------------------------------------------
requireTeacher();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: ../../class_overview.php');
    exit();
}

// ---- CSRF ---------------------------------------------------------------
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    die('Invalid request (CSRF token mismatch). Please refresh the page and try again.');
}

$userId = (int) $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT teacher_id FROM teachers WHERE user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$teacher = $stmt->fetch();
if (!$teacher) {
    die('Teacher record not found for this account.');
}
$teacherId = (int) $teacher['teacher_id'];

// ---- Read + validate the posted identifiers ----------------------------
$attemptId = filter_input(INPUT_POST, 'attempt_id', FILTER_VALIDATE_INT);
$quizId    = filter_input(INPUT_POST, 'quiz_id', FILTER_VALIDATE_INT);
$subjectId = filter_input(INPUT_POST, 'subject_id', FILTER_VALIDATE_INT);
$sectionId = filter_input(INPUT_POST, 'section_id', FILTER_VALIDATE_INT);
$term      = $_POST['term'] ?? null;

$isCorrectIn = $_POST['is_correct'] ?? []; // [question_id => '1' | '0']
$pointsIn    = $_POST['points'] ?? [];     // [question_id => '3.5']

if (!$attemptId || !$quizId || !$subjectId || !$sectionId) {
    header('Location: ../../courses.php');
    exit();
}

// ---- Ownership check ----------------------------------------------------
// The attempt's quiz must belong to an offering this teacher actually
// teaches, otherwise a crafted POST could let a teacher grade another
// class's quiz. Also grabs the quiz's total possible points for max_score.
$stmt = $pdo->prepare("
    SELECT qa.attempt_id,
           (SELECT COALESCE(SUM(points), 0) FROM quiz_questions WHERE quiz_id = q.quiz_id) AS quiz_total
    FROM quiz_attempts qa
    JOIN quizzes q ON q.quiz_id = qa.quiz_id
    JOIN classofferings co ON co.offering_id = q.offering_id
    WHERE qa.attempt_id = ? AND qa.quiz_id = ? AND co.teacher_id = ?
    LIMIT 1
");
$stmt->execute([$attemptId, $quizId, $teacherId]);
$attemptCheck = $stmt->fetch();
if (!$attemptCheck) {
    die('Attempt not found, or you do not have permission to grade it.');
}
$quizTotalPoints = (float) $attemptCheck['quiz_total'];

// ---- Load this quiz's questions so points can be validated/clamped ------
$stmt = $pdo->prepare("SELECT question_id, points FROM quiz_questions WHERE quiz_id = ?");
$stmt->execute([$quizId]);
$questionPoints = [];
foreach ($stmt->fetchAll() as $row) {
    $questionPoints[(int) $row['question_id']] = (float) $row['points'];
}

if (empty($questionPoints)) {
    die('This quiz has no questions to grade.');
}

// quiz_answers has a UNIQUE KEY on (attempt_id, question_id), so this only
// ever touches rows for questions the student actually has an answer row
// for — it never invents an answer to a question they skipped.
$upsert = $pdo->prepare("
    UPDATE quiz_answers
    SET is_correct = ?, points_awarded = ?
    WHERE attempt_id = ? AND question_id = ?
");

$pdo->beginTransaction();
try {
    foreach ($questionPoints as $questionId => $maxPoints) {
        // Only questions the form actually rendered inputs for (i.e. ones the
        // student answered) are present here; skipped questions are left alone.
        if (!array_key_exists($questionId, $pointsIn)) {
            continue;
        }

        $rawPoints = $pointsIn[$questionId];
        if ($rawPoints === '' || $rawPoints === null || !is_numeric($rawPoints)) {
            continue; // left blank / not a number -> don't touch this answer
        }

        $points  = max(0, min($maxPoints, (float) $rawPoints));
        $correct = (isset($isCorrectIn[$questionId]) && $isCorrectIn[$questionId] === '1') ? 1 : 0;

        $upsert->execute([$correct, $points, $attemptId, $questionId]);
    }

    // Recompute the attempt's total from the (now updated) per-question scores
    // rather than trusting a client-submitted total.
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(points_awarded), 0) AS total FROM quiz_answers WHERE attempt_id = ?");
    $stmt->execute([$attemptId]);
    $newScore = (float) $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        UPDATE quiz_attempts
        SET score = ?, max_score = ?, status = 'graded'
        WHERE attempt_id = ?
    ");
    $stmt->execute([$newScore, $quizTotalPoints, $attemptId]);

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    die('Could not save corrections. Please try again.');
}

// ---- Redirect back to the same answer-review modal ----------------------
$params = [
    'subject_id' => $subjectId,
    'section_id' => $sectionId,
    'view'       => 'quizzes',
    'quiz_id'    => $quizId,
    'attempt_id' => $attemptId,
];
if ($term) {
    $params['term'] = $term;
}
header('Location: ../../class_overview.php?' . http_build_query($params));
exit();