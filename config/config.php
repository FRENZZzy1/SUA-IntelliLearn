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
 * Every class offering follows the single, school-wide "current term".
 * Whenever the active term (per resolveCurrentTerm()) has moved on, this
 * makes sure every subject/section that was running in the previous term
 * gets a *new* classofferings row for the new term — it never rewrites
 * the old row in place. The old row (and everything hung off its
 * offering_id: enrollments, attendance, assignments, grades, quizzes,
 * announcements, materials) is left exactly as it was, so past terms
 * stay permanent, queryable records instead of being overwritten.
 *
 * Called after "Set Term Interval" is saved, and on courses.php load, so
 * it also picks up a term boundary being crossed purely by the passage
 * of time (no admin action needed).
 *
 * Only touches the school year currently flagged is_current on
 * schoolyears — classofferings from any other school year are history
 * and must never be advanced or cloned.
 *
 * A subject+section pairing is treated as one "lineage" across terms.
 * For each lineage, this finds whichever row already reached the
 * furthest term, and — if that's still behind the school-wide current
 * term — clones it forward one term at a time (TRM 1 -> TRM 2 -> TRM 3),
 * carrying over teacher/schedule/capacity/status from the term it's
 * cloned from, plus every actively-enrolled student (so the roster
 * continues instead of starting empty). This also naturally catches up
 * a lineage that's more than one term behind, e.g. nobody opened the
 * site while TRM 2 was active.
 *
 * Cloning stops for a lineage as soon as a row already exists at the
 * next term (e.g. an admin added it manually) — that row is left as-is
 * and the lineage isn't advanced past it, rather than erroring out or
 * clobbering a manually-created class.
 */
function syncCourseTermsToCurrent($pdo) {
    $currentTerm = resolveCurrentTerm(getTermIntervals($pdo));
    if ($currentTerm === null) {
        return; // No interval configured / today falls outside every range — leave classes as-is.
    }

    $termOrder    = ['TRM 1', 'TRM 2', 'TRM 3'];
    $currentIndex = array_search($currentTerm, $termOrder, true);

    try {
        $schoolYearId = $pdo->query("SELECT school_year_id FROM schoolyears WHERE is_current = 1 LIMIT 1")->fetchColumn();
    } catch (PDOException $e) {
        return;
    }
    if (!$schoolYearId) {
        return; // No current school year configured — nothing to advance.
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

    // Keep only the furthest-along row per (subject_id, section_id) lineage.
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

                // Carry the roster forward: everyone still actively
                // enrolled in the term being left behind is re-enrolled
                // in the new term's offering.
                $copyEnrollments->execute([$newOfferingId, $source['offering_id']]);

                $pdo->commit();
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                // Duplicate (subject_id, section_id, quarter, school_year_id)
                // — a row for this term already exists (e.g. added
                // manually). Leave it as-is and stop advancing this
                // lineage rather than clobbering it or failing every
                // other lineage's sync.
                break;
            }

            // The next loop iteration (if the lineage is more than one
            // term behind) clones from the row we just created, so it
            // carries forward whatever the newest term's state is.
            $source['offering_id'] = $newOfferingId;
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