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


// ================= DATABASE CONNECTION =================
// TODO: adjust this path so it points to your actual db connection file
require_once '../../config/config.php';


// ================= DATA LAYER =================
require_once 'assests/api/dashboard_functions.php';
$aum_endpoint = '/public/admin/assests/api/add_user_handler.php';
$aum_endpoint = '/SUA-IntelliLearn/public/admin/assests/api/add_user_handler.php';
include 'assests/api/add_user_modal.php';
include 'assests/api/get_enrollments_count.php';

requireAdmin();

$totalStudents   = get_total_students($pdo);
$totalTeachers   = get_total_teachers($pdo);
$totalCourses    = get_total_Class($pdo);
$pendingEnrollments = get_pending_enrollments($pdo);
$totalUsersCount = get_total_users_count($pdo);
$recentUsers     = get_recent_users($pdo, 4);

$pendingEnrollmentData   = get_pending_enrollment_groups($pdo, 5);
$pendingEnrollmentGroups = $pendingEnrollmentData['groups'];
$totalPendingGroups      = $pendingEnrollmentData['total_groups'];

$courseEnrollmentProgress = get_course_enrollment_progress($pdo, 6);
$recentCourseOfferings    = get_recent_course_offerings($pdo, 4);

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
    <!-- Enrollment module styles reused for the Pending Enrollments widget (course-requested/link-btn/modal classes) -->
    <link rel="stylesheet" href="assests/css/courses.css">
    <link rel="stylesheet" href="assests/css/add_course.css">
    <link rel="stylesheet" href="assests/css/enrollment.css">
    <!-- Header Module Styles (paired with includes/admin_header.php) -->
    
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
        <?php include 'assests/api/add_class_modal.php'; ?>
        

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
                        <h3><?php echo number_format($totalCourses); ?></h3>
                        <p>Active Courses</p>
                        <div class="stat-trend up">
                            <i class="fas fa-info-circle"></i>
                            <span>live count</span>
                        </div>
                    </div>
                    <div class="stat-icon courses">
                        <i class="fas fa-book-open"></i>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <h3><?php echo number_format($pendingEnrollments); ?></h3>
                        <p>Pending Enrollments</p>
                        <div class="stat-trend down">
                            <i class="fas fa-info-circle"></i>
                            <span>live count</span>
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
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="announcement-list" id="announcementList">
                             <p style="text-align:center; color: var(--text-muted); padding: 20px 0;">Loading announcements...</p>
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
                                <button class="quick-action-btn" onclick="openAddUserModal()">
                                    <i class="fas fa-user-plus qa-icon-green"></i>
                                    <span>Add User Account</span>
                                </button>
                                <button class="quick-action-btn" onclick="openAddCourseModal()">
                                    <i class="fas fa-plus-circle qa-icon-blue"></i>
                                    <span>Create Class</span>
                                </button>
                                <button class="quick-action-btn" onclick="showToast('Email Students modal opened')">
                                    <i class="fas fa-envelope qa-icon-orange"></i>
                                    <span>Email Students</span>
                                </button>
                                <button class="quick-action-btn" onclick="window.location.href='announcement.php'">
                                    <i class="fas fa-bullhorn qa-icon-purple"></i>
                                    <span>Post Announcement</span>
                                </button>
                                <button class="quick-action-btn" onclick="showToast('Generate Report modal opened')">
                                    <i class="fas fa-file-export qa-icon-red"></i>
                                    <span>Generate Report</span>
                                </button>
                                <button class="quick-action-btn" onclick="backupData(this)">
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
                            <a href="#" onclick="window.location.href='user_management.php'">View All Users →</a>
                        </div>
                    </div>
                </div>

                <!-- Enrollment Module -->
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-a-plus"></i> Pending Enrollments</h2>
                        <div class="card-actions">
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="enrollment-stats">
                            <div class="enroll-stat pending">
                                <h4><?php echo number_format($pendingCount); ?></h4>
                                <p>Pending</p>
                            </div>
                            <div class="enroll-stat approved">
                                <h4><?php echo number_format($totalEnrolled); ?></h4>
                                <p>Total Enrolled</p>
                            </div>
                            <div class="enroll-stat approved">
                                <h4><?php echo number_format($enrolledNewThisWeek); ?></h4>
                                <p>This Week</p>
                            </div>
                            <div class="enroll-stat denied">
                                <h4><?php echo number_format($deniedCount); ?></h4>
                                <p>Denied</p>
                            </div>
                        </div>

                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Student Name</th>
                                    <th>Course Requested</th>
                                    <th>Grade Level/Strand</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="pendingEnrollTableBody">
                                <?php if (empty($pendingEnrollmentGroups)): ?>
                                <tr>
                                    <td colspan="4" style="text-align:center; color: var(--text-muted); padding: 24px;">No pending enrollment requests.</td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($pendingEnrollmentGroups as $g):
                                    $initials  = get_initials($g['student_name']);
                                    $avatarColor = get_avatar_color($g['student_name']);
                                    $idsCsv    = implode(',', $g['request_ids']);
                                    $subjects  = $g['subjects'];
                                    $subjectsJson = htmlspecialchars(json_encode($subjects), ENT_QUOTES);
                                    $gradeStrand = 'Grade ' . (int) $g['grade_level'] . ($g['strand'] ? ' &middot; ' . htmlspecialchars($g['strand']) : '');
                                ?>
                                <tr data-request-id="<?php echo htmlspecialchars($idsCsv); ?>">
                                    <td>
                                        <div class="user-cell">
                                            <div class="avatar" style="background: <?php echo $avatarColor; ?>;"><?php echo $initials; ?></div>
                                            <div class="name"><?php echo htmlspecialchars($g['student_name']); ?></div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if (count($subjects) > 1): ?>
                                        <button type="button" class="link-btn" data-subjects="<?php echo $subjectsJson; ?>" data-student="<?php echo htmlspecialchars($g['student_name']); ?>" onclick="showPendingClassesModal(this)">
                                            <i class="fas fa-eye"></i> View Classes (<?php echo count($subjects); ?>)
                                        </button>
                                        <?php else: ?>
                                            <?php echo htmlspecialchars($subjects[0]); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $gradeStrand; ?></td>
                                    <td>
                                        <button class="approve-btn" onclick="approvePendingGroup('<?php echo htmlspecialchars($idsCsv); ?>', this)">Approve</button>
                                        <button class="deny-btn" onclick="denyPendingGroup('<?php echo htmlspecialchars($idsCsv); ?>', this)">Deny</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        <div class="table-footer">
                            <span>Showing <?php echo count($pendingEnrollmentGroups); ?> of <?php echo number_format($totalPendingGroups); ?> pending requests</span>
                            <a href="enrollment.php">View All →</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- View Classes Modal (Pending Enrollments widget) -->
            <div class="modal-overlay" id="pendingClassesOverlay" onclick="if (event.target === this) closePendingClassesModal()">
                <div class="modal-box" style="max-width: 420px;">
                    <div class="modal-header">
                        <h2 id="pendingClassesTitle">Classes Requested</h2>
                        <button type="button" class="modal-close" onclick="closePendingClassesModal()" aria-label="Close">&times;</button>
                    </div>
                    <div class="modal-body">
                        <ul id="pendingClassesList" style="margin: 0; padding-left: 20px; line-height: 1.9;"></ul>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-secondary" onclick="closePendingClassesModal()">Close</button>
                    </div>
                </div>
            </div>

            <script>
                const PENDING_ENROLL_CSRF = <?php echo json_encode($csrfToken); ?>;
                const CURRENT_USER_ID = <?php echo json_encode((int) $_SESSION['user_id']); ?>;

                function showPendingClassesModal(btn) {
                    const subjects = JSON.parse(btn.dataset.subjects || '[]');
                    document.getElementById('pendingClassesTitle').textContent = btn.dataset.student + "'s Requested Classes";
                    const list = document.getElementById('pendingClassesList');
                    list.innerHTML = '';
                    subjects.forEach(s => {
                        const li = document.createElement('li');
                        li.textContent = s;
                        list.appendChild(li);
                    });
                    document.getElementById('pendingClassesOverlay').classList.add('open');
                }

                function closePendingClassesModal() {
                    document.getElementById('pendingClassesOverlay').classList.remove('open');
                }

                function approvePendingGroup(idsCsv, btnEl) {
                    const ids = idsCsv.split(',');
                    if (btnEl) { btnEl.disabled = true; btnEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Approving...'; }
                    pendingApproveSequential(ids, 0, { approved: 0, failed: 0 });
                }

                function pendingApproveSequential(ids, idx, summary) {
                    if (idx >= ids.length) {
                        if (summary.failed) alert(`${summary.approved} approved. ${summary.failed} failed.`);
                        location.reload();
                        return;
                    }
                    const fd = new FormData();
                    fd.append('csrf', PENDING_ENROLL_CSRF);
                    fd.append('request_id', ids[idx]);

                    fetch('approve_enrollment.php', { method: 'POST', headers: { 'Accept': 'application/json' }, body: fd })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) summary.approved++; else summary.failed++;
                            pendingApproveSequential(ids, idx + 1, summary);
                        })
                        .catch(() => { summary.failed++; pendingApproveSequential(ids, idx + 1, summary); });
                }

                function denyPendingGroup(idsCsv, btnEl) {
                    if (!confirm('Deny this enrollment request? This denies every subject requested.')) return;
                    const ids = idsCsv.split(',');
                    if (btnEl) { btnEl.disabled = true; btnEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Denying...'; }
                    pendingDenySequential(ids, 0, { denied: 0, failed: 0 });
                }

                function pendingDenySequential(ids, idx, summary) {
                    if (idx >= ids.length) {
                        if (summary.failed) alert(`${summary.denied} denied. ${summary.failed} failed.`);
                        location.reload();
                        return;
                    }
                    const fd = new FormData();
                    fd.append('csrf', PENDING_ENROLL_CSRF);
                    fd.append('request_id', ids[idx]);

                    fetch('deny_enrollment.php', { method: 'POST', headers: { 'Accept': 'application/json' }, body: fd })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) summary.denied++; else summary.failed++;
                            pendingDenySequential(ids, idx + 1, summary);
                        })
                        .catch(() => { summary.failed++; pendingDenySequential(ids, idx + 1, summary); });
                }
            </script>

            <!-- ================= ROW 3: COURSES + PROGRESS ================= -->
            <div class="dashboard-grid-2 fade-in">

                <!-- Courses & Subjects Module -->
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-book"></i> Courses & Subjects</h2>
                        <div class="card-actions">
                            <button class="btn-sm btn-primary" onclick="openAddCourseModal()">
                                <i class="fas fa-plus"></i> New Class
                            </button>
                        </div>
                    </div>
                    <div class="card-body" style="padding: 0;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Course / Subject</th>
                                    <th>Grade Level/Strand</th>
                                    <th>Teacher Assigned</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentCourseOfferings)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 24px;">No class offerings found.</td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($recentCourseOfferings as $c):
                                    $subjectInitial = strtoupper(substr($c['subject_name'], 0, 1));
                                    $avatarColor    = get_avatar_color($c['subject_name']);
                                    $isActive       = $c['status'] === 'active';
                                    $gradeStrand    = 'Grade ' . $c['grade_level'] . ($c['strand'] ? ' &middot; ' . htmlspecialchars($c['strand']) : '');
                                ?>
                                <tr>
                                    <td>
                                        <div class="user-cell">
                                            <div class="avatar" style="background: <?php echo $avatarColor; ?>; width: 28px; height: 28px; font-size: 0.7rem;">
                                                <?php echo htmlspecialchars($subjectInitial); ?>
                                            </div>
                                            <div class="name"><?php echo htmlspecialchars($c['subject_name']); ?></div>
                                        </div>
                                    </td>
                                    <td><?php echo $gradeStrand; ?></td>
                                    <td><?php echo $c['teacher_name'] ? htmlspecialchars($c['teacher_name']) : '— Unassigned —'; ?></td>
                                    <td><span class="status-badge <?php echo $isActive ? 'status-active' : 'status-inactive'; ?>"><span class="status-dot"></span> <?php echo $isActive ? 'Active' : 'Inactive'; ?></span></td>
                                    <td>
                                        <div class="table-actions">
                                            <button class="btn-view" onclick="openViewStudentsModal(<?php echo (int) $c['offering_id']; ?>)">View Students</button>
                                            <button class="btn-edit" onclick="editCourseOffering(<?php echo (int) $c['offering_id']; ?>)">Update</button>
                                            <button class="btn-deactivate" onclick="deleteCourseOffering(<?php echo (int) $c['offering_id']; ?>, this)">Delete</button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        <div class="table-footer">
                            <span>Showing <?php echo count($recentCourseOfferings); ?> of <?php echo number_format($totalCourses); ?> courses</span>
                            <a href="courses.php">View All Courses →</a>
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
                            <?php if (empty($courseEnrollmentProgress)): ?>
                            <p style="text-align:center; color: var(--text-muted); padding: 20px 0;">No active class offerings yet.</p>
                            <?php else: ?>
                            <?php foreach ($courseEnrollmentProgress as $cp): ?>
                            <div class="course-progress-item">
                                <div class="course-progress-header">
                                    <span><?php echo htmlspecialchars($cp['label']); ?></span>
                                    <small><?php echo (int) $cp['enrolled']; ?> / <?php echo (int) $cp['capacity']; ?> enrolled</small>
                                </div>
                                <div class="progress-bar-bg">
                                    <div class="progress-bar-fill" style="width: <?php echo (int) $cp['percent']; ?>%; background: <?php echo $cp['color']; ?>;"></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Update Course Modal (Courses & Subjects widget — same modal used on courses.php) -->
            <div class="modal-overlay" id="editCourseOverlay" onclick="if (event.target === this) closeEditCourseModal()">
                <div class="modal-box">
                    <div class="modal-header">
                        <h2>Update Course</h2>
                        <button type="button" class="modal-close" onclick="closeEditCourseModal()" aria-label="Close">&times;</button>
                    </div>

                    <div class="modal-errors" id="editCourseErrors" hidden></div>

                    <form id="editCourseForm">
                        <input type="hidden" name="csrf" value="<?= clean($csrfToken) ?>">
                        <input type="hidden" name="offering_id" id="e_offering_id" value="">

                        <div class="modal-body">
                            <div class="form-row">
                                <label for="e_subject_id">Subject</label>
                                <select id="e_subject_id" name="subject_id" required>
                                    <option value="">Select a subject</option>
                                    <?php foreach ($modalSubjects as $s): ?>
                                        <option value="<?= (int) $s['subject_id'] ?>"><?= clean($s['subject_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-row">
                                <label for="e_section_id">Section</label>
                                <select id="e_section_id" name="section_id" required>
                                    <option value="">Select a section</option>
                                    <?php foreach ($modalSections as $sec): ?>
                                        <option value="<?= (int) $sec['section_id'] ?>">
                                            Grade <?= clean($sec['grade_level']) ?> — <?= clean($sec['section_name']) ?><?= $sec['strand'] ? ' (' . clean($sec['strand']) . ')' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-row">
                                <label for="e_teacher_id">Teacher</label>
                                <select id="e_teacher_id" name="teacher_id" required>
                                    <option value="">Select a teacher</option>
                                    <?php foreach ($modalTeachers as $t): ?>
                                        <option value="<?= (int) $t['teacher_id'] ?>"><?= clean($t['firstname'] . ' ' . $t['lastname']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-row-split">
                                <div class="form-row">
                                    <label for="e_quarter">Term</label>
                                    <select id="e_quarter" name="quarter" required>
                                        <option value="">Select</option>
                                        <?php foreach (['TRM 1', 'TRM 2', 'TRM 3'] as $q): ?>
                                            <option value="<?= $q ?>"><?= $q ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-row">
                                    <label for="e_school_year_id">School Year</label>
                                    <select id="e_school_year_id" name="school_year_id" required>
                                        <option value="">Select</option>
                                        <?php foreach ($modalSchoolYears as $sy): ?>
                                            <option value="<?= (int) $sy['school_year_id'] ?>"><?= clean($sy['label']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-row">
                                    <label for="e_capacity">Capacity</label>
                                    <input type="number" id="e_capacity" name="capacity" min="1" value="50" required>
                                </div>

                                <div class="form-row">
                                    <label for="e_status">Status</label>
                                    <select id="e_status" name="status" required>
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-row-split">
                                <div class="form-row">
                                    <label for="e_schedule_days">Schedule Days</label>
                                    <input type="text" id="e_schedule_days" name="schedule_days" maxlength="20" placeholder="e.g. M - W">
                                    <span class="field-note">Optional</span>
                                </div>

                                <div class="form-row">
                                    <label for="e_start_time">Start Time</label>
                                    <input type="text" id="e_start_time" name="start_time" maxlength="20" placeholder="e.g. 7:00 AM">
                                </div>

                                <div class="form-row">
                                    <label for="e_end_time">End Time</label>
                                    <input type="text" id="e_end_time" name="end_time" maxlength="20" placeholder="e.g. 10:00 AM">
                                </div>
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn-secondary" onclick="closeEditCourseModal()">Cancel</button>
                            <button type="submit" class="btn-primary" id="editCourseSubmitBtn"><i class="fas fa-check"></i> Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>

            <script>
                // ---- Update Course modal (Courses & Subjects widget) ----
                // Reuses submitModalForm(), already defined by the included add_class_modal.php.
                const courseOfferingsData = <?= json_encode(array_reduce($recentCourseOfferings, function ($carry, $c) {
                    $carry[(int) $c['offering_id']] = [
                        'offering_id'    => (int) $c['offering_id'],
                        'subject_id'     => (int) $c['subject_id'],
                        'section_id'     => (int) $c['section_id'],
                        'teacher_id'     => $c['teacher_id'],
                        'quarter'        => $c['quarter'],
                        'school_year_id' => (int) $c['school_year_id'],
                        'schedule_days'  => $c['schedule_days'] ?? '',
                        'start_time'     => $c['start_time'] ? date('g:i A', strtotime($c['start_time'])) : '',
                        'end_time'       => $c['end_time'] ? date('g:i A', strtotime($c['end_time'])) : '',
                        'capacity'       => (int) $c['capacity'],
                        'status'         => $c['status'],
                    ];
                    return $carry;
                }, []), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

                function openEditCourseModal(course) {
                    document.getElementById('e_offering_id').value = course.offering_id;
                    document.getElementById('e_subject_id').value = course.subject_id;
                    document.getElementById('e_section_id').value = course.section_id;
                    document.getElementById('e_teacher_id').value = course.teacher_id;
                    document.getElementById('e_quarter').value = course.quarter;
                    document.getElementById('e_school_year_id').value = course.school_year_id;
                    document.getElementById('e_capacity').value = course.capacity;
                    document.getElementById('e_status').value = course.status;
                    document.getElementById('e_schedule_days').value = course.schedule_days || '';
                    document.getElementById('e_start_time').value = course.start_time || '';
                    document.getElementById('e_end_time').value = course.end_time || '';

                    document.getElementById('editCourseErrors').hidden = true;
                    document.getElementById('editCourseOverlay').classList.add('open');
                }

                function closeEditCourseModal() {
                    document.getElementById('editCourseOverlay').classList.remove('open');
                }

                // Called by the widget's Update button — looks up the row's data and
                // opens the modal in place instead of leaving the dashboard.
                function editCourseOffering(offeringId) {
                    const data = courseOfferingsData[offeringId];
                    if (!data) {
                        showToast('Could not load that course. Please refresh.');
                        return;
                    }
                    openEditCourseModal(data);
                }

                document.getElementById('editCourseForm').addEventListener('submit', function (e) {
                    e.preventDefault();
                    submitModalForm(
                        this,
                        'update_course.php',
                        document.getElementById('editCourseSubmitBtn'),
                        document.getElementById('editCourseErrors'),
                        '<i class="fas fa-check"></i> Save Changes'
                    );
                });
            </script>

            <!-- View Students Modal (Courses & Subjects widget) -->
            <div class="modal-overlay" id="viewStudentsOverlay" onclick="if (event.target === this) closeViewStudentsModal()">
                <div class="modal-box" style="max-width: 900px; width: 90vw;">
                    <div class="modal-header">
                        <h2 id="vsModalTitle">Enrolled Students</h2>
                        <button type="button" class="modal-close" onclick="closeViewStudentsModal()" aria-label="Close">&times;</button>
                    </div>

                    <div class="modal-errors" id="viewStudentsErrors" hidden></div>

                    <div class="modal-body" style="max-height: 65vh; overflow-y: auto;">
                        <p id="vsModalSubtitle" style="margin: 0 0 12px; color: var(--text-muted);"></p>

                        <div class="vs-toolbar" style="display:flex; align-items:center; gap:10px; margin-bottom:14px; flex-wrap:wrap;">
                            <div class="header-search" style="flex:1; min-width:200px;">
                                <i class="fas fa-search"></i>
                                <input type="text" id="vsSearchInput" placeholder="Search by name or LRN..."
                                       style="width:100%;" oninput="filterViewStudents()">
                            </div>
                            <select id="vsGenderFilter" class="select-filter" onchange="filterViewStudents()">
                                <option value="all">All Genders</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                            </select>
                        </div>

                        <table class="course-table">
                            <thead>
                                <tr>
                                    <th>LRN</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Gender</th>
                                    <th>Status</th>
                                    <th>Enrolled On</th>
                                </tr>
                            </thead>
                            <tbody id="vsStudentsTableBody">
                                <tr>
                                    <td colspan="6" style="text-align:center; padding: 24px; color: var(--text-muted);">Loading...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn-secondary" id="vsExportBtn" onclick="exportViewStudents()" disabled>
                            <i class="fas fa-file-csv"></i> Export to Excel
                        </button>
                        <button type="button" class="btn-secondary" onclick="closeViewStudentsModal()">Close</button>
                    </div>
                </div>
            </div>

            <script>
                // ---- View Students modal (Courses & Subjects widget) ----
                // escapeHtml() is defined globally in assests/js/dashboard.js
                let currentViewOfferingId = null;
                let vsAllStudents = [];
                let vsCourseInfo = null;

                function openViewStudentsModal(offeringId) {
                    const overlay = document.getElementById('viewStudentsOverlay');
                    const errorBox = document.getElementById('viewStudentsErrors');
                    const tbody = document.getElementById('vsStudentsTableBody');
                    const title = document.getElementById('vsModalTitle');
                    const subtitle = document.getElementById('vsModalSubtitle');
                    const exportBtn = document.getElementById('vsExportBtn');
                    const searchInput = document.getElementById('vsSearchInput');
                    const genderFilter = document.getElementById('vsGenderFilter');

                    currentViewOfferingId = offeringId;
                    vsAllStudents = [];
                    vsCourseInfo = null;
                    exportBtn.disabled = true;

                    searchInput.value = '';
                    genderFilter.value = 'all';

                    errorBox.hidden = true;
                    title.textContent = 'Enrolled Students';
                    subtitle.textContent = '';
                    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding: 24px; color: var(--text-muted);">Loading...</td></tr>';
                    overlay.classList.add('open');

                    fetch('assests/api/get_course_students.php?offering_id=' + encodeURIComponent(offeringId), {
                        method: 'GET',
                        headers: { 'Accept': 'application/json' }
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (!data.success) {
                            tbody.innerHTML = '';
                            errorBox.innerHTML = (data.errors || ['Something went wrong.']).map(err => '<div>' + escapeHtml(err) + '</div>').join('');
                            errorBox.hidden = false;
                            return;
                        }

                        exportBtn.disabled = data.students.length === 0;

                        vsAllStudents = data.students;
                        vsCourseInfo = data.course;

                        title.textContent = vsCourseInfo.subject_name + ' — ' + vsCourseInfo.section_name;

                        renderVsStudents(vsAllStudents);
                    })
                    .catch(() => {
                        tbody.innerHTML = '';
                        errorBox.innerHTML = '<div>Something went wrong. Please try again.</div>';
                        errorBox.hidden = false;
                    });
                }

                function renderVsStudents(list) {
                    const tbody = document.getElementById('vsStudentsTableBody');
                    const subtitle = document.getElementById('vsModalSubtitle');

                    if (vsCourseInfo) {
                        const filtered = list.length !== vsAllStudents.length;
                        subtitle.textContent = 'Grade ' + vsCourseInfo.grade_level + (vsCourseInfo.strand ? ' · ' + vsCourseInfo.strand : '')
                            + ' · ' + (filtered ? list.length + ' of ' + vsAllStudents.length + ' shown' : vsAllStudents.length + '/' + vsCourseInfo.capacity + ' enrolled');
                    }

                    if (vsAllStudents.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding: 24px; color: var(--text-muted);">No students enrolled in this course yet.</td></tr>';
                        return;
                    }

                    if (list.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding: 24px; color: var(--text-muted);">No students match your search.</td></tr>';
                        return;
                    }

                    tbody.innerHTML = list.map(function (s) {
                        const fullName = [s.firstname, s.middlename, s.lastname].filter(Boolean).join(' ');
                        const statusLabel = s.status.charAt(0).toUpperCase() + s.status.slice(1);
                        const enrolledDate = s.enrolled_at ? new Date(s.enrolled_at.replace(' ', 'T')).toLocaleDateString() : '—';
                        const genderLabel = s.Gender ? (s.Gender.charAt(0).toUpperCase() + s.Gender.slice(1)) : '—';
                        return '<tr>'
                            + '<td>' + escapeHtml(s.student_lrn) + '</td>'
                            + '<td>' + escapeHtml(fullName) + '</td>'
                            + '<td>' + (s.email ? escapeHtml(s.email) : '— None —') + '</td>'
                            + '<td>' + escapeHtml(genderLabel) + '</td>'
                            + '<td><span class="status-dot-badge ' + (s.status === 'active' ? 'active' : 'inactive') + '"><span class="dot"></span>' + escapeHtml(statusLabel) + '</span></td>'
                            + '<td>' + escapeHtml(enrolledDate) + '</td>'
                            + '</tr>';
                    }).join('');
                }

                function filterViewStudents() {
                    const query = document.getElementById('vsSearchInput').value.trim().toLowerCase();
                    const gender = document.getElementById('vsGenderFilter').value;

                    const filtered = vsAllStudents.filter(function (s) {
                        if (gender !== 'all' && (s.Gender || '').toLowerCase() !== gender) {
                            return false;
                        }
                        if (query) {
                            const fullName = [s.firstname, s.middlename, s.lastname].filter(Boolean).join(' ').toLowerCase();
                            const lrn = String(s.student_lrn || '').toLowerCase();
                            if (!fullName.includes(query) && !lrn.includes(query)) {
                                return false;
                            }
                        }
                        return true;
                    });

                    renderVsStudents(filtered);
                }

                function closeViewStudentsModal() {
                    document.getElementById('viewStudentsOverlay').classList.remove('open');
                    currentViewOfferingId = null;
                    vsAllStudents = [];
                    vsCourseInfo = null;
                }

                function exportViewStudents() {
                    if (!currentViewOfferingId) return;
                    window.location = 'assests/api/export_course_students.php?offering_id=' + encodeURIComponent(currentViewOfferingId);
                }
            </script>

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
    <!-- Floating chat assistant (self-contained: injects its own styles/markup) -->
    <script src="assests/js/chatbot.js"></script>
</body>
</html>