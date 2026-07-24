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

    /* Profile dropdown */
    .ahp-profile { position: relative; }
    .ahp-profile-trigger {
        display: flex; align-items: center; gap: 8px;
        cursor: pointer; user-select: none;
    }
    .ahp-profile-trigger .fa-chevron-down {
        font-size: 0.65rem; color: #9ca3af; margin-left: 2px;
        transition: transform 0.15s ease;
    }
    .ahp-profile.open .fa-chevron-down { transform: rotate(180deg); }
    .ahp-profile-menu {
        display: none;
        position: absolute;
        top: calc(100% + 8px);
        right: 0;
        width: 220px;
        background: #fff;
        border-radius: 12px;
        box-shadow: var(--shadow-lg, 0 10px 30px rgba(0,0,0,0.15));
        border: 1px solid rgba(0,0,0,0.06);
        z-index: 1000;
        overflow: hidden;
        padding: 6px;
    }
    .ahp-profile.open .ahp-profile-menu { display: block; }
    .ahp-profile-header {
        display: flex; align-items: center; gap: 10px;
        padding: 10px 10px 12px;
        border-bottom: 1px solid #f0f0f2;
        margin-bottom: 6px;
    }
    .ahp-profile-header .header-avatar { flex-shrink: 0; }
    .ahp-profile-header-text { min-width: 0; }
    .ahp-profile-header-name {
        font-size: 0.85rem; font-weight: 600; color: #1f2937;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .ahp-profile-header-role {
        font-size: 0.72rem; color: #9ca3af; text-transform: capitalize;
    }
    .ahp-profile-item {
        display: flex; align-items: center; gap: 10px;
        width: 100%; padding: 9px 10px; border-radius: 8px;
        font-size: 0.83rem; color: #374151; text-decoration: none;
        background: none; border: none; cursor: pointer; text-align: left;
        font-family: inherit;
    }
    .ahp-profile-item i { width: 16px; text-align: center; color: #9ca3af; }
    .ahp-profile-item:hover { background: #f5f5f7; }
    .ahp-profile-item.ahp-logout { color: #b91c1c; margin-top: 4px; }
    .ahp-profile-item.ahp-logout i { color: #b91c1c; }
    .ahp-profile-item.ahp-logout:hover { background: #fef2f2; }
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
        <div class="ahp-profile" id="ahpProfile">
            <button type="button"
                    class="header-btn ahp-profile-trigger"
                    style="width: auto; gap: 8px; padding: 0 12px; border-radius: 20px;"
                    title="<?php echo htmlspecialchars($userRole); ?>"
                    onclick="ahpToggleMenu()"
                    aria-haspopup="true"
                    aria-expanded="false">
                <div class="header-avatar" style="width: 28px; height: 28px; font-size: 0.7rem;"><?php echo htmlspecialchars($initials); ?></div>
                <span style="font-size: 0.8rem; font-weight: 500;"><?php echo htmlspecialchars($displayName); ?></span>
                <i class="fas fa-chevron-down"></i>
            </button>
            <div class="ahp-profile-menu" id="ahpProfileMenu">
                <div class="ahp-profile-header">
                    <div class="header-avatar" style="width: 34px; height: 34px; font-size: 0.75rem;"><?php echo htmlspecialchars($initials); ?></div>
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
<script>
(function () {
    function ahpEl() { return document.getElementById('ahpProfile'); }

    window.ahpToggleMenu = function () {
        var el = ahpEl();
        var trigger = el.querySelector('.ahp-profile-trigger');
        var isOpen = el.classList.toggle('open');
        trigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    };

    function ahpClose() {
        var el = ahpEl();
        if (!el) return;
        el.classList.remove('open');
        el.querySelector('.ahp-profile-trigger').setAttribute('aria-expanded', 'false');
    }

    // Close when clicking outside the dropdown.
    document.addEventListener('click', function (e) {
        var el = ahpEl();
        if (el && !el.contains(e.target)) ahpClose();
    });

    // Close on Escape.
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') ahpClose();
    });
})();
</script>