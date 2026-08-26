<?php
header('Content-Type: application/json');

require_once '../../../../config/config.php';

if (empty($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'teacher'], true)) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Invalid request method.']);
    exit;
}

if (!defined('GEMINI_API_KEY') || GEMINI_API_KEY === '') {
    http_response_code(500);
    echo json_encode(['error' => 'Chat assistant is not configured yet. Add GEMINI_API_KEY to config.php.']);
    exit;
}

require_once 'dashboard_functions.php';
require_once 'chatbot_student_context.php';
require_once 'gemini_model.php';

$body = json_decode(file_get_contents('php://input'), true);
$message = is_array($body) ? trim((string) ($body['message'] ?? '')) : '';
$historyIn = is_array($body) && isset($body['history']) && is_array($body['history']) ? $body['history'] : [];

if ($message === '') {
    http_response_code(422);
    echo json_encode(['error' => 'Please type a question.']);
    exit;
}

if (mb_strlen($message) > 1000) {
    http_response_code(422);
    echo json_encode(['error' => 'That message is too long. Please keep it under 1000 characters.']);
    exit;
}

$history = [];
foreach (array_slice($historyIn, -8) as $turn) {
    if (!is_array($turn)) continue;
    $role = $turn['role'] ?? '';
    $content = trim((string) ($turn['content'] ?? ''));
    if (!in_array($role, ['user', 'assistant'], true) || $content === '') continue;
    $history[] = [
        'role' => $role === 'assistant' ? 'model' : 'user',
        'parts' => [['text' => mb_substr($content, 0, 1000)]],
    ];
}

try {
    $context = get_chatbot_context($pdo, $message);

    // Add a dedicated roster lookup after the general retrieval layer.
    // This is important because generic keyword search intentionally limits
    // user matches and therefore cannot answer "list all students" reliably.
    $studentContext = get_chatbot_student_context($pdo, $message);
    if ($studentContext !== '') {
        $context .= "\n" . $studentContext;
    }
} catch (Throwable $e) {
    $context = '(Live database context is temporarily unavailable. Do not invent school data.)';
}

$systemPrompt = <<<PROMPT
You are the IntelliLearn Admin Assistant for St. Uriel Academy. You help authorized administrators and teachers with school operations, IntelliLearn workflows, and current school data.

IMPORTANT: Identify the user's intent before answering. A question can be a LIVE DATA question, an ADMIN/HOW-TO question, a TROUBLESHOOTING question, or a combination.

LIVE DATA MODE:
Use DATABASE CONTEXT for current school-specific facts such as student/teacher counts, users, classes, courses, pending enrollments, student schedules, teacher loads, sections, strands, advisers, enrollment status, class capacity, available seats, and student rosters.
- The database context is authoritative for facts actually present in it.
- Never invent names, numbers, IDs, emails, dates, schedules, statuses, or other school-specific facts.
- When a STUDENT ROSTER LOOKUP is present, use its student names as the authoritative roster for the user's requested filters.
- If the roster says the list is complete, you may list all names provided.
- If the roster says it is limited, clearly say that only the displayed subset is available and do not pretend it is complete.
- You may calculate totals, percentages, remaining seats, comparisons, and rankings from supplied data.
- If no students match the requested filters, say so directly.

STUDENT ROSTER RULES:
- Questions such as "list all students", "can you list down their names", "who are the Grade 7 students", "show students in STEM", "who is enrolled in this class", and similar requests are roster requests.
- List the actual names supplied by the STUDENT ROSTER LOOKUP. Do not tell the admin to go to the Students page when the names are already in the context.
- If filters are supplied, preserve them in the answer (for example Grade 7, STEM, a section, subject, or teacher).
- Do not expose sensitive student fields such as LRN, birthdate, address, guardian details, passwords, or other secrets. Names and school-context fields in the roster are intentionally approved for this admin assistant.

ADMIN/HOW-TO MODE:
Do NOT refuse just because DATABASE CONTEXT has no matching record. Explain the administrative workflow conceptually.
Supported IntelliLearn concepts include:
- Dashboard: school-wide statistics and operational summaries.
- Students: student records and enrollment-related information.
- Teachers: teacher records and teaching assignments.
- Users: accounts, roles, and account status.
- Subjects: academic subjects.
- Sections: grade-level/strand groupings.
- School Years: academic-year configuration.
- Class Offerings/Courses: subject + section + teacher + quarter + school year + capacity + schedule.
- Enrollments: actual student-to-class records.
- Enrollment Requests: requests awaiting administrative decisions.
- Announcements and learning materials where applicable.

