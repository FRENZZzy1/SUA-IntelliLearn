<?php
/**
 * Database Configuration & Security Helpers
 * Supports BOTH MySQLi (for existing login.php) AND PDO (for new features)
 */

// Configure the PHP session cookie before starting the session.
// A normal session remains available across tab closes. The login page
// explicitly extends the cookie lifetime only when "Remember me" is used.
$sessionSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443);

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $sessionSecure,
    'httponly' => true,
    'samesite' => 'Lax'
]);

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
 * Check if user is logged in.
 *
 * Also enforces single-device login: on every call, the session's
 * 'session_token' (set at login time) is checked against the
 * 'current_session_token' column in the users table. If a newer
 * login happened elsewhere, that UPDATE overwrote the DB token, so
 * this session's token no longer matches and the session is destroyed.
 */
function isLoggedIn() {
    global $pdo;

    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        return false;
    }

    $stmt = $pdo->prepare("SELECT current_session_token FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $row = $stmt->fetch();

    $sessionToken = $_SESSION['session_token'] ?? null;

    if (!$row || $sessionToken === null || $row['current_session_token'] !== $sessionToken) {
        // No matching user, or this session has been superseded by a
        // newer login elsewhere — force this device out.
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        return false;
    }

    return true;
}

/**
 * Check if logged-in user is admin
 */
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Check if logged-in user is a teacher
 */
function isTeacher() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'teacher';
}

/**
 * Require teacher access or redirect.
 */
function requireTeacher() {
    if (!isLoggedIn() || !isTeacher()) {
        $wantsJson = isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json');
        $isAjax    = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

        if ($wantsJson || $isAjax) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'errors' => ['Your session has expired. Please refresh the page and log in again.']]);
            exit();
        }

        header("Location: /SUA-INTELLILEARN/public/login.php");
        exit();
    }
}

/**
 * Require admin access or redirect.
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

        header("Location: /SUA-INTELLILEARN/public/login.php");
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

// ============================================================
// Term Interval Helpers
// ============================================================
// Term start/end is configured once (Classes & Subjects > "Set Term
// Interval") instead of being picked per class. Each of TRM 1/2/3 can
// either follow a recurring month range (e.g. June - September) or an
// exact start/end date for the current cycle. Stored as a single JSON
// blob under system_settings.setting_key = 'term_intervals'.

/**
 * Load the configured term intervals, filled in with safe defaults for
 * any term that hasn't been set up yet (mode "month", no months/dates set).
 */
function getTermIntervals($pdo) {
    $defaults = [
        'TRM 1' => ['mode' => 'month', 'start_month' => null, 'end_month' => null, 'start_date' => null, 'end_date' => null],
        'TRM 2' => ['mode' => 'month', 'start_month' => null, 'end_month' => null, 'start_date' => null, 'end_date' => null],
        'TRM 3' => ['mode' => 'month', 'start_month' => null, 'end_month' => null, 'start_date' => null, 'end_date' => null],
    ];

    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $stmt->execute(['term_intervals']);
        $raw = $stmt->fetchColumn();

        if ($raw) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                foreach ($defaults as $term => $shape) {
                    if (isset($decoded[$term]) && is_array($decoded[$term])) {
                        $defaults[$term] = array_merge($shape, $decoded[$term]);
                    }
                }
            }
        }
    } catch (PDOException $e) {
        // system_settings missing/unreachable — page still renders with
        // "not configured" defaults; saving will surface the real error.
    }

    return $defaults;
}

/**
 * Work out which term (if any) covers a given date, based on the
 * configured intervals. Returns 'TRM 1' / 'TRM 2' / 'TRM 3', or null if
 * nothing is configured or the date falls outside every configured range.
 */
