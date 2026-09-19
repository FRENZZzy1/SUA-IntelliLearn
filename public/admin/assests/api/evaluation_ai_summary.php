<?php
/**
 * evaluation_ai_summary.php
 * -----------------------------------------------------------------
 * POST endpoint behind the "Generate AI summary" buttons on
 * admin/analytics.php. Always returns JSON.
 *
 * Body (JSON or form): { round_id, teacher_id?, csrf }
 *   teacher_id omitted/0 -> school-wide summary
 *   teacher_id > 0       -> summary for that one teacher
 *
 * THE SUMMARY IS NOT STORED. This script performs no INSERT/UPDATE, writes
 * nothing to the session, and tells the browser not to cache the reply. It
 * is generated fresh on every click and disappears when the page is closed.
 * -----------------------------------------------------------------
 */

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../includes/gemini_evaluation.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, max-age=0');

requireAdminModule('analytics', 'read');

function teval_ai_fail(int $code, string $message): void
{
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    teval_ai_fail(405, 'Invalid request method.');
}

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) $body = $_POST;

$csrf = (string) ($body['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
if (!validateCSRFToken($csrf)) {
    teval_ai_fail(419, 'Your session expired. Please refresh the page and try again.');
}

if (!teval_tables_ready($pdo)) {
    teval_ai_fail(500, 'The teacher evaluation tables are missing. Run teacher_evaluation_migration.sql first.');
}
if (!gemini_is_configured()) {
    teval_ai_fail(503, 'AI summaries aren\'t set up yet. Add a GEMINI_API_KEY in config.php (free key: https://aistudio.google.com/apikey).');
}

$roundId   = (int) ($body['round_id'] ?? 0);
$teacherId = (int) ($body['teacher_id'] ?? 0);

$round = $roundId > 0 ? teval_get_round($pdo, $roundId) : null;
if (!$round) {
    teval_ai_fail(404, 'That survey round was not found.');
}

try {
    $results = teval_round_results($pdo, $round);

    if ($teacherId > 0) {
        if (!isset($results['teachers'][$teacherId])) {
            teval_ai_fail(404, 'No evaluation responses exist for that teacher in this round.');
        }
        $count = $results['teachers'][$teacherId]['responses'];
    } else {
        $count = $results['overview']['submitted'];
    }

    if ($count < TEACHER_EVAL_MIN_RESPONSES_FOR_AI) {
        teval_ai_fail(422, 'At least ' . TEACHER_EVAL_MIN_RESPONSES_FOR_AI . ' responses are needed before an AI summary is generated (this has ' . $count . '). This protects students\' anonymity.');
    }

    $payload = teval_build_ai_payload($pdo, $round, $results, $teacherId > 0 ? $teacherId : null);
    if (!$payload) {
        teval_ai_fail(404, 'Nothing to summarize.');
    }
} catch (PDOException $e) {
    teval_ai_fail(500, 'Database error while preparing the summary.');
}

// Release the session lock: the Gemini call can take a while, and holding it
// would freeze the admin's other tabs in the meantime.
session_write_close();
@set_time_limit(120);

$result = gemini_summarize_teacher_evaluation($payload);

if (!$result['success']) {
    teval_ai_fail(502, $result['error'] ?? 'The AI summary failed.');
}

echo json_encode([
    'success'      => true,
    'scope'        => $payload['scope'],
    'summary'      => $result['summary'],
    'model'        => $result['model'],
    'generated_at' => date('c'),
    'stored'       => false,
]);
