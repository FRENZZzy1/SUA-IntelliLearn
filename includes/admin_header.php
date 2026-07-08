<?php
/**
 * admin_header.php
 *
 * Reusable top header for admin pages.
 * Expects a session to already be started (session_start()) and the
 * user's info already validated by the including page — this file just
 * displays whatever is in $_SESSION.
 *
 * Include it the same way you include admin_sidebar.php:
 *   include '../../includes/admin_header.php';
 */

$displayName = $_SESSION['username'] ?? 'Guest';
$userRole    = $_SESSION['role'] ?? '';

// Reuse get_initials() from dashboard_functions.php if it's already loaded,
// otherwise fall back to a simple inline version so this file also works
// on pages that don't include the dashboard data layer.
if (function_exists('get_initials')) {
    $initials = get_initials($displayName);
} else {
    $parts = preg_split('/\s+/', trim($displayName));
    $initials = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        $initials .= strtoupper(substr($part, 0, 1));
    }
    $initials = $initials ?: '?';
}
?>
<link rel="stylesheet" href="/SUA-INTELLILEARN/includes/css/header.css">
<header class="top-header">
    <div class="header-search">
        <i class="fas fa-search"></i>
        <input type="text" placeholder="Search users, courses, reports...">
    </div>
    <div class="header-actions">
        <button class="header-btn">
            <i class="fas fa-bell"></i>
            <span class="notif-dot"></span>
        </button>
        <button class="header-btn">
            <i class="fas fa-question-circle"></i>
        </button>
        <button class="header-btn" style="width: auto; gap: 8px; padding: 0 12px; border-radius: 20px;" title="<?php echo htmlspecialchars($userRole); ?>">
            <div class="header-avatar" style="width: 28px; height: 28px; font-size: 0.7rem;"><?php echo htmlspecialchars($initials); ?></div>
            <span style="font-size: 0.8rem; font-weight: 500;"><?php echo htmlspecialchars($displayName); ?></span>
        </button>
    </div>
</header>
