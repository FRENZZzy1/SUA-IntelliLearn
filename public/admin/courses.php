<?php
// config.php already calls session_start(), opens $conn (MySQLi) and $pdo (PDO),
// and defines requireAdmin() / clean() / CSRF + flash helpers.
require_once __DIR__ . '/../../config/config.php';

requireAdmin();
include 'assests/api/add_class_modal.php';

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

// ================= SCHEDULE FORMAT HELPER =================
// Formats schedule_days ("M - W") + start_time/end_time ("07:00:00")
// into "M - W : 7 AM - 10 AM" for display. Returns null if nothing is set.
function formatScheduleDisplay(?string $days, ?string $start, ?string $end): ?string
{
    $days  = $days ? trim($days) : '';
    $time  = '';

    if ($start && $end) {
        $time = date('g A', strtotime($start)) . ' - ' . date('g A', strtotime($end));
    } elseif ($start) {
        $time = date('g A', strtotime($start));
    }

    if ($days === '' && $time === '') {
        return null;
    }

    if ($days !== '' && $time !== '') {
        return $days . ' : ' . $time;
    }

    return $days !== '' ? $days : $time;
}

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
        'status'      => $_GET['status'] ?? null,
        'grade'       => $_GET['grade'] ?? null,
        'strand'      => $_GET['strand'] ?? null,
        'quarter'     => $_GET['quarter'] ?? null,
        'school_year' => $_GET['school_year'] ?? null,
        'q'           => $_GET['q'] ?? null,
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
if (!in_array($openView, ['sections', 'subjects', 'export'], true)) {
    $openView = '';
}

// ================= FILTER INPUTS =================
$statusFilter     = $_GET['status']  ?? 'all';             // all | active | inactive
$gradeFilter      = $_GET['grade']   ?? 'all';             // all | 7..12
$strandFilter     = $_GET['strand']  ?? 'all';             // all | GAS | ABM | HUMSS | TVL
$quarterFilter    = $_GET['quarter'] ?? 'all';             // all | 1..3
$schoolYearFilter = $_GET['school_year'] ?? 'all';         // all | school_year_id
$searchQuery      = trim($_GET['q'] ?? '');

if (!in_array($statusFilter, ['all', 'active', 'inactive'], true)) {
    $statusFilter = 'all';
}

if ($quarterFilter !== 'all' && !in_array($quarterFilter, ['TRM 1', 'TRM 2', 'TRM 3'], true)) {
    $quarterFilter = 'all';
}

