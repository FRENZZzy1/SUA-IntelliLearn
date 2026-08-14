<?php
/**
 * generate_quiz.php
 *
 * POST (application/json), Accept: application/json:
 * {
 *   "offering_id": 12,
 *   "num_items": 10,
 *   "question_type": "mcq" | "true_false" | "identification" | "mixed",
 *   "difficulty": "easy" | "average" | "difficult",
 *   "topic": "free-text description of the topic/coverage",
 *   "title": "optional quiz title",
 *   "csrf_token": "..."
 * }
 *
 * Response:
 * { "success": true, "job_id": 5, "model_used": "...", "quiz": {...}, "questions": [...] }
 *
 * This endpoint only calls the AI and returns a draft — nothing is written
 * to quizzes/quiz_questions/quiz_choices until the teacher hits Save
 * (save_quiz.php). We DO log the attempt in quiz_generation_jobs so
 * there's an audit trail even for drafts the teacher discards.
 */

header('Content-Type: application/json');

require_once $_SERVER['DOCUMENT_ROOT'] . '/SUA-IntelliLearn/config/config.php';
requireTeacher();

// ------------------------------------------------------------------
// Parse + validate input
// ------------------------------------------------------------------
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
$numItems    = (int) ($body['num_items'] ?? 0);
$questionType = $body['question_type'] ?? 'mixed';
$difficulty   = $body['difficulty'] ?? 'average';
$topic        = trim($body['topic'] ?? '');
$titleInput   = trim($body['title'] ?? '');

$errors = [];

if ($offeringId <= 0) {
    $errors[] = 'Please choose a subject/class.';
}
if ($numItems < 1 || $numItems > 50) {
    $errors[] = 'Number of items must be between 1 and 50.';
}
if (!in_array($questionType, ['mcq', 'true_false', 'identification', 'mixed'], true)) {
    $errors[] = 'Invalid question type.';
}
if (!in_array($difficulty, ['easy', 'average', 'difficult'], true)) {
    $errors[] = 'Invalid difficulty.';
}
if ($topic === '') {
    $errors[] = 'Please describe the topic to cover.';
} elseif (mb_strlen($topic) > 2000) {
    $errors[] = 'Topic description is too long (max 2000 characters).';
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit();
}

// ------------------------------------------------------------------
// Resolve teacher + confirm they own this offering
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

