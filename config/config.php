<?php
/**
 * Database Configuration & Security Helpers
 * Supports BOTH MySQLi (for existing login.php) AND PDO (for new features)
 */

// Session cookies are persistent when the Remember Me cookie exists.
$rememberCookieName = 'sua_remember_token';
$rememberDays = 30;
$hasRememberCookie = !empty($_COOKIE[$rememberCookieName]);
$sessionLifetime = $hasRememberCookie ? ($rememberDays * 24 * 60 * 60) : 0;
$sessionSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443);

session_set_cookie_params([
    'lifetime' => $sessionLifetime,
    'path' => '/',
    'secure' => $sessionSecure,
    'httponly' => true,
    'samesite' => 'Lax'
]);
date_default_timezone_set('Asia/Manila');
session_start();

require_once __DIR__ . '/password_policy.php';

require_once __DIR__ . '/password_policy.php';

$host = "localhost";
$dbname = "lms";
$user = "root";
$password = "";

$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    die("MySQLi Connection Failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

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

function clean($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Generate a unique student username in the format STU-[7 random digits].
 * Uses random_int() (CSPRNG) and zero-pads so leading zeros are allowed.
 * Retries on the (rare) chance of a collision with an existing username.
 */
function generateStudentUsername(PDO $pdo): string {
    $check = $pdo->prepare("SELECT 1 FROM Users WHERE username = ? LIMIT 1");
    for ($i = 0; $i < 20; $i++) {
        $username = 'STU-' . str_pad((string) random_int(0, 9999999), 7, '0', STR_PAD_LEFT);
        $check->execute([$username]);
        if (!$check->fetch()) {
            return $username;
        }
    }
    throw new Exception("Could not generate a unique username. Please try again.");
}

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
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        return false;
    }
    return true;
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function isTeacher() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'teacher';
}

