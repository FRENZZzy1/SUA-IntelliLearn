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


<aside class="sidebar" id="sidebar">
    <div class="toggle-btn" onclick="toggleSidebar()" title="Toggle Sidebar">
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
            <a href="../../public/admin/dashboard.php"
                class="nav-item <?= $current === 'dashboard.php' ? 'active' : '' ?>">
                <div class="nav-icon-wrap"><i class="fas fa-th-large"></i></div>
                <span class="nav-label">Dashboard</span>
            </a>
            <a href="../../public/admin/user_management.php"
                class="nav-item <?= $current === 'user_management.php' ? 'active' : '' ?>">
                <div class="nav-icon-wrap"><i class="fas fa-user"></i></div>
                <span class="nav-label">User Management</span>
            </a>
            <a href="../../public/admin/courses.php" class="nav-item <?= $current === 'courses.php' ? 'active' : '' ?>">
                <div class="nav-icon-wrap"><i class="fas fa-book"></i></div>
                <span class="nav-label">Classes & Subjects</span>
            </a>
        </div>

        <div class="nav-section">
            <div class="nav-section-title">Management</div>
            <a href="../../public/admin/enrollment.php" class="nav-item <?= $current === 'enrollment.php' ? 'active' : '' ?>">
                <div class="nav-icon-wrap"><i class="fas fa-user-plus"></i></div>
                <span class="nav-label">Enrollment</span>
            </a>
            <a href="../../public/admin/announcement.php"
                class="nav-item <?= $current === 'announcement.php' ? 'active' : '' ?>">
                <div class="nav-icon-wrap"><i class="fas fa-bullhorn"></i></div>
                <span class="nav-label">Announcements</span>
            </a>
        </div>

        <div class="nav-section">
            <div class="nav-section-title">Reports</div>
            <a href="#" class="nav-item" onclick="setActive(this)">
                <div class="nav-icon-wrap"><i class="fas fa-chart-line"></i></div>
                <span class="nav-label">System Analytics</span>
            </a>
            <a href="../../public/admin/settings.php" class="nav-item" onclick="setActive(this)">
                <div class="nav-icon-wrap"><i class="fas fa-cog"></i></div>
                <span class="nav-label">Settings</span>
            </a>
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