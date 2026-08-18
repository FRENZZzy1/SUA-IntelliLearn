<?php
/**
 * analyze_student_risk.php
 * -----------------------------------------------------------------
 * POST endpoint (Accept: application/json) called from
 * at_risk_students.php's "AI Insights" button.
 *
 * Body (JSON or form): { student_id, offering_id, force? }
 *
 * Verifies the requesting teacher actually owns the offering (so one
 * teacher can't pull AI insights on another teacher's students), then
 * either returns a cached insight or calls Gemini and caches the result.
 * -----------------------------------------------------------------
 */

require_once '../../config/config.php';
require_once 'assets/api/at_risk_functions.php';
require_once '../../includes/gemini_client.php';

header('Content-Type: application/json');
requireTeacher();

$userId = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT teacher_id FROM teachers WHERE user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$teacherRow = $stmt->fetch();
if (!$teacherRow) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Teacher record not found.']);
    exit();
}
$teacherId = (int) $teacherRow['teacher_id'];

// ---- Read input (supports JSON body or regular POST) ------------------
$raw = file_get_contents('php://input');
$body = json_decode($raw, true) ?: [];
$studentId  = (int) ($body['student_id']  ?? $_POST['student_id']  ?? 0);
$offeringId = (int) ($body['offering_id'] ?? $_POST['offering_id'] ?? 0);
$force      = !empty($body['force']) || !empty($_POST['force']);

if ($studentId <= 0 || $offeringId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'student_id and offering_id are required.']);
    exit();
}

// ---- Ownership check: this offering must belong to this teacher -------
$stmt = $pdo->prepare("SELECT 1 FROM classofferings WHERE offering_id = ? AND teacher_id = ? LIMIT 1");
$stmt->execute([$offeringId, $teacherId]);
if (!$stmt->fetch()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'You do not have access to this class.']);
    exit();
}

// ---- Serve from cache unless a fresh analysis was requested -----------
if (!$force) {
    $cached = get_cached_insight($pdo, $studentId, $offeringId);
    if ($cached) {
        echo json_encode(['success' => true] + $cached);
        exit();
    }
}

// ---- Compute current metrics -------------------------------------------
$data = get_at_risk_single($pdo, $studentId, $offeringId);
if (!$data) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Student is not actively enrolled in this class.']);
    exit();
}

if (!gemini_is_configured()) {
    echo json_encode([
        'success' => false,
        'error'   => 'AI insights aren\'t set up yet — add a GEMINI_API_KEY in config.php (get a free key at https://aistudio.google.com/apikey).',
        'risk_label' => $data['risk_label'],
        'risk_score' => $data['risk_score'],
    ]);
    exit();
}

$result = gemini_analyze_student_risk($data['name'], $data['subject'], $data['risk_label'], $data);

if (!$result['success']) {
    http_response_code(502);
    echo json_encode(['success' => false, 'error' => $result['error'] ?? 'AI analysis failed.']);
    exit();
}

save_insight(
    $pdo,
    $studentId,
    $offeringId,
    $data['risk_label'],
    $data['risk_score'],
    $result['why'],
    $result['how'],
    $result['recommended_actions']
);

echo json_encode([
    'success'             => true,
    'why'                 => $result['why'],
    'how'                 => $result['how'],
    'recommended_actions' => $result['recommended_actions'],
    'generated_at'        => date('c'),
    'cached'              => false,
]);
