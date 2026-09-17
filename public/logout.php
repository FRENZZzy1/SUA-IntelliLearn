<?php
/**
 * logout.php
 *
 * Destroys the current session and invalidates the persistent Remember Me
 * token so logging out cannot immediately sign the user back in.
 */

require_once '../config/config.php';

// Invalidate the server-side token first. This also preserves the existing
// single-device rule: any current session/remember cookie using this token
// becomes invalid immediately.
if (!empty($_SESSION['user_id'])) {
    $clearTokenStmt = $conn->prepare("UPDATE users SET current_session_token=NULL WHERE id=?");
    $clearTokenStmt->bind_param("i", $_SESSION['user_id']);
    $clearTokenStmt->execute();
}

// Clear the persistent Remember Me cookie.
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443);
setcookie('sua_remember_token', '', [
    'expires' => time() - 3600,
    'path' => '/',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax'
]);

$_SESSION = [];

if (session_status() === PHP_SESSION_ACTIVE) {
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    session_destroy();
}

header('Location: /SUA-INTELLILEARN/public/login.php');
exit();