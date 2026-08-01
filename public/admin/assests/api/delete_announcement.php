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

$id = (int) ($_POST['announcement_id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'errors' => ['Missing or invalid announcement id.']]);
    exit();
}

try {
    $stmt = $pdo->prepare("DELETE FROM announcements WHERE announcement_id = :id");
    $stmt->execute([':id' => $id]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'errors' => ['Database error: ' . $e->getMessage()]]);
}