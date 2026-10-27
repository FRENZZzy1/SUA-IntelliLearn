<?php
require_once __DIR__ . '/loading_screen.php';
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


<aside class="sidebar" id="sidebar">
    <div class="toggle-btn" onclick="suaToggleSidebar()" title="Toggle Sidebar" role="button" tabindex="0" aria-label="Toggle sidebar" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();suaToggleSidebar();}">
        <i class="fas fa-chevron-left"></i>
    </div>

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
            <?php if (adminCanRead('settings')): ?><a href="../../public/admin/settings.php" class="nav-item <?= $current === 'settings.php' ? 'active' : '' ?>" onclick="setActive(this)">
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
/* Remember the sidebar's collapsed/expanded state across pages.
   The state is stored in this browser and only changes when the toggle
   button is clicked, so moving to another module keeps it as it was. */
(function () {
    var KEY = 'sua_admin_sidebar_collapsed';
    var sidebar = document.getElementById('sidebar');
    if (!sidebar) return;

    function read() {
        try { return localStorage.getItem(KEY) === '1'; } catch (e) { return false; }
    }
    function write(collapsed) {
        try { localStorage.setItem(KEY, collapsed ? '1' : '0'); } catch (e) {}
    }

    // Apply the saved state immediately, without animating on page load.
    if (read()) {
        var guard = document.createElement('style');
        guard.id = 'suaSidebarNoAnim';
        guard.textContent = '.sidebar, .sidebar *, .main-content, .top-header { transition: none !important; }';
        document.head.appendChild(guard);
        sidebar.classList.add('collapsed');
        window.addEventListener('load', function () {
            requestAnimationFrame(function () {
                requestAnimationFrame(function () {
                    var g = document.getElementById('suaSidebarNoAnim');
                    if (g) g.remove();
                });
            });
        });
    }

    window.suaToggleSidebar = function () {
        var collapsed = sidebar.classList.toggle('collapsed');
        write(collapsed);
    };
})();
</script>
