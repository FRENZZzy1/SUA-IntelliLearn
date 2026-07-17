<?php
// config.php already calls session_start(), opens $conn (MySQLi) and $pdo (PDO),
// and defines requireAdmin() / clean() / CSRF + flash helpers.
require_once __DIR__ . '/../../config/config.php';

requireAdmin();

// ================= FLASH MESSAGE (e.g. after a delete) =================
$flash = getFlashMessage();

// ================= SUBJECT COLOR MAP =================
// Purely cosmetic — tags/bars fall back to gray for any subject not listed here.
$subjectColors = [
    'Math'                => ['bg' => '#dbeafe', 'text' => '#1d4ed8', 'bar' => '#2563eb'],
    'Science'             => ['bg' => '#dcfce7', 'text' => '#15803d', 'bar' => '#16a34a'],
    'English'             => ['bg' => '#ede9fe', 'text' => '#7c3aed', 'bar' => '#7c3aed'],
    'Filipino'            => ['bg' => '#fef3c7', 'text' => '#b45309', 'bar' => '#d97706'],
    'TLE'                 => ['bg' => '#ccfbf1', 'text' => '#0f766e', 'bar' => '#0d9488'],
    'MAPEH'               => ['bg' => '#fee2e2', 'text' => '#b91c1c', 'bar' => '#9ca3af'],
    'Araling Panlipunan'  => ['bg' => '#fce7f3', 'text' => '#be185d', 'bar' => '#db2777'],
];

// ================= DELETE HANDLER =================
// courses.php?delete=ID&csrf=...  (confirmed client-side, then this runs on reload)
if (isset($_GET['delete']) && ctype_digit($_GET['delete'])) {
    if (!validateCSRFToken($_GET['csrf'] ?? '')) {
        setFlashMessage('error', 'That delete link expired. Please try again.');
    } else {
        $stmt = $pdo->prepare("DELETE FROM classofferings WHERE offering_id = ?");
        $stmt->execute([(int) $_GET['delete']]);
        setFlashMessage('success', 'Course deleted.');
    }

    header("Location: courses.php?" . http_build_query(array_filter([
        'status' => $_GET['status'] ?? null,
        'grade'  => $_GET['grade'] ?? null,
        'strand' => $_GET['strand'] ?? null,
        'q'      => $_GET['q'] ?? null,
    ])));
    exit();
}

// courses.php?delete_section=ID&csrf=...  (Sections List "Delete" link)
if (isset($_GET['delete_section']) && ctype_digit($_GET['delete_section'])) {
    if (!validateCSRFToken($_GET['csrf'] ?? '')) {
        setFlashMessage('error', 'That delete link expired. Please try again.');
    } else {
        try {
            $stmt = $pdo->prepare("DELETE FROM sections WHERE section_id = ?");
            $stmt->execute([(int) $_GET['delete_section']]);
            setFlashMessage('success', 'Section deleted.');
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                setFlashMessage('error', 'That section still has courses assigned to it. Remove those courses first.');
            } else {
                setFlashMessage('error', 'Database error: ' . $e->getMessage());
            }
        }
    }

    header("Location: courses.php?open=sections");
    exit();
}

// courses.php?delete_subject=ID&csrf=...  (Subjects List "Delete" link)
if (isset($_GET['delete_subject']) && ctype_digit($_GET['delete_subject'])) {
    if (!validateCSRFToken($_GET['csrf'] ?? '')) {
        setFlashMessage('error', 'That delete link expired. Please try again.');
    } else {
        try {
            $stmt = $pdo->prepare("DELETE FROM subjects WHERE subject_id = ?");
            $stmt->execute([(int) $_GET['delete_subject']]);
            setFlashMessage('success', 'Subject deleted.');
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                setFlashMessage('error', 'That subject is still used by existing courses. Remove those courses first.');
            } else {
                setFlashMessage('error', 'Database error: ' . $e->getMessage());
            }
        }
    }

    header("Location: courses.php?open=subjects");
    exit();
}

$csrfToken = generateCSRFToken();

// Which view-panel (if any) should be open on load — set after a
// Sections/Subjects add, update, or delete action so the panel doesn't
// collapse on the user after they just worked in it.
$openView = $_GET['open'] ?? '';
if (!in_array($openView, ['sections', 'subjects'], true)) {
    $openView = '';
}

// ================= FILTER INPUTS =================
$statusFilter = $_GET['status'] ?? 'all';                 // all | active | inactive
$gradeFilter  = $_GET['grade']  ?? 'all';                 // all | 7..12
$strandFilter = $_GET['strand'] ?? 'all';                 // all | STEM | ABM | HUMSS | TVL
$searchQuery  = trim($_GET['q'] ?? '');

if (!in_array($statusFilter, ['all', 'active', 'inactive'], true)) {
    $statusFilter = 'all';
}

// ================= BUILD MAIN QUERY =================
$where  = [];
$params = [];

