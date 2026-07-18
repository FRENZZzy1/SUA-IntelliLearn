<?php
$current = basename($_SERVER['PHP_SELF']);

// ================= DYNAMIC USER INFO (sidebar footer) =================
// Expects a session to already be started by the including page (e.g. dashboard.php).
$displayName = $_SESSION['username'] ?? 'Guest';
$rawRole     = $_SESSION['role'] ?? '';

// Map raw role values (e.g. "admin", "teacher") to a friendlier label.
// Add more mappings here as you add roles.
$roleLabels = [
    'admin'   => 'System Administrator',
    'teacher' => 'Teacher',
    'student' => 'Student',
];
$roleKey   = strtolower($rawRole);
$roleLabel = $roleLabels[$roleKey] ?? ($rawRole !== '' ? ucfirst($rawRole) : 'User');

// Reuse get_initials() from dashboard_functions.php if it's already loaded,
// otherwise fall back to a simple inline version.
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
    <div class="toggle-btn" onclick="toggleSidebar()">
        <i class="fas fa-chevron-left"></i>
    </div>

    <div class="sidebar-header">
        <div class="sidebar-logo">
            <i class="fas fa-graduation-cap" style="color: var(--primary); font-size: 1.2rem;"></i>
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
                <i class="fas fa-th-large"></i>
                <span class="nav-label">Dashboard</span>
            </a>
            <a href="../../public/admin/user_management.php"
                class="nav-item <?= $current === 'user_management.php' ? 'active' : '' ?>">
                <i class="fas fa-user"></i>
                <span class="nav-label">User Management</span>
            </a>

            <a href="../../public/admin/courses.php" class="nav-item <?= $current === 'courses.php' ? 'active' : '' ?>">
                <i class="fas fa-book"></i>
                <span class="nav-label">Classess & Subjects</span>
            </a>

        </div>

        <div class="nav-section">
            <div class="nav-section-title">Management</div>
            <a href="../../public/admin/enrollment.php" class="nav-item <?= $current === 'enrollment.php' ? 'active' : '' ?>">
                <i class="fas fa-user-plus"></i>
                <span class="nav-label">Enrollment</span>
                <span class="nav-badge">17</span>
            </a>
            <a href="../../public/admin/announcement.php"
                class="nav-item <?= $current === 'announcement.php' ? 'active' : '' ?>">
                <i class="fas fa-bullhorn"></i>
                <span class="nav-label">Announcements</span>
            </a>

        </div>

        <div class="nav-section">
            <div class="nav-section-title">Reports</div>
            <a href="#" class="nav-item" onclick="setActive(this)">
                <i class="fas fa-chart-line"></i>
                <span class="nav-label">System Analytics</span>
            </a>
            <a href="#" class="nav-item" onclick="setActive(this)">
                <i class="fas fa-cog"></i>
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