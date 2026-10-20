<?php
/**
 * SUA IntelliLearn - Admin access-level and module permission helpers.
 *
 * Full access: every admin module is readable/writable.
 * Limited: module-level Read / Read & Write permissions are stored per admin.
 * Read Only: selected modules are readable only; no writes are allowed.
 */

if (!defined('SUA_ADMIN_MODULES')) {
    define('SUA_ADMIN_MODULES', [
        'dashboard'    => 'Dashboard',
        'users'        => 'User Management',
        'courses'      => 'Classes & Subjects',
        'announcements'=> 'Announcements',
        'analytics'    => 'System Analytics',
        'settings'     => 'Settings',
    ]);
}

function ensureAdminPermissionsTable(PDO $pdo): void {
    static $checked = false;
    if ($checked) return;
    $checked = true;
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_permissions (
            permission_id INT NOT NULL AUTO_INCREMENT,
            user_id INT NOT NULL,
            module_key VARCHAR(50) NOT NULL,
            permission ENUM('read','write') NOT NULL DEFAULT 'read',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (permission_id),
            UNIQUE KEY uq_admin_module (user_id, module_key),
            KEY idx_admin_permissions_user (user_id),
            CONSTRAINT fk_admin_permissions_user
              FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    } catch (Throwable $e) {
        // The explicit admin_access_permissions.sql migration remains available
        // when the database account does not have DDL permission.
    }
}

/**
 * Temporary Full access.
 *
 * A Full-access admin who is NOT the Principal must be given a limited number of
 * days (admin.full_access_expires_at). When that moment passes the account is
 * downgraded to Read Only automatically; the Principal keeps Full access for good.
 */
if (!defined('SUA_FULL_ACCESS_MAX_DAYS')) define('SUA_FULL_ACCESS_MAX_DAYS', 365);
if (!defined('SUA_FULL_ACCESS_FALLBACK_LEVEL')) define('SUA_FULL_ACCESS_FALLBACK_LEVEL', 'read_only');

/** Adds admin.full_access_expires_at when missing. Returns true if the column is usable. */
function ensureAdminFullAccessExpiry(PDO $pdo): bool {
    static $available = null;
    if ($available !== null) return $available;
    try {
        $has = $pdo->query("SHOW COLUMNS FROM admin LIKE 'full_access_expires_at'")->fetch();
        if (!$has) {
            $pdo->exec("ALTER TABLE admin ADD COLUMN full_access_expires_at DATETIME NULL DEFAULT NULL AFTER access_level");
        }
        return $available = true;
    } catch (Throwable $e) {
        // No DDL permission: run admin_full_access_expiry.sql by hand.
        return $available = false;
    }
}

