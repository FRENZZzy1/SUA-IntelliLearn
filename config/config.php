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
 * Adds is_archived / archived_at to classofferings, sections, subjects and schoolyears the
 * first time it runs (so no manual SQL import is required), plus
 * schoolyears.auto_archive_paused (set when an admin restores a school year so
 * the automatic year-end archive leaves it alone). Safe to run on every
 * request: it only issues ALTER TABLE when a column is missing.
 */
function ensureArchiveColumns(PDO $pdo): void {
    try {
        $rows = $pdo->query(
            "SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND ((COLUMN_NAME = 'is_archived' AND TABLE_NAME IN ('classofferings', 'sections', 'subjects', 'schoolyears'))
                 OR (COLUMN_NAME = 'auto_archive_paused' AND TABLE_NAME = 'schoolyears'))"
        )->fetchAll(PDO::FETCH_ASSOC);
        $have = [];
        foreach ($rows as $r) {
            $have[$r['TABLE_NAME'] . '.' . $r['COLUMN_NAME']] = true;
        }
        foreach (['classofferings', 'sections', 'subjects', 'schoolyears'] as $table) {
            if (!isset($have[$table . '.is_archived'])) {
                $pdo->exec("ALTER TABLE `$table`
                            ADD COLUMN `is_archived` TINYINT(1) NOT NULL DEFAULT 0,
                            ADD COLUMN `archived_at` DATETIME NULL DEFAULT NULL");
            }
        }
        if (!isset($have['schoolyears.auto_archive_paused'])) {
            $pdo->exec("ALTER TABLE `schoolyears` ADD COLUMN `auto_archive_paused` TINYINT(1) NOT NULL DEFAULT 0");
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

/**
 * Is this email already used by ANY account, regardless of role?
 * An email is one-use only: once it belongs to a student, teacher or admin
 * it cannot be used to create (or be changed onto) another account.
 *
 * Checks students.email, teachers.email, admin.email and users.username
 * (teachers/admins log in with their email as their username).
 * $excludeUserId lets an account keep/re-save its own email when editing.
 */
function isEmailInUse(PDO $pdo, string $email, ?int $excludeUserId = null): bool {
    $email = trim($email);
    if ($email === '') return false;
    $ex = $excludeUserId ?? 0;
    $queries = [
        "SELECT 1 FROM students WHERE LOWER(email) = LOWER(?) AND user_id <> ? LIMIT 1",
        "SELECT 1 FROM teachers WHERE LOWER(email) = LOWER(?) AND user_id <> ? LIMIT 1",
        "SELECT 1 FROM admin    WHERE LOWER(email) = LOWER(?) AND user_id <> ? LIMIT 1",
        "SELECT 1 FROM users    WHERE LOWER(username) = LOWER(?) AND id <> ? LIMIT 1",
    ];
    foreach ($queries as $sql) {
        $st = $pdo->prepare($sql);
        $st->execute([$email, $ex]);
        if ($st->fetchColumn()) return true;
    }
    return false;
}

const EMAIL_IN_USE_MESSAGE = "This email address is already used by another account. Each email can only be used for one account, regardless of role.";

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

// ================= SCHOOL YEAR ROLLOVER & YEAR-END ARCHIVING =================
// Closing out a school year is three steps, all driven from Settings > School Year:
//   1. Add the new year with "Copy sections & classes from <old year>". The new
//      year gets the same sections and classes (teacher, schedule, capacity) as
//      TRM 1 offerings, with NO students - promotion is a separate, later step.
//   2. Set the new year as current - students and teachers now see only its classes.
//   3. The old year is archived - automatically once its end date has passed
//      (autoArchiveEndedSchoolYears), or by hand with the Archive button. Its
//      classes and sections get the existing is_archived flag (so every page
//      that already hides archived rows keeps working) and the year itself is
//      stamped. Nothing is ever deleted.
// Restoring a year undoes exactly what archiving it hid, using the shared
// archived_at stamp, so rows an admin archived by hand earlier stay archived.
// A restored year is marked auto_archive_paused so the automatic archive does
// not undo the restore; the Archive button archives it again.

/**
 * Copy the sections and classes of one school year into another. Students and
 * enrollments are NOT copied. Each class is created as a TRM 1 offering using
 * the teacher, schedule and capacity of its most recent term; the normal term
 * rollover (syncCourseTermsToCurrent) then carries it into TRM 2 / TRM 3.
 *
 * Skipped: subjects that are archived, and sections/classes an admin archived by
 * hand before the source year was archived (they were retired on purpose).
 * Safe to run twice: sections and classes that already exist are not duplicated.
 *
 * Must run inside the caller's transaction.
 *
 * @return array{sections_created:int, sections_reused:int, classes_created:int, classes_skipped:int}
 * @throws InvalidArgumentException when either year does not exist or both are the same.
 */
function copySchoolYearStructure(PDO $pdo, int $fromYearId, int $toYearId): array
{
    if ($fromYearId === $toYearId) {
        throw new InvalidArgumentException('Pick a different school year to copy from.');
    }

    $yearStmt = $pdo->prepare("SELECT school_year_id, archived_at FROM schoolyears WHERE school_year_id = ?");
    $yearStmt->execute([$fromYearId]);
    $from = $yearStmt->fetch(PDO::FETCH_ASSOC);
    if (!$from) {
        throw new InvalidArgumentException('The school year to copy from was not found.');
    }
    $yearStmt->execute([$toYearId]);
    if (!$yearStmt->fetch(PDO::FETCH_ASSOC)) {
        throw new InvalidArgumentException('The new school year was not found.');
    }

    // A row counts as "in use last year" when it is not archived, or when it was
    // hidden by the year archive itself (same archived_at stamp as the year).
    $yearBatch = $from['archived_at'];
    $wasInUse = static function (array $row) use ($yearBatch): bool {
        return (int) $row['is_archived'] === 0
            || ($yearBatch !== null && $row['archived_at'] === $yearBatch);
    };

    $result = ['sections_created' => 0, 'sections_reused' => 0, 'classes_created' => 0, 'classes_skipped' => 0];

    // ---- Sections ------------------------------------------------------
    $secStmt = $pdo->prepare(
        "SELECT section_id, section_name, grade_level, adviser_id, is_archived, archived_at
         FROM sections WHERE school_year_id = ? ORDER BY grade_level, section_name"
    );
    $secStmt->execute([$fromYearId]);
    $findSection = $pdo->prepare("SELECT section_id FROM sections WHERE section_name = ? AND grade_level = ? AND school_year_id = ?");
    $addSection  = $pdo->prepare("INSERT INTO sections (section_name, grade_level, adviser_id, school_year_id) VALUES (?, ?, ?, ?)");

    $sectionMap = []; // old section_id => new section_id
    foreach ($secStmt->fetchAll(PDO::FETCH_ASSOC) as $sec) {
        if (!$wasInUse($sec)) {
            continue;
        }
        $findSection->execute([$sec['section_name'], $sec['grade_level'], $toYearId]);
        $existingId = $findSection->fetchColumn();
        if ($existingId) {
            $sectionMap[(int) $sec['section_id']] = (int) $existingId;
            $result['sections_reused']++;
            continue;
        }
        $addSection->execute([$sec['section_name'], $sec['grade_level'], $sec['adviser_id'], $toYearId]);
        $sectionMap[(int) $sec['section_id']] = (int) $pdo->lastInsertId();
        $result['sections_created']++;
    }

    // ---- Classes (one TRM 1 offering per subject + section) ------------
    $offStmt = $pdo->prepare(
        "SELECT co.subject_id, co.section_id, co.teacher_id, co.schedule_days, co.start_time, co.end_time,
                co.capacity, co.status, co.is_archived, co.archived_at
         FROM classofferings co
         JOIN subjects sub ON sub.subject_id = co.subject_id AND sub.is_archived = 0
         WHERE co.school_year_id = ?
         ORDER BY FIELD(co.quarter, 'TRM 1', 'TRM 2', 'TRM 3')"
    );
    $offStmt->execute([$fromYearId]);

    $latest = []; // later terms overwrite earlier ones, so the most recent term's details win
    foreach ($offStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (!isset($sectionMap[(int) $row['section_id']]) || !$wasInUse($row)) {
            continue;
        }
        $latest[$row['subject_id'] . ':' . $row['section_id']] = $row;
    }

    $findOffering = $pdo->prepare("SELECT 1 FROM classofferings WHERE subject_id = ? AND section_id = ? AND quarter = 'TRM 1' AND school_year_id = ?");
    $addOffering  = $pdo->prepare(
        "INSERT INTO classofferings (class_code, subject_id, teacher_id, section_id, quarter, school_year_id, schedule_days, start_time, end_time, capacity, status)
         VALUES (?, ?, ?, ?, 'TRM 1', ?, ?, ?, ?, ?, ?)"
    );
    foreach ($latest as $row) {
        $newSectionId = $sectionMap[(int) $row['section_id']];
        $findOffering->execute([$row['subject_id'], $newSectionId, $toYearId]);
        if ($findOffering->fetch()) {
            $result['classes_skipped']++;
            continue;
        }
        $addOffering->execute([
            generateClassCode($pdo), $row['subject_id'], $row['teacher_id'], $newSectionId, $toYearId,
            $row['schedule_days'], $row['start_time'], $row['end_time'], $row['capacity'], $row['status'],
        ]);
        $result['classes_created']++;
    }

    return $result;
}

/**
 * Archive a finished school year: every class of that year, every section of
 * that year that no live class still uses, and the year itself. Nothing is
 * deleted; restoreSchoolYear() reverses it.
 *
 * A school year can be archived when it is not the current year, or when it is
 * the current year but its end date has already passed (a year that is still
 * running cannot be archived). Archiving is NOT held back by a running term:
 * year-level archiving never touches the term schedule.
 *
 * The year row is locked first, so two requests arriving together (the
 * automatic year-end archive can fire on any page load) cannot both archive it
 * and leave classes with a different archived_at stamp than the year.
 *
 * @param string|null $today Y-m-d, for tests; defaults to today.
 * @return array{classes:int, sections:int}
 * @throws InvalidArgumentException when the year is missing, still running, or already archived.
 */
function archiveSchoolYear(PDO $pdo, int $yearId, ?string $today = null): array
{
    $today  = $today ?? date('Y-m-d');
    $stamp  = date('Y-m-d H:i:s');
    $counts = ['classes' => 0, 'sections' => 0];

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT school_year_id, end_date, is_current, is_archived FROM schoolyears WHERE school_year_id = ? FOR UPDATE");
        $stmt->execute([$yearId]);
        $year = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$year) {
            throw new InvalidArgumentException('School year not found.');
        }
        if ((int) $year['is_archived'] === 1) {
            throw new InvalidArgumentException('That school year is already archived.');
        }
        if ((int) $year['is_current'] === 1 && $year['end_date'] >= $today) {
            throw new InvalidArgumentException(
                'The current school year is still running (it ends on ' . date('M j, Y', strtotime($year['end_date']))
                . '). It can be archived once it has ended, or after another school year is made current.'
            );
        }

        $classes = $pdo->prepare("UPDATE classofferings SET is_archived = 1, archived_at = ? WHERE school_year_id = ? AND is_archived = 0");
        $classes->execute([$stamp, $yearId]);
        $counts['classes'] = $classes->rowCount();

        // A section can host classes of another year (the Add Class form lets an
        // admin pick any section + year), so only archive sections nothing live uses.
        $sections = $pdo->prepare(
            "UPDATE sections SET is_archived = 1, archived_at = ?
             WHERE school_year_id = ? AND is_archived = 0
               AND NOT EXISTS (SELECT 1 FROM classofferings co WHERE co.section_id = sections.section_id AND co.is_archived = 0)"
        );
        $sections->execute([$stamp, $yearId]);
        $counts['sections'] = $sections->rowCount();

        $pdo->prepare("UPDATE schoolyears SET is_archived = 1, archived_at = ?, auto_archive_paused = 0 WHERE school_year_id = ?")
            ->execute([$stamp, $yearId]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return $counts;
}

/**
 * Undo archiveSchoolYear(): bring back the classes and sections that archiving
 * the year hid (matched by the shared archived_at stamp) and un-archive the year.
 * Anything an admin archived by hand stays archived.
 *
 * The year is also marked auto_archive_paused, so the automatic year-end archive
 * does not immediately undo the restore. The admin can archive it again with the
 * Archive button, which switches automatic archiving back on for it.
 *
 * @return array{classes:int, sections:int}
 * @throws InvalidArgumentException when the year is missing or not archived.
 */
function restoreSchoolYear(PDO $pdo, int $yearId): array
{
    $counts = ['classes' => 0, 'sections' => 0];

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT is_archived, archived_at FROM schoolyears WHERE school_year_id = ? FOR UPDATE");
        $stmt->execute([$yearId]);
        $year = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$year) {
            throw new InvalidArgumentException('School year not found.');
        }
        if ((int) $year['is_archived'] !== 1) {
            throw new InvalidArgumentException('That school year is not archived.');
        }

        $stamp = $year['archived_at'];
        if ($stamp !== null) {
            $classes = $pdo->prepare("UPDATE classofferings SET is_archived = 0, archived_at = NULL WHERE school_year_id = ? AND is_archived = 1 AND archived_at = ?");
            $classes->execute([$yearId, $stamp]);
            $counts['classes'] = $classes->rowCount();

            $sections = $pdo->prepare("UPDATE sections SET is_archived = 0, archived_at = NULL WHERE school_year_id = ? AND is_archived = 1 AND archived_at = ?");
            $sections->execute([$yearId, $stamp]);
            $counts['sections'] = $sections->rowCount();
        }

        $pdo->prepare("UPDATE schoolyears SET is_archived = 0, archived_at = NULL, auto_archive_paused = 1 WHERE school_year_id = ?")
            ->execute([$yearId]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return $counts;
}

/**
 * Automatic year-end archive. Archives every school year whose end date has
 * passed (the day after its end date) that is not already archived and that an
 * admin has not restored (auto_archive_paused). Classes and sections are
 * archived exactly as the manual Archive button does it, so the same Restore
 * undoes it.
 *
 * Runs from config.php on every request; when there is nothing to do it costs
 * a single small SELECT. It never throws, so it can never break a page.
 *
 * @param string|null $today Y-m-d, for tests; defaults to today.
 * @return list<array{school_year_id:int, classes:int, sections:int}> the years archived by this run.
 */
function autoArchiveEndedSchoolYears(PDO $pdo, ?string $today = null): array
{
    $today = $today ?? date('Y-m-d');
    $done  = [];

    try {
        $stmt = $pdo->prepare(
            "SELECT school_year_id FROM schoolyears
             WHERE end_date < ? AND is_archived = 0 AND auto_archive_paused = 0
             ORDER BY end_date, school_year_id"
        );
        $stmt->execute([$today]);

        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $yearId) {
            try {
                $counts = archiveSchoolYear($pdo, (int) $yearId, $today);
                $done[] = ['school_year_id' => (int) $yearId] + $counts;
            } catch (InvalidArgumentException $e) {
                // Another request archived it first, or it changed meanwhile: nothing to do.
            }
        }
    } catch (Throwable $e) {
        // Missing columns or a database hiccup must never take a page down.
    }

    return $done;
}
autoArchiveEndedSchoolYears($pdo);

// ================= VIEWING ARCHIVED CLASSES (admin / teacher / student) =================
// Archiving never deletes anything, so every role has an "Archived" section where
// the classes of a past school year stay readable. These helpers are shared by
// those three Archived sections.

/**
 * SQL condition for "this class is archived": the class itself was archived, its
 * section was archived, or its whole school year was archived. Pass the table
 * aliases used in the query (classofferings, sections, schoolyears).
 */
function archivedClassSql(string $co = 'co', string $sec = 'sec', string $sy = 'sy'): string
{
    return "({$co}.is_archived = 1 OR {$sec}.is_archived = 1 OR {$sy}.is_archived = 1)";
}

/**
 * The school years that have at least one archived class, newest first - the
 * options of the "School Year" filter in every Archived section.
 *
 * Pass $teacherId to list only the years that teacher taught archived classes in,
 * or $studentId for the years the student was enrolled in archived classes.
 * Pass neither (admin) to list every year that has archived classes.
 *
 * @return list<array{school_year_id:int,label:string,is_current:int,is_archived:int,class_count:int}>
 */
function getArchivedSchoolYears(PDO $pdo, ?int $teacherId = null, ?int $studentId = null): array
{
    $sql = "SELECT sy.school_year_id, sy.label, sy.is_current, sy.is_archived,
                   COUNT(DISTINCT CONCAT(co.subject_id, '-', co.section_id)) AS class_count
            FROM classofferings co
            JOIN sections sec   ON sec.section_id = co.section_id
            JOIN schoolyears sy ON sy.school_year_id = co.school_year_id";
    $params = [];
    if ($studentId !== null) {
        $sql .= " JOIN enrollments e ON e.offering_id = co.offering_id AND e.student_id = ? AND e.status IN ('enrolled','completed')";
        $params[] = $studentId;
    }
    $sql .= " WHERE " . archivedClassSql();
    if ($teacherId !== null) {
        $sql .= " AND co.teacher_id = ?";
        $params[] = $teacherId;
    }
    $sql .= " GROUP BY sy.school_year_id, sy.label, sy.is_current, sy.is_archived, sy.start_date
              ORDER BY sy.start_date DESC, sy.school_year_id DESC";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Chooses which school year an Archived section shows: the one asked for
 * (?sy=) when it is in $years, otherwise the newest. Null when $years is empty.
 */
function pickArchivedSchoolYear(array $years, $requestedId): ?array
{
    if (!$years) {
        return null;
    }
    if ($requestedId !== null && ctype_digit((string) $requestedId)) {
        foreach ($years as $y) {
            if ((int) $y['school_year_id'] === (int) $requestedId) {
                return $y;
            }
        }
    }
    return $years[0];
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

/**
 * Live class grade averages (replaces the old `grades` table).
 *
 * For each offering, works out every enrolled student's Written Work /
 * Performance Task / Exam percentage from quiz_attempts and submissions (latest
 * attempt of each item), turns them into the weighted final grade, then averages
 * across students. Nothing is stored: the numbers always match the Grade Book.
 *
 * Returns [offering_id => ['graded' => int, 'ww' => ?float, 'pt' => ?float,
 *                          'exam' => ?float, 'final' => ?float]]
 * Offerings with no graded work simply have null averages and graded = 0.
 */
function computeOfferingGradeAverages(PDO $pdo, array $offeringIds): array {
    $offeringIds = array_values(array_unique(array_map('intval', $offeringIds)));
    $result = [];
    foreach ($offeringIds as $oid) {
        $result[$oid] = ['graded' => 0, 'ww' => null, 'pt' => null, 'exam' => null, 'final' => null];
    }
    if (!$offeringIds) {
        return $result;
    }
    $in = implode(',', array_fill(0, count($offeringIds), '?'));

    $per = []; // [offering][student]['ww'|'pt'|'exam'][] = percent

    $stmt = $pdo->prepare("
        SELECT q.offering_id, qa.student_id, qa.score, qa.max_score
        FROM quizzes q
        JOIN quiz_attempts qa ON qa.quiz_id = q.quiz_id
            AND qa.attempt_number = (
                SELECT MAX(x.attempt_number) FROM quiz_attempts x
                WHERE x.quiz_id = q.quiz_id AND x.student_id = qa.student_id
            )
        JOIN enrollments e ON e.offering_id = q.offering_id AND e.student_id = qa.student_id
            AND e.status IN ('enrolled','completed')
        WHERE q.offering_id IN ($in)
    ");
    $stmt->execute($offeringIds);
    foreach ($stmt->fetchAll() as $r) {
        $max = (float) ($r['max_score'] ?? 0);
        if ($r['score'] !== null && $max > 0) {
            $per[(int) $r['offering_id']][(int) $r['student_id']]['ww'][] = ((float) $r['score'] / $max) * 100;
        }
    }

    $stmt = $pdo->prepare("
        SELECT a.offering_id, a.type, a.points, sub.student_id, sub.score
        FROM assignments a
        JOIN submissions sub ON sub.assignment_id = a.assignment_id
            AND sub.attempt_number = (
                SELECT MAX(x.attempt_number) FROM submissions x
                WHERE x.assignment_id = a.assignment_id AND x.student_id = sub.student_id
            )
        JOIN enrollments e ON e.offering_id = a.offering_id AND e.student_id = sub.student_id
            AND e.status IN ('enrolled','completed')
        WHERE a.offering_id IN ($in)
    ");
    $stmt->execute($offeringIds);
    foreach ($stmt->fetchAll() as $r) {
        $points = (float) $r['points'];
        if ($r['score'] === null || $points <= 0) continue;
        $key = (($r['type'] ?? 'Activity') === 'Exam') ? 'exam' : 'pt';
        $per[(int) $r['offering_id']][(int) $r['student_id']][$key][] = ((float) $r['score'] / $points) * 100;
    }

    $avg = function (array $v): ?float {
        return $v ? round(array_sum($v) / count($v), 1) : null;
    };
    foreach ($per as $oid => $students) {
        $ww = $pt = $ex = $fin = [];
        foreach ($students as $comp) {
            $w = $avg($comp['ww'] ?? []);
            $p = $avg($comp['pt'] ?? []);
            $e = $avg($comp['exam'] ?? []);
            $f = computeWeightedFinalGrade($w, $p, $e);
            if ($f === null) continue;
            $fin[] = $f;
            if ($w !== null) $ww[] = $w;
            if ($p !== null) $pt[] = $p;
            if ($e !== null) $ex[] = $e;
        }
        $result[$oid] = [
            'graded' => count($fin),
            'ww'     => $ww  ? array_sum($ww)  / count($ww)  : null,
            'pt'     => $pt  ? array_sum($pt)  / count($pt)  : null,
            'exam'   => $ex  ? array_sum($ex)  / count($ex)  : null,
            'final'  => $fin ? array_sum($fin) / count($fin) : null,
        ];
    }
    return $result;
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