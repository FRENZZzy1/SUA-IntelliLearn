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
<style>
    /* Global search dropdown — scoped here since header.css is shared/unknown at edit time */
    .header-search { position: relative; }
    .search-results-dropdown {
        display: none;
        position: absolute;
        top: calc(100% + 8px);
        left: 0;
        width: 380px;
        max-width: 90vw;
        max-height: 420px;
        overflow-y: auto;
        background: #fff;
        border-radius: 12px;
        box-shadow: var(--shadow-lg, 0 10px 30px rgba(0,0,0,0.15));
        border: 1px solid rgba(0,0,0,0.06);
        z-index: 1000;
    }
    .search-results-dropdown.open { display: block; }
    .search-group-label {
        padding: 10px 16px 4px;
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #9ca3af;
    }
    .search-result-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 16px;
        cursor: pointer;
        text-decoration: none;
        color: inherit;
    }
    .search-result-item:hover { background: #f5f5f7; }
    .search-result-icon {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.7rem;
        font-weight: 600;
        color: #fff;
        flex-shrink: 0;
    }
    .search-result-main { display: flex; flex-direction: column; min-width: 0; }
    .search-result-title {
        font-size: 0.85rem;
        font-weight: 500;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .search-result-sub { font-size: 0.72rem; color: #9ca3af; }
    .search-empty-state, .search-loading-state {
        padding: 20px 16px;
        text-align: center;
        font-size: 0.8rem;
        color: #9ca3af;
    }
</style>
<header class="top-header">
    <div class="header-search">
        <i class="fas fa-search"></i>
        <input type="text" id="globalSearchInput" placeholder="Search users, courses, subjects..." autocomplete="off">
        <div id="searchResultsDropdown" class="search-results-dropdown"></div>
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