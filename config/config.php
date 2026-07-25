<?php
/**
 * Database Configuration & Security Helpers
 * Supports BOTH MySQLi (for existing login.php) AND PDO (for new features)
 */

session_start();

$host = "localhost";
$dbname = "lms";
$user = "root";
$password = "";

// ============================================================
// MySQLi Connection (for existing login.php and legacy code)
// ============================================================
$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    die("MySQLi Connection Failed: " . $conn->connect_error);
}

// Set charset for MySQLi
$conn->set_charset("utf8mb4");

// ============================================================
// PDO Connection (for new user_management.php and secure CRUD)
// ============================================================
try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    die("PDO Connection Failed: " . $e->getMessage());
}

// ============================================================
// Security Helpers
// ============================================================

/**
 * Clean output data to prevent XSS
 */
function clean($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Check if logged-in user is admin
 */
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Require admin access or redirect.
 *
 * AJAX/fetch calls (enrollment.php's approve/deny/reopen/add-request
 * endpoints, etc.) always send `Accept: application/json`. For those,
 * a session/role failure returns a JSON 401 instead of an HTML
 * redirect — otherwise fetch() follows the redirect to login.php,
 * gets back an HTML page instead of JSON, and res.json() throws,
 * which surfaces to the user as a generic "Something went wrong."
 */
function requireAdmin() {
    if (!isLoggedIn() || !isAdmin()) {
        $wantsJson = isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json');
        $isAjax    = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

        if ($wantsJson || $isAjax) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'errors' => ['Your session has expired. Please refresh the page and log in again.']]);
            exit();
        }

        header("Location: ../../login.php");
        exit();
    }
}

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token
 */
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Flash message helper
 */
function setFlashMessage($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlashMessage() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

define('OPENROUTER_API_KEY', '');   // from openrouter.ai/keys
define('OPENROUTER_MODEL', 'nvidia/nemotron-3-super-120b-a12b:free'); // Check https://openrouter.ai/models?max_price=0 for current free models


?>