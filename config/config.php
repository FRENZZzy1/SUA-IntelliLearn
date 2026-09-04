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
 * Check if user is logged in.
 *
 * Also enforces single-device login: on every call, the session's
 * 'session_token' (set at login time) is checked against the
 * 'current_session_token' column in the users table. If a newer
 * login happened elsewhere (e.g. on another device), that UPDATE
 * overwrote the DB token, so this session's token no longer matches
 * and the session is destroyed here — effectively logging this
 * device out.
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
 *
 * Same JSON-vs-redirect behavior as requireAdmin() — the quiz generator's
 * fetch() calls (generate_quiz.php, save_quiz.php) send
 * `Accept: application/json`, so a session/role failure returns a JSON 401
 * instead of an HTML redirect that fetch() can't parse.
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
        } else { // 'month'
            $start = $cfg['start_month'] !== null ? (int) $cfg['start_month'] : null;
            $end   = $cfg['end_month'] !== null ? (int) $cfg['end_month'] : null;
            if ($start === null || $end === null) continue;

            if ($start <= $end) {
                if ($month >= $start && $month <= $end) return $term;
            } else {
                // Range wraps the new year, e.g. Nov (11) - Feb (2).
                if ($month >= $start || $month <= $end) return $term;
            }
        }
    }

    return null;
}

/**
 * Every class offering follows the single, school-wide "current term" —
 * there's no per-class term to edit. Whenever the active term (per
 * resolveCurrentTerm()) has moved on, this pushes every classofferings
 * row onto the new term so the whole school list advances together,
 * whether those classes were created yesterday or last year.
 *
 * Called after "Set Term Interval" is saved, and on courses.php load, so
 * it also picks up a term boundary being crossed purely by the passage
 * of time (no admin action needed).
 *
 * Rows are updated one at a time (not a single bulk UPDATE) so that a
 * unique-key collision on one row — e.g. two classes for the same
 * subject/section/school-year that would otherwise land on the same term
 * value — doesn't block every other row from advancing. Colliding rows
 * are simply left on their previous term until the collision is resolved.
 */
function syncCourseTermsToCurrent($pdo) {
    $currentTerm = resolveCurrentTerm(getTermIntervals($pdo));
    if ($currentTerm === null) {
        return; // No interval configured / today falls outside every range — leave classes as-is.
    }

    try {
        $stmt = $pdo->prepare("SELECT offering_id FROM classofferings WHERE quarter <> ?");
        $stmt->execute([$currentTerm]);
        $staleIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        return;
    }

    if (!$staleIds) {
        return;
    }

    $update = $pdo->prepare("UPDATE classofferings SET quarter = ? WHERE offering_id = ?");
    foreach ($staleIds as $offeringId) {
        try {
            $update->execute([$currentTerm, $offeringId]);
        } catch (PDOException $e) {
            // Duplicate (subject_id, section_id, quarter, school_year_id) —
            // another class already occupies that slot. Skip and leave this
            // one on its previous term rather than failing the whole sync.
            continue;
        }
    }
}

// Canonical display order for attendance term breakdowns: the three
// configured terms, then a catch-all bucket for dates that don't fall
// inside any configured interval.
const ATTENDANCE_TERM_ORDER = ['TRM 1', 'TRM 2', 'TRM 3', 'Unscheduled'];

/**
 * Which term (TRM 1/2/3) a given date falls into, per the configured
 * term intervals. Used to label attendance records by term.
 *
 * This is deliberately independent of classofferings.quarter, which only
 * reflects whichever term is *currently* active (it advances
 * automatically as terms roll over — see syncCourseTermsToCurrent()
 * above) and so can't be used to tell which term an attendance record
 * taken weeks or months ago actually belonged to. Instead, each
 * attendance_date is resolved against the intervals independently, the
 * same way "Set Term Interval" resolves today's date.
 *
 * Falls back to 'Unscheduled' for dates outside every configured range
 * (e.g. attendance was recorded before intervals were set up).
 */
function attendanceTermForDate(array $termIntervals, string $dateStr): string {
    $date = DateTime::createFromFormat('Y-m-d', $dateStr);
    if (!$date) {
        return 'Unscheduled';
    }
    return resolveCurrentTerm($termIntervals, $date) ?? 'Unscheduled';
}

 define('GEMINI_API_KEY', '');


?>