<?php
/**
 * assests/api/chatbot.php
 *
 * AJAX endpoint backing the floating "chat head" assistant on the admin
 * dashboard. Retrieves scoped, non-sensitive context from the school DB
 * (see get_chatbot_context() in dashboard_functions.php), hands it to an
 * LLM via the Gemini API (Google AI Studio, free tier), and returns the
 * reply.
 *
 * Expects a session to already exist (admin/teacher logged in) — same
 * auth assumption as search.php.
 *
 * ---------------------------------------------------------------------
 * SETUP REQUIRED — add this line to config/config.php:
 *
 *   define('GEMINI_API_KEY', 'AIza...');   // from aistudio.google.com/apikey
 *
 * Google AI Studio's free tier covers the Flash / Flash-Lite model
 * family, subject to per-project rate limits (requests/minute and
 * requests/day). See https://ai.google.dev/gemini-api/docs/pricing for
 * the current free-tier lineup and https://ai.google.dev/gemini-api/docs/rate-limits
 * for limits. Edit GEMINI_PREFERRED_FREE_MODELS in gemini_model.php if
 * that lineup changes.
 * ---------------------------------------------------------------------
 */

header('Content-Type: application/json');

// ================= DATABASE CONNECTION =================
require_once '../../../../config/config.php';

// ================= AUTH GUARD =================
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

// ================= DATA LAYER =================
require_once 'dashboard_functions.php';
require_once 'gemini_model.php';

// ================= READ + VALIDATE REQUEST BODY =================
$raw = file_get_contents('php://input');
$body = json_decode($raw, true);

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

// Only keep well-formed {role, content} turns, cap to the last 8 so the
// payload (and token usage) stays bounded, and clamp each turn's length.
// Gemini uses "model" rather than "assistant" for the AI's turns, so we
// translate roles here to keep the rest of the request-handling code
// (and the frontend's history format in chatbot.js) unchanged.
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

// ================= RETRIEVE DB CONTEXT =================
try {
    $context = get_chatbot_context($pdo, $message);
} catch (Throwable $e) {
    $context = "(Could not retrieve database context due to an internal error.)";
}

$systemPrompt = <<<PROMPT
You are the IntelliLearn Assistant, embedded in St. Uriel Academy's admin dashboard. You're talking to school staff (admins/teachers) — a knowledgeable colleague, not a public-facing bot.

The "DATABASE CONTEXT" below is a live snapshot retrieved for this specific question. Depending on what was asked, it may include school-wide stats, matching students/teachers, a student's real schedule, a teacher's real teaching load, course capacity/seats remaining, section/strand/adviser info, enrollment request status, and learning materials.

How to use it:
- The one hard rule: never invent a name, number, email, ID, date, or status that isn't in the context. Everything else is about using good judgment.
- Work with what you're given, even if it's partial — if you have a student's schedule but not their enrollment status, answer what you can and note what's missing, rather than declining the whole thing.
- Feel free to do your own math or reasoning over the data (totals, comparisons, "who has the most X," seats remaining vs. capacity) — you don't need that pre-computed for you.
- If the context is genuinely empty or off-topic for the question, say so plainly and suggest a more specific name, grade, section, or subject would help find it. But if you have partial or adjacent info that's clearly relevant, use it instead of stonewalling.
- Blend freely into general academic/operational advice (study strategies, handling a low-enrollment section, scheduling conflicts, etc.) using your own reasoning — just keep any data you cite from the context accurate, and it should be obvious what's "from the system" versus your own suggestion.
- Match the admin's tone: quick factual questions get a quick factual answer; open-ended ones ("what should I do about...") get a bit more room to actually help.
- Wholly unrelated asks (general trivia, coding help, etc.) — just say you're scoped to this school's data and operations, and move on.

DATABASE CONTEXT:
{$context}
PROMPT;

// Gemini's "contents" array is the conversation turns only (no system
// role in-line); the system prompt goes in the separate
// systemInstruction field instead.
$contents = array_merge(
    $history,
    [['role' => 'user', 'parts' => [['text' => $message]]]]
);

// ================= CALL GEMINI (auto model selection + failover) =================
/**
 * Attempts one chat completion against a given Gemini model. Returns a
 * uniform result shape so the try-loop below can treat "reachable but
 * bad response" and "unreachable" the same way.
 */
function call_gemini_model(string $model, string $systemPrompt, array $contents): array {
    $payload = json_encode([
        'contents' => $contents,
        'systemInstruction' => ['parts' => [['text' => $systemPrompt]]],
        'generationConfig' => [
            'temperature' => 0.3,
            'maxOutputTokens' => 500,
        ],
    ]);

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_CONNECTTIMEOUT => 10, // fail fast if we can't even reach Google
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
        $message = ($curlErrno === CURLE_OPERATION_TIMEDOUT)
            ? ($httpCode === 0
                ? 'Timed out trying to reach Gemini.'
                : 'Gemini accepted the request but took too long to respond.')
            : 'Could not reach the chat assistant service: ' . $curlError;
        return ['ok' => false, 'error' => $message, 'status' => 504];
    }

    $decoded = json_decode($response, true);

    // Rate limit / quota exhaustion (common on the free tier) comes back
    // as HTTP 429 — worth its own message so failover to the next
    // candidate model doesn't look like a generic error.
    if ($httpCode === 429) {
        return ['ok' => false, 'error' => 'This model is rate-limited on the free tier right now.', 'status' => 429];
    }

    $text = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? null;

    if ($httpCode !== 200 || $text === null) {
        // A response can be well-formed but empty because it was blocked
        // (finishReason SAFETY/RECITATION/etc.) rather than because the
        // request failed outright — surface that distinctly.
        $finishReason = $decoded['candidates'][0]['finishReason'] ?? null;
        if ($httpCode === 200 && $finishReason) {
            return ['ok' => false, 'error' => 'Response was blocked by Gemini (reason: ' . $finishReason . ').', 'status' => 502];
        }
        $apiError = $decoded['error']['message'] ?? 'Unexpected response from the chat assistant service.';
        return ['ok' => false, 'error' => $apiError, 'status' => 502];
    }

    return ['ok' => true, 'reply' => trim($text)];
}

$candidates = get_gemini_model_candidates();
$lastResult = null;
$maxAttempts = min(count($candidates), 4); // don't let one slow request cascade through every model

for ($i = 0; $i < $maxAttempts; $i++) {
    $model = $candidates[$i];
    $lastResult = call_gemini_model($model, $systemPrompt, $contents);

    if ($lastResult['ok']) {
        cache_working_gemini_model($model);
        echo json_encode(['reply' => $lastResult['reply']]);
        exit;
    }
}

// Every attempted model failed.
http_response_code($lastResult['status'] ?? 502);
echo json_encode([
    'error' => $lastResult['error'] ?? 'All models failed to respond right now. Please try again shortly.',
]);