function resolveCurrentTerm(array $intervals, ?DateTime $onDate = null) {
    $onDate = $onDate ?? new DateTime('today');
    $month  = (int) $onDate->format('n');
    $ymd    = $onDate->format('Y-m-d');

    foreach (['TRM 1', 'TRM 2', 'TRM 3'] as $term) {
        $cfg = $intervals[$term] ?? null;
        if (!$cfg) continue;

        if ($cfg['mode'] === 'date') {
            if ($cfg['start_date'] && $cfg['end_date'] && $ymd >= $cfg['start_date'] && $ymd <= $cfg['end_date']) {
                return $term;
            }
        } else {
            $start = $cfg['start_month'] !== null ? (int) $cfg['start_month'] : null;
            $end   = $cfg['end_month'] !== null ? (int) $cfg['end_month'] : null;
            if ($start === null || $end === null) continue;

            if ($start <= $end) {
                if ($month >= $start && $month <= $end) return $term;
            } else {
                if ($month >= $start || $month <= $end) return $term;
            }
        }
    }

    return null;
}

/**
 * Every class offering follows the single, school-wide "current term".
 */
function syncCourseTermsToCurrent($pdo) {
    $currentTerm = resolveCurrentTerm(getTermIntervals($pdo));
    if ($currentTerm === null) {
        return;
    }

    $termOrder    = ['TRM 1', 'TRM 2', 'TRM 3'];
    $currentIndex = array_search($currentTerm, $termOrder, true);

    try {
        $schoolYearId = $pdo->query("SELECT school_year_id FROM schoolyears WHERE is_current = 1 LIMIT 1")->fetchColumn();
    } catch (PDOException $e) {
        return;
    }
    if (!$schoolYearId) {
        return;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT offering_id, subject_id, section_id, teacher_id, schedule_days, start_time, end_time, capacity, status, quarter
            FROM classofferings
            WHERE school_year_id = ?
        ");
        $stmt->execute([$schoolYearId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return;
    }

    if (!$rows) {
        return;
    }

    $latest = [];
    foreach ($rows as $row) {
        $idx = array_search($row['quarter'], $termOrder, true);
        if ($idx === false) {
            continue;
        }
        $key = $row['subject_id'] . ':' . $row['section_id'];
        if (!isset($latest[$key]) || $idx > $latest[$key]['idx']) {
            $row['idx']  = $idx;
            $latest[$key] = $row;
        }
    }

    $insertOffering = $pdo->prepare("
        INSERT INTO classofferings (subject_id, teacher_id, section_id, quarter, school_year_id, schedule_days, start_time, end_time, capacity, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $copyEnrollments = $pdo->prepare("
        INSERT INTO enrollments (student_id, offering_id, status)
        SELECT student_id, ?, 'active' FROM enrollments WHERE offering_id = ? AND status = 'active'
    ");

    foreach ($latest as $source) {
        $idx = $source['idx'];

        while ($idx < $currentIndex) {
            $idx++;
            $nextTerm = $termOrder[$idx];

            try {
                $pdo->beginTransaction();

                $insertOffering->execute([
                    $source['subject_id'],
                    $source['teacher_id'],
                    $source['section_id'],
                    $nextTerm,
                    $schoolYearId,
                    $source['schedule_days'],
                    $source['start_time'],
                    $source['end_time'],
                    $source['capacity'],
                    $source['status'],
                ]);
                $newOfferingId = (int) $pdo->lastInsertId();
                $copyEnrollments->execute([$newOfferingId, $source['offering_id']]);
                $pdo->commit();
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                break;
            }

            $source['offering_id'] = $newOfferingId;
        }
    }
}

const ATTENDANCE_TERM_ORDER = ['TRM 1', 'TRM 2', 'TRM 3', 'Unscheduled'];

/**
 * Return the configured term label for an attendance date.
 */
function resolveAttendanceTermLabel($pdo, $date) {
    $term = resolveCurrentTerm(getTermIntervals($pdo), new DateTime($date));
    return $term ?? 'Unscheduled';
}

// Additional project helper functions continue below this point.
// Keep the remainder of the existing config.php helpers unchanged.