$stmt = $pdo->prepare("
    SELECT co.offering_id, s.subject_name, sec.section_name, sec.grade_level
    FROM classofferings co
    JOIN subjects s ON s.subject_id = co.subject_id
    JOIN sections sec ON sec.section_id = co.section_id
    WHERE co.offering_id = ? AND co.teacher_id = ?
");
$stmt->execute([$offeringId, $teacherId]);
$offering = $stmt->fetch();

if (!$offering) {
    http_response_code(403);
    echo json_encode(['success' => false, 'errors' => ["You're not assigned to that class."]]);
    exit();
}

// ------------------------------------------------------------------
// Log the generation job (pending -> processing -> completed/failed)
// ------------------------------------------------------------------
$stmt = $pdo->prepare("
    INSERT INTO quiz_generation_jobs (offering_id, requested_by, source_type, topic_prompt, status)
    VALUES (?, ?, 'topic', ?, 'processing')
");
$stmt->execute([$offeringId, $_SESSION['user_id'], $topic]);
$jobId = (int) $pdo->lastInsertId();

// ------------------------------------------------------------------
// Build the prompt
// ------------------------------------------------------------------
$typeInstruction = match ($questionType) {
    'mcq'            => 'Every question must be multiple choice ("mcq") with exactly 4 choices.',
    'true_false'     => 'Every question must be true/false ("true_false") with exactly 2 choices: "True" and "False".',
    'identification' => 'Every question must be identification/short-answer ("short_answer") — a single accepted correct answer, no choices.',
    default          => 'Use a mix of question types across "mcq", "true_false", and "short_answer" — vary them across the quiz rather than clustering the same type together.',
};

$difficultyInstruction = match ($difficulty) {
    'easy'      => 'Keep questions straightforward, testing recall and basic understanding.',
    'difficult' => 'Make questions challenging, testing application, analysis, or synthesis of the topic — avoid pure recall.',
    default     => 'Use a moderate difficulty that tests real understanding, not just memorization.',
};

$gradeContext = 'Grade ' . (int) $offering['grade_level'] . ' — ' . $offering['subject_name'] . ' (' . $offering['section_name'] . ')';

$schemaExample = <<<JSON
{
  "title": "short descriptive quiz title",
  "description": "one-sentence description of what the quiz covers",
  "questions": [
    {
      "question_text": "string",
      "question_type": "mcq",
      "choices": [
        {"text": "string", "is_correct": true},
        {"text": "string", "is_correct": false},
        {"text": "string", "is_correct": false},
        {"text": "string", "is_correct": false}
      ]
    },
    {
      "question_text": "string",
      "question_type": "true_false",
      "choices": [
        {"text": "True", "is_correct": true},
        {"text": "False", "is_correct": false}
      ]
    },
    {
      "question_text": "string",
      "question_type": "short_answer",
      "correct_answer": "string"
    }
  ]
}
JSON;

$systemPrompt = "You are a curriculum-aligned quiz writer for a K-12 learning management system. "
    . "You output ONLY valid JSON matching the exact schema you're given — no markdown fences, no commentary, no trailing text.";

$userPrompt = <<<PROMPT
Create a {$numItems}-item quiz for: {$gradeContext}.

Topic to cover: "{$topic}"

Question type rule: {$typeInstruction}
Difficulty: {$difficultyInstruction}

Requirements:
- Return exactly {$numItems} questions in the "questions" array.
- For "mcq" questions: exactly 4 choices, exactly one with "is_correct": true, the other 3 plausible but clearly wrong (no "all of the above").
- For "true_false" questions: exactly 2 choices, "True" and "False" in that order, exactly one marked "is_correct": true.
- For "short_answer" questions: no "choices" field — instead include "correct_answer" as a short, unambiguous expected answer (a word or short phrase).
- Questions must stay strictly on-topic for what was described above and appropriate for the stated grade level.
- Do not repeat the same question twice.
- Output raw JSON only, matching this exact structure:

{$schemaExample}
PROMPT;

// ------------------------------------------------------------------
// Call Google Gemini — try a short list of models in order, since a
// given model can occasionally be overloaded/rate-limited.
// ------------------------------------------------------------------
$preferredModels = [
    'gemini-3.5-flash-lite',
    'gemini-3.1-flash-lite',
    'gemini-2.5-flash-lite',
    'gemini-2.5-flash',
];

function callGemini(string $model, string $systemPrompt, string $userPrompt): array {
    if (!defined('GEMINI_API_KEY') || GEMINI_API_KEY === '') {
        return ['ok' => false, 'error' => 'GEMINI_API_KEY is not configured in config.php.'];
    }

    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 45,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'x-goog-api-key: ' . GEMINI_API_KEY,
        ],
        CURLOPT_POST       => true,
        CURLOPT_POSTFIELDS => json_encode([
            'systemInstruction' => [
                'parts' => [['text' => $systemPrompt]],
            ],
            'contents' => [
                ['role' => 'user', 'parts' => [['text' => $userPrompt]]],
            ],
            'generationConfig' => [
                'temperature'      => 0.7,
                'maxOutputTokens'  => 4000,
                // Ask Gemini to return raw JSON directly — no markdown
                // fences to strip, unlike the OpenRouter free models.
                'responseMimeType' => 'application/json',
            ],
        ]),
    ]);

    $response  = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return ['ok' => false, 'error' => "cURL error: {$curlError}"];
    }
    if ($httpCode < 200 || $httpCode >= 300) {
        return ['ok' => false, 'error' => "Gemini returned HTTP {$httpCode}: " . substr($response, 0, 300)];
    }

    $decoded = json_decode($response, true);
    $content = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? null;

    if (!$content) {
        // Common cause: the response was cut off or blocked by safety
        // filters, in which case candidates[0].finishReason explains why.
        $finishReason = $decoded['candidates'][0]['finishReason'] ?? 'unknown';
        return ['ok' => false, 'error' => "No content in Gemini response (finishReason: {$finishReason})."];
    }

    // Belt-and-suspenders: strip markdown fences in case a model adds them
    // despite responseMimeType being set to application/json.
    $content = trim($content);
    $content = preg_replace('/^```(?:json)?\s*/i', '', $content);
    $content = preg_replace('/```\s*$/', '', $content);

    $quizJson = json_decode($content, true);
    if (!is_array($quizJson) || !isset($quizJson['questions']) || !is_array($quizJson['questions'])) {
        return ['ok' => false, 'error' => 'Model did not return valid quiz JSON.'];
    }

    return ['ok' => true, 'quiz' => $quizJson];
}