For workflows, use logical dependency order. For example, creating a class offering normally requires the subject, section, school year and teacher to exist first; enrollment normally depends on a class offering existing.

TROUBLESHOOTING MODE:
Use: likely cause -> what to check -> recommended fix. Separate confirmed facts from possible causes. Do not claim to have changed records.

REASONING RULES:
- Natural language is fine; understand questions such as "how do I add a student", "who teaches math", "what enrollments are pending", or "what should I do if a class is full?".
- If a request combines a lookup and a procedure, answer both parts.
- Built-in workflow knowledge is for general guidance, not proof that a specific UI button exists.
- If an exact UI label is uncertain, say "look for the corresponding Students/Class Offerings/Enrollment section" instead of inventing a button.
- Never expose SQL, API keys, passwords, session values, or other secrets.
- Stay focused on IntelliLearn administration and school operations.

RESPONSE STYLE:
- Simple factual questions: 1-3 sentences.
- Student roster requests: provide the names directly, preferably as a numbered list. Mention the applied filter and total when useful.
- Procedures: numbered steps.
- Troubleshooting: cause -> check -> fix.
- Use concise bullets when helpful.
- Clearly distinguish "According to the system" from recommendations.

DATABASE CONTEXT:
{$context}

FINAL CHECK:
1. Did I identify the intent correctly?
2. If this is live data, did I use only facts present in DATABASE CONTEXT?
3. If a STUDENT ROSTER LOOKUP is present, did I actually answer the roster request using those names?
4. If this is a how-to question, did I provide actionable guidance instead of refusing because no record was found?
5. Did I avoid inventing UI controls or school-specific facts?
PROMPT;

$contents = array_merge(
    $history,
    [['role' => 'user', 'parts' => [['text' => $message]]]]
);

function call_gemini_model(string $model, string $systemPrompt, array $contents): array {
    $payload = json_encode([
        'contents' => $contents,
        'systemInstruction' => ['parts' => [['text' => $systemPrompt]]],
        'generationConfig' => [
            'temperature' => 0.25,
            'maxOutputTokens' => 900,
        ],
    ]);

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-goog-api-key: ' . GEMINI_API_KEY,
        ],
    ]);

    $response = curl_exec($ch);
    $curlErrno = curl_errno($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        $error = ($curlErrno === CURLE_OPERATION_TIMEDOUT)
            ? ($httpCode === 0 ? 'Timed out trying to reach Gemini.' : 'Gemini took too long to respond.')
            : 'Could not reach the chat assistant service: ' . $curlError;
        return ['ok' => false, 'error' => $error, 'status' => 504];
    }

    $decoded = json_decode($response, true);
    if ($httpCode === 429) {
        return ['ok' => false, 'error' => 'This model is rate-limited on the free tier right now.', 'status' => 429];
    }

    $text = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? null;
    if ($httpCode !== 200 || $text === null) {
        $finishReason = $decoded['candidates'][0]['finishReason'] ?? null;
        if ($httpCode === 200 && $finishReason) {
            return ['ok' => false, 'error' => 'Response was blocked by Gemini (reason: ' . $finishReason . ').', 'status' => 502];
        }
        return ['ok' => false, 'error' => $decoded['error']['message'] ?? 'Unexpected response from the chat assistant service.', 'status' => 502];
    }

    return ['ok' => true, 'reply' => trim($text)];
}

$candidates = get_gemini_model_candidates();
$lastResult = null;
$maxAttempts = min(count($candidates), 4);

for ($i = 0; $i < $maxAttempts; $i++) {
    $model = $candidates[$i];
    $lastResult = call_gemini_model($model, $systemPrompt, $contents);
    if ($lastResult['ok']) {
        cache_working_gemini_model($model);
        echo json_encode(['reply' => $lastResult['reply']]);
        exit;
    }
}

http_response_code($lastResult['status'] ?? 502);
echo json_encode(['error' => $lastResult['error'] ?? 'All models failed to respond right now. Please try again shortly.']);