<?php
/**
 * Backend endpoint for the Deny action on enrollment.php.
 * Called via fetch() — always returns JSON, never renders a page.
 */

require_once __DIR__ . '/../../config/config.php';

requireAdmin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'errors' => ['Invalid request method.']]);
    exit();
}

if (!validateCSRFToken($_POST['csrf'] ?? '')) {
    http_response_code(419);
    echo json_encode(['success' => false, 'errors' => ['Your session expired. Please refresh the page and try again.']]);
    exit();
}

$requestId = $_POST['request_id'] ?? '';

if (!ctype_digit((string) $requestId)) {
    echo json_encode(['success' => false, 'errors' => ['Invalid request.']]);
    exit();
}
$requestId = (int) $requestId;

$stmt = $pdo->prepare("SELECT status FROM enrollment_requests WHERE request_id = ?");
$stmt->execute([$requestId]);
$status = $stmt->fetchColumn();

if ($status === false) {
    echo json_encode(['success' => false, 'errors' => ['Request not found.']]);
    exit();
}

if ($status !== 'pending') {
    echo json_encode(['success' => false, 'errors' => ['This request has already been decided.']]);
    exit();
}

try {
    $pdo->prepare("UPDATE enrollment_requests SET status = 'denied', decided_at = NOW(), decided_by = ? WHERE request_id = ?")
        ->execute([$_SESSION['user_id'] ?? null, $requestId]);

    setFlashMessage('success', 'Enrollment request denied.');
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => ['Database error: ' . $e->getMessage()]]);
}
