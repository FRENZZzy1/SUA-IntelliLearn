<?php
/**
 * AJAX endpoint: delete a single announcement.
 * Called via fetch() from the dashboard's Announcements widget.
 * Always returns JSON, never renders a page.
 */

require_once __DIR__ . '/../../../../config/config.php';

requireAdmin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'errors' => ['Invalid request method.']]);
    exit();
}

if (!validateCSRFToken($_POST['csrf'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'errors' => ['Invalid or expired session token. Please refresh and try again.']]);
    exit();
}

$id = (int) ($_POST['announcement_id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'errors' => ['Missing or invalid announcement id.']]);
    exit();
}

try {
    // Only the original poster may delete their own announcement.
    $stmt = $pdo->prepare("DELETE FROM announcements WHERE announcement_id = :id AND posted_by = :posted_by");
    $stmt->execute([':id' => $id, ':posted_by' => $_SESSION['user_id']]);

    if ($stmt->rowCount() === 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'errors' => ["You can only delete announcements you posted."]]);
        exit();
    }

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'errors' => ['Database error: ' . $e->getMessage()]]);
}