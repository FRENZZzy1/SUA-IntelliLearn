<?php
/**
 * Backend endpoint for fetching announcements (dashboard card / announcements page).
 * Called via fetch() — always returns JSON, never renders a page.
 */

require_once __DIR__ . '/../../../../config/config.php';

requireAdmin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'errors' => ['Invalid request method.']]);
    exit();
}

// Optional filters
$statusFilter   = $_GET['status']   ?? 'published';   // published | draft | all
$audienceFilter = $_GET['audience'] ?? 'all';         // all | students | teachers | parents ...
$limit          = $_GET['limit']    ?? 10;

if (!ctype_digit((string) $limit) || (int) $limit < 1) {
    $limit = 10;
}
$limit = min((int) $limit, 100); // sane upper bound

$where  = [];
$params = [];

if ($statusFilter !== 'all') {
    $where[] = 'a.status = ?';
    $params[] = $statusFilter;
}

if ($audienceFilter !== 'all') {
    $where[] = 'a.audience = ?';
    $params[] = $audienceFilter;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

try {
    $sql = "
        SELECT
            a.announcement_id,
            a.posted_by,
            CONCAT(u.firstname, ' ', u.lastname) AS posted_by_name,
            a.title,
            a.body,
            a.audience,
            a.priority,
            a.offering_id,
            a.status,
            a.is_pinned,
            a.created_at,
            a.updated_at
        FROM announcements a
        LEFT JOIN teachers u ON u.user_id = a.posted_by
        {$whereSql}
        ORDER BY a.is_pinned DESC, a.created_at DESC
        LIMIT {$limit}
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $announcements = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'announcements' => $announcements,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'errors' => ['Database error: ' . $e->getMessage()]]);
}