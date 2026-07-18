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
    $context = get_chatbot_context($conn, $message);
} catch (Throwable $e) {
    $context = "(Could not retrieve database context due to an internal error.)";
}

$systemPrompt = <<<PROMPT
You are the IntelliLearn Assistant, a helpful admin-facing chatbot embedded in St. Uriel Academy's school management dashboard.

Answer questions using ONLY the data provided below in the "DATABASE CONTEXT" section. This context is a live snapshot pulled for this specific question.
- If the answer isn't in the context, say you don't have that information available right now rather than guessing or inventing details.
- Be concise and direct — a sentence or two, or a short list, is usually enough.
- You are talking to school staff (admins/teachers), not students, so it's fine to discuss operational data like enrollment counts, course rosters, and staffing.
- Never invent names, numbers, emails, or IDs that are not present in the context.
- If asked something unrelated to the school system (general knowledge, coding help, etc.), politely say you're scoped to this dashboard's data.

DATABASE CONTEXT:
{$context}
PROMPT;

$messages = array_merge(
    [['role' => 'system', 'content' => $systemPrompt]],
    $history,
    [['role' => 'user', 'content' => $message]]
);

// ================= CALL OPENROUTER =================
$payload = json_encode([
    'model' => OPENROUTER_MODEL,
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
    http_response_code(504);
    if ($curlErrno === CURLE_OPERATION_TIMEDOUT) {
        // Distinguish "couldn't even connect" from "connected but the
        // model/API never finished responding" — they point at different fixes.
        $message = $httpCode === 0
            ? 'Timed out trying to reach OpenRouter. Your server\'s outbound network may be blocking or throttling requests to openrouter.ai — check with your host/firewall.'
            : 'OpenRouter accepted the request but the model took too long to respond. The free model may be overloaded right now — try again, or switch OPENROUTER_MODEL to a different ":free" slug.';
    } else {
        $message = 'Could not reach the chat assistant service: ' . $curlError;
    }
    echo json_encode(['error' => $message]);
    exit;
}

$decoded = json_decode($response, true);

if ($httpCode !== 200 || !isset($decoded['choices'][0]['message']['content'])) {
    $apiError = $decoded['error']['message'] ?? 'Unexpected response from the chat assistant service.';
    http_response_code(502);
    echo json_encode(['error' => $apiError]);
    exit;
}

echo json_encode([
    'reply' => trim($decoded['choices'][0]['message']['content']),
]);