if ($statusFilter !== 'all') {
    $where[] = 'co.status = ?';
    $params[] = $statusFilter;
}

if ($gradeFilter !== 'all' && ctype_digit($gradeFilter)) {
    $where[] = 'sec.grade_level = ?';
    $params[] = (int) $gradeFilter;
}

if ($strandFilter !== 'all') {
    $where[] = 'sec.strand = ?';
    $params[] = $strandFilter;
}

if ($searchQuery !== '') {
    $where[] = '(s.subject_name LIKE ? OR sec.section_name LIKE ? OR CONCAT(t.firstname, " ", t.lastname) LIKE ?)';
    $like = '%' . $searchQuery . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$sql = "
    SELECT
        co.offering_id,
        co.subject_id,
        co.capacity,
        co.status,
        co.quarter,
        s.subject_name,
        sec.section_id,
        sec.section_name,
        sec.grade_level,
        sec.strand,
        t.teacher_id,
        t.firstname AS teacher_firstname,
        t.lastname  AS teacher_lastname,
        (SELECT COUNT(*) FROM enrollments e
            WHERE e.offering_id = co.offering_id AND e.status = 'active') AS enrolled_count
    FROM classofferings co
    JOIN subjects s   ON s.subject_id = co.subject_id
    JOIN sections sec ON sec.section_id = co.section_id
    LEFT JOIN teachers t ON t.teacher_id = co.teacher_id
    {$whereSql}
    ORDER BY sec.grade_level ASC, s.subject_name ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$courses = $stmt->fetchAll();

$totalShown = count($courses);

// ================= STATS (unaffected by filters — whole school) =================
$totalCourses  = (int) $pdo->query("SELECT COUNT(*) FROM classofferings")->fetchColumn();
$activeCourses = (int) $pdo->query("SELECT COUNT(*) FROM classofferings WHERE status = 'active'")->fetchColumn();

$teachersAssigned = (int) $pdo->query("SELECT COUNT(DISTINCT teacher_id) FROM classofferings")->fetchColumn();
$totalTeachers    = (int) $pdo->query("SELECT COUNT(*) FROM teachers")->fetchColumn();

$totalEnrollees = (int) $pdo->query("SELECT COUNT(*) FROM enrollments WHERE status = 'active'")->fetchColumn();

// ================= DATA FOR "NEW COURSE" / "UPDATE COURSE" MODALS =================
$modalSubjects = $pdo->query("SELECT subject_id, subject_name FROM subjects ORDER BY subject_name")->fetchAll();
$modalSections = $pdo->query("SELECT section_id, section_name, grade_level, strand FROM sections ORDER BY grade_level, section_name")->fetchAll();
$modalTeachers = $pdo->query("SELECT teacher_id, firstname, lastname FROM teachers ORDER BY lastname, firstname")->fetchAll();

// ================= DATA FOR "NEW SECTION" MODAL =================
$currentSchoolYear = $pdo->query("SELECT school_year_id, label FROM schoolyears WHERE is_current = 1 LIMIT 1")->fetch();

// ================= DATA FOR "SECTIONS" VIEW =================
$sectionsList = $pdo->query("
    SELECT
        sec.section_id,
        sec.section_name,
        sec.grade_level,
        sec.strand,
        sec.adviser_id,
        t.firstname AS adviser_firstname,
        t.lastname  AS adviser_lastname,
        sy.label AS school_year_label,
        (SELECT COUNT(*) FROM classofferings co
            WHERE co.section_id = sec.section_id) AS course_count,
        (SELECT COUNT(*) FROM enrollments e
            JOIN classofferings co2 ON co2.offering_id = e.offering_id
            WHERE co2.section_id = sec.section_id AND e.status = 'active') AS student_count
    FROM sections sec
    LEFT JOIN teachers t     ON t.teacher_id = sec.adviser_id
    LEFT JOIN schoolyears sy ON sy.school_year_id = sec.school_year_id
    ORDER BY sec.grade_level ASC, sec.section_name ASC
")->fetchAll();

// ================= DATA FOR "SUBJECTS" VIEW =================
$subjectsList = $pdo->query("
    SELECT
        s.subject_id,
        s.subject_name,
        s.description,
        (SELECT COUNT(*) FROM classofferings co
            WHERE co.subject_id = s.subject_id) AS offering_count,
        (SELECT COUNT(DISTINCT co.section_id) FROM classofferings co
            WHERE co.subject_id = s.subject_id) AS section_count
    FROM subjects s
    ORDER BY s.subject_name ASC
")->fetchAll();
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
<link rel="stylesheet" href="assests/css/add_course.css">
</head>
<body>

<?php include '../../includes/admin_sidebar.php'; ?>

<div class="main-content" id="mainContent">

    <?php if ($flash): ?>
    <div class="flash-message flash-<?= clean($flash['type']) ?>">
        <?= clean($flash['message']) ?>
    </div>
    <?php endif; ?>

    <!-- Page Header -->
    <div class="page-header">
        <h1>Courses &amp; Subjects</h1>
        <form class="header-actions" method="get" action="courses.php">
            <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
            <input type="hidden" name="grade" value="<?= htmlspecialchars($gradeFilter) ?>">
            <input type="hidden" name="strand" value="<?= htmlspecialchars($strandFilter) ?>">
            <div class="header-search">
                <i class="fas fa-search"></i>
                <input type="text" name="q" value="<?= htmlspecialchars($searchQuery) ?>" placeholder="Search courses, teachers..." onchange="this.form.submit()">
            </div>
            <div class="icon-circle-btn" title="Notifications">
                <i class="fas fa-bell"></i>
                <span class="dot"></span>
            </div>
            <div class="icon-circle-btn" title="Help">
                <i class="fas fa-question"></i>
            </div>
        </form>
    </div>

    <!-- Quick Stats -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-trend info">Live</div>
            <div class="stat-value"><?= htmlspecialchars($totalCourses) ?></div>
            <div class="stat-label">Total Courses</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= htmlspecialchars($activeCourses) ?></div>
            <div class="stat-label">Active Courses</div>
        </div>
        <div class="stat-card">
            <div class="stat-trend info"><?= htmlspecialchars($teachersAssigned) ?>/<?= htmlspecialchars($totalTeachers) ?></div>
            <div class="stat-value"><?= htmlspecialchars($teachersAssigned) ?></div>
            <div class="stat-label">Teachers Assigned</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= htmlspecialchars($totalEnrollees) ?></div>
            <div class="stat-label">Total Enrollees</div>
        </div>
    </div>

    <!-- Filter / Action Toolbar -->
    <div class="toolbar-row">
        <div class="toolbar-left">
            <a class="filter-pill <?= $statusFilter === 'all' ? 'active' : '' ?>"
               href="?<?= http_build_query(array_filter(['status' => 'all', 'grade' => $gradeFilter, 'strand' => $strandFilter, 'q' => $searchQuery])) ?>">All Courses</a>
            <a class="filter-pill <?= $statusFilter === 'active' ? 'active' : '' ?>"
               href="?<?= http_build_query(array_filter(['status' => 'active', 'grade' => $gradeFilter, 'strand' => $strandFilter, 'q' => $searchQuery])) ?>">Active</a>
            <a class="filter-pill <?= $statusFilter === 'inactive' ? 'active' : '' ?>"
               href="?<?= http_build_query(array_filter(['status' => 'inactive', 'grade' => $gradeFilter, 'strand' => $strandFilter, 'q' => $searchQuery])) ?>">Inactive</a>

            <form method="get" action="courses.php" style="display:contents">
                <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
                <input type="hidden" name="q" value="<?= htmlspecialchars($searchQuery) ?>">

                <select class="select-filter" name="grade" onchange="this.form.submit()">
                    <option value="all" <?= $gradeFilter === 'all' ? 'selected' : '' ?>>All Grade Levels</option>
                    <?php foreach ([7, 8, 9, 10, 11, 12] as $g): ?>
                        <option value="<?= $g ?>" <?= $gradeFilter == $g ? 'selected' : '' ?>>Grade <?= $g ?></option>
                    <?php endforeach; ?>
                </select>

                <select class="select-filter" name="strand" onchange="this.form.submit()">
                    <option value="all" <?= $strandFilter === 'all' ? 'selected' : '' ?>>All Strands</option>
                    <?php foreach (['STEM', 'ABM', 'HUMSS', 'TVL'] as $s): ?>
                        <option value="<?= $s ?>" <?= $strandFilter === $s ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        <div class="toolbar-right">
            <button class="btn-secondary" disabled title="Not enabled — CSV import not implemented"><i class="fas fa-file-import"></i> Import</button>
            <button class="btn-secondary <?= $openView === 'subjects' ? 'active' : '' ?>" id="toggleSubjectsBtn" onclick="togglePanel('subjects', this)"><i class="fas fa-list"></i> View Subjects</button>
            <button class="btn-secondary" onclick="openAddSubjectModal()"><i class="fas fa-book"></i> New Subject</button>
            <button class="btn-secondary <?= $openView === 'sections' ? 'active' : '' ?>" id="toggleSectionsBtn" onclick="togglePanel('sections', this)"><i class="fas fa-list"></i> View Sections</button>
            <button class="btn-secondary" onclick="openAddSectionModal()"><i class="fas fa-layer-group"></i> New Section</button>
            <button class="btn-primary" onclick="openAddCourseModal()"><i class="fas fa-plus"></i> New Course</button>
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
                <?php if (empty($courses)): ?>
                <tr>
                    <td colspan="6" style="text-align:center; padding: 40px; color: var(--text-muted);">
                        No courses match these filters.
                    </td>
                </tr>
                <?php endif; ?>

                <?php foreach ($courses as $course):
                    $colors = $subjectColors[$course['subject_name']] ?? ['bg' => '#e5e7eb', 'text' => '#374151', 'bar' => '#9ca3af'];
                    $pct = $course['capacity'] > 0 ? round(($course['enrolled_count'] / $course['capacity']) * 100) : 0;
                    $teacherName = $course['teacher_id'] ? trim($course['teacher_firstname'] . ' ' . $course['teacher_lastname']) : null;
                    $courseName = $course['subject_name'] . ' — ' . $course['section_name'];
                ?>
                <tr>
                    <td>
                        <div class="course-cell">
                            <span class="subject-tag" style="background: <?= $colors['bg'] ?>; color: <?= $colors['text'] ?>;">
                                <?= htmlspecialchars($course['subject_name']) ?>
                            </span>
                            <span class="course-name"><?= htmlspecialchars($courseName) ?></span>
                        </div>
                    </td>
                    <td>Grade <?= htmlspecialchars($course['grade_level']) ?><?= $course['strand'] ? ' · ' . htmlspecialchars($course['strand']) : '' ?></td>
                    <td><?= $teacherName ? htmlspecialchars($teacherName) : '— Unassigned —' ?></td>
                    <td>
                        <div class="enrollment-cell">
                            <div class="enrollment-bar">
                                <div class="enrollment-bar-fill" style="width: <?= min($pct, 100) ?>%; background: <?= $colors['bar'] ?>;"></div>
                            </div>
                            <span class="enrollment-fraction"><?= htmlspecialchars($course['enrolled_count']) ?>/<?= htmlspecialchars($course['capacity']) ?></span>
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
                            <a href="javascript:void(0)"
                               data-course="<?= htmlspecialchars(json_encode([
                                   'offering_id' => (int) $course['offering_id'],
                                   'subject_id'  => (int) $course['subject_id'],
                                   'section_id'  => (int) $course['section_id'],
                                   'teacher_id'  => $course['teacher_id'] ? (int) $course['teacher_id'] : '',
                                   'quarter'     => (int) $course['quarter'],
                                   'capacity'    => (int) $course['capacity'],
                                   'status'      => $course['status'],
                               ]), ENT_QUOTES, 'UTF-8') ?>"
                               onclick="openEditCourseModal(this)">Update</a>
                            <a class="delete" href="courses.php?delete=<?= (int) $course['offering_id'] ?>&csrf=<?= urlencode($csrfToken) ?>&status=<?= urlencode($statusFilter) ?>&grade=<?= urlencode($gradeFilter) ?>&strand=<?= urlencode($strandFilter) ?>&q=<?= urlencode($searchQuery) ?>"
                               onclick="return confirmDelete('course')">Delete</a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="list-panel-footer">
            <span class="count-note">Showing <?= htmlspecialchars($totalShown) ?> of <?= htmlspecialchars($totalCourses) ?> courses</span>
        </div>
    </div>
    <!-- /Course List -->

    <!-- Sections List (hidden until "View Sections" is clicked) -->
    <div class="view-panel <?= $openView === 'sections' ? 'open' : '' ?>" id="view-sections">
    <div class="list-panel">
        <div class="list-panel-header">
            <h2>Sections List</h2>
            <span class="count-note"><?= htmlspecialchars(count($sectionsList)) ?> sections</span>
        </div>

        <table class="course-table">
            <thead>
                <tr>
                    <th>Section</th>
                    <th>Grade Level</th>
                    <th>Adviser</th>
                    <th>School Year</th>
                    <th>Courses Offered</th>
                    <th>Enrolled Students</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($sectionsList)): ?>
                <tr>
                    <td colspan="7" style="text-align:center; padding: 40px; color: var(--text-muted);">
                        No sections found. Use "New Section" to add one.
                    </td>
                </tr>
                <?php endif; ?>

                <?php foreach ($sectionsList as $sec):
                    $adviserName = $sec['adviser_firstname']
                        ? trim($sec['adviser_firstname'] . ' ' . $sec['adviser_lastname'])
                        : null;
                ?>
                <tr>
                    <td><span class="course-name"><?= htmlspecialchars($sec['section_name']) ?></span></td>
                    <td>Grade <?= htmlspecialchars($sec['grade_level']) ?><?= $sec['strand'] ? ' · ' . htmlspecialchars($sec['strand']) : '' ?></td>
                    <td><?= $adviserName ? htmlspecialchars($adviserName) : '— None —' ?></td>
                    <td><?= $sec['school_year_label'] ? htmlspecialchars($sec['school_year_label']) : '— None —' ?></td>
                    <td><?= (int) $sec['course_count'] ?></td>
                    <td><?= (int) $sec['student_count'] ?></td>
                    <td>
                        <div class="row-actions">
                            <a href="javascript:void(0)"
                               data-section="<?= htmlspecialchars(json_encode([
                                   'section_id'   => (int) $sec['section_id'],
                                   'section_name' => $sec['section_name'],
                                   'grade_level'  => (int) $sec['grade_level'],
                                   'strand'       => $sec['strand'] ?? '',
                                   'adviser_id'   => $sec['adviser_id'] ? (int) $sec['adviser_id'] : '',
                               ]), ENT_QUOTES, 'UTF-8') ?>"
                               onclick="openEditSectionModal(this)">Update</a>
                            <a class="delete" href="courses.php?delete_section=<?= (int) $sec['section_id'] ?>&csrf=<?= urlencode($csrfToken) ?>"
                               onclick="return confirmDelete('section')">Delete</a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="list-panel-footer">
            <span class="count-note"><?= htmlspecialchars(count($sectionsList)) ?> sections total</span>
        </div>
    </div>
    </div>
    <!-- /Sections List -->

    <!-- Subjects List (hidden until "View Subjects" is clicked) -->
    <div class="view-panel <?= $openView === 'subjects' ? 'open' : '' ?>" id="view-subjects">
    <div class="list-panel">
        <div class="list-panel-header">
            <h2>Subjects List</h2>
            <span class="count-note"><?= htmlspecialchars(count($subjectsList)) ?> subjects</span>
        </div>

        <table class="course-table">
            <thead>
                <tr>
                    <th>Subject</th>
                    <th>Description</th>
                    <th>Courses Offered</th>
                    <th>Sections Covered</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($subjectsList)): ?>
                <tr>
                    <td colspan="5" style="text-align:center; padding: 40px; color: var(--text-muted);">
                        No subjects found. Use "New Subject" to add one.
                    </td>
                </tr>
                <?php endif; ?>

                <?php foreach ($subjectsList as $subj):
                    $colors = $subjectColors[$subj['subject_name']] ?? ['bg' => '#e5e7eb', 'text' => '#374151', 'bar' => '#9ca3af'];
                ?>
                <tr>
                    <td>
                        <div class="course-cell">
                            <span class="subject-tag" style="background: <?= $colors['bg'] ?>; color: <?= $colors['text'] ?>;">
                                <?= htmlspecialchars($subj['subject_name']) ?>
                            </span>
                        </div>
                    </td>
                    <td style="white-space: normal;"><?= $subj['description'] ? htmlspecialchars($subj['description']) : '— No description —' ?></td>
                    <td><?= (int) $subj['offering_count'] ?></td>
                    <td><?= (int) $subj['section_count'] ?></td>
                    <td>
                        <div class="row-actions">
                            <a href="javascript:void(0)"
                               data-subject="<?= htmlspecialchars(json_encode([
                                   'subject_id'   => (int) $subj['subject_id'],
                                   'subject_name' => $subj['subject_name'],
                                   'description'  => $subj['description'] ?? '',
                               ]), ENT_QUOTES, 'UTF-8') ?>"
                               onclick="openEditSubjectModal(this)">Update</a>
                            <a class="delete" href="courses.php?delete_subject=<?= (int) $subj['subject_id'] ?>&csrf=<?= urlencode($csrfToken) ?>"
                               onclick="return confirmDelete('subject')">Delete</a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="list-panel-footer">
            <span class="count-note"><?= htmlspecialchars(count($subjectsList)) ?> subjects total</span>
        </div>
    </div>
    </div>
    <!-- /Subjects List -->

    <!-- Add Course Modal -->
    <div class="modal-overlay" id="addCourseOverlay" onclick="if (event.target === this) closeAddCourseModal()">
        <div class="modal-box">
            <div class="modal-header">
                <h2>Add New Course</h2>
                <button type="button" class="modal-close" onclick="closeAddCourseModal()" aria-label="Close">&times;</button>
            </div>

            <div class="modal-errors" id="addCourseErrors" hidden></div>

            <form id="addCourseForm">
                <input type="hidden" name="csrf" value="<?= clean($csrfToken) ?>">

                <div class="modal-body">
                    <div class="form-row">
                        <label for="m_subject_id">Subject</label>
                        <select id="m_subject_id" name="subject_id" required>
                            <option value="">Select a subject</option>
                            <?php foreach ($modalSubjects as $s): ?>
                                <option value="<?= (int) $s['subject_id'] ?>"><?= clean($s['subject_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-row">
                        <label for="m_section_id">Section</label>
                        <select id="m_section_id" name="section_id" required>
                            <option value="">Select a section</option>
                            <?php foreach ($modalSections as $sec): ?>
                                <option value="<?= (int) $sec['section_id'] ?>">
                                    Grade <?= clean($sec['grade_level']) ?> — <?= clean($sec['section_name']) ?><?= $sec['strand'] ? ' (' . clean($sec['strand']) . ')' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-row">
                        <label for="m_teacher_id">Teacher</label>
                        <select id="m_teacher_id" name="teacher_id" required>
                            <option value="">Select a teacher</option>
                            <?php foreach ($modalTeachers as $t): ?>
                                <option value="<?= (int) $t['teacher_id'] ?>"><?= clean($t['firstname'] . ' ' . $t['lastname']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-row-split">
                        <div class="form-row">
                            <label for="m_quarter">Quarter</label>
                            <select id="m_quarter" name="quarter" required>
                                <option value="">Select</option>
                                <?php foreach ([1, 2, 3, 4] as $q): ?>
                                    <option value="<?= $q ?>">Quarter <?= $q ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-row">
                            <label for="m_capacity">Capacity</label>
                            <input type="number" id="m_capacity" name="capacity" min="1" value="50" required>
                        </div>

                        <div class="form-row">
                            <label for="m_status">Status</label>
                            <select id="m_status" name="status" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeAddCourseModal()">Cancel</button>
                    <button type="submit" class="btn-primary" id="addCourseSubmitBtn"><i class="fas fa-plus"></i> Add Course</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Update Course Modal -->
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
                            <label for="e_quarter">Quarter</label>
                            <select id="e_quarter" name="quarter" required>
                                <option value="">Select</option>
                                <?php foreach ([1, 2, 3, 4] as $q): ?>
                                    <option value="<?= $q ?>">Quarter <?= $q ?></option>
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
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeEditCourseModal()">Cancel</button>
                    <button type="submit" class="btn-primary" id="editCourseSubmitBtn"><i class="fas fa-check"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- New Section Modal -->
    <div class="modal-overlay" id="addSectionOverlay" onclick="if (event.target === this) closeAddSectionModal()">
        <div class="modal-box">
            <div class="modal-header">
                <h2>New Section</h2>
                <button type="button" class="modal-close" onclick="closeAddSectionModal()" aria-label="Close">&times;</button>
            </div>

            <div class="modal-errors" id="addSectionErrors" hidden></div>

            <form id="addSectionForm">
                <input type="hidden" name="csrf" value="<?= clean($csrfToken) ?>">

                <div class="modal-body">
                    <div class="form-row">
                        <label for="s_section_name">Section Name</label>
                        <input type="text" id="s_section_name" name="section_name" placeholder="e.g. Rizal" required>
                    </div>

                    <div class="form-row-split">
                        <div class="form-row">
                            <label for="s_grade_level">Grade Level</label>
                            <select id="s_grade_level" name="grade_level" required>
                                <option value="">Select</option>
                                <?php foreach ([7, 8, 9, 10, 11, 12] as $g): ?>
                                    <option value="<?= $g ?>">Grade <?= $g ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-row">
                            <label for="s_strand">Strand</label>
                            <select id="s_strand" name="strand">
                                <option value="">None</option>
                                <?php foreach (['STEM', 'ABM', 'HUMSS', 'TVL'] as $s): ?>
                                    <option value="<?= $s ?>"><?= $s ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <label for="s_adviser_id">Adviser</label>
                        <select id="s_adviser_id" name="adviser_id">
                            <option value="">No adviser yet</option>
                            <?php foreach ($modalTeachers as $t): ?>
                                <option value="<?= (int) $t['teacher_id'] ?>"><?= clean($t['firstname'] . ' ' . $t['lastname']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="field-note">Each teacher can only advise one section.</span>
                    </div>

                    <div class="form-row">
                        <span class="field-note">This section will be added to school year <strong><?= $currentSchoolYear ? clean($currentSchoolYear['label']) : '— none set —' ?></strong>.</span>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeAddSectionModal()">Cancel</button>
                    <button type="submit" class="btn-primary" id="addSectionSubmitBtn"><i class="fas fa-plus"></i> Add Section</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Update Section Modal -->
    <div class="modal-overlay" id="editSectionOverlay" onclick="if (event.target === this) closeEditSectionModal()">
        <div class="modal-box">
            <div class="modal-header">
                <h2>Update Section</h2>
                <button type="button" class="modal-close" onclick="closeEditSectionModal()" aria-label="Close">&times;</button>
            </div>

            <div class="modal-errors" id="editSectionErrors" hidden></div>

            <form id="editSectionForm">
                <input type="hidden" name="csrf" value="<?= clean($csrfToken) ?>">
                <input type="hidden" name="section_id" id="es_section_id" value="">

                <div class="modal-body">
                    <div class="form-row">
                        <label for="es_section_name">Section Name</label>
                        <input type="text" id="es_section_name" name="section_name" placeholder="e.g. Rizal" required>
                    </div>

                    <div class="form-row-split">
                        <div class="form-row">
                            <label for="es_grade_level">Grade Level</label>
                            <select id="es_grade_level" name="grade_level" required>
                                <option value="">Select</option>
                                <?php foreach ([7, 8, 9, 10, 11, 12] as $g): ?>
                                    <option value="<?= $g ?>">Grade <?= $g ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-row">
                            <label for="es_strand">Strand</label>
                            <select id="es_strand" name="strand">
                                <option value="">None</option>
                                <?php foreach (['STEM', 'ABM', 'HUMSS', 'TVL'] as $s): ?>
                                    <option value="<?= $s ?>"><?= $s ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <label for="es_adviser_id">Adviser</label>
                        <select id="es_adviser_id" name="adviser_id">
                            <option value="">No adviser yet</option>
                            <?php foreach ($modalTeachers as $t): ?>
                                <option value="<?= (int) $t['teacher_id'] ?>"><?= clean($t['firstname'] . ' ' . $t['lastname']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="field-note">Each teacher can only advise one section.</span>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeEditSectionModal()">Cancel</button>
                    <button type="submit" class="btn-primary" id="editSectionSubmitBtn"><i class="fas fa-check"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- New Subject Modal -->
    <div class="modal-overlay" id="addSubjectOverlay" onclick="if (event.target === this) closeAddSubjectModal()">
        <div class="modal-box">
            <div class="modal-header">
                <h2>New Subject</h2>
                <button type="button" class="modal-close" onclick="closeAddSubjectModal()" aria-label="Close">&times;</button>
            </div>

            <div class="modal-errors" id="addSubjectErrors" hidden></div>

            <form id="addSubjectForm">
                <input type="hidden" name="csrf" value="<?= clean($csrfToken) ?>">

                <div class="modal-body">
                    <div class="form-row">
                        <label for="sub_subject_name">Subject Name</label>
                        <input type="text" id="sub_subject_name" name="subject_name" placeholder="e.g. Research" required>
                    </div>

                    <div class="form-row">
                        <label for="sub_description">Description</label>
                        <textarea id="sub_description" name="description" placeholder="Optional short description"></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeAddSubjectModal()">Cancel</button>
                    <button type="submit" class="btn-primary" id="addSubjectSubmitBtn"><i class="fas fa-plus"></i> Add Subject</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Update Subject Modal -->
    <div class="modal-overlay" id="editSubjectOverlay" onclick="if (event.target === this) closeEditSubjectModal()">
        <div class="modal-box">
            <div class="modal-header">
                <h2>Update Subject</h2>
                <button type="button" class="modal-close" onclick="closeEditSubjectModal()" aria-label="Close">&times;</button>
            </div>

            <div class="modal-errors" id="editSubjectErrors" hidden></div>

            <form id="editSubjectForm">
                <input type="hidden" name="csrf" value="<?= clean($csrfToken) ?>">
                <input type="hidden" name="subject_id" id="esub_subject_id" value="">

                <div class="modal-body">
                    <div class="form-row">
                        <label for="esub_subject_name">Subject Name</label>
                        <input type="text" id="esub_subject_name" name="subject_name" placeholder="e.g. Research" required>
                    </div>

                    <div class="form-row">
                        <label for="esub_description">Description</label>
                        <textarea id="esub_description" name="description" placeholder="Optional short description"></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeEditSubjectModal()">Cancel</button>
                    <button type="submit" class="btn-primary" id="editSubjectSubmitBtn"><i class="fas fa-check"></i> Save Changes</button>
                </div>
            </form>
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

    // ---- Delete confirmation ----
    function confirmDelete(type) {
        const messages = {
            course:  'Delete this course? This cannot be undone.',
            section: 'Delete this section? This cannot be undone.',
            subject: 'Delete this subject? This cannot be undone.',
        };
        return confirm(messages[type] || messages.course);
    }

    // ---- Toggle the Sections / Subjects list panels ----
    // Each panel is independent and hidden by default; clicking its
    // "View..." button reveals it (and clicking again hides it).
    function togglePanel(view, btn) {
        const panel = document.getElementById('view-' + view);
        const isOpen = panel.classList.toggle('open');
        btn.classList.toggle('active', isOpen);

        if (isOpen) {
            panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }

    // ---- Generic modal submit helper ----
    // Posts a form to an endpoint, shows validation errors inline, and
    // reloads on success so the flash message + updated list/stats appear.
    function submitModalForm(form, url, submitBtn, errorBox, idleLabel, openPanel) {
        errorBox.hidden = true;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

        fetch(url, {
            method: 'POST',
            headers: { 'Accept': 'application/json' },
            body: new FormData(form)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                if (openPanel) {
                    const params = new URLSearchParams(window.location.search);
                    params.set('open', openPanel);
                    window.location.href = 'courses.php?' + params.toString();
                } else {
                    location.reload();
                }
            } else {
                errorBox.innerHTML = data.errors.map(err => '<div>' + err + '</div>').join('');
                errorBox.hidden = false;
                submitBtn.disabled = false;
                submitBtn.innerHTML = idleLabel;
            }
        })
        .catch(() => {
            errorBox.innerHTML = '<div>Something went wrong. Please try again.</div>';
            errorBox.hidden = false;
            submitBtn.disabled = false;
            submitBtn.innerHTML = idleLabel;
        });
    }

    // ---- Add Course modal ----
    function openAddCourseModal() {
        document.getElementById('addCourseOverlay').classList.add('open');
        document.getElementById('addCourseErrors').hidden = true;
    }

    function closeAddCourseModal() {
        document.getElementById('addCourseOverlay').classList.remove('open');
    }

    document.getElementById('addCourseForm').addEventListener('submit', function (e) {
        e.preventDefault();
        submitModalForm(
            this,
            'add_course.php',
            document.getElementById('addCourseSubmitBtn'),
            document.getElementById('addCourseErrors'),
            '<i class="fas fa-plus"></i> Add Course'
        );
    });

    // ---- Update Course modal ----
    function openEditCourseModal(triggerEl) {
        const course = JSON.parse(triggerEl.dataset.course);

        document.getElementById('e_offering_id').value = course.offering_id;
        document.getElementById('e_subject_id').value = course.subject_id;
        document.getElementById('e_section_id').value = course.section_id;
        document.getElementById('e_teacher_id').value = course.teacher_id;
        document.getElementById('e_quarter').value = course.quarter;
        document.getElementById('e_capacity').value = course.capacity;
        document.getElementById('e_status').value = course.status;

        document.getElementById('editCourseErrors').hidden = true;
        document.getElementById('editCourseOverlay').classList.add('open');
    }

    function closeEditCourseModal() {
        document.getElementById('editCourseOverlay').classList.remove('open');
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

    // ---- New Section modal ----
    function openAddSectionModal() {
        document.getElementById('addSectionOverlay').classList.add('open');
        document.getElementById('addSectionErrors').hidden = true;
    }

    function closeAddSectionModal() {
        document.getElementById('addSectionOverlay').classList.remove('open');
    }

    document.getElementById('addSectionForm').addEventListener('submit', function (e) {
        e.preventDefault();
        submitModalForm(
            this,
            'add_section.php',
            document.getElementById('addSectionSubmitBtn'),
            document.getElementById('addSectionErrors'),
            '<i class="fas fa-plus"></i> Add Section',
            'sections'
        );
    });

    // ---- Update Section modal ----
    function openEditSectionModal(triggerEl) {
        const section = JSON.parse(triggerEl.dataset.section);

        document.getElementById('es_section_id').value = section.section_id;
        document.getElementById('es_section_name').value = section.section_name;
        document.getElementById('es_grade_level').value = section.grade_level;
        document.getElementById('es_strand').value = section.strand;
        document.getElementById('es_adviser_id').value = section.adviser_id;

        document.getElementById('editSectionErrors').hidden = true;
        document.getElementById('editSectionOverlay').classList.add('open');
    }

    function closeEditSectionModal() {
        document.getElementById('editSectionOverlay').classList.remove('open');
    }

    document.getElementById('editSectionForm').addEventListener('submit', function (e) {
        e.preventDefault();
        submitModalForm(
            this,
            'update_section.php',
            document.getElementById('editSectionSubmitBtn'),
            document.getElementById('editSectionErrors'),
            '<i class="fas fa-check"></i> Save Changes',
            'sections'
        );
    });

    // ---- New Subject modal ----
    function openAddSubjectModal() {
        document.getElementById('addSubjectOverlay').classList.add('open');
        document.getElementById('addSubjectErrors').hidden = true;
    }

    function closeAddSubjectModal() {
        document.getElementById('addSubjectOverlay').classList.remove('open');
    }

    document.getElementById('addSubjectForm').addEventListener('submit', function (e) {
        e.preventDefault();
        submitModalForm(
            this,
            'add_subject.php',
            document.getElementById('addSubjectSubmitBtn'),
            document.getElementById('addSubjectErrors'),
            '<i class="fas fa-plus"></i> Add Subject',
            'subjects'
        );
    });

    // ---- Update Subject modal ----
    function openEditSubjectModal(triggerEl) {
        const subject = JSON.parse(triggerEl.dataset.subject);

        document.getElementById('esub_subject_id').value = subject.subject_id;
        document.getElementById('esub_subject_name').value = subject.subject_name;
        document.getElementById('esub_description').value = subject.description;

        document.getElementById('editSubjectErrors').hidden = true;
        document.getElementById('editSubjectOverlay').classList.add('open');
    }

    function closeEditSubjectModal() {
        document.getElementById('editSubjectOverlay').classList.remove('open');
    }

    document.getElementById('editSubjectForm').addEventListener('submit', function (e) {
        e.preventDefault();
        submitModalForm(
            this,
            'update_subject.php',
            document.getElementById('editSubjectSubmitBtn'),
            document.getElementById('editSubjectErrors'),
            '<i class="fas fa-check"></i> Save Changes',
            'subjects'
        );
    });

    // ---- Shared: Escape closes whichever modal is open ----
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        closeAddCourseModal();
        closeEditCourseModal();
        closeAddSectionModal();
        closeEditSectionModal();
        closeAddSubjectModal();
        closeEditSubjectModal();
    });
</script>

</body>
</html>