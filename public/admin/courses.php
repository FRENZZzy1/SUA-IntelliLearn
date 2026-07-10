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

$csrfToken = generateCSRFToken();

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

// ================= DATA FOR "NEW COURSE" MODAL =================
$modalSubjects = $pdo->query("SELECT subject_id, subject_name FROM subjects ORDER BY subject_name")->fetchAll();
$modalSections = $pdo->query("SELECT section_id, section_name, grade_level, strand FROM sections ORDER BY grade_level, section_name")->fetchAll();
$modalTeachers = $pdo->query("SELECT teacher_id, firstname, lastname FROM teachers ORDER BY lastname, firstname")->fetchAll();
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
                            <a href="edit_course.php?id=<?= (int) $course['offering_id'] ?>">Edit</a>
                            <a href="assign_teacher.php?id=<?= (int) $course['offering_id'] ?>">Assign</a>
                            <a class="delete" href="courses.php?delete=<?= (int) $course['offering_id'] ?>&csrf=<?= urlencode($csrfToken) ?>&status=<?= urlencode($statusFilter) ?>&grade=<?= urlencode($gradeFilter) ?>&strand=<?= urlencode($strandFilter) ?>&q=<?= urlencode($searchQuery) ?>"
                               onclick="return confirmDelete()">Delete</a>
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
    function confirmDelete() {
        return confirm('Delete this course? This cannot be undone.');
    }

    // ---- Add Course modal ----
    function openAddCourseModal() {
        document.getElementById('addCourseOverlay').classList.add('open');
        document.getElementById('addCourseErrors').hidden = true;
    }

    function closeAddCourseModal() {
        document.getElementById('addCourseOverlay').classList.remove('open');
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeAddCourseModal();
    });

    document.getElementById('addCourseForm').addEventListener('submit', function (e) {
        e.preventDefault();

        const submitBtn = document.getElementById('addCourseSubmitBtn');
        const errorBox = document.getElementById('addCourseErrors');
        errorBox.hidden = true;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';

        fetch('add_course.php', {
            method: 'POST',
            headers: { 'Accept': 'application/json' },
            body: new FormData(this)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // Reload so the (already server-side) flash message + updated
                // course list + stats show up in one consistent page load.
                location.reload();
            } else {
                errorBox.innerHTML = data.errors.map(err => '<div>' + err + '</div>').join('');
                errorBox.hidden = false;
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-plus"></i> Add Course';
            }
        })
        .catch(() => {
            errorBox.innerHTML = '<div>Something went wrong. Please try again.</div>';
            errorBox.hidden = false;
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-plus"></i> Add Course';
        });
    });
</script>

</body>
</html>