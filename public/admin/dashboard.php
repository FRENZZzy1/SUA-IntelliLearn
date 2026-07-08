<?php
/**
 * SUA IntelliLearn - Admin Dashboard
 * St. Uriel Academy Admin Portal
 * 
 * Modular structure:
 *   - sidebar.php (reusable sidebar component)
 *   - sidebar.css (sidebar-specific styles)
 *   - dashboard.css (main dashboard styles)
 */

// ================= SESSION / AUTH GUARD =================
session_start();

if (!isset($_SESSION['user_id'])) {
    // TODO: adjust this path to match where your login.php actually lives
    header("Location: ../../login.php");
    exit();
}

// ================= DATABASE CONNECTION =================
// TODO: adjust this path so it points to your actual db connection file
require_once '../../config/config.php';

// ================= DATA LAYER =================
require_once 'assests/api/dashboard_functions.php';

$totalStudents   = get_total_students($conn);
$totalTeachers   = get_total_teachers($conn);
$totalUsersCount = get_total_users_count($conn);
$recentUsers     = get_recent_users($conn, 4);

// NOTE: There is no Courses or Enrollments table in the current schema yet,
// so "Active Courses" and "Pending Enrollments" stay static for now.
// Once those tables exist, add get_active_courses_count() etc. to
// dashboard_functions.php the same way as the functions above.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SUA IntelliLearn - Admin Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Main Dashboard Styles (excludes sidebar/header styles) -->
    <link rel="stylesheet" href="assests/css/dashboard.css">
    <!-- Header Module Styles (paired with includes/admin_header.php) -->
    <link rel="stylesheet" href="assests/css/header.css">
