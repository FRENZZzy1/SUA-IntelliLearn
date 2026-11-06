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
<link rel="stylesheet" href="/SUA-INTELLILEARN/includes/css/admin_header.css">
<header class="top-header admin-top-header">
    <div class="header-search">
        <i class="fas fa-search"></i>
        <input type="text" id="globalSearchInput" placeholder="Search users, courses, subjects..." autocomplete="off" aria-label="Search users, courses, subjects" aria-keyshortcuts="/ Control+K Meta+K">
        <kbd class="hs-kbd" aria-hidden="true">/</kbd>
        <div id="searchResultsDropdown" class="search-results-dropdown"></div>
    </div>
    <div class="header-actions">
        <div class="shn" id="shnRoot" data-user-id="<?php echo (int) ($_SESSION['user_id'] ?? 0); ?>">
            <button type="button" class="header-btn" id="shnBtn"
                    aria-haspopup="true" aria-expanded="false" aria-controls="shnPanel"
                    aria-label="Notifications" title="Notifications">
                <i class="fas fa-bell"></i>
                <span class="shn-badge" id="shnBadge" aria-hidden="true"></span>
            </button>
            <div class="shn-panel" id="shnPanel" role="dialog" aria-label="Notifications">
                <div class="shn-head">
                    <h3 class="shn-title">Notifications</h3>
                    <div class="shn-head-actions">
                        <button type="button" class="shn-markall" id="shnMarkAll" disabled>Mark all as read</button>
                        <button type="button" class="shn-markall shn-clear" id="shnClearAll" disabled>Clear all</button>
                    </div>
                </div>
                <div class="shn-tabs" role="tablist">
                    <button type="button" class="shn-tab active" data-tab="all" role="tab">All</button>
                    <button type="button" class="shn-tab" data-tab="unread" role="tab">Unread</button>
                </div>
                <div class="shn-list" id="shnList"></div>
            </div>
        </div>
        <button type="button" class="header-btn sua-help-trigger" id="suaHelpTrigger" aria-label="Help and about SUA IntelliLearn" title="Help and about SUA IntelliLearn">
            <i class="fas fa-question-circle"></i>
        </button>
        <?php include __DIR__ . '/help_center.php'; ?>
        <span class="header-divider" aria-hidden="true"></span>
        <div class="ahp-profile" id="ahpProfile">
            <button type="button"
                    class="header-btn ahp-profile-trigger"
                    title="<?php echo htmlspecialchars($userRole); ?>"
                    onclick="ahpToggleMenu()"
                    aria-haspopup="true"
                    aria-expanded="false"
                    aria-label="Account menu for <?php echo htmlspecialchars($displayName); ?>">
                <div class="header-avatar ahp-avatar"><?php echo htmlspecialchars($initials); ?></div>
                <span class="ahp-user">
                    <span class="ahp-user-name"><?php echo htmlspecialchars($displayName); ?></span>
                    <?php if ($userRole !== ''): ?>
                    <span class="ahp-user-role"><?php echo htmlspecialchars($userRole); ?></span>
                    <?php endif; ?>
                </span>
                <i class="fas fa-chevron-down"></i>
            </button>
            <div class="ahp-profile-menu" id="ahpProfileMenu">
                <div class="ahp-profile-header">
                    <div class="header-avatar ahp-avatar ahp-avatar-lg"><?php echo htmlspecialchars($initials); ?></div>
                    <div class="ahp-profile-header-text">
                        <div class="ahp-profile-header-name"><?php echo htmlspecialchars($displayName); ?></div>
                        <div class="ahp-profile-header-role"><?php echo htmlspecialchars($userRole); ?></div>
                    </div>
                </div>
                <a href="/SUA-INTELLILEARN/public/admin/profile.php" class="ahp-profile-item">
                    <i class="fas fa-user-cog"></i> Profile Settings
                </a>
                <a href="/SUA-INTELLILEARN/public/logout.php" class="ahp-profile-item ahp-logout">
                    <i class="fas fa-arrow-right-from-bracket"></i> Logout
                </a>
            </div>
        </div>
    </div>
</header>
<script src="/SUA-INTELLILEARN/includes/js/admin_header.js"></script>