if ($schoolYearFilter !== 'all' && !ctype_digit((string) $schoolYearFilter)) {
    $schoolYearFilter = 'all';
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

if ($quarterFilter !== 'all') {
    $where[] = 'co.quarter = ?';
    $params[] = $quarterFilter;
}

if ($schoolYearFilter !== 'all') {
    $where[] = 'co.school_year_id = ?';
    $params[] = (int) $schoolYearFilter;
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
        co.school_year_id,
        co.schedule_days,
        co.start_time,
        co.end_time,
        s.subject_name,
        sec.section_id,
        sec.section_name,
        sec.grade_level,
        sec.strand,
        t.teacher_id,
        t.firstname AS teacher_firstname,
        t.lastname  AS teacher_lastname,
        syco.label AS offering_school_year_label,
        (SELECT COUNT(*) FROM enrollments e
            WHERE e.offering_id = co.offering_id AND e.status = 'active') AS enrolled_count
    FROM classofferings co
    JOIN subjects s   ON s.subject_id = co.subject_id
    JOIN sections sec ON sec.section_id = co.section_id
    LEFT JOIN teachers t ON t.teacher_id = co.teacher_id
    LEFT JOIN schoolyears syco ON syco.school_year_id = co.school_year_id
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

<style>
    /* ---- Search & Export panel (self-contained, doesn't require courses.css changes) ---- */
    .export-search-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        gap: 18px;
        padding: 20px;
    }
    .export-search-card {
        background: var(--card-bg, #fff);
        border: 1px solid var(--border, #e2e8f0);
        border-radius: 12px;
        padding: 18px;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    .export-search-card-title {
        font-weight: 700;
        font-size: 15px;
        color: var(--text, #1e293b);
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .export-search-card-title i { color: #2563eb; }
    .export-search-card-hint {
        margin: -4px 0 2px;
        font-size: 12.5px;
        color: var(--text-muted, #64748b);
    }
    .export-search-box { position: relative; }
    .export-search-box input {
        width: 100%;
        box-sizing: border-box;
        padding: 10px 12px;
        border: 1px solid var(--border, #e2e8f0);
        border-radius: 8px;
        font-size: 14px;
        font-family: inherit;
    }
    .export-search-box input:focus {
        outline: none;
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
    }
    .export-search-dropdown {
        position: absolute;
        top: calc(100% + 4px);
        left: 0;
        right: 0;
        background: #fff;
        border: 1px solid var(--border, #e2e8f0);
        border-radius: 8px;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.12);
        max-height: 260px;
        overflow-y: auto;
        z-index: 30;
    }
    .export-search-dropdown-item {
        padding: 10px 12px;
        cursor: pointer;
        border-bottom: 1px solid #f1f5f9;
    }
    .export-search-dropdown-item:last-child { border-bottom: none; }
    .export-search-dropdown-item:hover { background: #f8fafc; }
    .export-search-dropdown-item .esdi-name { font-weight: 600; font-size: 13.5px; color: #1e293b; }
    .export-search-dropdown-item .esdi-sub { font-size: 12px; color: #94a3b8; margin-top: 2px; }
    .export-search-dropdown-empty {
        padding: 14px 12px;
        font-size: 13px;
        color: #94a3b8;
        text-align: center;
    }
    .export-selected-chip {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        color: #1d4ed8;
        border-radius: 8px;
        padding: 8px 10px;
        font-size: 13px;
        font-weight: 600;
    }
    .export-selected-chip button {
        background: none;
        border: none;
        color: #1d4ed8;
        cursor: pointer;
        font-size: 15px;
        line-height: 1;
        padding: 0 2px;
    }
    .export-btn {
        width: 100%;
        justify-content: center;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .export-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    /* ---- Live search box (Class List, instant client-side filter) ---- */
    .live-search-box {
        position: relative;
        max-width: 280px;
        flex: 1 1 220px;
    }
    .live-search-box i {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: 13px;
        pointer-events: none;
    }
    .live-search-box input {
        width: 100%;
        box-sizing: border-box;
        padding: 10px 12px 10px 32px;
        border: 1px solid var(--border, #e2e8f0);
        border-radius: 8px;
        font-size: 14px;
        font-family: inherit;
    }
    .live-search-box input:focus {
        outline: none;
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
    }
    .live-search-clear {
        position: absolute;
        right: 8px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: #94a3b8;
        cursor: pointer;
        font-size: 15px;
        line-height: 1;
        padding: 2px 4px;
        display: none;
    }
    .live-search-clear.show { display: inline-block; }
    .no-results-row td {
        text-align: center;
        padding: 40px;
        color: var(--text-muted, #64748b);
    }
</style>
</head>
<body>

<?php include '../../includes/admin_sidebar.php'; ?>
<?php include '../../includes/admin_header.php'; ?>
<div class="main-content" id="mainContent">

    <?php if ($flash): ?>
    <div class="flash-message flash-<?= clean($flash['type']) ?>">
        <?= clean($flash['message']) ?>
    </div>
    <?php endif; ?>

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-text">
            <h1>Classes &amp; Subjects</h1>
            <p>Manage class offerings, sections, and subjects across the school.</p>
        </div>
        <form class="header-actions" method="get" action="courses.php">
            <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
            <input type="hidden" name="grade" value="<?= htmlspecialchars($gradeFilter) ?>">
            <input type="hidden" name="strand" value="<?= htmlspecialchars($strandFilter) ?>">
            <input type="hidden" name="quarter" value="<?= htmlspecialchars($quarterFilter) ?>">
            <input type="hidden" name="school_year" value="<?= htmlspecialchars($schoolYearFilter) ?>">
          
        </form>
    </div>

    <!-- Quick Stats -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-book-open"></i></div>
            <div class="stat-body">
                <div class="stat-trend info">Live</div>
                <div class="stat-value"><?= htmlspecialchars($totalCourses) ?></div>
                <div class="stat-label">Total Courses</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-circle-check"></i></div>
            <div class="stat-body">
                <div class="stat-value"><?= htmlspecialchars($activeCourses) ?></div>
                <div class="stat-label">Active Courses</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon info"><i class="fas fa-chalkboard-teacher"></i></div>
            <div class="stat-body">
                <div class="stat-trend info"><?= htmlspecialchars($teachersAssigned) ?>/<?= htmlspecialchars($totalTeachers) ?></div>
                <div class="stat-value"><?= htmlspecialchars($teachersAssigned) ?></div>
                <div class="stat-label">Teachers Assigned</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon accent"><i class="fas fa-user-graduate"></i></div>
            <div class="stat-body">
                <div class="stat-value"><?= htmlspecialchars($totalEnrollees) ?></div>
                <div class="stat-label">Total Enrollees</div>
            </div>
        </div>
    </div>

    <!-- Filter / Action Toolbar -->
    <div class="toolbar">
        <div class="filter-bar">
            <a class="filter-pill <?= $statusFilter === 'all' ? 'active' : '' ?>"
               href="?<?= http_build_query(array_filter(['status' => 'all', 'grade' => $gradeFilter, 'strand' => $strandFilter, 'quarter' => $quarterFilter, 'school_year' => $schoolYearFilter, 'q' => $searchQuery])) ?>">All Courses</a>
            <a class="filter-pill <?= $statusFilter === 'active' ? 'active' : '' ?>"
               href="?<?= http_build_query(array_filter(['status' => 'active', 'grade' => $gradeFilter, 'strand' => $strandFilter, 'quarter' => $quarterFilter, 'school_year' => $schoolYearFilter, 'q' => $searchQuery])) ?>">Active</a>
            <a class="filter-pill <?= $statusFilter === 'inactive' ? 'active' : '' ?>"
               href="?<?= http_build_query(array_filter(['status' => 'inactive', 'grade' => $gradeFilter, 'strand' => $strandFilter, 'quarter' => $quarterFilter, 'school_year' => $schoolYearFilter, 'q' => $searchQuery])) ?>">Inactive</a>

            <!-- Live client-side search: instantly filters the Class List table below
                 by subject, section, or teacher name. No page reload, no server call. -->
            <div class="live-search-box">
                <i class="fas fa-search"></i>
                <input type="text"
                       id="liveSearchInput"
                       placeholder="Search teacher, subject, or section..."
                       autocomplete="off"
                       oninput="filterCourseList(this.value)">
                <button type="button" class="live-search-clear" id="liveSearchClearBtn" onclick="clearCourseListSearch()" aria-label="Clear search">&times;</button>
            </div>

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
                    <?php foreach (['GAS', 'ABM', 'HUMSS', 'TVL'] as $s): ?>
                        <option value="<?= $s ?>" <?= $strandFilter === $s ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>

                <select class="select-filter" name="quarter" onchange="this.form.submit()">
                    <option value="all" <?= $quarterFilter === 'all' ? 'selected' : '' ?>>All Terms</option>
                    <?php foreach (['TRM 1', 'TRM 2', 'TRM 3'] as $q): ?>
                        <option value="<?= $q ?>" <?= $quarterFilter === $q ? 'selected' : '' ?>><?= $q ?></option>
                    <?php endforeach; ?>
                </select>

                <select class="select-filter" name="school_year" onchange="this.form.submit()">
                    <option value="all" <?= $schoolYearFilter === 'all' ? 'selected' : '' ?>>All School Years</option>
                    <?php foreach ($modalSchoolYears as $sy): ?>
                        <option value="<?= (int) $sy['school_year_id'] ?>" <?= $schoolYearFilter == $sy['school_year_id'] ? 'selected' : '' ?>><?= clean($sy['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <div class="action-bar">
            <div class="action-bar-group">
                <span class="action-bar-group-label">View</span>
                <button class="btn-secondary <?= $openView === 'subjects' ? 'active' : '' ?>" id="toggleSubjectsBtn" onclick="togglePanel('subjects', this)"><i class="fas fa-list"></i> Subjects</button>
                <button class="btn-secondary <?= $openView === 'sections' ? 'active' : '' ?>" id="toggleSectionsBtn" onclick="togglePanel('sections', this)"><i class="fas fa-list"></i> Sections</button>
                <button class="btn-secondary <?= $openView === 'export' ? 'active' : '' ?>" id="toggleExportBtn" onclick="togglePanel('export', this)"><i class="fas fa-file-export"></i> Search &amp; Export</button>
            </div>
            <div class="action-bar-group">
                <span class="action-bar-group-label">Create</span>
                <button class="btn-secondary" onclick="openAddSubjectModal()"><i class="fas fa-book"></i> New Subject</button>
                <button class="btn-secondary" onclick="openAddSectionModal()"><i class="fas fa-layer-group"></i> New Section</button>
                <button class="btn-primary" onclick="openAddCourseModal()"><i class="fas fa-plus"></i> New Class</button>
            </div>
        </div>
    </div>

    <!-- Course List -->
    <div class="list-panel">
        <div class="list-panel-header">
            <h2>Class List</h2>
            <span class="count-note" id="courseCountTop">Showing <?= htmlspecialchars($totalShown) ?> of <?= htmlspecialchars($totalCourses) ?> courses</span>
        </div>

        <table class="course-table" id="courseTable">
            <thead>
                <tr>
                    <th>Course / Subject</th>
                    <th>Section</th>
                    <th>Grade Level/Strand</th>
                    <th>Term</th>
                    <th>School Year</th>
                    <th>Schedule</th>
                    <th>Teacher Assigned</th>
                    <th>Enrollment</th>
                    <th>Status</th>
                    <th class="col-actions">Actions</th>
                </tr>
            </thead>
            <tbody id="courseTableBody">
                <?php if (empty($courses)): ?>
                <tr class="empty-row">
                    <td colspan="10">
                        No courses match these filters.
                    </td>
                </tr>
                <?php endif; ?>

                <?php foreach ($courses as $course):
                    $colors = $subjectColors[$course['subject_name']] ?? ['bg' => '#e5e7eb', 'text' => '#374151', 'bar' => '#9ca3af'];
                    $pct = $course['capacity'] > 0 ? round(($course['enrolled_count'] / $course['capacity']) * 100) : 0;
                    $teacherName = $course['teacher_id'] ? trim($course['teacher_firstname'] . ' ' . $course['teacher_lastname']) : null;
                    $scheduleDisplay = formatScheduleDisplay($course['schedule_days'], $course['start_time'], $course['end_time']);
                    // Pre-baked lowercase blob this row matches against for the
                    // instant client-side search box (subject + section + teacher name).
                    $searchBlob = mb_strtolower(trim(
                        $course['subject_name'] . ' ' .
                        $course['section_name'] . ' ' .
                        ($teacherName ?? '')
                    ));
                ?>
                <tr class="course-row" data-search="<?= htmlspecialchars($searchBlob) ?>">
                    <td>
                        <div class="course-cell">
                            <span class="subject-tag" style="background: <?= $colors['bg'] ?>; color: <?= $colors['text'] ?>;">
                                <?= htmlspecialchars($course['subject_name']) ?>
                            </span>
                        </div>
                    </td>
                    <td class="course-name"><?= htmlspecialchars($course['section_name']) ?></td>
                    <td>Grade <?= htmlspecialchars($course['grade_level']) ?><?= $course['strand'] ? ' · ' . htmlspecialchars($course['strand']) : '' ?></td>
                    <td><?= htmlspecialchars($course['quarter']) ?></td>
                    <td><?= $course['offering_school_year_label'] ? htmlspecialchars($course['offering_school_year_label']) : '<span class="field-note">— None —</span>' ?></td>
                    <td><?= $scheduleDisplay ? htmlspecialchars($scheduleDisplay) : '<span class="field-note">— Not set —</span>' ?></td>
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
                    <td class="col-actions">
                        <div class="row-actions">
                            <a href="javascript:void(0)"
                               onclick="openViewStudentsModal(<?= (int) $course['offering_id'] ?>)">View Students</a>
                            <a href="javascript:void(0)"
                               data-course="<?= htmlspecialchars(json_encode([
                                   'offering_id'   => (int) $course['offering_id'],
                                   'subject_id'    => (int) $course['subject_id'],
                                   'section_id'    => (int) $course['section_id'],
                                   'teacher_id'    => $course['teacher_id'] ? (int) $course['teacher_id'] : '',
                                   'quarter'       => $course['quarter'],
                                   'school_year_id' => (int) $course['school_year_id'],
                                   'schedule_days' => $course['schedule_days'] ?? '',
                                   'start_time'    => $course['start_time'] ? date('g:i A', strtotime($course['start_time'])) : '',
                                   'end_time'      => $course['end_time'] ? date('g:i A', strtotime($course['end_time'])) : '',
                                   'capacity'      => (int) $course['capacity'],
                                   'status'        => $course['status'],
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
            <span class="count-note" id="courseCountBottom">Showing <?= htmlspecialchars($totalShown) ?> of <?= htmlspecialchars($totalCourses) ?> courses</span>
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
                    <th>Grade Level/Strand</th>
                    <th>Adviser</th>
                    <th>School Year</th>
                    <th>Courses Offered</th>
                    <th>Enrolled Students</th>
                    <th class="col-actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($sectionsList)): ?>
                <tr class="empty-row">
                    <td colspan="7">
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
                    <td class="col-actions">
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
                    <th class="col-actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($subjectsList)): ?>
                <tr class="empty-row">
                    <td colspan="5">
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
                    <td class="col-actions">
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

    <!-- Search & Export (hidden until "Search & Export" is clicked) -->
    <div class="view-panel <?= $openView === 'export' ? 'open' : '' ?>" id="view-export">
    <div class="list-panel">
        <div class="list-panel-header">
            <h2>Search &amp; Export Classes</h2>
            <span class="count-note">Find a teacher, section, or student and export their classes to Excel</span>
        </div>

        <div class="export-search-grid">

            <!-- Search by Teacher -->
            <div class="export-search-card">
                <div class="export-search-card-title"><i class="fas fa-chalkboard-teacher"></i> By Teacher</div>
                <p class="export-search-card-hint">e.g. "Rose" — exports every class that teacher handles</p>
                <div class="export-search-box">
                    <input type="text" id="exportTeacherInput" placeholder="Search teacher name..." autocomplete="off"
                           oninput="exportSearchInput('teacher', this.value)">
                    <div class="export-search-dropdown" id="exportTeacherDropdown" hidden></div>
                </div>
                <div class="export-selected-chip" id="exportTeacherChip" hidden></div>
                <button type="button" class="btn-primary export-btn" id="exportTeacherBtn" disabled onclick="exportSelected('teacher')">
                    <i class="fas fa-file-excel"></i> Export Teacher's Classes
                </button>
            </div>

            <!-- Search by Section -->
            <div class="export-search-card">
                <div class="export-search-card-title"><i class="fas fa-layer-group"></i> By Section</div>
                <p class="export-search-card-hint">e.g. "A110" — exports every class offered in that section</p>
                <div class="export-search-box">
                    <input type="text" id="exportSectionInput" placeholder="Search section name..." autocomplete="off"
                           oninput="exportSearchInput('section', this.value)">
                    <div class="export-search-dropdown" id="exportSectionDropdown" hidden></div>
                </div>
                <div class="export-selected-chip" id="exportSectionChip" hidden></div>
                <button type="button" class="btn-primary export-btn" id="exportSectionBtn" disabled onclick="exportSelected('section')">
                    <i class="fas fa-file-excel"></i> Export Section's Classes
                </button>
            </div>

            <!-- Search by Student -->
            <div class="export-search-card">
                <div class="export-search-card-title"><i class="fas fa-user-graduate"></i> By Student</div>
                <p class="export-search-card-hint">e.g. "Frenz" — exports every class that student is enrolled in</p>
                <div class="export-search-box">
                    <input type="text" id="exportStudentInput" placeholder="Search student name or LRN..." autocomplete="off"
                           oninput="exportSearchInput('student', this.value)">
                    <div class="export-search-dropdown" id="exportStudentDropdown" hidden></div>
                </div>
                <div class="export-selected-chip" id="exportStudentChip" hidden></div>
                <button type="button" class="btn-primary export-btn" id="exportStudentBtn" disabled onclick="exportSelected('student')">
                    <i class="fas fa-file-excel"></i> Export Student's Classes
                </button>
            </div>

        </div>
    </div>
    </div>
    <!-- /Search & Export -->

    <!-- Add Course Modal -->
   

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

    <!-- View Students Modal -->
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
                            <td colspan="5" style="text-align:center; padding: 24px; color: var(--text-muted);">
                                Loading...
                            </td>
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
                                <?php foreach (['GAS', 'ABM', 'HUMSS', 'TVL'] as $s): ?>
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
                                <?php foreach (['GAS', 'ABM', 'HUMSS', 'TVL'] as $s): ?>
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

    // ---- Live search: instantly filters the Class List table ----
    // Matches each row's pre-baked data-search string (subject + section +
    // teacher name, all lowercase) against whatever the admin types.
    // No server round-trip — pure client-side show/hide.
    const TOTAL_COURSES = <?= (int) $totalCourses ?>;
    let noResultsRow = null;

    function getOrCreateNoResultsRow() {
        if (noResultsRow) return noResultsRow;
        const tbody = document.getElementById('courseTableBody');
        noResultsRow = document.createElement('tr');
        noResultsRow.className = 'no-results-row';
        noResultsRow.innerHTML = '<td colspan="10">No courses match your search.</td>';
        tbody.appendChild(noResultsRow);
        return noResultsRow;
    }

    function filterCourseList(rawValue) {
        const q = rawValue.trim().toLowerCase();
        const rows = document.querySelectorAll('#courseTableBody tr.course-row');
        const clearBtn = document.getElementById('liveSearchClearBtn');
        clearBtn.classList.toggle('show', q.length > 0);

        let visible = 0;
        rows.forEach(row => {
            const match = q === '' || row.dataset.search.includes(q);
            row.style.display = match ? '' : 'none';
            if (match) visible++;
        });

        const noRow = getOrCreateNoResultsRow();
        noRow.style.display = (visible === 0 && rows.length > 0) ? '' : 'none';

        const label = 'Showing ' + visible + ' of ' + TOTAL_COURSES + ' courses';
        const top = document.getElementById('courseCountTop');
        const bottom = document.getElementById('courseCountBottom');
        if (top) top.textContent = label;
        if (bottom) bottom.textContent = label;
    }

    function clearCourseListSearch() {
        const input = document.getElementById('liveSearchInput');
        input.value = '';
        filterCourseList('');
        input.focus();
    }

    // ---- Search & Export panel ----
    const exportEndpoints = {
        teacher: 'assests/api/export_teacher_classes.php?teacher_id=',
        section: 'assests/api/export_section_classes.php?section_id=',
        student: 'assests/api/export_student_classes.php?student_id=',
    };
    const exportSelected_ = { teacher: null, section: null, student: null };
    const exportDebounce_ = { teacher: null, section: null, student: null };

    function exportSearchInput(type, value) {
        // Typing again after a selection invalidates it.
        if (exportSelected_[type]) {
            exportSelected_[type] = null;
            document.getElementById('export' + capitalize(type) + 'Chip').hidden = true;
            document.getElementById('export' + capitalize(type) + 'Btn').disabled = true;
        }

        clearTimeout(exportDebounce_[type]);
        const dropdown = document.getElementById('export' + capitalize(type) + 'Dropdown');

        const q = value.trim();
        if (q.length < 1) {
            dropdown.hidden = true;
            dropdown.innerHTML = '';
            return;
        }

        exportDebounce_[type] = setTimeout(() => {
            fetch('assests/api/search_entities.php?type=' + type + '&q=' + encodeURIComponent(q), {
                headers: { 'Accept': 'application/json' }
            })
            .then(res => res.json())
            .then(data => {
                dropdown.innerHTML = '';
                if (!data.success || data.results.length === 0) {
                    dropdown.innerHTML = '<div class="export-search-dropdown-empty">No matches found.</div>';
                    dropdown.hidden = false;
                    return;
                }

                data.results.forEach(function (r) {
                    const item = document.createElement('div');
                    item.className = 'export-search-dropdown-item';
                    item.innerHTML = '<div class="esdi-name"></div><div class="esdi-sub"></div>';
                    item.querySelector('.esdi-name').textContent = r.label;
                    item.querySelector('.esdi-sub').textContent = r.sublabel;
                    item.onclick = function () { selectExportEntity(type, r.id, r.label, r.sublabel); };
                    dropdown.appendChild(item);
                });
                dropdown.hidden = false;
            })
            .catch(() => {
                dropdown.innerHTML = '<div class="export-search-dropdown-empty">Something went wrong.</div>';
                dropdown.hidden = false;
            });
        }, 250);
    }

    function selectExportEntity(type, id, label, sublabel) {
        exportSelected_[type] = { id, label };

        const input = document.getElementById('export' + capitalize(type) + 'Input');
        const dropdown = document.getElementById('export' + capitalize(type) + 'Dropdown');
        const chip = document.getElementById('export' + capitalize(type) + 'Chip');
        const btn = document.getElementById('export' + capitalize(type) + 'Btn');

        input.value = label;
        dropdown.hidden = true;
        dropdown.innerHTML = '';

        chip.innerHTML = '<span>' + escapeHtml(label) + ' <span style="font-weight:400; color:#3b82f6;">· ' + escapeHtml(sublabel) + '</span></span>'
            + '<button type="button" onclick="clearExportSelection(\'' + type + '\')" aria-label="Clear">&times;</button>';
        chip.hidden = false;
        btn.disabled = false;
    }

    function clearExportSelection(type) {
        exportSelected_[type] = null;
        document.getElementById('export' + capitalize(type) + 'Input').value = '';
        document.getElementById('export' + capitalize(type) + 'Chip').hidden = true;
        document.getElementById('export' + capitalize(type) + 'Btn').disabled = true;
        document.getElementById('export' + capitalize(type) + 'Input').focus();
    }

    function exportSelected(type) {
        const sel = exportSelected_[type];
        if (!sel) return;
        window.location = exportEndpoints[type] + encodeURIComponent(sel.id);
    }

    function capitalize(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }

    // Close any open export dropdown when clicking elsewhere on the page.
    document.addEventListener('click', function (e) {
        ['teacher', 'section', 'student'].forEach(function (type) {
            const box = document.getElementById('export' + capitalize(type) + 'Input')?.closest('.export-search-box');
            const dropdown = document.getElementById('export' + capitalize(type) + 'Dropdown');
            if (box && dropdown && !box.contains(e.target)) {
                dropdown.hidden = true;
            }
        });
    });

   

    // ---- Update Course modal ----
    function openEditCourseModal(triggerEl) {
        const course = JSON.parse(triggerEl.dataset.course);

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

    // ---- View Students modal ----
    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str ?? '';
        return div.innerHTML;
    }

    let currentViewOfferingId = null;
    let vsAllStudents = [];   // full roster for the currently open offering
    let vsCourseInfo = null;  // { subject_name, section_name, grade_level, strand, capacity }

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

        // reset search/filter state each time the modal is opened for a course
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

    // Renders a given list of students into the table and keeps the
    // subtitle's enrolled count in sync with what's actually showing.
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

    // Applies the current search text + gender filter to the in-memory
    // roster (vsAllStudents) and re-renders. Runs entirely client-side,
    // so no extra requests are made while typing/selecting.
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
        closeViewStudentsModal();
        closeAddSectionModal();
        closeEditSectionModal();
        closeAddSubjectModal();
        closeEditSubjectModal();
    });
</script>

</body>
</html>