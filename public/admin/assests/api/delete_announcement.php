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
$currentUserId = (int) ($_SESSION['user_id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'errors' => ['Missing or invalid announcement id.']]);
    exit();
}

try {
    // Confirm the announcement exists and check ownership before deleting.
    $check = $pdo->prepare("SELECT posted_by FROM announcements WHERE announcement_id = :id");
    $check->execute([':id' => $id]);
    $announcement = $check->fetch();

    if (!$announcement) {
        http_response_code(404);
        echo json_encode(['success' => false, 'errors' => ['Announcement not found.']]);
        exit();
    }

    if ((int) $announcement['posted_by'] !== $currentUserId) {
        http_response_code(403);
        echo json_encode(['success' => false, 'errors' => ['You can only delete announcements you created.']]);
        exit();
    }

    $stmt = $pdo->prepare("DELETE FROM announcements WHERE announcement_id = :id AND posted_by = :posted_by");
    $stmt->execute([':id' => $id, ':posted_by' => $currentUserId]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'errors' => ['Database error: ' . $e->getMessage()]]);
}