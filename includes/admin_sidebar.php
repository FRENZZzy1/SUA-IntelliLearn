<?php
$current = basename($_SERVER['PHP_SELF']);

// ================= CUSTOM SIDEBAR ICON CONFIG =================
// Set the path to your custom icon image (SVG/PNG). Leave empty to use the default graduation cap.
// Example: $customSidebarIcon = '../../assets/images/sua-logo.svg';
$customSidebarIcon = 'assests/images/logo.jpg'; 
// ==============================================================

// ================= DYNAMIC USER INFO (sidebar footer) =================
$displayName = $_SESSION['username'] ?? 'Guest';
$rawRole     = $_SESSION['role'] ?? '';

$roleLabels = [
    'admin'   => 'System Administrator',
    'teacher' => 'Teacher',
    'student' => 'Student',
];
$roleKey   = strtolower($rawRole);
$roleLabel = $roleLabels[$roleKey] ?? ($rawRole !== '' ? ucfirst($rawRole) : 'User');

if (function_exists('get_initials')) {
    $sidebarInitials = get_initials($displayName);
} else {
    $parts = preg_split('/\s+/', trim($displayName));
    $sidebarInitials = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        $sidebarInitials .= strtoupper(substr($part, 0, 1));
    }
    $sidebarInitials = $sidebarInitials ?: '?';
}
?>
<!-- Sidebar Stylesheet -->
<link rel="stylesheet" href="/SUA-INTELLILEARN/includes/css/admin_sidebar.css">


<!-- Mobile hamburger (visible only on small screens) -->
<button type="button" class="mobile-menu-btn" id="mobileMenuBtn" onclick="toggleMobileSidebar()" aria-label="Open menu" aria-controls="sidebar" aria-expanded="false">
    <i class="fas fa-bars"></i>
</button>

<!-- Backdrop overlay -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeMobileSidebar()"></div>

<aside class="sidebar" id="sidebar">
    <button type="button" class="toggle-btn" id="sidebarToggle" aria-label="Collapse sidebar" title="Collapse sidebar" aria-controls="sidebar" aria-expanded="true">
        <i class="fas fa-chevron-left" aria-hidden="true"></i>
    </button>

    <div class="sidebar-header">
        <div class="sidebar-logo">
            <?php if (!empty($customSidebarIcon) && file_exists($customSidebarIcon)): ?>
                <img src="<?= htmlspecialchars($customSidebarIcon) ?>" alt="SUA Logo">
            <?php else: ?>
                <i class="fas fa-graduation-cap" style="color: var(--primary); font-size: 1.2rem;"></i>
            <?php endif; ?>
        </div>
        <div class="sidebar-brand">
            St. Uriel Academy
            <span>Admin Portal</span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section">
            <div class="nav-section-title">Main</div>
            <?php if (adminCanRead('dashboard')): ?><a href="../../public/admin/dashboard.php"
                class="nav-item <?= $current === 'dashboard.php' ? 'active' : '' ?>">
                <div class="nav-icon-wrap"><i class="fas fa-th-large"></i></div>
                <span class="nav-label">Dashboard</span>
            </a><?php endif; ?>
            <?php if (adminCanRead('users')): ?><a href="../../public/admin/user_management.php"
                class="nav-item <?= $current === 'user_management.php' ? 'active' : '' ?>">
                <div class="nav-icon-wrap"><i class="fas fa-user"></i></div>
                <span class="nav-label">User Management</span>
            </a><?php endif; ?>
            <?php if (adminCanRead('courses')): ?><a href="../../public/admin/courses.php" class="nav-item <?= $current === 'courses.php' ? 'active' : '' ?>">
                <div class="nav-icon-wrap"><i class="fas fa-book"></i></div>
                <span class="nav-label">Classes & Subjects</span>
            </a><?php endif; ?>
        </div>

        <div class="nav-section">
            <div class="nav-section-title">Management</div>
            <?php if (adminCanRead('announcements')): ?><a href="../../public/admin/announcement.php"
                class="nav-item <?= $current === 'announcement.php' ? 'active' : '' ?>">
                <div class="nav-icon-wrap"><i class="fas fa-bullhorn"></i></div>
                <span class="nav-label">Announcements</span>
            </a><?php endif; ?>
        </div>

        <div class="nav-section">
            <div class="nav-section-title">Reports</div>
            <?php if (adminCanRead('analytics')): ?><a href="../../public/admin/analytics.php" class="nav-item <?= $current === 'analytics.php' ? 'active' : '' ?>">
                <div class="nav-icon-wrap"><i class="fas fa-chart-line"></i></div>
                <span class="nav-label">System Analytics</span>
            </a><?php endif; ?>
            <?php if (adminCanRead('settings')): ?><a href="../../public/admin/settings.php" class="nav-item <?= $current === 'settings.php' ? 'active' : '' ?>">
                <div class="nav-icon-wrap"><i class="fas fa-cog"></i></div>
                <span class="nav-label">Settings</span>
            </a><?php endif; ?>
        </div>
    </nav>

    <div class="sidebar-footer">
        <div class="user-mini">
            <div class="user-avatar"><?php echo htmlspecialchars($sidebarInitials); ?></div>
            <div class="user-info">
                <div class="name"><?php echo htmlspecialchars($displayName); ?></div>
                <div class="role"><?php echo htmlspecialchars($roleLabel); ?></div>
            </div>
        </div>
    </div>
