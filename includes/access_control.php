<?php
/**
 * SUA IntelliLearn - Admin access-level and module permission helpers.
 *
 * Full access: every admin module is readable/writable and enrollment approval is allowed.
 * Limited: module-level Read / Read & Write permissions are stored per admin.
 * Read Only: selected modules are readable only; no writes are allowed.
 */

if (!defined('SUA_ADMIN_MODULES')) {
    define('SUA_ADMIN_MODULES', [
        'dashboard'    => 'Dashboard',
        'users'        => 'User Management',
        'courses'      => 'Classes & Subjects',
        'enrollment'   => 'Enrollment',
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
            can_approve_enrollment TINYINT(1) NOT NULL DEFAULT 0,
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

function adminAccessLevel(): string {
    global $pdo;
    static $level = null;
    if ($level !== null) return $level;

    if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
        return $level = 'none';
    }

    try {
        $stmt = $pdo->prepare("SELECT access_level FROM Admin WHERE user_id = ? LIMIT 1");
        $stmt->execute([(int)$_SESSION['user_id']]);
        $value = $stmt->fetchColumn();
        return $level = $value ?: 'limited';
    } catch (Throwable $e) {
        return $level = 'limited';
    }
}

function adminPermissions(int $userId = 0): array {
    global $pdo;
    $userId = $userId ?: (int)($_SESSION['user_id'] ?? 0);
    if ($userId <= 0) return [];

    static $cache = [];
    if (isset($cache[$userId])) return $cache[$userId];

    try {
        $stmt = $pdo->prepare("SELECT module_key, permission, can_approve_enrollment FROM admin_permissions WHERE user_id = ?");
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $row) {
            $out[$row['module_key']] = [
                'permission' => in_array($row['permission'], ['read', 'write'], true) ? $row['permission'] : 'read',
                'can_approve_enrollment' => (bool)$row['can_approve_enrollment'],
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
    if (adminAccessLevel() === 'full') return true;
    $p = adminPermissions();
    return isset($p[$module]) && $p[$module]['permission'] === 'write';
}

function adminCanApproveEnrollment(): bool {
    if (($_SESSION['role'] ?? '') !== 'admin') return false;
    if (adminAccessLevel() === 'full') return true;
    $p = adminPermissions();
    return !empty($p['enrollment']['can_approve_enrollment']);
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
            $modules[$module] = ['permission' => 'write', 'can_approve_enrollment' => $module === 'enrollment'];
        }
    } else {
        $decoded = json_decode($json, true);
        if (is_array($decoded)) {
            foreach ($decoded as $module => $cfg) {
                if (!isset(SUA_ADMIN_MODULES[$module]) || !is_array($cfg)) continue;
                $permission = ($accessLevel === 'read_only') ? 'read' : (($cfg['permission'] ?? '') === 'write' ? 'write' : 'read');
                $modules[$module] = [
                    'permission' => $permission,
                    'can_approve_enrollment' => $module === 'enrollment' && !empty($cfg['can_approve_enrollment']),
                ];
            }
        }
    }

    $stmt = $pdo->prepare("INSERT INTO admin_permissions (user_id, module_key, permission, can_approve_enrollment) VALUES (?, ?, ?, ?)");
    foreach ($modules as $module => $cfg) {
        $stmt->execute([$userId, $module, $cfg['permission'], $cfg['can_approve_enrollment'] ? 1 : 0]);
    }
}

/**
 * Map the current admin PHP endpoint to a logical module.
 */
function adminModuleForCurrentScript(): ?string {
    $file = basename($_SERVER['SCRIPT_FILENAME'] ?? '');

    $map = [
        'dashboard.php' => 'dashboard',
        'user_management.php' => 'users',
        'courses.php' => 'courses',
        'add_course.php' => 'courses',
        'update_course.php' => 'courses',
        'add_section.php' => 'courses',
        'update_section.php' => 'courses',
        'add_subject.php' => 'courses',
        'update_subject.php' => 'courses',
        'get_offering_sections.php' => 'courses',
        'get_section_offerings.php' => 'courses',

        'enrollment.php' => 'enrollment',
        'add_enrollment_request.php' => 'enrollment',
        'get_enrollments_count.php' => 'enrollment',
        'approve_enrollment.php' => 'enrollment',
        'deny_enrollment.php' => 'enrollment',
        'reopen_enrollment.php' => 'enrollment',

        'announcement.php' => 'announcements',

        'settings.php' => 'settings',
        'save_settings.php' => 'settings',
        'save_term_intervals.php' => 'settings',
        'set_current_school_year.php' => 'settings',
    ];

    return $map[$file] ?? null;
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
            echo '<!doctype html><html><head><meta charset="utf-8"><title>Access Denied</title></head><body style="font-family:Arial,sans-serif;padding:40px"><h2>Access denied</h2><p>Your account does not have permission to access this module.</p><p><a href="/SUA-INTELLILEARN/public/admin/dashboard.php">Return to Dashboard</a></p></body></html>';
        }
        exit();
    }
}

/**
 * Enforce permissions automatically for admin pages after config.php loads.
 * GET = read; POST = write. Enrollment approval has its own special permission.
 */
function enforceCurrentAdminModuleAccess(): void {
    global $pdo;
    ensureAdminPermissionsTable($pdo);
    if (($_SESSION['role'] ?? '') !== 'admin') return;

    $module = adminModuleForCurrentScript();
    if ($module === null) return;

    $file = basename($_SERVER['SCRIPT_FILENAME'] ?? '');
    if ($file === 'approve_enrollment.php') {
        requireAdminModule('enrollment', 'read');
        if (!adminCanApproveEnrollment()) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'errors' => ['Your account is not allowed to approve enrollment requests.']]);
            exit();
        }
        return;
    }

    $permission = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' ? 'write' : 'read';
    requireAdminModule($module, $permission);
}
?>