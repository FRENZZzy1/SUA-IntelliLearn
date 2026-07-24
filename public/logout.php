<?php
/**
 * logout.php
 *
 * Destroys the current session and sends the user back to the login
 * page. Linked from the profile dropdown in includes/admin_header.php.
 *
 * NOTE: placed next to login.php (same folder) so the '../config/config.php'
 * path below matches the one login.php already uses. If your login.php
 * lives somewhere else, move this file alongside it and adjust the
 * require path + the href in admin_header.php to match.
 */

require_once '../config/config.php'; // starts the session (same as config.php does for login.php)

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