</aside>
<script>
/* Sidebar behaviour: desktop collapse (remembered across pages) + mobile drawer. */
(function () {
    'use strict';
    var KEY = 'sua_sidebar_collapsed';
    var BREAKPOINT = 768;

    var sidebar   = document.getElementById('sidebar');
    if (!sidebar) return;
    var overlay   = document.getElementById('sidebarOverlay');
    var mobileBtn = document.getElementById('mobileMenuBtn');
    var toggleBtn = document.getElementById('sidebarToggle');
    var root      = document.documentElement;

    function isMobile() { return window.innerWidth <= BREAKPOINT; }
    function readSaved() { try { return localStorage.getItem(KEY) === '1'; } catch (e) { return false; } }
    function save(v) { try { localStorage.setItem(KEY, v ? '1' : '0'); } catch (e) {} }

    /* Native tooltips on the icons while the rail is collapsed */
    function syncTooltips() {
        var collapsed = sidebar.classList.contains('collapsed') && !isMobile();
        sidebar.querySelectorAll('.nav-item').forEach(function (a) {
            var label = a.querySelector('.nav-label');
            if (!label) return;
            if (collapsed) a.setAttribute('title', label.textContent.trim());
            else a.removeAttribute('title');
        });
    }

    function applyCollapsed(collapsed) {
        sidebar.classList.toggle('collapsed', collapsed);
        root.classList.toggle('sidebar-collapsed', collapsed);   /* hook for page CSS */
        if (toggleBtn) {
            var text = collapsed ? 'Expand sidebar' : 'Collapse sidebar';
            toggleBtn.setAttribute('aria-expanded', String(!collapsed));
            toggleBtn.setAttribute('aria-label', text);
            toggleBtn.setAttribute('title', text);
        }
        syncTooltips();
    }

    /* Restore the saved state without animating on page load */
    var saved = readSaved();
    if (saved) {
        var guard = document.createElement('style');
        guard.id = 'suaSidebarNoAnim';
        guard.textContent = '.sidebar, .sidebar *, .main-content, .top-header { transition: none !important; }';
        document.head.appendChild(guard);
        window.addEventListener('load', function () {
            requestAnimationFrame(function () {
                requestAnimationFrame(function () {
                    var g = document.getElementById('suaSidebarNoAnim');
                    if (g) g.remove();
                });
            });
        });
    }
    applyCollapsed(saved);

    /* ---------- Mobile drawer ---------- */
    function setMobileOpen(open) {
        sidebar.classList.toggle('mobile-open', open);
        if (overlay) overlay.classList.toggle('active', open);
        document.body.classList.toggle('sidebar-locked', open);
        if (mobileBtn) {
            mobileBtn.innerHTML = open ? '<i class="fas fa-times"></i>' : '<i class="fas fa-bars"></i>';
            mobileBtn.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
            mobileBtn.setAttribute('aria-expanded', String(open));
        }
    }
    window.closeMobileSidebar  = function () { setMobileOpen(false); };
    window.toggleMobileSidebar = function () { setMobileOpen(!sidebar.classList.contains('mobile-open')); };

    /* Desktop collapse / mobile drawer dispatcher */
    window.toggleSidebar = window.suaToggleSidebar = function () {
        if (isMobile()) { window.toggleMobileSidebar(); return; }
        var collapsed = !sidebar.classList.contains('collapsed');
        applyCollapsed(collapsed);
        save(collapsed);
    };

    if (toggleBtn) toggleBtn.addEventListener('click', window.toggleSidebar);

    /* Close drawer when a link is tapped */
    sidebar.querySelectorAll('.nav-item').forEach(function (link) {
        link.addEventListener('click', function () { if (isMobile()) setMobileOpen(false); });
    });

    /* ESC closes the drawer */
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') setMobileOpen(false);
    });

    /* Swipe left to close */
    var touchStartX = 0;
    sidebar.addEventListener('touchstart', function (e) {
        touchStartX = e.touches[0].clientX;
    }, { passive: true });
    sidebar.addEventListener('touchend', function (e) {
        if (!sidebar.classList.contains('mobile-open')) return;
        if (touchStartX - e.changedTouches[0].clientX > 60) setMobileOpen(false);
    }, { passive: true });

    /* Leaving mobile width: close the drawer and refresh tooltips */
    window.addEventListener('resize', function () {
        if (!isMobile()) setMobileOpen(false);
        syncTooltips();
    });
})();
</script>