<?php
/**
 * assests/api/chatbot.php
 *
 * AJAX endpoint backing the floating "chat head" assistant on the admin
 * dashboard. Retrieves scoped, non-sensitive context from the school DB
 * (see get_chatbot_context() in dashboard_functions.php), hands it to an
 * LLM via OpenRouter (https://openrouter.ai), and returns the reply.
 *
 * Expects a session to already exist (admin/teacher logged in) — same
 * auth assumption as search.php.
 *
 * ---------------------------------------------------------------------
 * SETUP REQUIRED — add these two lines to config/config.php:
 *
 *   define('OPENROUTER_API_KEY', 'sk-or-v1-...');   // from openrouter.ai/keys
 *   define('OPENROUTER_MODEL', 'meta-llama/llama-3.3-70b-instruct:free');
 *
 * OpenRouter's free-model lineup changes over time — check
 * https://openrouter.ai/models?max_price=0 for the current list of
 * ":free" model slugs and swap OPENROUTER_MODEL if one gets retired.
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

if (!defined('OPENROUTER_API_KEY') || OPENROUTER_API_KEY === '') {
    http_response_code(500);
    echo json_encode(['error' => 'Chat assistant is not configured yet. Add OPENROUTER_API_KEY to config.php.']);
    exit;
}

// ================= DATA LAYER =================
require_once 'dashboard_functions.php';
require_once 'openrouter_model.php';

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
$history = [];
foreach (array_slice($historyIn, -8) as $turn) {
    if (!is_array($turn)) continue;
    $role = $turn['role'] ?? '';
    $content = trim((string) ($turn['content'] ?? ''));
    if (!in_array($role, ['user', 'assistant'], true) || $content === '') continue;
    $history[] = ['role' => $role, 'content' => mb_substr($content, 0, 1000)];
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

$messages = array_merge(
    [['role' => 'system', 'content' => $systemPrompt]],
    $history,
    [['role' => 'user', 'content' => $message]]
);

// ================= CALL OPENROUTER (auto model selection + failover) =================
/**
 * Attempts one chat completion against a given model. Returns a uniform
 * result shape so the try-loop below can treat "reachable but bad
 * response" and "unreachable" the same way.
 */
function call_openrouter_model(string $model, array $messages): array {
    $payload = json_encode([
        'model' => $model,
        'messages' => $messages,
        'temperature' => 0.3,
        'max_tokens' => 500,
    ]);

    $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_CONNECTTIMEOUT => 10, // fail fast if we can't even reach OpenRouter
        CURLOPT_TIMEOUT => 45,        // free models can be slower than paid ones
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . OPENROUTER_API_KEY,
            // Optional but recommended by OpenRouter for their leaderboard/analytics.
            'HTTP-Referer: ' . ($_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_HOST'] ?? 'localhost')),
            'X-Title: SUA IntelliLearn Admin Dashboard',
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
                ? 'Timed out trying to reach OpenRouter.'
                : 'OpenRouter accepted the request but the model took too long to respond.')
            : 'Could not reach the chat assistant service: ' . $curlError;
        return ['ok' => false, 'error' => $message, 'status' => 504];
    }

    $decoded = json_decode($response, true);

    if ($httpCode !== 200 || !isset($decoded['choices'][0]['message']['content'])) {
        $apiError = $decoded['error']['message'] ?? 'Unexpected response from the chat assistant service.';
        return ['ok' => false, 'error' => $apiError, 'status' => 502];
    }

    return ['ok' => true, 'reply' => trim($decoded['choices'][0]['message']['content'])];
}

$candidates = get_openrouter_model_candidates();
$triedLiveCatalog = false;
$lastResult = null;
$maxAttempts = 4; // don't let one slow request cascade through every free model

for ($i = 0, $attempts = 0; $attempts < $maxAttempts; $i++, $attempts++) {
    // Once we've exhausted the cached + preferred list, fetch the live
    // free-model catalog once as a last resort and keep going.
    if ($i >= count($candidates)) {
        if ($triedLiveCatalog) break;
        $triedLiveCatalog = true;
        $candidates = array_values(array_unique(array_merge($candidates, get_live_free_openrouter_models())));
        if ($i >= count($candidates)) break;
    }

    $model = $candidates[$i];
    $lastResult = call_openrouter_model($model, $messages);

    if ($lastResult['ok']) {
        cache_working_openrouter_model($model);
        echo json_encode(['reply' => $lastResult['reply']]);
        exit;
    }
}

// Every attempted model failed.
http_response_code($lastResult['status'] ?? 502);
echo json_encode([
    'error' => $lastResult['error'] ?? 'All free models failed to respond right now. Please try again shortly.',
]);