</head>
<body>

    <!-- ================= SIDEBAR MODULE (includes sidebar.css automatically) ================= -->
    <?php 
    include '../../includes/admin_sidebar.php';  
    ?>

    <!-- ================= MAIN CONTENT ================= -->
    <div class="main-content">

        <!-- ================= HEADER MODULE ================= -->
        <?php include '../../includes/admin_header.php'; ?>

        <div class="content-wrapper">

            <!-- ================= WELCOME MODULE ================= -->
            <div class="welcome-banner fade-in">
                <h1>Good morning, Administrator! 👋</h1>
                <p>System overview for St. Uriel Academy — Monday, June 21, 2026</p>
            </div>

            <!-- ================= STATS MODULE ================= -->
            <div class="stats-grid fade-in">
                <div class="stat-card">
                    <div class="stat-info">
                        <h3><?php echo number_format($totalStudents); ?></h3>
                        <p>Total Students</p>
                        <div class="stat-trend up">
                            <i class="fas fa-user-check"></i>
                            <span>Live count</span>
                        </div>
                    </div>
                    <div class="stat-icon students">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <h3><?php echo number_format($totalTeachers); ?></h3>
                        <p>Total Teachers</p>
                        <div class="stat-trend up">
                            <i class="fas fa-user-check"></i>
                            <span>Live count</span>
                        </div>
                    </div>
                    <div class="stat-icon teachers">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>24</h3>
                        <p>Active Courses</p>
                        <div class="stat-trend up">
                            <i class="fas fa-info-circle"></i>
                            <span>Static — add a Courses table</span>
                        </div>
                    </div>
                    <div class="stat-icon courses">
                        <i class="fas fa-book-open"></i>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>17</h3>
                        <p>Pending Enrollments</p>
                        <div class="stat-trend down">
                            <i class="fas fa-info-circle"></i>
                            <span>Static — add an Enrollments table</span>
                        </div>
                    </div>
                    <div class="stat-icon enroll">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                </div>
            </div>

            <!-- ================= ROW 1: ANNOUNCEMENTS + QUICK ACTIONS ================= -->
            <div class="dashboard-grid fade-in">

                <!-- Announcements Module -->
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-bullhorn"></i> Announcements</h2>
                        <div class="card-actions">
                            <button class="btn-sm btn-primary" onclick="showToast('Post Announcement modal opened')">
                                <i class="fas fa-plus"></i> Post Announcement
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="announcement-list">
                            <div class="announcement-item urgent">
                                <div class="announcement-meta">
                                    <span class="announcement-tag tag-urgent">Urgent</span>
                                    <span class="announcement-date">June 20, 2026</span>
                                </div>
                                <div class="announcement-title">Quarter 3 Examination Schedule Released</div>
                                <div class="announcement-desc">The Q3 exam schedule has been finalized. Examinations begin July 7-11, 2026. Please review the full schedule posted on the school bulletin board and prepare accordingly.</div>
                                <div class="announcement-actions">
                                    <a class="action-link" onclick="showToast('Editing announcement...')">Edit</a>
                                    <a class="action-link danger" onclick="showToast('Announcement deleted')">Delete</a>
                                </div>
                            </div>
                            <div class="announcement-item">
                                <div class="announcement-meta">
                                    <span class="announcement-tag tag-academic">Academic</span>
                                    <span class="announcement-date">June 18, 2026</span>
                                </div>
                                <div class="announcement-title">System Maintenance — June 25, 10 PM</div>
                                <div class="announcement-desc">Scheduled maintenance for data backup and system updates. Platform unavailable for approx. 2 hours.</div>
                                <div class="announcement-actions">
                                    <a class="action-link" onclick="showToast('Editing announcement...')">Edit</a>
                                    <a class="action-link danger" onclick="showToast('Announcement deleted')">Delete</a>
                                </div>
                            </div>
                            <div class="announcement-item event">
                                <div class="announcement-meta">
                                    <span class="announcement-tag tag-event">Event</span>
                                    <span class="announcement-date">June 15, 2026</span>
                                </div>
                                <div class="announcement-title">No Classes: July 5-7 (Foundation Week)</div>
                                <div class="announcement-desc">In observance of the Feast of St. Uriel, no classes on July 5-7. Classes resume on July 8.</div>
                                <div class="announcement-actions">
                                    <a class="action-link" onclick="showToast('Editing announcement...')">Edit</a>
                                    <a class="action-link danger" onclick="showToast('Announcement deleted')">Delete</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions + System Analytics Module -->
                <div style="display: flex; flex-direction: column; gap: 20px;">
                    <div class="card">
                        <div class="card-header">
                            <h2><i class="fas fa-bolt"></i> Quick Actions</h2>
                        </div>
                        <div class="card-body">
                            <div class="quick-actions-grid">
                                <button class="quick-action-btn" onclick="showToast('Add User Account modal opened')">
                                    <i class="fas fa-user-plus qa-icon-green"></i>
                                    <span>Add User Account</span>
                                </button>
                                <button class="quick-action-btn" onclick="showToast('Create Course modal opened')">
                                    <i class="fas fa-plus-circle qa-icon-blue"></i>
                                    <span>Create Course</span>
                                </button>
                                <button class="quick-action-btn" onclick="showToast('Email Students modal opened')">
                                    <i class="fas fa-envelope qa-icon-orange"></i>
                                    <span>Email Students</span>
                                </button>
                                <button class="quick-action-btn" onclick="showToast('Post Announcement modal opened')">
                                    <i class="fas fa-bullhorn qa-icon-purple"></i>
                                    <span>Post Announcement</span>
                                </button>
                                <button class="quick-action-btn" onclick="showToast('Generate Report modal opened')">
                                    <i class="fas fa-file-export qa-icon-red"></i>
                                    <span>Generate Report</span>
                                </button>
                                <button class="quick-action-btn" onclick="showToast('Backup Data initiated')">
                                    <i class="fas fa-database qa-icon-teal"></i>
                                    <span>Backup Data</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h2><i class="fas fa-chart-pie"></i> System Usage Statistics</h2>
                        </div>
                        <div class="card-body">
                            <div class="analytics-grid">
                                <div class="analytics-card">
                                    <h4>38</h4>
                                    <p>Active Today</p>
                                </div>
                                <div class="analytics-card">
                                    <h4>127</h4>
                                    <p>Logins This Week</p>
                                </div>
                                <div class="analytics-card">
                                    <h4>14</h4>
                                    <p>Quizzes Taken</p>
                                </div>
                                <div class="analytics-card">
                                    <h4>3</h4>
                                    <p>AI Generations</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ================= ROW 2: USER MANAGEMENT + ENROLLMENT ================= -->
            <div class="dashboard-grid-2 fade-in" style="margin-bottom: 20px;">

                <!-- User Management Module -->
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-users-cog"></i> User Management</h2>
                        <div class="card-actions">
                            <button class="btn-sm btn-outline" onclick="showToast('Filtering users...')">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                            <button class="btn-sm btn-primary" onclick="showToast('Add User modal opened')">
                                <i class="fas fa-plus"></i> Add User
                            </button>
                        </div>
                    </div>
                    <div class="card-body" style="padding: 0;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Joined</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($recentUsers)): ?>
                                    <?php foreach ($recentUsers as $row): ?>
                                        <?php
                                            $displayName = htmlspecialchars($row['full_name']);
                                            $email = htmlspecialchars($row['email'] ?? '');
                                            $initials = get_initials($row['full_name']);
                                            $avatarColor = get_avatar_color($row['username']);
                                            $isActive = ($row['status'] === 'active');
                                            $statusClass = $isActive ? 'status-active' : 'status-inactive';
                                            $statusLabel = $isActive ? 'Active' : 'Inactive';
                                            $joined = date('M j, Y', strtotime($row['created_at']));
                                            $jsName = addslashes($displayName);
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="user-cell">
                                                    <div class="avatar" style="background: <?php echo $avatarColor; ?>;"><?php echo $initials; ?></div>
                                                    <div>
                                                        <div class="name"><?php echo $displayName; ?></div>
                                                        <div class="email"><?php echo $email; ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><span class="status-badge status-active"><span class="status-dot"></span> <?php echo htmlspecialchars($row['Role']); ?></span></td>
                                            <td><span class="status-badge <?php echo $statusClass; ?>"><span class="status-dot"></span> <?php echo $statusLabel; ?></span></td>
                                            <td style="color: var(--text-muted); font-size: 0.8rem;"><?php echo $joined; ?></td>
                                            <td>
                                                <div class="table-actions">
                                                    <button class="btn-view" onclick="showToast('Viewing <?php echo $jsName; ?>...')">View</button>
                                                    <button class="btn-edit" onclick="showToast('Editing <?php echo $jsName; ?>...')">Edit</button>
                                                    <button class="btn-deactivate" onclick="showToast('<?php echo $isActive ? 'Deactivating' : 'Activating'; ?> <?php echo $jsName; ?>...')"><?php echo $isActive ? 'Deactivate' : 'Activate'; ?></button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 24px;">No users found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        <div class="table-footer">
                            <span>Showing <?php echo min(4, $totalUsersCount); ?> of <?php echo number_format($totalUsersCount); ?> accounts</span>
                            <a href="#" onclick="showToast('Viewing all users...')">View All Users →</a>
                        </div>
                    </div>
                </div>

                <!-- Enrollment Module -->
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-user-plus"></i> Pending Enrollments</h2>
                        <div class="card-actions">
                            <button class="btn-sm btn-success" onclick="showToast('Approving all pending...')">
                                <i class="fas fa-check"></i> Approve All
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="enrollment-stats">
                            <div class="enroll-stat pending">
                                <h4>17</h4>
                                <p>Pending</p>
                            </div>
                            <div class="enroll-stat approved">
                                <h4>459</h4>
                                <p>Total Enrolled</p>
                            </div>
                            <div class="enroll-stat approved">
                                <h4>42</h4>
                                <p>This Week</p>
                            </div>
                            <div class="enroll-stat denied">
                                <h4>5</h4>
                                <p>Denied</p>
                            </div>
                        </div>

                        <div class="enroll-filters">
                            <select>
                                <option>All Grade Levels</option>
                                <option>Grade 7</option>
                                <option>Grade 8</option>
                                <option>Grade 9</option>
                                <option>Grade 10</option>
                                <option>Grade 11</option>
                                <option>Grade 12</option>
                            </select>
                            <select>
                                <option>All Courses</option>
                                <option>Mathematics</option>
                                <option>Science</option>
                                <option>English</option>
                                <option>Filipino</option>
                            </select>
                        </div>

                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Grade</th>
                                    <th>Course</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>
                                        <div class="user-cell">
                                            <div class="avatar" style="background: var(--info);">MR</div>
                                            <div class="name">Miguel Reyes</div>
                                        </div>
                                    </td>
                                    <td>Grade 10</td>
                                    <td>Science 10</td>
                                    <td>
                                        <button class="approve-btn" onclick="showToast('Approved Miguel Reyes')">Approve</button>
                                        <button class="deny-btn" onclick="showToast('Denied Miguel Reyes')">Deny</button>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <div class="user-cell">
                                            <div class="avatar" style="background: #ec4899;">AT</div>
                                            <div class="name">Ana Torres</div>
                                        </div>
                                    </td>
                                    <td>Grade 10</td>
                                    <td>English 10</td>
                                    <td>
                                        <button class="approve-btn" onclick="showToast('Approved Ana Torres')">Approve</button>
                                        <button class="deny-btn" onclick="showToast('Denied Ana Torres')">Deny</button>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <div class="user-cell">
                                            <div class="avatar" style="background: var(--warning);">CB</div>
                                            <div class="name">Carlo Bautista</div>
                                        </div>
                                    </td>
                                    <td>Grade 10</td>
                                    <td>Filipino 10</td>
                                    <td>
                                        <button class="approve-btn" onclick="showToast('Approved Carlo Bautista')">Approve</button>
                                        <button class="deny-btn" onclick="showToast('Denied Carlo Bautista')">Deny</button>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <div class="user-cell">
                                            <div class="avatar" style="background: #8b5cf6;">LM</div>
                                            <div class="name">Liza Mendoza</div>
                                        </div>
                                    </td>
                                    <td>Grade 9</td>
                                    <td>Science 9</td>
                                    <td>
                                        <button class="approve-btn" onclick="showToast('Approved Liza Mendoza')">Approve</button>
                                        <button class="deny-btn" onclick="showToast('Denied Liza Mendoza')">Deny</button>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <div class="user-cell">
                                            <div class="avatar" style="background: var(--success);">JF</div>
                                            <div class="name">Jose Fernandez</div>
                                        </div>
                                    </td>
                                    <td>Grade 11</td>
                                    <td>Entrepreneurship (ABM)</td>
                                    <td>
                                        <button class="approve-btn" onclick="showToast('Approved Jose Fernandez')">Approve</button>
                                        <button class="deny-btn" onclick="showToast('Denied Jose Fernandez')">Deny</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <div class="table-footer">
                            <span>Showing 5 of 17 pending requests</span>
                            <a href="#" onclick="showToast('Viewing all enrollments...')">View All →</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ================= ROW 3: COURSES + PROGRESS ================= -->
            <div class="dashboard-grid-2 fade-in">

                <!-- Courses & Subjects Module -->
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-book"></i> Courses & Subjects</h2>
                        <div class="card-actions">
                            <button class="btn-sm btn-outline" onclick="showToast('Exporting courses...')">
                                <i class="fas fa-download"></i> Export
                            </button>
                            <button class="btn-sm btn-primary" onclick="showToast('New Course modal opened')">
                                <i class="fas fa-plus"></i> New Course
                            </button>
                        </div>
                    </div>
                    <div class="card-body" style="padding: 0;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Course / Subject</th>
                                    <th>Grade Level</th>
                                    <th>Teacher</th>
                                    <th>Enrolled</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>
                                        <div class="user-cell">
                                            <div class="avatar" style="background: var(--info); width: 28px; height: 28px; font-size: 0.7rem;">
                                                <i class="fas fa-calculator" style="font-size: 0.7rem;"></i>
                                            </div>
                                            <div class="name">Mathematics 10</div>
                                        </div>
                                    </td>
                                    <td>Grade 10</td>
                                    <td>Ms. Dela Cruz</td>
                                    <td>42/50</td>
                                    <td><span class="status-badge status-active"><span class="status-dot"></span> Active</span></td>
                                    <td>
                                        <div class="table-actions">
                                            <button class="btn-edit" onclick="showToast('Editing Mathematics 10...')">Edit</button>
                                            <button class="btn-view" onclick="showToast('Assigning teacher...')">Assign</button>
                                            <button class="btn-deactivate" onclick="showToast('Deleting course...')">Delete</button>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <div class="user-cell">
                                            <div class="avatar" style="background: var(--success); width: 28px; height: 28px; font-size: 0.7rem;">
                                                <i class="fas fa-flask" style="font-size: 0.7rem;"></i>
                                            </div>
                                            <div class="name">Science 10</div>
                                        </div>
                                    </td>
                                    <td>Grade 10</td>
                                    <td>Ms. Villanueva</td>
                                    <td>38/50</td>
                                    <td><span class="status-badge status-active"><span class="status-dot"></span> Active</span></td>
                                    <td>
                                        <div class="table-actions">
                                            <button class="btn-edit" onclick="showToast('Editing Science 10...')">Edit</button>
                                            <button class="btn-view" onclick="showToast('Assigning teacher...')">Assign</button>
                                            <button class="btn-deactivate" onclick="showToast('Deleting course...')">Delete</button>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <div class="user-cell">
                                            <div class="avatar" style="background: #8b5cf6; width: 28px; height: 28px; font-size: 0.7rem;">
                                                <i class="fas fa-language" style="font-size: 0.7rem;"></i>
                                            </div>
                                            <div class="name">English 10</div>
                                        </div>
                                    </td>
                                    <td>Grade 10</td>
                                    <td>Ms. Aguilar</td>
                                    <td>45/50</td>
                                    <td><span class="status-badge status-active"><span class="status-dot"></span> Active</span></td>
                                    <td>
                                        <div class="table-actions">
                                            <button class="btn-edit" onclick="showToast('Editing English 10...')">Edit</button>
                                            <button class="btn-view" onclick="showToast('Assigning teacher...')">Assign</button>
                                            <button class="btn-deactivate" onclick="showToast('Deleting course...')">Delete</button>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <div class="user-cell">
                                            <div class="avatar" style="background: var(--warning); width: 28px; height: 28px; font-size: 0.7rem;">
                                                <i class="fas fa-flag" style="font-size: 0.7rem;"></i>
                                            </div>
                                            <div class="name">Filipino 10</div>
                                        </div>
                                    </td>
                                    <td>Grade 10</td>
                                    <td>Mr. Greg Ramos</td>
                                    <td>40/50</td>
                                    <td><span class="status-badge status-active"><span class="status-dot"></span> Active</span></td>
                                    <td>
                                        <div class="table-actions">
                                            <button class="btn-edit" onclick="showToast('Editing Filipino 10...')">Edit</button>
                                            <button class="btn-view" onclick="showToast('Assigning teacher...')">Assign</button>
                                            <button class="btn-deactivate" onclick="showToast('Deleting course...')">Delete</button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <div class="table-footer">
                            <span>Showing 4 of 24 courses</span>
                            <a href="#" onclick="showToast('Viewing all courses...')">View All Courses →</a>
                        </div>
                    </div>
                </div>

                <!-- Course Enrollment Progress Module -->
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-chart-bar"></i> Course Enrollment Progress</h2>
                    </div>
                    <div class="card-body">
                        <div class="course-progress-list">
                            <div class="course-progress-item">
                                <div class="course-progress-header">
                                    <span>Mathematics 10</span>
                                    <small>42 / 50 enrolled</small>
                                </div>
                                <div class="progress-bar-bg">
                                    <div class="progress-bar-fill progress-fill-math" style="width: 84%;"></div>
                                </div>
                            </div>
                            <div class="course-progress-item">
                                <div class="course-progress-header">
                                    <span>Science 10</span>
                                    <small>38 / 50 enrolled</small>
                                </div>
                                <div class="progress-bar-bg">
                                    <div class="progress-bar-fill progress-fill-science" style="width: 76%;"></div>
                                </div>
                            </div>
                            <div class="course-progress-item">
                                <div class="course-progress-header">
                                    <span>English 10</span>
                                    <small>45 / 50 enrolled</small>
                                </div>
                                <div class="progress-bar-bg">
                                    <div class="progress-bar-fill progress-fill-english" style="width: 90%;"></div>
                                </div>
                            </div>
                            <div class="course-progress-item">
                                <div class="course-progress-header">
                                    <span>Filipino 10</span>
                                    <small>40 / 50 enrolled</small>
                                </div>
                                <div class="progress-bar-bg">
                                    <div class="progress-bar-fill progress-fill-filipino" style="width: 80%;"></div>
                                </div>
                            </div>
                            <div class="course-progress-item">
                                <div class="course-progress-header">
                                    <span>Araling Panlipunan 10</span>
                                    <small>35 / 50 enrolled</small>
                                </div>
                                <div class="progress-bar-bg">
                                    <div class="progress-bar-fill" style="width: 70%; background: var(--danger);"></div>
                                </div>
                            </div>
                            <div class="course-progress-item">
                                <div class="course-progress-header">
                                    <span>TLE - ICT</span>
                                    <small>48 / 50 enrolled</small>
                                </div>
                                <div class="progress-bar-bg">
                                    <div class="progress-bar-fill" style="width: 96%; background: var(--primary);"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Toast Notification -->
    <div id="toast" style="
        position: fixed;
        bottom: 24px;
        right: 24px;
        background: var(--text-dark);
        color: #fff;
        padding: 12px 20px;
        border-radius: 8px;
        font-size: 0.85rem;
        box-shadow: var(--shadow-lg);
        transform: translateY(100px);
        opacity: 0;
        transition: all 0.3s ease;
        z-index: 9999;
        display: flex;
        align-items: center;
        gap: 8px;
    ">
        <i class="fas fa-info-circle"></i>
        <span id="toast-msg">Notification</span>
    </div>

    <script src="assests/js/dashboard.js">
    </script>
</body>
</html>