/** Downgrades every admin whose temporary Full access has lapsed. */
function expireDueFullAccess(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    $done = true;
    if (!ensureAdminFullAccessExpiry($pdo)) return;
    try {
        $ids = $pdo->query("SELECT user_id FROM admin
                            WHERE access_level = 'full'
                              AND full_access_expires_at IS NOT NULL
                              AND full_access_expires_at <= NOW()")->fetchAll(PDO::FETCH_COLUMN);
        if (!$ids) return;
        $in = implode(',', array_map('intval', $ids));
        $fallback = SUA_FULL_ACCESS_FALLBACK_LEVEL;
        $pdo->exec("UPDATE admin SET access_level = " . $pdo->quote($fallback) . ", full_access_expires_at = NULL WHERE user_id IN ($in)");
        // Full access had stored Read & Write on every module; keep the modules visible but read-only.
        $pdo->exec("UPDATE admin_permissions SET permission = 'read' WHERE user_id IN ($in)");
    } catch (Throwable $e) {
        // Fail closed below: adminAccessLevel() also refuses a lapsed Full level.
    }
}

/** Only non-Principal admins with Full access get a time limit. */
function adminFullAccessNeedsExpiry(string $accessLevel, string $position): bool {
    return $accessLevel === 'full' && $position !== 'principal';
}

/**
 * Validates the "full access for N days" input.
 * Returns N, 0 = keep the current expiry (only when $canKeep), or null = nothing to set.
 */
function validateFullAccessDays(string $accessLevel, string $position, $raw, bool $canKeep, array &$errors): ?int {
    if (!adminFullAccessNeedsExpiry($accessLevel, $position)) return null;
    $raw = trim((string)$raw);
    if ($raw === '') {
        if ($canKeep) return 0;
        $errors[] = "Enter how many days this admin keeps Full access.";
        return null;
    }
    if (!ctype_digit($raw) || (int)$raw < 1 || (int)$raw > SUA_FULL_ACCESS_MAX_DAYS) {
        $errors[] = "Full access duration must be between 1 and " . SUA_FULL_ACCESS_MAX_DAYS . " days.";
        return null;
    }
    return (int)$raw;
}

/** Stores (or clears) the expiry that matches the saved access level / position. */
function saveAdminFullAccessExpiry(PDO $pdo, int $userId, string $accessLevel, string $position, ?int $days): void {
    if (!ensureAdminFullAccessExpiry($pdo)) return;
    if (!adminFullAccessNeedsExpiry($accessLevel, $position)) {
        $pdo->prepare("UPDATE admin SET full_access_expires_at = NULL WHERE user_id = ?")->execute([$userId]);
    } elseif ($days !== null && $days > 0) {
        $pdo->prepare("UPDATE admin SET full_access_expires_at = DATE_ADD(NOW(), INTERVAL ? DAY) WHERE user_id = ?")
            ->execute([$days, $userId]);
    } // $days === 0: keep the existing expiry untouched
}

/** Expiry info for display: ['expires_at' => 'Y-m-d H:i:s', 'days_left' => int] or null. */
function adminFullAccessInfo(PDO $pdo, int $userId): ?array {
    if (!ensureAdminFullAccessExpiry($pdo)) return null;
    try {
        $stmt = $pdo->prepare("SELECT full_access_expires_at AS expires_at,
                                      GREATEST(0, CEIL(TIMESTAMPDIFF(MINUTE, NOW(), full_access_expires_at) / 1440)) AS days_left
                               FROM admin WHERE user_id = ? AND access_level = 'full' AND full_access_expires_at IS NOT NULL");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? ['expires_at' => $row['expires_at'], 'days_left' => (int)$row['days_left']] : null;
    } catch (Throwable $e) {
        return null;
    }
}

function adminAccessLevel(): string {
    global $pdo;
    static $level = null;
    if ($level !== null) return $level;

    if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
        return $level = 'none';
    }

    try {
        $hasExpiry = ensureAdminFullAccessExpiry($pdo);
        if ($hasExpiry) expireDueFullAccess($pdo);
        $stmt = $pdo->prepare($hasExpiry
            ? "SELECT access_level, (full_access_expires_at IS NOT NULL AND full_access_expires_at <= NOW()) AS lapsed FROM admin WHERE user_id = ? LIMIT 1"
            : "SELECT access_level, 0 AS lapsed FROM admin WHERE user_id = ? LIMIT 1");
        $stmt->execute([(int)$_SESSION['user_id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $value = $row['access_level'] ?? null;
        // A lapsed temporary Full access never grants Full, even if the downgrade write failed.
        if ($value === 'full' && !empty($row['lapsed'])) $value = SUA_FULL_ACCESS_FALLBACK_LEVEL;
        // Fail closed: an unknown / missing level must never grant more than read access.
        return $level = in_array($value, ['full', 'limited', 'read_only'], true) ? $value : 'read_only';
    } catch (Throwable $e) {
        return $level = 'read_only';
    }
}

function adminPermissions(int $userId = 0): array {
    global $pdo;
    $userId = $userId ?: (int)($_SESSION['user_id'] ?? 0);
    if ($userId <= 0) return [];

    static $cache = [];
    if (isset($cache[$userId])) return $cache[$userId];

    try {
        $stmt = $pdo->prepare("SELECT module_key, permission FROM admin_permissions WHERE user_id = ?");
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $row) {
            $out[$row['module_key']] = [
                'permission' => in_array($row['permission'], ['read', 'write'], true) ? $row['permission'] : 'read',
                'can_approve_enrollment' => !empty($row['can_approve_enrollment']),
            ];
        }
        return $cache[$userId] = $out;
    } catch (Throwable $e) {
        return $cache[$userId] = [];
    }
}

function adminCanRead(string $module): bool {
    if (($_SESSION['role'] ?? '') !== 'admin') return false;
    if (adminAccessLevel() === 'full') return true;
    $p = adminPermissions();
    return isset($p[$module]) && in_array($p[$module]['permission'], ['read', 'write'], true);
}

function adminCanWrite(string $module): bool {
    if (($_SESSION['role'] ?? '') !== 'admin') return false;
    $level = adminAccessLevel();
    if ($level === 'full') return true;
    if ($level === 'read_only') return false; // Read Only never writes, whatever is in admin_permissions
    $p = adminPermissions();
    return isset($p[$module]) && $p[$module]['permission'] === 'write';
}

/**
 * First admin page the current admin may open (Dashboard if allowed, otherwise the
 * first module with Read / Write). Used as the landing page after login and as the
 * "go back" target, so an admin without Dashboard access is not sent to a dead end.
 */
function adminLandingUrl(): string {
    $base  = '/SUA-INTELLILEARN/public/admin/';
    $pages = [
        'dashboard'     => 'dashboard.php',
        'users'         => 'user_management.php',
        'courses'       => 'courses.php',
        'announcements' => 'announcement.php',
        'analytics'     => 'analytics.php',
        'settings'      => 'settings.php',
    ];
    foreach ($pages as $module => $page) {
        if (adminCanRead($module)) return $base . $page;
    }
    return $base . 'profile.php'; // no module access at all: profile is always allowed
}

function adminIsReadOnly(): bool {
    return ($_SESSION['role'] ?? '') === 'admin' && adminAccessLevel() === 'read_only';
}

/**
 * Some admin pages change data through plain GET links (e.g. courses.php?archive=5,
 * announcement.php?action=delete). A GET is normally treated as "read", which let
 * Read Only / read-permission admins run those actions. This detects them so they
 * are checked as WRITE requests.
 */
function adminRequestIsMutatingGet(string $file): bool {
    $perFile = [
        'courses.php'      => ['archive', 'restore', 'archive_section', 'restore_section', 'archive_subject', 'restore_subject'],
        'announcement.php' => ['action'],
    ];
    $generic = ['action', 'delete', 'remove', 'archive', 'restore', 'publish', 'unpublish', 'toggle_pin', 'approve', 'decline', 'unenroll'];
    foreach (array_merge($perFile[$file] ?? [], $generic) as $key) {
        if (isset($_GET[$key])) return true;
    }
    return false;
}

/**
 * Save the selected module permissions from the User Management form.
 * Full access is represented by a complete write + approval set so changing
 * the access level later remains predictable.
 */
function saveAdminPermissions(PDO $pdo, int $userId, string $accessLevel, string $json): void {
    $pdo->prepare("DELETE FROM admin_permissions WHERE user_id = ?")->execute([$userId]);

    $modules = [];
    if ($accessLevel === 'full') {
        foreach (array_keys(SUA_ADMIN_MODULES) as $module) {
            $modules[$module] = ['permission' => 'write', 'can_approve_enrollment' => false];
        }
    } else {
        $decoded = json_decode($json, true);
        if (is_array($decoded)) {
            foreach ($decoded as $module => $cfg) {
                if (!isset(SUA_ADMIN_MODULES[$module]) || !is_array($cfg)) continue;
                $permission = ($accessLevel === 'read_only') ? 'read' : (($cfg['permission'] ?? '') === 'write' ? 'write' : 'read');
                $modules[$module] = [
                    'permission' => $permission,
                    'can_approve_enrollment' => false, // enrollment is decided by teachers only
                ];
            }
        }
    }

    $stmt = $pdo->prepare("INSERT INTO admin_permissions (user_id, module_key, permission) VALUES (?, ?, ?)");
    foreach ($modules as $module => $cfg) {
        $stmt->execute([$userId, $module, $cfg['permission']]);
    }
}

/**
 * Every admin endpoint and the module it belongs to.
 *
 *   mode 'auto'  : GET = read, POST = write
 *   mode 'read'  : always a read-only action (exports, lookups, reports)
 *   mode 'write' : always a state-changing action
 *   mode 'open'  : not module-specific (own profile, notifications, search, chatbot)
 *
 * Any script inside /public/admin/ that is NOT listed here is treated as
 * write-protected: a POST to it is refused for non-Full admins, so a newly
 * added endpoint can never silently bypass the permission check.
 */
function adminAccessRules(): array {
    return [
        // Dashboard
        'dashboard.php'            => ['dashboard', 'auto'],
        'generate_report.php'      => ['dashboard', 'read'],
        'backup_data.php'          => ['dashboard', 'write'],
        'model_settings.php'       => ['dashboard', 'auto'],

        // User Management
        'user_management.php'      => ['users', 'auto'],
        'user_management_logic.php'=> ['users', 'auto'],
        'add_user_handler.php'     => ['users', 'write'],
        'import_student_users.php' => ['users', 'write'],
        'export_student_accounts.php' => ['users', 'read'],
        'student_import_template.php' => ['users', 'read'],
        'user_history.php'         => ['users', 'read'],

        // Classes & Subjects
        'courses.php'              => ['courses', 'auto'],
        'add_course.php'           => ['courses', 'write'],
        'update_course.php'        => ['courses', 'write'],
        'add_section.php'          => ['courses', 'write'],
        'update_section.php'       => ['courses', 'write'],
        'add_subject.php'          => ['courses', 'write'],
        'update_subject.php'       => ['courses', 'write'],
        'delete_course_offering.php' => ['courses', 'write'],
        'unenroll_students.php'    => ['courses', 'write'],
        'get_course_students.php'  => ['courses', 'read'],
        'search_entities.php'      => ['courses', 'read'],
        'export_course_students.php' => ['courses', 'read'],
        'export_section_classes.php' => ['courses', 'read'],
        'export_student_classes.php' => ['courses', 'read'],
        'export_teacher_classes.php' => ['courses', 'read'],

        // Announcements
        'announcement.php'         => ['announcements', 'auto'],
        'delete_announcement.php'  => ['announcements', 'write'],
        'get_announcements.php'    => ['announcements', 'read'],

        // System Analytics
        'analytics.php'            => ['analytics', 'auto'],
        'evaluation_ai_summary.php'=> ['analytics', 'write'],

        // Settings
        'settings.php'             => ['settings', 'auto'],
        'save_settings.php'        => ['settings', 'write'],
        'save_term_intervals.php'  => ['settings', 'write'],
        'set_current_school_year.php' => ['settings', 'write'],
        'add_school_year.php'      => ['settings', 'write'],
        'teacher_evaluation_round.php' => ['settings', 'write'],

        // Not module-specific
        'profile.php'              => [null, 'open'],
        'profile_handler.php'      => [null, 'open'],
        'notifications.php'        => [null, 'open'],
        'search.php'               => [null, 'open'],
        'chatbot.php'              => [null, 'open'],
    ];
}

/**
 * Backwards-compatible helper: which module does the current script belong to?
 */
function adminModuleForCurrentScript(): ?string {
    $file = basename($_SERVER['SCRIPT_FILENAME'] ?? '');
    $rules = adminAccessRules();
    return $rules[$file][0] ?? null;
}

function requireAdminModule(string $module, string $permission = 'read'): void {
    requireAdmin();

    $allowed = $permission === 'write' ? adminCanWrite($module) : adminCanRead($module);
    if (!$allowed) {
        http_response_code(403);
        $isJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
            || (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest')
            || str_ends_with(basename($_SERVER['SCRIPT_FILENAME'] ?? ''), '.php') && $_SERVER['REQUEST_METHOD'] === 'POST';

        if ($isJson) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'errors' => ['You do not have permission to perform this action.']]);
        } else {
            $landing = adminLandingUrl();
            // Default landing page (Dashboard) not allowed: send the admin to the first module they can open.
            if ($module === 'dashboard' && basename(parse_url($landing, PHP_URL_PATH)) !== basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
                header('Location: ' . $landing);
                exit();
            }
            echo '<!doctype html><html><head><meta charset="utf-8"><title>Access Denied</title></head><body style="font-family:Arial,sans-serif;padding:40px"><h2>Access denied</h2><p>Your account does not have permission to access this module.</p><p><a href="' . htmlspecialchars($landing, ENT_QUOTES) . '">Go back</a></p></body></html>';
        }
        exit();
    }
}

/**
 * Enforce permissions automatically for admin pages after config.php loads.
 * GET = read; POST = write (unless the endpoint is flagged read/write/open).
 */
function enforceCurrentAdminModuleAccess(): void {
    global $pdo;
    ensureAdminPermissionsTable($pdo);
    if (($_SESSION['role'] ?? '') !== 'admin') return;

    $scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME'] ?? '');
    if (strpos($scriptPath, '/public/admin/') === false) return; // only guard admin endpoints

    $file   = basename($scriptPath);
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $rules  = adminAccessRules();

    $isSafeMethod = ($method === 'GET' || $method === 'HEAD');

    // Unknown admin endpoint: reads pass, writes are refused unless Full Access.
    if (!isset($rules[$file])) {
        if (!$isSafeMethod || adminRequestIsMutatingGet($file)) {
            requireAdminModule('__unmapped__', 'write');
        }
        return;
    }

    [$module, $mode] = $rules[$file];
    if ($mode === 'open' || $module === null) return;

    if ($mode === 'auto') {
        $permission = $isSafeMethod ? 'read' : 'write';
        // GET links that change data (archive/restore/delete/publish/pin...) are WRITE actions.
        if ($isSafeMethod && adminRequestIsMutatingGet($file)) {
            $permission = 'write';
        }
    } else {
        $permission = $mode;
    }

    requireAdminModule($module, $permission);
}
?>