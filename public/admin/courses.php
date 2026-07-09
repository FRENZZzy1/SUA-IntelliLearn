<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    // TODO: adjust this path to match where your login.php actually lives
    header("Location: ../../login.php");
    exit();
}

// ================= SAMPLE / PLACEHOLDER DATA =================
// TODO: replace with a real query against your courses table.
// Each subject key maps to a tag color + a matching enrollment-bar color.
$subjectColors = [
    'Math'                => ['bg' => '#dbeafe', 'text' => '#1d4ed8', 'bar' => '#2563eb'],
    'Science'             => ['bg' => '#dcfce7', 'text' => '#15803d', 'bar' => '#16a34a'],
    'English'             => ['bg' => '#ede9fe', 'text' => '#7c3aed', 'bar' => '#7c3aed'],
    'Filipino'            => ['bg' => '#fef3c7', 'text' => '#b45309', 'bar' => '#d97706'],
    'TLE'                 => ['bg' => '#ccfbf1', 'text' => '#0f766e', 'bar' => '#0d9488'],
    'MAPEH'               => ['bg' => '#fee2e2', 'text' => '#b91c1c', 'bar' => '#9ca3af'],
    'Araling Panlipunan'  => ['bg' => '#fce7f3', 'text' => '#be185d', 'bar' => '#db2777'],
];

$courses = [
    ['subject' => 'Math',               'name' => 'Mathematics 10',           'grade' => 'Grade 10', 'teacher' => 'Mr. Dela Cruz',   'enrolled' => 42, 'capacity' => 50, 'status' => 'active'],
    ['subject' => 'Science',            'name' => 'Science 10',               'grade' => 'Grade 10', 'teacher' => 'Ms. Villanueva',  'enrolled' => 38, 'capacity' => 50, 'status' => 'active'],
    ['subject' => 'English',            'name' => 'English 10',               'grade' => 'Grade 10', 'teacher' => 'Ms. Aquino',      'enrolled' => 45, 'capacity' => 50, 'status' => 'active'],
    ['subject' => 'Filipino',           'name' => 'Filipino 10',              'grade' => 'Grade 10', 'teacher' => 'Gng. Ramos',      'enrolled' => 40, 'capacity' => 50, 'status' => 'active'],
    ['subject' => 'Math',               'name' => 'Mathematics 9',            'grade' => 'Grade 9',  'teacher' => 'Mr. Dela Cruz',   'enrolled' => 39, 'capacity' => 50, 'status' => 'active'],
    ['subject' => 'Science',            'name' => 'Science 9',                'grade' => 'Grade 9',  'teacher' => 'Ms. Villanueva',  'enrolled' => 35, 'capacity' => 50, 'status' => 'active'],
    ['subject' => 'English',            'name' => 'Oral Communication (SHS)', 'grade' => 'Grade 11', 'teacher' => 'Ms. Aquino',      'enrolled' => 30, 'capacity' => 50, 'status' => 'active'],
    ['subject' => 'TLE',                'name' => 'Entrepreneurship (ABM)',   'grade' => 'Grade 11', 'teacher' => 'Mr. Soriano',     'enrolled' => 28, 'capacity' => 50, 'status' => 'active'],
    ['subject' => 'MAPEH',              'name' => 'Physical Education 12',    'grade' => 'Grade 12', 'teacher' => null,              'enrolled' => 0,  'capacity' => 50, 'status' => 'inactive'],
    ['subject' => 'Araling Panlipunan', 'name' => 'Araling Panlipunan 8',     'grade' => 'Grade 8',  'teacher' => 'Gng. Ramos',      'enrolled' => 40, 'capacity' => 50, 'status' => 'active'],
];

$totalCourses    = 24;
$activeCourses   = 21;
$teachersAssigned = 18;
$totalTeachers    = 24;
$totalEnrollees  = 459;
$totalShown      = count($courses);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Courses & Subjects | SUA IntelliLearn Admin</title>

<!-- Fonts & Icons -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<!-- Courses Stylesheet -->
<link rel="stylesheet" href="assests/css/courses.css">
</head>
<body>

<?php include '../../includes/admin_sidebar.php'; ?>