$result   = null;
$modelUsed = null;
$attemptErrors = [];

foreach ($preferredModels as $model) {
    $attempt = callGemini($model, $systemPrompt, $userPrompt);
    if ($attempt['ok']) {
        $result    = $attempt['quiz'];
        $modelUsed = $model;
        break;
    }
    $attemptErrors[] = "{$model}: {$attempt['error']}";
}

if ($result === null) {
    $pdo->prepare("UPDATE quiz_generation_jobs SET status = 'failed', error_message = ?, completed_at = NOW() WHERE job_id = ?")
        ->execute([implode(' | ', $attemptErrors), $jobId]);

    http_response_code(502);
    echo json_encode([
        'success' => false,
        'errors'  => ['The AI model is unavailable right now. Please try again in a moment.'],
    ]);
    exit();
}

// ------------------------------------------------------------------
// Normalize + validate the AI's output before handing it to the browser
// ------------------------------------------------------------------
$cleanQuestions = [];
foreach ($result['questions'] as $q) {
    $qText = trim($q['question_text'] ?? '');
    $qType = $q['question_type'] ?? '';
    if ($qText === '' || !in_array($qType, ['mcq', 'true_false', 'short_answer'], true)) {
        continue; // skip malformed entries rather than failing the whole batch
    }

    $clean = [
        'question_text' => $qText,
        'question_type' => $qType,
        'points'        => 1,
    ];

    if ($qType === 'short_answer') {
        $answer = trim($q['correct_answer'] ?? '');
        if ($answer === '') {
            continue;
        }
        $clean['correct_answer'] = $answer;
        $clean['choices'] = [];
    } else {
        $choices = [];
        $hasCorrect = false;
        foreach ((array) ($q['choices'] ?? []) as $c) {
            $text = trim($c['text'] ?? '');
            if ($text === '') continue;
            $isCorrect = !empty($c['is_correct']);
            if ($isCorrect) $hasCorrect = true;
            $choices[] = ['text' => $text, 'is_correct' => $isCorrect];
        }
        if (count($choices) < 2 || !$hasCorrect) {
            continue; // unusable question, drop it
        }
        // Ensure exactly one correct choice — if the model marked >1, keep only the first
        $seenCorrect = false;
        foreach ($choices as &$c) {
            if ($c['is_correct']) {
                if ($seenCorrect) { $c['is_correct'] = false; }
                $seenCorrect = true;
            }
        }
        unset($c);
        $clean['choices'] = $choices;
    }

    $cleanQuestions[] = $clean;
}

if (empty($cleanQuestions)) {
    $pdo->prepare("UPDATE quiz_generation_jobs SET status = 'failed', error_message = 'Model returned no usable questions.', completed_at = NOW() WHERE job_id = ?")
        ->execute([$jobId]);

    http_response_code(502);
    echo json_encode(['success' => false, 'errors' => ['The AI returned no usable questions. Try adjusting the topic and generating again.']]);
    exit();
}

$pdo->prepare("UPDATE quiz_generation_jobs SET status = 'completed', completed_at = NOW() WHERE job_id = ?")
    ->execute([$jobId]);

$quizTitle = $titleInput !== '' ? $titleInput : trim($result['title'] ?? ('Quiz: ' . $offering['subject_name']));
$quizDescription = trim($result['description'] ?? $topic);

echo json_encode([
    'success'    => true,
    'job_id'     => $jobId,
    'model_used' => $modelUsed,
    'quiz'       => [
        'title'       => $quizTitle,
        'description' => $quizDescription,
        'offering_id' => $offeringId,
    ],
    'questions' => $cleanQuestions,
]);