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


/**
 * Archive support for the Classes & Subjects module.
 * Adds is_archived / archived_at to classofferings, sections and subjects the
 * first time it runs (so no manual SQL import is required). Safe to run on
 * every request: it only issues ALTER TABLE when a column is missing.
 */
function ensureArchiveColumns(PDO $pdo): void {
    try {
        $have = $pdo->query(
            "SELECT TABLE_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = 'is_archived'
               AND TABLE_NAME IN ('classofferings', 'sections', 'subjects')"
        )->fetchAll(PDO::FETCH_COLUMN);
        foreach (['classofferings', 'sections', 'subjects'] as $table) {
            if (!in_array($table, $have, true)) {
                $pdo->exec("ALTER TABLE `$table`
                            ADD COLUMN `is_archived` TINYINT(1) NOT NULL DEFAULT 0,
                            ADD COLUMN `archived_at` DATETIME NULL DEFAULT NULL");
            }
        }
    } catch (PDOException $e) {
        // Never block the page load; the migration file archive_migration.sql can be run by hand.
    }
}
ensureArchiveColumns($pdo);


/**
 * Validate and normalize Philippine mobile numbers.
 * Accepted input: 09XXXXXXXXX or +63 9XX XXX XXXX (spaces/hyphens are optional).
 * Returns the canonical +639XXXXXXXXX value, or null for an empty value.
 */
function validatePhilippineMobileNumber(string $number, bool $allowEmpty = true): ?string {
    $number = trim($number);
    if ($number === '') {
        if ($allowEmpty) return null;
        throw new InvalidArgumentException('A Philippine mobile number is required.');
    }

    $normalized = preg_replace('/[\\s-]+/', '', $number);
    if (preg_match('/^09\\d{9}$/', $normalized)) {
        return '+63' . substr($normalized, 1);
    }
    if (preg_match('/^\\+639\\d{9}$/', $normalized)) {
        return $normalized;
    }

    throw new InvalidArgumentException('Contact number must be a Philippine mobile number in 09XXXXXXXXX or +63 9XX XXX XXXX format.');
}

/**
 * Validate a person's name (first / middle / last / guardian).
 * Only letters (any language, e.g. "Ñ") and single spaces between words are allowed.
 * Returns an error message, or null when the value is acceptable.
 */
function validatePersonName(string $value, string $label, bool $required = true): ?string {
    $value = trim($value);
    if ($value === '') {
        return $required ? "$label is required." : null;
    }
    if (!preg_match('/^[\p{L}\p{M}]+(?: [\p{L}\p{M}]+)*$/u', $value)) {
        return "$label can only contain letters (no numbers, symbols or extra spaces).";
    }
    if (mb_strlen($value) > 100) {
        return "$label must not be longer than 100 characters.";
    }
    return null;
}

/**
 * Validate an LRN: exactly 12 digits, numbers only.
 * Returns an error message, or null when the value is acceptable.
 */
function validateLrnValue(string $value): ?string {
    $value = trim($value);
    if ($value === '') {
        return "LRN is required.";
    }
    if (!preg_match('/^\d+$/', $value)) {
        return "LRN can only contain numbers (0-9).";
    }
    if (strlen($value) !== 12) {
        return "LRN must be exactly 12 digits (you entered " . strlen($value) . ").";
    }
    return null;
}

/**
 * Validate an email using Gmail-style username rules for the part before the "@":
 * letters (a-z), numbers (0-9) and periods only; 6-30 characters; a period cannot be
 * the first or last character and cannot appear twice in a row. The domain must be a
 * normal domain name. Returns an error message, or null when acceptable.
 */
function validateEmailAddress(string $email, bool $required = true): ?string {
    $email = trim($email);
    if ($email === '') return $required ? "Email address is required." : null;
    if (substr_count($email, '@') !== 1) return "Email address must contain a single @ (e.g. name@example.com).";
    [$local, $domain] = explode('@', $email);
    if (!preg_match('/^[A-Za-z0-9.]+$/', $local)) return "Email username can only contain letters (a-z), numbers (0-9) and periods (.).";
    if (strlen($local) < 6 || strlen($local) > 30) return "Email username (the part before @) must be between 6 and 30 characters.";
    if ($local[0] === '.' || substr($local, -1) === '.') return "Email username cannot start or end with a period.";
    if (strpos($local, '..') !== false) return "Email username cannot have more than one period in a row.";
    if (!preg_match('/^(?:[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?\.)+[A-Za-z]{2,}$/', $domain)) return "Email domain is invalid (e.g. gmail.com).";
    return null;
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

/**
 * Generate a unique class code in the format CLS-[6 random uppercase
 * letters/digits] for a new classofferings row.
 * Uses random_int() (CSPRNG). Retries on the (rare) chance of a collision
 * with an existing class_code.
 */
function generateClassCode(PDO $pdo): string {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // no 0/O/1/I to avoid confusion
    $check = $pdo->prepare("SELECT 1 FROM classofferings WHERE class_code = ? LIMIT 1");
    for ($i = 0; $i < 20; $i++) {
        $code = 'CLS-';
        for ($j = 0; $j < 6; $j++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        $check->execute([$code]);
        if (!$check->fetch()) {
            return $code;
        }
    }
    throw new Exception("Could not generate a unique class code. Please try again.");
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
        'TRM 1' => ['mode' => 'date', 'start_month' => null, 'end_month' => null, 'start_date' => null, 'end_date' => null],
        'TRM 2' => ['mode' => 'date', 'start_month' => null, 'end_month' => null, 'start_date' => null, 'end_date' => null],
        'TRM 3' => ['mode' => 'date', 'start_month' => null, 'end_month' => null, 'start_date' => null, 'end_date' => null],
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
        if (!empty($cfg['start_date']) && !empty($cfg['end_date']) && $ymd >= $cfg['start_date'] && $ymd <= $cfg['end_date']) {
            return $term;
        }
    }
    return null;
}

/**
 * The term that is running today (e.g. 'TRM 2'), or null when today is not
 * inside any configured term interval. While a term is active, archiving
 * classes, sections and subjects is disabled (restoring is still allowed).
 */
function getActiveTerm($pdo) {
    return resolveCurrentTerm(getTermIntervals($pdo));
}

function archiveBlockedMessage($activeTerm) {
    return 'Archiving is disabled while a term is active (' . $activeTerm . ' is currently running). You can archive classes, sections and subjects once the term has ended.';
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
        $stmt = $pdo->prepare("SELECT offering_id, subject_id, section_id, teacher_id, schedule_days, start_time, end_time, capacity, status, quarter, is_archived, archived_at FROM classofferings WHERE school_year_id = ?");
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

    $insertOffering = $pdo->prepare("INSERT INTO classofferings (class_code, subject_id, teacher_id, section_id, quarter, school_year_id, schedule_days, start_time, end_time, capacity, status, is_archived, archived_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $copyEnrollments = $pdo->prepare("INSERT INTO enrollments (student_id, offering_id, status) SELECT student_id, ?, 'enrolled' FROM enrollments WHERE offering_id = ? AND status = 'enrolled'");

    foreach ($latest as $source) {
        $idx = $source['idx'];
        while ($idx < $currentIndex) {
            $idx++;
            $nextTerm = $termOrder[$idx];
            try {
                $pdo->beginTransaction();
                $insertOffering->execute([
                    generateClassCode($pdo),
                    $source['subject_id'], $source['teacher_id'], $source['section_id'], $nextTerm,
                    $schoolYearId, $source['schedule_days'], $source['start_time'], $source['end_time'],
                    $source['capacity'], $source['status'],
                    (int) $source['is_archived'], $source['archived_at']
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
        require_once __DIR__ . '/../includes/access_control.php';
        header('Location: ' . adminLandingUrl());
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