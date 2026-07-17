<?php
/**
 * assests/api/search.php
 *
 * AJAX endpoint backing the header search bar.
 * Returns JSON: { users: [...], courses: [...], subjects: [...] }
 *
 * Expects a session to already exist (admin/teacher/staff logged in) —
 * same auth assumption as the rest of the dashboard pages.
 */

header('Content-Type: application/json');

// ================= DATABASE CONNECTION =================
require_once '../../../../config/config.php';

// ================= AUTH GUARD =================
// Only logged-in admin-side users should be able to hit this endpoint.
if (empty($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'teacher'], true)) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}



// ================= DATA LAYER =================
require_once 'dashboard_functions.php';

$term = isset($_GET['q']) ? trim((string) $_GET['q']) : '';

// Require at least 2 characters so we're not running a wildcard LIKE
// query against every table on every keystroke.
if (mb_strlen($term) < 2) {
    echo json_encode(['users' => [], 'courses' => [], 'subjects' => []]);
    exit;
}

try {
    $results = global_search($conn, $term, 6);
    echo json_encode($results);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Search failed']);
}