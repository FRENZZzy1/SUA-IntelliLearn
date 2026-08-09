<?php
$current = basename($_SERVER['PHP_SELF']);

// ================= CUSTOM SIDEBAR ICON CONFIG =================
$customSidebarIcon = 'assests/images/logo.jpg';
// ==============================================================

// ================= DYNAMIC USER INFO (sidebar footer) =================
// Student usernames are auto-generated (e.g. "STU-1234-030510"), not real
// names, so pass $studentFullName (e.g. from the Students table) from the
// including page when available. Falls back to the session username.
$displayName = $studentFullName ?? ($_SESSION['username'] ?? 'Guest');
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

// ================= OPTIONAL: FOOTER GRADE/SECTION LINE =================
// Pass $studentGradeSection (e.g. "Grade 10 - Rizal") from the including
// page before requiring this file. Falls back to the role label if unset.
$footerSubline = $studentGradeSection ?? $roleLabel;

// ================= OPTIONAL: NAV BADGE COUNTS =================
// Pass these from the including page before requiring this file.
$assignmentsDueCount       = $assignmentsDueCount ?? null;
$unreadAnnouncementsCount  = $unreadAnnouncementsCount ?? null;
?>
<!-- Sidebar Stylesheet -->
<link rel="stylesheet" href="/SUA-INTELLILEARN/includes/css/student_sidebar.css">

<!-- Mobile hamburger (visible only on small screens) -->
<button class="mobile-menu-btn" id="mobileMenuBtn" onclick="toggleMobileSidebar()" aria-label="Open menu">
    <i class="fas fa-bars"></i>
</button>

<!-- Backdrop overlay -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeMobileSidebar()"></div>

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
            <span>Student's Portal</span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section">
            <div class="nav-section-title">Main</div>
            <a href="../../public/student/dashboard.php"
                class="nav-item <?= $current === 'dashboard.php' ? 'active' : '' ?>">
                <div class="nav-icon-wrap"><i class="fas fa-th-large"></i></div>
                <span class="nav-label">Dashboard</span>
            </a>
            <a href="../../public/student/courses.php"
                class="nav-item <?= $current === 'courses.php' ? 'active' : '' ?>">
                <div class="nav-icon-wrap"><i class="fas fa-book"></i></div>
                <span class="nav-label">My Courses</span>
            </a>
            <a href="../../public/student/assignments.php"
                class="nav-item <?= $current === 'assignments.php' ? 'active' : '' ?>">
                <div class="nav-icon-wrap"><i class="fas fa-file-pen"></i></div>
                <span class="nav-label">Assignments</span>
                <?php if (!empty($assignmentsDueCount)): ?>
                    <span class="nav-badge"><?= (int) $assignmentsDueCount ?></span>
                <?php endif; ?>
            </a>
        </div>

        <div class="nav-section">
            <div class="nav-section-title">Learning</div>
            <a href="../../public/student/quizzes.php"
                class="nav-item <?= $current === 'quizzes.php' ? 'active' : '' ?>">
                <div class="nav-icon-wrap"><i class="fas fa-file-circle-question"></i></div>
                <span class="nav-label">Quizzes</span>
            </a>
            <a href="../../public/student/grades.php"
                class="nav-item <?= $current === 'grades.php' ? 'active' : '' ?>">
                <div class="nav-icon-wrap"><i class="fas fa-chart-line"></i></div>
                <span class="nav-label">My Grades</span>
            </a>
            <a href="../../public/student/attendance.php"
                class="nav-item <?= $current === 'attendance.php' ? 'active' : '' ?>">
                <div class="nav-icon-wrap"><i class="fas fa-clipboard-check"></i></div>
                <span class="nav-label">Attendance</span>
            </a>
        </div>

        <div class="nav-section">
            <div class="nav-section-title">More</div>
            <a href="../../public/student/announcements.php"
                class="nav-item <?= $current === 'announcements.php' ? 'active' : '' ?>">
                <div class="nav-icon-wrap"><i class="fas fa-bullhorn"></i></div>
                <span class="nav-label">Announcements</span>
                <?php if (!empty($unreadAnnouncementsCount)): ?>
                    <span class="nav-badge"><?= (int) $unreadAnnouncementsCount ?></span>
                <?php endif; ?>
            </a>
            <a href="../../public/student/calendar.php"
                class="nav-item <?= $current === 'calendar.php' ? 'active' : '' ?>">
                <div class="nav-icon-wrap"><i class="fas fa-calendar-days"></i></div>
                <span class="nav-label">Calendar</span>
            </a>
            <a href="../../public/student/settings.php"
                class="nav-item <?= $current === 'settings.php' ? 'active' : '' ?>">
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
                <div class="role"><?php echo htmlspecialchars($footerSubline); ?></div>
            </div>
        </div>
    </div>
</aside>

<script>
(function() {
    'use strict';
    const sidebar   = document.getElementById('sidebar');
    const overlay   = document.getElementById('sidebarOverlay');
    const mobileBtn = document.getElementById('mobileMenuBtn');
    if (!sidebar) return;

    const BREAKPOINT = 768;

    /* ---------- Helpers ---------- */
    function isMobile() {
        return window.innerWidth <= BREAKPOINT;
    }

    window.closeMobileSidebar = function() {
        sidebar.classList.remove('mobile-open');
        overlay.classList.remove('active');
        document.body.classList.remove('sidebar-locked');
        if (mobileBtn) {
            mobileBtn.innerHTML = '<i class="fas fa-bars"></i>';
            mobileBtn.setAttribute('aria-label', 'Open menu');
        }
    };

    window.toggleMobileSidebar = function() {
        const willOpen = !sidebar.classList.contains('mobile-open');
        sidebar.classList.toggle('mobile-open');
        overlay.classList.toggle('active');
        document.body.classList.toggle('sidebar-locked', willOpen);
        if (mobileBtn) {
            mobileBtn.innerHTML = willOpen
                ? '<i class="fas fa-times"></i>'
                : '<i class="fas fa-bars"></i>';
            mobileBtn.setAttribute('aria-label', willOpen ? 'Close menu' : 'Open menu');
        }
    };

    /* Desktop collapse / mobile drawer dispatcher */
    window.toggleSidebar = function() {
        if (isMobile()) {
            toggleMobileSidebar();
        } else {
            sidebar.classList.toggle('collapsed');
        }
    };

    /* ---------- Auto-close on link tap (mobile) ---------- */
    sidebar.querySelectorAll('.nav-item').forEach(function(link) {
        link.addEventListener('click', function() {
            if (isMobile()) closeMobileSidebar();
        });
    });

    /* ---------- Keyboard: ESC closes drawer ---------- */
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeMobileSidebar();
    });

    /* ---------- Swipe-to-close ---------- */
    let touchStartX = 0;
    sidebar.addEventListener('touchstart', function(e) {
        touchStartX = e.touches[0].clientX;
    }, { passive: true });

    sidebar.addEventListener('touchend', function(e) {
        if (!sidebar.classList.contains('mobile-open')) return;
        const diff = touchStartX - e.changedTouches[0].clientX;
        if (diff > 60) closeMobileSidebar();   // swiped left > 60 px
    }, { passive: true });

    /* ---------- Reset when resizing back to desktop ---------- */
    window.addEventListener('resize', function() {
        if (!isMobile()) closeMobileSidebar();
    });
})();
</script>