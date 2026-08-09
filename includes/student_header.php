<?php
/**
 * student_header.php
 *
 * Reusable top header for student pages. Same contract as
 * admin_header.php / teacher_header.php — session already started, user
 * already validated by the including page, this file just displays
 * $_SESSION.
 *
 * Include it the same way you include student_sidebar.php:
 *   include '../../includes/student_header.php';
 *
 * Note: $displayName defaults to the session username, which for
 * students is an auto-generated login (e.g. "STU-1234-030510"), not a
 * real name. Set $studentFullName before including this file (same as
 * student_sidebar.php) if you want the student's real name shown here.
 */

$displayName = $studentFullName ?? ($_SESSION['username'] ?? 'Guest');
$userRole    = $_SESSION['role'] ?? '';

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
    /* Search dropdown — scoped here since header.css is shared/unknown at edit time */
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
    .shp-profile { position: relative; }
    .shp-profile-trigger {
        display: flex; align-items: center; gap: 8px;
        cursor: pointer; user-select: none;
    }
    .shp-profile-trigger .fa-chevron-down {
        font-size: 0.65rem; color: #9ca3af; margin-left: 2px;
        transition: transform 0.15s ease;
    }
    .shp-profile.open .fa-chevron-down { transform: rotate(180deg); }
    .shp-profile-menu {
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
    .shp-profile.open .shp-profile-menu { display: block; }
    .shp-profile-header {
        display: flex; align-items: center; gap: 10px;
        padding: 10px 10px 12px;
        border-bottom: 1px solid #f0f0f2;
        margin-bottom: 6px;
    }
    .shp-profile-header .header-avatar { flex-shrink: 0; }
    .shp-profile-header-text { min-width: 0; }
    .shp-profile-header-name {
        font-size: 0.85rem; font-weight: 600; color: #1f2937;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .shp-profile-header-role {
        font-size: 0.72rem; color: #9ca3af; text-transform: capitalize;
    }
    .shp-profile-item {
        display: flex; align-items: center; gap: 10px;
        width: 100%; padding: 9px 10px; border-radius: 8px;
        font-size: 0.83rem; color: #374151; text-decoration: none;
        background: none; border: none; cursor: pointer; text-align: left;
        font-family: inherit;
    }
    .shp-profile-item i { width: 16px; text-align: center; color: #9ca3af; }
    .shp-profile-item:hover { background: #f5f5f7; }
    .shp-profile-item.shp-logout { color: #b91c1c; margin-top: 4px; }
    .shp-profile-item.shp-logout i { color: #b91c1c; }
    .shp-profile-item.shp-logout:hover { background: #fef2f2; }
</style>
<header class="top-header">
    <div class="header-search">
        <i class="fas fa-search"></i>
        <input type="text" id="globalSearchInput" placeholder="Search courses, announcements..." autocomplete="off">
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
        <div class="shp-profile" id="shpProfile">
            <button type="button"
                    class="header-btn shp-profile-trigger"
                    style="width: auto; gap: 8px; padding: 0 12px; border-radius: 20px;"
                    title="<?php echo htmlspecialchars($userRole); ?>"
                    onclick="shpToggleMenu()"
                    aria-haspopup="true"
                    aria-expanded="false">
                <div class="header-avatar" style="width: 28px; height: 28px; font-size: 0.7rem;"><?php echo htmlspecialchars($initials); ?></div>
                <span style="font-size: 0.8rem; font-weight: 500;"><?php echo htmlspecialchars($displayName); ?></span>
                <i class="fas fa-chevron-down"></i>
            </button>
            <div class="shp-profile-menu" id="shpProfileMenu">
                <div class="shp-profile-header">
                    <div class="header-avatar" style="width: 34px; height: 34px; font-size: 0.75rem;"><?php echo htmlspecialchars($initials); ?></div>
                    <div class="shp-profile-header-text">
                        <div class="shp-profile-header-name"><?php echo htmlspecialchars($displayName); ?></div>
                        <div class="shp-profile-header-role"><?php echo htmlspecialchars($userRole); ?></div>
                    </div>
                </div>
                <a href="/SUA-INTELLILEARN/public/student/settings.php" class="shp-profile-item">
                    <i class="fas fa-user-cog"></i> Profile Settings
                </a>
                <a href="/SUA-INTELLILEARN/public/logout.php" class="shp-profile-item shp-logout">
                    <i class="fas fa-arrow-right-from-bracket"></i> Logout
                </a>
            </div>
        </div>
    </div>
</header>
<script>
(function () {
    function shpEl() { return document.getElementById('shpProfile'); }

    window.shpToggleMenu = function () {
        var el = shpEl();
        var trigger = el.querySelector('.shp-profile-trigger');
        var isOpen = el.classList.toggle('open');
        trigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    };

    function shpClose() {
        var el = shpEl();
        if (!el) return;
        el.classList.remove('open');
        el.querySelector('.shp-profile-trigger').setAttribute('aria-expanded', 'false');
    }

    // Close when clicking outside the dropdown.
    document.addEventListener('click', function (e) {
        var el = shpEl();
        if (el && !el.contains(e.target)) shpClose();
    });

    // Close on Escape.
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') shpClose();
    });
})();
</script>