function requireTeacher() {
    if (!isLoggedIn() || !isTeacher()) {
        $wantsJson = isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json');
        $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
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

function requireAdmin() {
    if (!isLoggedIn() || !isAdmin()) {
        $wantsJson = isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json');
        $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
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

function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

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
        // Keep safe defaults when the setting cannot be read.
    }
    return $defaults;
}

function resolveCurrentTerm(array $intervals, ?DateTime $onDate = null) {
    $onDate = $onDate ?? new DateTime('today');
    $month = (int)$onDate->format('n');
    $ymd = $onDate->format('Y-m-d');

    foreach (['TRM 1', 'TRM 2', 'TRM 3'] as $term) {
        $cfg = $intervals[$term] ?? null;
        if (!$cfg) continue;
        if ($cfg['mode'] === 'date') {
            if ($cfg['start_date'] && $cfg['end_date'] && $ymd >= $cfg['start_date'] && $ymd <= $cfg['end_date']) {
                return $term;
            }
        } else {
            $start = $cfg['start_month'] !== null ? (int)$cfg['start_month'] : null;
            $end = $cfg['end_month'] !== null ? (int)$cfg['end_month'] : null;
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

function syncCourseTermsToCurrent($pdo) {
    $currentTerm = resolveCurrentTerm(getTermIntervals($pdo));
    if ($currentTerm === null) return;

    $termOrder = ['TRM 1', 'TRM 2', 'TRM 3'];
    $currentIndex = array_search($currentTerm, $termOrder, true);

    try {
        $schoolYearId = $pdo->query("SELECT school_year_id FROM schoolyears WHERE is_current = 1 LIMIT 1")->fetchColumn();
    } catch (PDOException $e) {
        return;
    }
    if (!$schoolYearId) return;

    try {
        $stmt = $pdo->prepare("SELECT offering_id, subject_id, section_id, teacher_id, schedule_days, start_time, end_time, capacity, status, quarter FROM classofferings WHERE school_year_id = ?");
        $stmt->execute([$schoolYearId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return;
    }
    if (!$rows) return;

    $latest = [];
    foreach ($rows as $row) {
        $idx = array_search($row['quarter'], $termOrder, true);
        if ($idx === false) continue;
        $key = $row['subject_id'] . ':' . $row['section_id'];
        if (!isset($latest[$key]) || $idx > $latest[$key]['idx']) {
            $row['idx'] = $idx;
            $latest[$key] = $row;
        }
    }

    $insertOffering = $pdo->prepare("INSERT INTO classofferings (subject_id, teacher_id, section_id, quarter, school_year_id, schedule_days, start_time, end_time, capacity, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $copyEnrollments = $pdo->prepare("INSERT INTO enrollments (student_id, offering_id, status) SELECT student_id, ?, 'active' FROM enrollments WHERE offering_id = ? AND status = 'active'");

    foreach ($latest as $source) {
        $idx = $source['idx'];
        while ($idx < $currentIndex) {
            $idx++;
            $nextTerm = $termOrder[$idx];
            try {
                $pdo->beginTransaction();
                $insertOffering->execute([
                    $source['subject_id'], $source['teacher_id'], $source['section_id'], $nextTerm,
                    $schoolYearId, $source['schedule_days'], $source['start_time'], $source['end_time'],
                    $source['capacity'], $source['status']
                ]);
                $newOfferingId = (int)$pdo->lastInsertId();
                $copyEnrollments->execute([$newOfferingId, $source['offering_id']]);
                $pdo->commit();
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                break;
            }
            $source['offering_id'] = $newOfferingId;
        }
    }
}

// ---- Final grade weighting (DepEd-style: Written Work / Performance Task / Exam) ----
// Written Work  = average of all quiz percentages for the offering.
// Performance Task = average of all assignments tagged type='Activity'.
// Exam          = average of all assignments tagged type='Exam'.
const GRADE_COMPONENT_WEIGHTS = [
    'written_work'     => 0.30,
    'performance_task' => 0.50,
    'exam'             => 0.20,
];

/**
 * Combine the three DepEd-style grading components into one weighted final
 * grade. Any component with no graded items yet (null) is left out and the
 * remaining weights are renormalized, so a class that hasn't given an exam
 * yet still gets a fair grade from Written Work + Performance Task alone.
 */
function computeWeightedFinalGrade(?float $writtenWork, ?float $performanceTask, ?float $exam): ?float {
    $components = [
        'written_work'     => $writtenWork,
        'performance_task' => $performanceTask,
        'exam'             => $exam,
    ];
    $available = array_filter($components, fn($v) => $v !== null);
    if (!$available) {
        return null;
    }
    $weightTotal = 0.0;
    $scoreTotal = 0.0;
    foreach ($available as $key => $value) {
        $weightTotal += GRADE_COMPONENT_WEIGHTS[$key];
        $scoreTotal += $value * GRADE_COMPONENT_WEIGHTS[$key];
    }
    return round($scoreTotal / $weightTotal, 2);
}

const ATTENDANCE_TERM_ORDER = ['TRM 1', 'TRM 2', 'TRM 3', 'Unscheduled'];

function attendanceTermForDate(array $termIntervals, string $dateStr): string {
    $date = DateTime::createFromFormat('Y-m-d', $dateStr);
    if (!$date) return 'Unscheduled';
    return resolveCurrentTerm($termIntervals, $date) ?? 'Unscheduled';
}

define('GEMINI_API_KEY', '');

// If a valid session already exists, never show the login form again.
// This also fixes the common case where the user revisits login.php
// after closing a tab while Remember Me is active.
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'login.php' && isLoggedIn()) {
    $role = $_SESSION['role'] ?? '';
    if ($role === 'admin') {
        header('Location: admin/dashboard.php');
    } elseif ($role === 'teacher') {
        header('Location: teacher/dashboard.php');
    } elseif ($role === 'student') {
        header('Location: student/dashboard.php');
    }
    exit();
}

// Load granular admin module permissions after the core session/database helpers are defined.
require_once __DIR__ . '/../includes/access_control.php';
enforceCurrentAdminModuleAccess();
?>