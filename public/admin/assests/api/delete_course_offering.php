<?php
/**
 * AJAX endpoint: delete a single class offering.
 * Called via fetch() from the dashboard's Courses & Subjects widget.
 * Mirrors the delete handler already used on courses.php.
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
    echo json_encode(['success' => false, 'errors' => ['Your session expired. Please refresh the page and try again.']]);
    exit();
}

$id = (int) ($_POST['offering_id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'errors' => ['Missing or invalid course id.']]);
    exit();
}

try {
    $stmt = $pdo->prepare("DELETE FROM classofferings WHERE offering_id = :id");
    $stmt->execute([':id' => $id]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'errors' => ['Database error: ' . $e->getMessage()]]);
}