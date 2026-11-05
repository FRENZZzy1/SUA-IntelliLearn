<?php
/**
 * Admin-assisted password reset endpoint.
 * Teachers receive a one-time email link; students return to their original
 * generated default password (Lastname + MMDDYY + !).
 */
require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../config/teacher_account_setup.php';
requireAdmin();

header('Content-Type: application/json; charset=utf-8');

function password_reset_json(bool $success, array $data = []): void {
    echo json_encode(array_merge(['success' => $success], $data));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    password_reset_json(false, ['message' => 'Invalid request method.']);
}
if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
    http_response_code(403);
    password_reset_json(false, ['message' => 'Invalid security token. Refresh the page and try again.']);
}

$userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
$action = $_POST['action'] ?? '';
if (!$userId || $userId <= 0) {
    http_response_code(400);
    password_reset_json(false, ['message' => 'Invalid user account.']);
}

try {
    if ($action === 'send_teacher_password_reset') {
        $stmt = $pdo->prepare(
            "SELECT u.id, u.username, u.role, u.status, u.password,
                    t.firstname, t.middlename, t.lastname, t.email
             FROM Users u
             INNER JOIN Teachers t ON t.user_id = u.id
             WHERE u.id = ? AND u.role = 'teacher'
             LIMIT 1"
        );
        $stmt->execute([$userId]);
        $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$teacher || $teacher['status'] !== 'active') {
            http_response_code(404);
            password_reset_json(false, ['message' => 'Teacher account not found or not active.']);
        }
        if (empty($teacher['email']) || !filter_var($teacher['email'], FILTER_VALIDATE_EMAIL)) {
            password_reset_json(false, ['message' => 'This teacher does not have a valid registered email address.']);
        }

        $teacherSetupToken = teacher_generate_setup_token();
        $GLOBALS['teacherSetupToken'] = $teacherSetupToken;
        $resetUrl = teacher_setup_url((int)$teacher['id']);
        $tokenHash = password_hash('SETUP|' . $teacherSetupToken, PASSWORD_DEFAULT);
        $oldPasswordHash = $teacher['password'];

        // Replacing the password with a token hash invalidates the old password immediately.
        $update = $pdo->prepare("UPDATE Users SET password = ?, current_session_token = NULL, updated_at = NOW() WHERE id = ? AND role = 'teacher'");
        $update->execute([$tokenHash, $userId]);

        try {
            $name = trim($teacher['firstname'] . ' ' . ($teacher['middlename'] ?? '') . ' ' . $teacher['lastname']);
            send_teacher_password_reset_email($teacher['email'], $name, $teacher['username'], $resetUrl);
        } catch (Throwable $mailError) {
            // Restore the existing credential if delivery fails, so the teacher is not locked out.
            $restore = $pdo->prepare("UPDATE Users SET password = ?, updated_at = NOW() WHERE id = ? AND role = 'teacher'");
            $restore->execute([$oldPasswordHash, $userId]);
            throw $mailError;
        }

        password_reset_json(true, ['message' => 'Password-reset email sent to ' . $teacher['email'] . '. The link expires in 24 hours and can only be used once.']);
    }

    if ($action === 'reset_student_password') {
        $stmt = $pdo->prepare(
            "SELECT u.id, u.role, s.firstname, s.lastname, s.birthdate
             FROM Users u
             INNER JOIN Students s ON s.user_id = u.id
             WHERE u.id = ? AND u.role = 'student'
             LIMIT 1"
        );
        $stmt->execute([$userId]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$student) {
            http_response_code(404);
            password_reset_json(false, ['message' => 'Student account not found.']);
        }

        $birthdate = DateTime::createFromFormat('!Y-m-d', (string)$student['birthdate']);
        if (!$birthdate || $birthdate->format('Y-m-d') !== $student['birthdate']) {
            password_reset_json(false, ['message' => 'The student birthdate is invalid. Update the student profile before resetting the password.']);
        }

        $temporaryPassword = ucfirst(strtolower(trim($student['lastname']))) . $birthdate->format('mdy') . '!';
        $passwordHash = password_hash($temporaryPassword, PASSWORD_DEFAULT);
        $update = $pdo->prepare("UPDATE Users SET password = ?, current_session_token = NULL, updated_at = NOW() WHERE id = ? AND role = 'student'");
        $update->execute([$passwordHash, $userId]);

        password_reset_json(true, [
            'message' => 'Student password reset successfully.',
            'temporary_password' => $temporaryPassword
        ]);
    }

    http_response_code(400);
    password_reset_json(false, ['message' => 'Unknown password-reset action.']);
} catch (Throwable $e) {
    error_log('SUA password reset error: ' . $e->getMessage());
    http_response_code(500);
    password_reset_json(false, ['message' => 'Password reset failed. Check the server email configuration or application logs.']);
}
