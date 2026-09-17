<?php
/**
 * Student profile settings API.
 * Updates only the currently authenticated student's own record.
 */
require_once '../../../../config/config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn() || ($_SESSION['role'] ?? '') !== 'student') {
    http_response_code(403);
    echo json_encode(['success' => false, 'errors' => ['Unauthorized request.']]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'errors' => ['Method not allowed.']]);
    exit();
}

try {
    $token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($token)) {
        throw new RuntimeException('Invalid or expired security token.');
    }

    $userId = (int) ($_SESSION['user_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    $stmt = $pdo->prepare("SELECT s.student_id, s.email, u.password
                           FROM students s
                           JOIN users u ON u.id = s.user_id
                           WHERE s.user_id = ?
                           LIMIT 1");
    $stmt->execute([$userId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        throw new RuntimeException('Student profile not found.');
    }

    if ($action === 'update_info') {
        $email = trim($_POST['email'] ?? '');

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'errors' => ['Please enter a valid email address.']]);
            exit();
        }

        if ($email !== '' && strcasecmp($email, (string) $student['email']) !== 0) {
            $check = $pdo->prepare("SELECT 1 FROM students WHERE email = ? AND user_id <> ? LIMIT 1");
            $check->execute([$email, $userId]);
            if ($check->fetchColumn()) {
                echo json_encode(['success' => false, 'errors' => ['That email address is already in use.']]);
                exit();
            }
        }

        $stmt = $pdo->prepare("UPDATE students SET email = ?, updated_at = NOW() WHERE user_id = ?");
        $stmt->execute([$email !== '' ? $email : null, $userId]);
        echo json_encode(['success' => true]);
        exit();
    }

    if ($action === 'change_password') {
        $current = (string) ($_POST['current_password'] ?? '');
        $new = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');
        $errors = [];

        if ($current === '') $errors[] = 'Current password is required.';
        if (strlen($new) < 8) $errors[] = 'New password must be at least 8 characters.';
        if ($new !== $confirm) $errors[] = 'New password and confirmation do not match.';
        if ($new === $current && $new !== '') $errors[] = 'New password must be different from your current password.';

        $passwordValid = password_verify($current, (string) $student['password']);
        if ($current !== '' && !$passwordValid) {
            $errors[] = 'Current password is incorrect.';
        }

        if ($errors) {
            echo json_encode(['success' => false, 'errors' => $errors]);
            exit();
        }

        $hash = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ? AND role = 'student'");
        $stmt->execute([$hash, $userId]);

        echo json_encode(['success' => true]);
        exit();
    }

    echo json_encode(['success' => false, 'errors' => ['Unknown profile action.']]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'errors' => [$e->getMessage()]]);
}
