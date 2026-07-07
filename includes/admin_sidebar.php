<?php
/**
 * SUA IntelliLearn - Sidebar Module
 * St. Uriel Academy Admin Portal
 * 
 * Includes: sidebar.css (separate stylesheet for sidebar styles)
 */
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
            <a href="#" class="nav-item active" onclick="setActive(this)">
                <i class="fas fa-th-large"></i>
                <span class="nav-label">Dashboard</span>
            </a>
            <a href="#" class="nav-item" onclick="setActive(this)">
                <i class="fas fa-users"></i>
                <span class="nav-label">User Management</span>
            </a>
            <a href="#" class="nav-item" onclick="setActive(this)">
                <i class="fas fa-book"></i>
                <span class="nav-label">Courses & Subjects</span>
            </a>
        </div>

        <div class="nav-section">
            <div class="nav-section-title">Management</div>
            <a href="#" class="nav-item" onclick="setActive(this)">
                <i class="fas fa-user-plus"></i>
                <span class="nav-label">Enrollment</span>
                <span class="nav-badge">17</span>
            </a>
            <a href="#" class="nav-item" onclick="setActive(this)">
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
            <div class="user-avatar">MS</div>
            <div class="user-info">
                <div class="name">Maria Santos</div>
                <div class="role">System Administrator</div>
            </div>
        </div>
    </div>
</aside>