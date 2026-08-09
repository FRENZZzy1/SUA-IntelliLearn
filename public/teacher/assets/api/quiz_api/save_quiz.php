<?php
/**
 * save_quiz.php
 *
 * POST (application/json), Accept: application/json:
 * {
 *   "offering_id": 12,
 *   "job_id": 5,                 // optional — links back to quiz_generation_jobs
 *   "title": "...",
 *   "description": "...",
 *   "time_limit_minutes": 30,    // optional
 *   "max_attempts": 1,
 *   "shuffle_questions": true,
 *   "status": "draft" | "published",
 *   "csrf_token": "...",
 *   "questions": [
 *     {
 *       "question_text": "...",
 *       "question_type": "mcq" | "true_false" | "short_answer",
 *       "points": 1,
 *       "ai_generated": true,
 *       "teacher_edited": false,
 *       "choices": [{"text": "...", "is_correct": true}, ...],   // mcq/true_false
 *       "correct_answer": "..."                                  // short_answer only
 *     }
 *   ]
 * }
 *
 * For short_answer questions, the accepted answer is stored as a single
 * quiz_choices row with is_correct = 1 (there's no separate "correct
 * answer" column on quiz_questions in this schema) — it's never shown to
 * students as a pickable choice, just used as the grading reference.
 */

header('Content-Type: application/json');

require_once $_SERVER['DOCUMENT_ROOT'] . '/SUA-IntelliLearn/config/config.php';
requireTeacher();

$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);

if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'errors' => ['Malformed request.']]);
    exit();
}

if (!validateCSRFToken($body['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'errors' => ['Invalid or expired security token. Please refresh the page.']]);
    exit();
}

$offeringId  = (int) ($body['offering_id'] ?? 0);
$jobId       = isset($body['job_id']) ? (int) $body['job_id'] : null;
$title       = trim($body['title'] ?? '');
$description = trim($body['description'] ?? '');
$timeLimit   = isset($body['time_limit_minutes']) && $body['time_limit_minutes'] !== '' ? (int) $body['time_limit_minutes'] : null;
$maxAttempts = max(1, (int) ($body['max_attempts'] ?? 1));
$shuffle     = !empty($body['shuffle_questions']) ? 1 : 0;
$status      = in_array($body['status'] ?? '', ['draft', 'published'], true) ? $body['status'] : 'draft';
$questions   = is_array($body['questions'] ?? null) ? $body['questions'] : [];

$errors = [];
if ($offeringId <= 0) $errors[] = 'Missing subject/class.';
if ($title === '') $errors[] = 'Quiz title is required.';
if (empty($questions)) $errors[] = 'A quiz needs at least one question.';

// Validate each question shape before touching the database
foreach ($questions as $i => $q) {
    $n = $i + 1;
    $qType = $q['question_type'] ?? '';
    if (trim($q['question_text'] ?? '') === '') {
        $errors[] = "Question {$n}: text is required.";
        continue;
    }
    if (!in_array($qType, ['mcq', 'true_false', 'short_answer'], true)) {
        $errors[] = "Question {$n}: invalid question type.";
        continue;
    }
    if ($qType === 'short_answer') {
        if (trim($q['correct_answer'] ?? '') === '') {
            $errors[] = "Question {$n}: an accepted answer is required.";
        }
    } else {
        $choices = $q['choices'] ?? [];
        $correctCount = 0;
        $validChoiceCount = 0;
        foreach ($choices as $c) {
            if (trim($c['text'] ?? '') === '') continue;
            $validChoiceCount++;
            if (!empty($c['is_correct'])) $correctCount++;
        }
        if ($validChoiceCount < 2) {
            $errors[] = "Question {$n}: needs at least 2 choices.";
        }
        if ($correctCount !== 1) {
            $errors[] = "Question {$n}: must have exactly one correct choice.";
        }
    }
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit();
}

// ------------------------------------------------------------------
// Confirm teacher owns this offering
// ------------------------------------------------------------------
$stmt = $pdo->prepare("SELECT teacher_id FROM teachers WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$teacherRow = $stmt->fetch();
if (!$teacherRow) {
    http_response_code(403);
    echo json_encode(['success' => false, 'errors' => ['Teacher profile not found.']]);
    exit();
}
$teacherId = (int) $teacherRow['teacher_id'];

$stmt = $pdo->prepare("SELECT offering_id FROM classofferings WHERE offering_id = ? AND teacher_id = ?");
$stmt->execute([$offeringId, $teacherId]);
if (!$stmt->fetch()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'errors' => ["You're not assigned to that class."]]);
    exit();
}

// ------------------------------------------------------------------
// Persist — quizzes -> quiz_questions -> quiz_choices, all-or-nothing
// ------------------------------------------------------------------
try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO quizzes
            (offering_id, created_by, title, description, generation_source,
             time_limit_minutes, max_attempts, shuffle_questions, status)
        VALUES (?, ?, ?, ?, 'topic', ?, ?, ?, ?)
    ");
    $stmt->execute([$offeringId, $teacherId, $title, $description, $timeLimit, $maxAttempts, $shuffle, $status]);
    $quizId = (int) $pdo->lastInsertId();

    $qStmt = $pdo->prepare("
        INSERT INTO quiz_questions
            (quiz_id, question_text, question_type, points, order_index, ai_generated, teacher_edited)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $cStmt = $pdo->prepare("
        INSERT INTO quiz_choices (question_id, choice_text, is_correct, order_index)
        VALUES (?, ?, ?, ?)
    ");

    foreach ($questions as $i => $q) {
        $qType     = $q['question_type'];
        $points    = isset($q['points']) && is_numeric($q['points']) ? (float) $q['points'] : 1;
        $aiGen     = !empty($q['ai_generated']) ? 1 : 0;
        $edited    = !empty($q['teacher_edited']) ? 1 : 0;

        $qStmt->execute([$quizId, trim($q['question_text']), $qType, $points, $i, $aiGen, $edited]);
        $questionId = (int) $pdo->lastInsertId();

        if ($qType === 'short_answer') {
            // Single reference-answer row; not presented to students as a pickable choice.
            $cStmt->execute([$questionId, trim($q['correct_answer']), 1, 0]);
        } else {
            $order = 0;
            foreach ($q['choices'] as $c) {
                $text = trim($c['text'] ?? '');
                if ($text === '') continue;
                $cStmt->execute([$questionId, $text, !empty($c['is_correct']) ? 1 : 0, $order]);
                $order++;
            }
        }
    }

    if ($jobId) {
        $pdo->prepare("UPDATE quiz_generation_jobs SET quiz_id = ? WHERE job_id = ? AND requested_by = ?")
            ->execute([$quizId, $jobId, $_SESSION['user_id']]);
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'quiz_id' => $quizId,
        'status'  => $status,
        'message' => $status === 'published' ? 'Quiz published.' : 'Quiz saved as draft.',
    ]);
} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'errors' => ['Could not save the quiz. Please try again.']]);
}