<div class="main-content" id="mainContent">

    <!-- Page Header -->
    <div class="page-header">
        <h1>Courses &amp; Subjects</h1>
        <div class="header-actions">
            <div class="header-search">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Search courses, teachers...">
            </div>
            <div class="icon-circle-btn" title="Notifications">
                <i class="fas fa-bell"></i>
                <span class="dot"></span>
            </div>
            <div class="icon-circle-btn" title="Help">
                <i class="fas fa-question"></i>
            </div>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-trend info">A.Y. 2025–26</div>
            <div class="stat-value"><?= htmlspecialchars($totalCourses) ?></div>
            <div class="stat-label">Total Courses</div>
        </div>
        <div class="stat-card">
            <div class="stat-trend"><i class="fas fa-arrow-up"></i> 2</div>
            <div class="stat-value"><?= htmlspecialchars($activeCourses) ?></div>
            <div class="stat-label">Active Courses</div>
        </div>
        <div class="stat-card">
            <div class="stat-trend info"><?= htmlspecialchars($teachersAssigned) ?> assigned</div>
            <div class="stat-value"><?= htmlspecialchars($teachersAssigned) ?></div>
            <div class="stat-label">Teachers Assigned</div>
        </div>
        <div class="stat-card">
            <div class="stat-trend"><i class="fas fa-arrow-up"></i> 12</div>
            <div class="stat-value"><?= htmlspecialchars($totalEnrollees) ?></div>
            <div class="stat-label">Total Enrollees</div>
        </div>
    </div>

    <!-- Filter / Action Toolbar -->
    <div class="toolbar-row">
        <div class="toolbar-left">
            <div class="filter-pill active" onclick="setFilterPill(this)">All Courses</div>
            <div class="filter-pill" onclick="setFilterPill(this)">Active</div>
            <div class="filter-pill" onclick="setFilterPill(this)">Inactive</div>
            <select class="select-filter">
                <option>All Grade Levels</option>
                <option>Grade 7</option>
                <option>Grade 8</option>
                <option>Grade 9</option>
                <option>Grade 10</option>
                <option>Grade 11</option>
                <option>Grade 12</option>
            </select>
            <select class="select-filter">
                <option>All Strands</option>
                <option>STEM</option>
                <option>ABM</option>
                <option>HUMSS</option>
                <option>TVL</option>
            </select>
        </div>
        <div class="toolbar-right">
            <button class="btn-secondary"><i class="fas fa-file-import"></i> Import</button>
            <button class="btn-primary" onclick="location.href='add_course.php'"><i class="fas fa-plus"></i> New Course</button>
        </div>
    </div>

    <!-- Course List -->
    <div class="list-panel">
        <div class="list-panel-header">
            <h2>Course List</h2>
            <span class="count-note">Showing <?= htmlspecialchars($totalShown) ?> of <?= htmlspecialchars($totalCourses) ?> courses</span>
        </div>

        <table class="course-table">
            <thead>
                <tr>
                    <th>Course / Subject</th>
                    <th>Grade Level</th>
                    <th>Teacher Assigned</th>
                    <th>Enrollment</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($courses as $course):
                    $colors = $subjectColors[$course['subject']] ?? ['bg' => '#e5e7eb', 'text' => '#374151', 'bar' => '#9ca3af'];
                    $pct = $course['capacity'] > 0 ? round(($course['enrolled'] / $course['capacity']) * 100) : 0;
                ?>
                <tr>
                    <td>
                        <div class="course-cell">
                            <span class="subject-tag" style="background: <?= $colors['bg'] ?>; color: <?= $colors['text'] ?>;">
                                <?= htmlspecialchars($course['subject']) ?>
                            </span>
                            <span class="course-name"><?= htmlspecialchars($course['name']) ?></span>
                        </div>
                    </td>
                    <td><?= htmlspecialchars($course['grade']) ?></td>
                    <td><?= $course['teacher'] ? htmlspecialchars($course['teacher']) : '— Unassigned —' ?></td>
                    <td>
                        <div class="enrollment-cell">
                            <div class="enrollment-bar">
                                <div class="enrollment-bar-fill" style="width: <?= $pct ?>%; background: <?= $colors['bar'] ?>;"></div>
                            </div>
                            <span class="enrollment-fraction"><?= htmlspecialchars($course['enrolled']) ?>/<?= htmlspecialchars($course['capacity']) ?></span>
                        </div>
                    </td>
                    <td>
                        <span class="status-dot-badge <?= $course['status'] ?>">
                            <span class="dot"></span>
                            <?= $course['status'] === 'active' ? 'Active' : 'Inactive' ?>
                        </span>
                    </td>
                    <td>
                        <div class="row-actions">
                            <a href="edit_course.php?id=1">Edit</a>
                            <a href="assign_teacher.php?id=1">Assign</a>
                            <a class="delete" href="#" onclick="return confirmDelete()">Delete</a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="list-panel-footer">
            <span class="count-note">Showing <?= htmlspecialchars($totalShown) ?> of <?= htmlspecialchars($totalCourses) ?> courses</span>
            <button class="btn-secondary" onclick="location.href='courses.php?view=all'">View All Courses</button>
        </div>
    </div>

</div>

<script>
    // ---- Sidebar collapse/expand (shared with sidebar module) ----
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('collapsed');
    }

    // ---- Sidebar nav active state (shared with sidebar module) ----
    function setActive(el) {
        document.querySelectorAll('.nav-item').forEach(item => item.classList.remove('active'));
        el.classList.add('active');
    }

    // ---- Filter pill toggle (visual only — wire up to a real filter/query later) ----
    function setFilterPill(el) {
        document.querySelectorAll('.filter-pill').forEach(p => p.classList.remove('active'));
        el.classList.add('active');
    }

    // ---- Delete confirmation ----
    function confirmDelete() {
        return confirm('Delete this course? This cannot be undone.');
    }
</script>

</body>
</html>
