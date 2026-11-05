<?php
/**
 * AJAX endpoint: ARCHIVE a class (formerly "delete a class offering").
 * Called via fetch() from the dashboard's Courses & Subjects widget.
 * Nothing is deleted any more: the class (every term row of the same
 * subject + section + school year) is flagged is_archived = 1, which hides
 * it from its students. It can be restored from admin/courses.php.
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
    $find = $pdo->prepare("SELECT subject_id, section_id, school_year_id FROM classofferings WHERE offering_id = :id");
    $find->execute([':id' => $id]);
    $row = $find->fetch();

    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'errors' => ['That class no longer exists.']]);
        exit();
    }

    $stmt = $pdo->prepare(
        "UPDATE classofferings SET is_archived = 1, archived_at = NOW()
         WHERE subject_id = :sub AND section_id = :sec AND school_year_id = :sy AND is_archived = 0"
    );
    $stmt->execute([':sub' => $row['subject_id'], ':sec' => $row['section_id'], ':sy' => $row['school_year_id']]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'errors' => ['Database error: ' . $e->getMessage()]]);
}
