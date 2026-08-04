<?php

require_once '../../../../config/config.php';
require_once 'dashboard_functions.php';

header('Content-Type: application/json');

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'errors' => ['Method not allowed.']]);
    exit;
}

$csrf = $_POST['csrf_token'] ?? '';
if (!validateCSRFToken($csrf)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'errors' => ['Your session has expired. Please refresh the page and try again.']]);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

if ($action === 'update_info') {
    $email    = trim($_POST['email'] ?? '');
    $position = trim($_POST['position'] ?? '');

    $profile = get_admin_profile($pdo, $userId);
    if (!$profile) {
        echo json_encode(['success' => false, 'errors' => ['Admin profile not found.']]);
        exit;
    }

    $result = update_admin_profile($pdo, (int) $profile['admin_id'], $email, $position);
    echo json_encode($result);
    exit;
}

if ($action === 'change_password') {
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $result = change_user_password($pdo, $userId, $current, $new, $confirm);
    echo json_encode($result);
    exit;
}

echo json_encode(['success' => false, 'errors' => ['Unknown action.']]);