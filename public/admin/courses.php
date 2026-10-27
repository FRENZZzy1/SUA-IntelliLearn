<?php
// config.php already calls session_start(), opens $conn (MySQLi) and $pdo (PDO),
// and defines requireAdmin() / clean() / CSRF + flash helpers.
require_once __DIR__ . '/../../config/config.php';

requireAdmin();

// add_class_modal.php prints its modal markup as soon as it's included. Buffer
// the output so the archive/restore handlers below can still redirect with
// header() (otherwise PHP raises "Cannot modify header information").
ob_start();
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

// ================= ARCHIVE / RESTORE HANDLERS =================
// Nothing in this module is deleted any more. "Archive" flags the row with
// is_archived = 1 (and stamps archived_at); "Restore" clears the flag again.
//   - An archived class is hidden from the students enrolled in it.
//   - An archived section hides EVERY class of that section from students
//     and teachers (the classes themselves keep their own flag untouched, so
//     restoring the section brings them straight back).
//   - An archived subject can no longer be picked for new classes.

/**
 * Archive/restore a class. A class has one classofferings row per term
 * (TRM 1/2/3), so every row of the same subject + section + school year is
 * flagged together. Returns the number of rows changed.
 */
function setClassArchived(PDO $pdo, int $offeringId, bool $archive): int
{
    $find = $pdo->prepare("SELECT subject_id, section_id, school_year_id FROM classofferings WHERE offering_id = ?");
    $find->execute([$offeringId]);
    $row = $find->fetch();
    if (!$row) {
        return 0;
    }

    $sql = $archive
        ? "UPDATE classofferings SET is_archived = 1, archived_at = NOW()
           WHERE subject_id = ? AND section_id = ? AND school_year_id = ?"
        : "UPDATE classofferings SET is_archived = 0, archived_at = NULL
           WHERE subject_id = ? AND section_id = ? AND school_year_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$row['subject_id'], $row['section_id'], $row['school_year_id']]);
    return $stmt->rowCount();
}

// Archiving is locked while a term is running (restoring is always allowed).
$archiveLockedTerm = getActiveTerm($pdo);

// courses.php?archive=ID&csrf=...   |   courses.php?restore=ID&csrf=...   (Class List)
foreach (['archive' => true, 'restore' => false] as $param => $doArchive) {
    if (isset($_GET[$param]) && ctype_digit($_GET[$param])) {
        if (!validateCSRFToken($_GET['csrf'] ?? '')) {
            setFlashMessage('error', 'That link expired. Please try again.');
        } elseif ($doArchive && $archiveLockedTerm !== null) {
            setFlashMessage('error', archiveBlockedMessage($archiveLockedTerm));
        } else {
            try {
                $changed = setClassArchived($pdo, (int) $_GET[$param], $doArchive);
                if ($changed === 0) {
                    setFlashMessage('error', 'That class no longer exists.');
                } elseif ($doArchive) {
                    setFlashMessage('success', 'Class archived. It is now hidden from its students. Use the "Archived" filter to restore it.');
                } else {
                    $secCheck = $pdo->prepare("SELECT sec.is_archived FROM classofferings co JOIN sections sec ON sec.section_id = co.section_id WHERE co.offering_id = ?");
                    $secCheck->execute([(int) $_GET[$param]]);
                    if ((int) $secCheck->fetchColumn() === 1) {
                        setFlashMessage('success', 'Class restored, but it stays hidden until its section is restored too.');
                    } else {
                        setFlashMessage('success', 'Class restored.');
                    }
                }
            } catch (PDOException $e) {
                setFlashMessage('error', 'Database error: ' . $e->getMessage());
            }
        }

        header("Location: courses.php?" . http_build_query(array_filter([
            'status'      => $_GET['status'] ?? null,
            'grade'       => $_GET['grade'] ?? null,
            'quarter'     => $_GET['quarter'] ?? null,
            'school_year' => $_GET['school_year'] ?? null,
            'q'           => $_GET['q'] ?? null,
        ])));
        exit();
    }
}

// courses.php?archive_section=ID / restore_section=ID&csrf=...  (Sections List)
foreach (['archive_section' => true, 'restore_section' => false] as $param => $doArchive) {
    if (isset($_GET[$param]) && ctype_digit($_GET[$param])) {
        if (!validateCSRFToken($_GET['csrf'] ?? '')) {
            setFlashMessage('error', 'That link expired. Please try again.');
        } elseif ($doArchive && $archiveLockedTerm !== null) {
            setFlashMessage('error', archiveBlockedMessage($archiveLockedTerm));
        } else {
            try {
                $stmt = $pdo->prepare($doArchive
                    ? "UPDATE sections SET is_archived = 1, archived_at = NOW() WHERE section_id = ?"
                    : "UPDATE sections SET is_archived = 0, archived_at = NULL WHERE section_id = ?");
                $stmt->execute([(int) $_GET[$param]]);
                setFlashMessage('success', $doArchive
                    ? 'Section archived. All of its classes are now hidden from students and teachers.'
                    : 'Section restored. Its classes are visible again.');
            } catch (PDOException $e) {
                setFlashMessage('error', 'Database error: ' . $e->getMessage());
            }
        }

        header("Location: courses.php?open=sections");
        exit();
    }
}

// courses.php?archive_subject=ID / restore_subject=ID&csrf=...  (Subjects List)
foreach (['archive_subject' => true, 'restore_subject' => false] as $param => $doArchive) {
    if (isset($_GET[$param]) && ctype_digit($_GET[$param])) {
        if (!validateCSRFToken($_GET['csrf'] ?? '')) {
            setFlashMessage('error', 'That link expired. Please try again.');
        } elseif ($doArchive && $archiveLockedTerm !== null) {
            setFlashMessage('error', archiveBlockedMessage($archiveLockedTerm));
        } else {
            try {
                $stmt = $pdo->prepare($doArchive
                    ? "UPDATE subjects SET is_archived = 1, archived_at = NOW() WHERE subject_id = ?"
                    : "UPDATE subjects SET is_archived = 0, archived_at = NULL WHERE subject_id = ?");
                $stmt->execute([(int) $_GET[$param]]);
                setFlashMessage('success', $doArchive
                    ? 'Subject archived. It can no longer be used for new classes.'
                    : 'Subject restored.');
            } catch (PDOException $e) {
                setFlashMessage('error', 'Database error: ' . $e->getMessage());
            }
        }

        header("Location: courses.php?open=subjects");
        exit();
    }
}

$csrfToken = generateCSRFToken();

// ================= TERM INTERVALS =================
// Configured once via "Set Term Interval" instead of being picked per
// class offering. Every class always follows this single, school-wide
// current term — sync stale rows onto it before we read the class list
// below, so classes advance automatically as terms roll over, not just
// when an admin re-saves the intervals.
syncCourseTermsToCurrent($pdo);

$termIntervals = getTermIntervals($pdo);
$currentTerm   = resolveCurrentTerm($termIntervals);

// Which view-panel (if any) should be open on load — set after a
// Sections/Subjects add, update, or delete action so the panel doesn't
// collapse on the user after they just worked in it.
$openView = $_GET['open'] ?? '';
if (!in_array($openView, ['sections', 'subjects', 'export'], true)) {
    $openView = '';
}

// ================= FILTER INPUTS =================
$statusFilter     = $_GET['status']  ?? 'all';             // all | active | inactive | archived
$gradeFilter      = $_GET['grade']   ?? 'all';             // all | 7..12
$quarterFilter    = $_GET['quarter'] ?? 'all';             // all | 1..3
$schoolYearFilter = $_GET['school_year'] ?? 'all';         // all | school_year_id
$searchQuery      = trim($_GET['q'] ?? '');

if (!in_array($statusFilter, ['all', 'active', 'inactive', 'archived'], true)) {
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

if ($statusFilter === 'archived') {
    // Archived = the class itself was archived, or its whole section was.
    $where[] = '(co.is_archived = 1 OR sec.is_archived = 1)';
} else {
    $where[] = 'co.is_archived = 0 AND sec.is_archived = 0';
    if ($statusFilter !== 'all') {
        $where[] = 'co.status = ?';
        $params[] = $statusFilter;
    }
}

if ($gradeFilter !== 'all' && ctype_digit($gradeFilter)) {
    $where[] = 'sec.grade_level = ?';
    $params[] = (int) $gradeFilter;
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
        co.class_code,
        co.subject_id,
        co.capacity,
        co.status,
        co.is_archived,
        sec.is_archived AS section_archived,
        co.quarter,
        co.school_year_id,
        co.schedule_days,
        co.start_time,
        co.end_time,
        s.subject_name,
        sec.section_id,
        sec.section_name,
        sec.grade_level,
        t.teacher_id,
        t.firstname AS teacher_firstname,
        t.lastname  AS teacher_lastname,
        syco.label AS offering_school_year_label,
        (SELECT COUNT(*) FROM enrollments e
            WHERE e.offering_id = co.offering_id AND e.status = 'enrolled') AS enrolled_count,
        (SELECT GROUP_CONCAT(DISTINCT st.strand ORDER BY st.strand SEPARATOR '-')
            FROM enrollments es
            JOIN students st ON st.student_id = es.student_id
            WHERE es.offering_id = co.offering_id AND es.status = 'enrolled'
              AND st.strand IS NOT NULL AND st.strand <> '') AS strands
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

// ================= GROUP TERM SIBLINGS FOR DISPLAY =================
// Each subject+section+school-year can now have a row per term (TRM 1/2/3
// each get their own permanent classofferings row — see
// syncCourseTermsToCurrent() in config.php). Rather than listing every
// term as its own line and cluttering the table, show only the
// most-recent term per class by default, with the older terms nested
// underneath as collapsed "sibling" rows an admin can expand.
$termOrder = ['TRM 1' => 0, 'TRM 2' => 1, 'TRM 3' => 2];
$courseGroups = [];
foreach ($courses as $course) {
    $groupKey = $course['subject_id'] . ':' . $course['section_id'] . ':' . $course['school_year_id'];
    $courseGroups[$groupKey][] = $course;
}

$orderedRows = [];
foreach ($courseGroups as $groupKey => $groupRows) {
    usort($groupRows, static fn($a, $b) => ($termOrder[$a['quarter']] ?? 0) <=> ($termOrder[$b['quarter']] ?? 0));

    $primary  = array_pop($groupRows); // Highest term reached so far = most recent.
    $siblings = $groupRows;            // Whatever's left, oldest term first.

    $orderedRows[] = ['course' => $primary, 'is_sibling' => false, 'group_key' => $groupKey, 'sibling_count' => count($siblings)];
    foreach ($siblings as $sibling) {
        $orderedRows[] = ['course' => $sibling, 'is_sibling' => true, 'group_key' => $groupKey, 'sibling_count' => count($siblings)];
    }
}

// ================= STATS (unaffected by filters — whole school) =================
$notArchivedSql = "is_archived = 0 AND section_id NOT IN (SELECT section_id FROM sections WHERE is_archived = 1)";
$totalCourses  = (int) $pdo->query("SELECT COUNT(*) FROM classofferings WHERE {$notArchivedSql}")->fetchColumn();
$activeCourses = (int) $pdo->query("SELECT COUNT(*) FROM classofferings WHERE status = 'active' AND {$notArchivedSql}")->fetchColumn();

$teachersAssigned = (int) $pdo->query("SELECT COUNT(DISTINCT teacher_id) FROM classofferings WHERE {$notArchivedSql}")->fetchColumn();
$totalTeachers    = (int) $pdo->query("SELECT COUNT(*) FROM teachers")->fetchColumn();

$totalEnrollees = (int) $pdo->query("SELECT COUNT(DISTINCT student_id) FROM enrollments WHERE status = 'enrolled' AND offering_id IN (SELECT offering_id FROM classofferings WHERE {$notArchivedSql})")->fetchColumn();

// ================= DATA FOR "NEW COURSE" / "UPDATE COURSE" MODALS =================


// ================= DATA FOR "NEW SECTION" MODAL =================
$currentSchoolYear = $pdo->query("SELECT school_year_id, label FROM schoolyears WHERE is_current = 1 LIMIT 1")->fetch();

// ================= DATA FOR "SECTIONS" VIEW =================
$sectionsList = $pdo->query("
    SELECT
        sec.section_id,
        sec.section_name,
        sec.grade_level,
        sec.adviser_id,
        sec.is_archived,
        t.firstname AS adviser_firstname,
        t.lastname  AS adviser_lastname,
        sy.label AS school_year_label,
        (SELECT COUNT(*) FROM classofferings co
            WHERE co.section_id = sec.section_id AND co.is_archived = 0) AS course_count,
        (SELECT COUNT(*) FROM enrollments e
            JOIN classofferings co2 ON co2.offering_id = e.offering_id
            WHERE co2.section_id = sec.section_id AND co2.is_archived = 0 AND e.status = 'enrolled') AS student_count
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
        s.is_archived,
        (SELECT COUNT(*) FROM classofferings co
            WHERE co.subject_id = s.subject_id AND co.is_archived = 0) AS offering_count,
        (SELECT COUNT(DISTINCT co.section_id) FROM classofferings co
            WHERE co.subject_id = s.subject_id AND co.is_archived = 0) AS section_count
    FROM subjects s
    ORDER BY s.subject_name ASC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Classes & Subjects | SUA IntelliLearn Admin</title>

<!-- Fonts & Icons -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<!-- Courses Stylesheet -->
<link rel="stylesheet" href="assests/css/courses.css">
<link rel="stylesheet" href="assests/css/add_course.css">

<?php include '../../includes/admin_readonly_ui.php'; ?>
</head>
<body>

<?php include '../../includes/admin_sidebar.php'; ?>
<?php include '../../includes/admin_header.php'; ?>
<div class="main-content cs-page" id="mainContent">

    <?php if ($flash): ?>
    <div class="flash-message flash-<?= clean($flash['type']) ?>" role="status">
        <i class="fas <?= $flash['type'] === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>" aria-hidden="true"></i>
        <span><?= clean($flash['message']) ?></span>
        <button type="button" class="flash-close" aria-label="Dismiss" onclick="this.parentElement.remove()">&times;</button>
    </div>
    <?php endif; ?>

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-text">
            <h1>Classes &amp; Subjects</h1>
            <p>Manage class offerings, sections, and subjects across the school.</p>
        </div>
        <div class="header-actions">
            <button type="button" class="btn-secondary" onclick="openAddSubjectModal()"><i class="fas fa-book" aria-hidden="true"></i> New Subject</button>
            <button type="button" class="btn-secondary" onclick="openAddSectionModal()"><i class="fas fa-layer-group" aria-hidden="true"></i> New Section</button>
            <button type="button" class="btn-primary" onclick="openAddCourseModal()"><i class="fas fa-plus" aria-hidden="true"></i> New Class</button>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-book-open" aria-hidden="true"></i></div>
            <div class="stat-body">
                <div class="stat-value"><?= htmlspecialchars($totalCourses) ?></div>
                <div class="stat-label">Total Courses</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon success"><i class="fas fa-circle-check" aria-hidden="true"></i></div>
            <div class="stat-body">
                <div class="stat-value"><?= htmlspecialchars($activeCourses) ?></div>
                <div class="stat-label">Active Courses</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon info"><i class="fas fa-chalkboard-teacher" aria-hidden="true"></i></div>
            <div class="stat-body">
                <div class="stat-value"><?= htmlspecialchars($teachersAssigned) ?><span class="stat-of"> / <?= htmlspecialchars($totalTeachers) ?></span></div>
                <div class="stat-label">Teachers Assigned</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon accent"><i class="fas fa-user-graduate" aria-hidden="true"></i></div>
            <div class="stat-body">
                <div class="stat-value"><?= htmlspecialchars($totalEnrollees) ?></div>
                <div class="stat-label">Total Enrollees</div>
            </div>
        </div>
    </div>

    <!-- View tabs: Classes / Subjects / Sections / Search & Export -->
    <div class="view-tabs" role="tablist" aria-label="Classes and subjects views">
        <button type="button" role="tab" class="view-tab <?= $openView === '' ? 'active' : '' ?>" data-view-tab="classes"
                id="toggleClassesBtn" aria-selected="<?= $openView === '' ? 'true' : 'false' ?>" onclick="showView('classes')">
            <i class="fas fa-chalkboard" aria-hidden="true"></i> Classes
            <span class="tab-count"><?= htmlspecialchars($totalCourses) ?></span>
        </button>
        <button type="button" role="tab" class="view-tab <?= $openView === 'subjects' ? 'active' : '' ?>" data-view-tab="subjects"
                id="toggleSubjectsBtn" aria-selected="<?= $openView === 'subjects' ? 'true' : 'false' ?>" onclick="showView('subjects')">
            <i class="fas fa-book" aria-hidden="true"></i> Subjects
            <span class="tab-count"><?= htmlspecialchars(count($subjectsList)) ?></span>
        </button>
        <button type="button" role="tab" class="view-tab <?= $openView === 'sections' ? 'active' : '' ?>" data-view-tab="sections"
                id="toggleSectionsBtn" aria-selected="<?= $openView === 'sections' ? 'true' : 'false' ?>" onclick="showView('sections')">
            <i class="fas fa-layer-group" aria-hidden="true"></i> Sections
            <span class="tab-count"><?= htmlspecialchars(count($sectionsList)) ?></span>
        </button>
        <button type="button" role="tab" class="view-tab <?= $openView === 'export' ? 'active' : '' ?>" data-view-tab="export"
                id="toggleExportBtn" aria-selected="<?= $openView === 'export' ? 'true' : 'false' ?>" onclick="showView('export')">
            <i class="fas fa-file-export" aria-hidden="true"></i> Search &amp; Export
        </button>
        <span class="view-tabs-spacer"></span>
        <button type="button" class="btn-secondary btn-sm" id="toggleTermIntervalBtn" onclick="openTermIntervalModal()">
            <i class="fas fa-calendar-days" aria-hidden="true"></i> Set Term Interval
        </button>
    </div>

    <!-- ================= CLASSES VIEW ================= -->
    <div class="view-panel <?= $openView === '' ? 'open' : '' ?>" id="view-classes">

    <?php
        $filtersActive = ($statusFilter !== 'all' || $gradeFilter !== 'all' || $quarterFilter !== 'all' || $schoolYearFilter !== 'all');
        $pillQuery = static function (string $status) use ($gradeFilter, $quarterFilter, $schoolYearFilter, $searchQuery): string {
            return '?' . http_build_query(array_filter([
                'status' => $status, 'grade' => $gradeFilter, 'quarter' => $quarterFilter,
                'school_year' => $schoolYearFilter, 'q' => $searchQuery,
            ]));
        };
    ?>

    <!-- Filter Toolbar -->
    <div class="toolbar">
        <!-- Live client-side search: instantly filters the Class List table below
             by subject, section, or teacher name. No page reload, no server call. -->
        <div class="live-search-box">
            <i class="fas fa-search" aria-hidden="true"></i>
            <input type="text"
                   id="liveSearchInput"
                   placeholder="Search teacher, subject, or section..."
                   aria-label="Search classes"
                   autocomplete="off"
                   oninput="filterCourseList(this.value)">
            <button type="button" class="live-search-clear" id="liveSearchClearBtn" onclick="clearCourseListSearch()" aria-label="Clear search">&times;</button>
        </div>

        <div class="segmented" role="group" aria-label="Filter by status">
            <a class="filter-pill <?= $statusFilter === 'all' ? 'active' : '' ?>" href="<?= $pillQuery('all') ?>">All</a>
            <a class="filter-pill <?= $statusFilter === 'active' ? 'active' : '' ?>" href="<?= $pillQuery('active') ?>">Active</a>
            <a class="filter-pill <?= $statusFilter === 'inactive' ? 'active' : '' ?>" href="<?= $pillQuery('inactive') ?>">Inactive</a>
            <a class="filter-pill <?= $statusFilter === 'archived' ? 'active' : '' ?>" href="<?= $pillQuery('archived') ?>"><i class="fas fa-box-archive" aria-hidden="true"></i> Archived</a>
        </div>

        <form method="get" action="courses.php" class="filter-selects">
            <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
            <input type="hidden" name="q" value="<?= htmlspecialchars($searchQuery) ?>">

            <select class="select-filter" name="grade" aria-label="Filter by grade level" onchange="this.form.submit()">
                <option value="all" <?= $gradeFilter === 'all' ? 'selected' : '' ?>>All Grade Levels</option>
                <?php foreach ([7, 8, 9, 10, 11, 12] as $g): ?>
                    <option value="<?= $g ?>" <?= $gradeFilter == $g ? 'selected' : '' ?>>Grade <?= $g ?></option>
                <?php endforeach; ?>
            </select>

            <select class="select-filter" name="quarter" aria-label="Filter by term" onchange="this.form.submit()">
                <option value="all" <?= $quarterFilter === 'all' ? 'selected' : '' ?>>All Terms</option>
                <?php foreach (['TRM 1', 'TRM 2', 'TRM 3'] as $q): ?>
                    <option value="<?= $q ?>" <?= $quarterFilter === $q ? 'selected' : '' ?>><?= $q ?></option>
                <?php endforeach; ?>
            </select>

            <select class="select-filter" name="school_year" aria-label="Filter by school year" onchange="this.form.submit()">
                <option value="all" <?= $schoolYearFilter === 'all' ? 'selected' : '' ?>>All School Years</option>
                <?php foreach ($modalSchoolYears as $sy): ?>
                    <option value="<?= (int) $sy['school_year_id'] ?>" <?= $schoolYearFilter == $sy['school_year_id'] ? 'selected' : '' ?>><?= clean($sy['label']) ?></option>
                <?php endforeach; ?>
            </select>

            <?php if ($filtersActive): ?>
            <a class="reset-filters" href="courses.php"><i class="fas fa-rotate-left" aria-hidden="true"></i> Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Course List -->
    <div class="list-panel">
        <div class="list-panel-header">
            <div class="list-panel-title">
                <h2>Class List</h2>
                <span class="count-note" id="courseCountTop">Showing <?= htmlspecialchars($totalShown) ?> of <?= htmlspecialchars($totalCourses) ?> courses</span>
            </div>
        </div>

        <div class="course-table-wrap" role="region" aria-label="Class list table" tabindex="0">
        <table class="course-table stack-table" id="courseTable">
            <thead>
                <tr>
                    <th>Class</th>
                    <th>Term</th>
                    <th>Schedule</th>
                    <th>Teacher</th>
                    <th>Enrollment</th>
                    <th>Status</th>
                    <th>Class Code</th>
                    <th class="col-actions">Actions</th>
                </tr>
            </thead>
            <tbody id="courseTableBody">
                <?php if (empty($courses)): ?>
                <tr class="empty-row">
                    <td colspan="8">
                        <div class="empty-state">
                            <div class="empty-icon"><i class="fas fa-inbox" aria-hidden="true"></i></div>
                            <strong>No classes match these filters</strong>
                            <span>Try a different filter, or create a new class.</span>
                        </div>
                    </td>
                </tr>
                <?php endif; ?>

                <?php foreach ($orderedRows as $entry):
                    $course       = $entry['course'];
                    $isSibling    = $entry['is_sibling'];
                    $groupKey     = $entry['group_key'];
                    $siblingCount = $entry['sibling_count'];
                    $colors = $subjectColors[$course['subject_name']] ?? ['bg' => '#e5e7eb', 'text' => '#374151', 'bar' => '#9ca3af'];
                    $pct = $course['capacity'] > 0 ? round(($course['enrolled_count'] / $course['capacity']) * 100) : 0;
                    $teacherName = $course['teacher_id'] ? trim($course['teacher_firstname'] . ' ' . $course['teacher_lastname']) : null;
                    $teacherInitials = $teacherName
                        ? mb_strtoupper(mb_substr((string) $course['teacher_firstname'], 0, 1) . mb_substr((string) $course['teacher_lastname'], 0, 1))
                        : '';
                    $scheduleDisplay = formatScheduleDisplay($course['schedule_days'], $course['start_time'], $course['end_time']);
                    $ownArchived     = (int) $course['is_archived'] === 1;
                    $sectionArchived = (int) $course['section_archived'] === 1;
                    $isArchivedRow   = $ownArchived || $sectionArchived;
                    $barColor        = $pct >= 100 ? '#dc2626' : $colors['bar'];
                    // Pre-baked lowercase blob this row matches against for the
                    // instant client-side search box (subject + section + teacher name).
                    $searchBlob = mb_strtolower(trim(
                        $course['subject_name'] . ' ' .
                        $course['section_name'] . ' ' .
                        ($teacherName ?? '')
                    ));
                ?>
                <tr class="course-row<?= $isSibling ? ' sibling-row' : '' ?><?= $isArchivedRow ? ' archived-row' : '' ?>"
                    data-search="<?= htmlspecialchars($searchBlob) ?>"
                    data-group="<?= htmlspecialchars($groupKey) ?>"
                    <?= $isSibling ? 'data-expanded="false" style="display:none"' : '' ?>>
                    <td data-label="Class">
                        <div class="class-cell">
                            <span class="subject-tag" style="background: <?= $colors['bg'] ?>; color: <?= $colors['text'] ?>;">
                                <?= htmlspecialchars($course['subject_name']) ?>
                            </span>
                            <div class="class-meta">
                                <span class="course-name"><?= $isSibling ? '<span class="sibling-indent">↳</span>' : '' ?>Section <?= htmlspecialchars($course['section_name']) ?></span>
                                <span class="class-sub">Grade <?= htmlspecialchars($course['grade_level']) ?><?= $course['strands'] ? ' · ' . htmlspecialchars($course['strands']) : '' ?></span>
                            </div>
                        </div>
                    </td>
                    <td data-label="Term">
                        <div class="term-cell">
                            <span class="term-chip"><?= htmlspecialchars($course['quarter']) ?></span>
                            <span class="term-sy"><?= $course['offering_school_year_label'] ? htmlspecialchars($course['offering_school_year_label']) : '<span class="field-note">No school year</span>' ?></span>
                            <?php if (!$isSibling && $siblingCount > 0): ?>
                                <button type="button" class="term-toggle" data-group="<?= htmlspecialchars($groupKey) ?>"
                                        aria-expanded="false" onclick="toggleCourseSiblings(this)">
                                    <span class="chevron">▸</span>
                                    <?= $siblingCount ?> earlier <?= $siblingCount === 1 ? 'term' : 'terms' ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td data-label="Schedule">
                        <?php if ($scheduleDisplay): ?>
                            <span class="sched"><i class="far fa-clock" aria-hidden="true"></i> <?= htmlspecialchars($scheduleDisplay) ?></span>
                        <?php else: ?>
                            <span class="field-note">Not set</span>
                        <?php endif; ?>
                    </td>
                    <td data-label="Teacher">
                        <?php if ($teacherName): ?>
                            <div class="person-cell">
                                <span class="avatar-sm" aria-hidden="true"><?= htmlspecialchars($teacherInitials) ?></span>
                                <span class="person-name"><?= htmlspecialchars($teacherName) ?></span>
                            </div>
                        <?php else: ?>
                            <span class="chip-muted"><i class="fas fa-user-slash" aria-hidden="true"></i> Unassigned</span>
                        <?php endif; ?>
                    </td>
                    <td data-label="Enrollment">
                        <div class="enrollment-cell" title="<?= (int) $pct ?>% of capacity">
                            <div class="enrollment-bar">
                                <div class="enrollment-bar-fill" style="width: <?= min($pct, 100) ?>%; background: <?= $barColor ?>;"></div>
                            </div>
                            <span class="enrollment-fraction"><strong><?= htmlspecialchars($course['enrolled_count']) ?></strong>/<?= htmlspecialchars($course['capacity']) ?></span>
                        </div>
                    </td>
                    <td data-label="Status">
                        <?php if ($isArchivedRow): ?>
                        <span class="status-dot-badge archived" title="<?= $ownArchived ? 'This class is archived' : 'Hidden because its section is archived' ?>">
                            <span class="dot"></span>
                            <?= $ownArchived ? 'Archived' : 'Section archived' ?>
                        </span>
                        <?php else: ?>
                        <span class="status-dot-badge <?= $course['status'] ?>">
                            <span class="dot"></span>
                            <?= $course['status'] === 'active' ? 'Active' : 'Inactive' ?>
                        </span>
                        <?php endif; ?>
                    </td>
                    <td data-label="Class Code">
                        <button type="button" class="class-code-badge" data-code="<?= htmlspecialchars($course['class_code']) ?>"
                                title="Click to copy" aria-label="Copy class code <?= htmlspecialchars($course['class_code']) ?>"
                                onclick="copyClassCode(this)">
                            <span><?= htmlspecialchars($course['class_code']) ?></span>
                            <i class="far fa-copy" aria-hidden="true"></i>
                        </button>
                    </td>
                    <td class="col-actions" data-label="Actions">
                        <div class="row-actions">
                            <button type="button" class="ra-btn" data-tip="View students" aria-label="View students"
                                    onclick="openViewStudentsModal(<?= (int) $course['offering_id'] ?>)"><i class="fas fa-users" aria-hidden="true"></i></button>
                            <?php if (!$isArchivedRow): ?>
                            <button type="button" class="ra-btn" data-tip="Update class" aria-label="Update class"
                               data-course="<?= htmlspecialchars(json_encode([
                                   'offering_id'   => (int) $course['offering_id'],
                                   'subject_id'    => (int) $course['subject_id'],
                                   'section_id'    => (int) $course['section_id'],
                                   'teacher_id'    => $course['teacher_id'] ? (int) $course['teacher_id'] : '',
                                   'quarter'       => $course['quarter'],
                                   'school_year_id' => (int) $course['school_year_id'],
                                   'schedule_days' => $course['schedule_days'] ?? '',
                                   'start_time'    => $course['start_time'] ? date('H:i', strtotime($course['start_time'])) : '',
                                   'end_time'      => $course['end_time'] ? date('H:i', strtotime($course['end_time'])) : '',
                                   'capacity'      => (int) $course['capacity'],
                                   'status'        => $course['status'],
                               ]), ENT_QUOTES, 'UTF-8') ?>"
                               onclick="openEditCourseModal(this)"><i class="fas fa-pen" aria-hidden="true"></i></button>
                            <?php if ($archiveLockedTerm !== null): ?>
                            <span class="ra-btn archive archive-disabled" data-tip="Archiving is disabled while <?= htmlspecialchars((string) $archiveLockedTerm) ?> is active" aria-disabled="true"><i class="fas fa-lock" aria-hidden="true"></i></span>
                            <?php else: ?>
                            <a class="ra-btn archive" data-tip="Archive class" aria-label="Archive class" href="courses.php?archive=<?= (int) $course['offering_id'] ?>&csrf=<?= urlencode($csrfToken) ?>&status=<?= urlencode($statusFilter) ?>&grade=<?= urlencode($gradeFilter) ?>&q=<?= urlencode($searchQuery) ?>"
                               onclick="return confirmArchive('course')"><i class="fas fa-box-archive" aria-hidden="true"></i></a>
                            <?php endif; ?>
                            <?php elseif ($ownArchived): ?>
                            <a class="ra-btn restore" data-tip="Restore class" aria-label="Restore class" href="courses.php?restore=<?= (int) $course['offering_id'] ?>&csrf=<?= urlencode($csrfToken) ?>&status=<?= urlencode($statusFilter) ?>&grade=<?= urlencode($gradeFilter) ?>&q=<?= urlencode($searchQuery) ?>"
                               onclick="return confirmArchive('restore-course')"><i class="fas fa-rotate-left" aria-hidden="true"></i></a>
                            <?php else: ?>
                            <span class="chip-muted" title="Restore the section from the Sections List to bring this class back">Restore its section</span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <div class="list-panel-footer">
            <span class="count-note" id="courseCountBottom">Showing <?= htmlspecialchars($totalShown) ?> of <?= htmlspecialchars($totalCourses) ?> courses</span>
        </div>
    </div>
    <!-- /Course List -->
    </div>
    <!-- /Classes view -->

    <!-- Sections List -->
    <div class="view-panel <?= $openView === 'sections' ? 'open' : '' ?>" id="view-sections">
    <div class="list-panel">
        <div class="list-panel-header">
            <div class="list-panel-title">
                <h2>Sections List</h2>
                <span class="count-note"><?= htmlspecialchars(count($sectionsList)) ?> sections</span>
            </div>
            <button type="button" class="btn-primary btn-sm" onclick="openAddSectionModal()"><i class="fas fa-plus" aria-hidden="true"></i> New Section</button>
        </div>

        <div class="course-table-wrap" tabindex="0" role="region" aria-label="Sections table">
        <table class="course-table stack-table">
            <thead>
                <tr>
                    <th>Section</th>
                    <th>Grade Level</th>
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
                        <div class="empty-state">
                            <div class="empty-icon"><i class="fas fa-layer-group" aria-hidden="true"></i></div>
                            <strong>No sections yet</strong>
                            <span>Use “New Section” to add one.</span>
                        </div>
                    </td>
                </tr>
                <?php endif; ?>

                <?php foreach ($sectionsList as $sec):
                    $adviserName = $sec['adviser_firstname']
                        ? trim($sec['adviser_firstname'] . ' ' . $sec['adviser_lastname'])
                        : null;
                    $adviserInitials = $adviserName
                        ? mb_strtoupper(mb_substr((string) $sec['adviser_firstname'], 0, 1) . mb_substr((string) $sec['adviser_lastname'], 0, 1))
                        : '';
                ?>
                <tr class="<?= !empty($sec['is_archived']) ? 'archived-row' : '' ?>">
                    <td data-label="Section"><span class="course-name"><?= htmlspecialchars($sec['section_name']) ?></span><?= !empty($sec['is_archived']) ? ' <span class="archive-badge">Archived</span>' : '' ?></td>
                    <td data-label="Grade Level"><span class="term-chip">Grade <?= htmlspecialchars($sec['grade_level']) ?></span></td>
                    <td data-label="Adviser">
                        <?php if ($adviserName): ?>
                            <div class="person-cell">
                                <span class="avatar-sm" aria-hidden="true"><?= htmlspecialchars($adviserInitials) ?></span>
                                <span class="person-name"><?= htmlspecialchars($adviserName) ?></span>
                            </div>
                        <?php else: ?>
                            <span class="chip-muted"><i class="fas fa-user-slash" aria-hidden="true"></i> None</span>
                        <?php endif; ?>
                    </td>
                    <td data-label="School Year"><?= $sec['school_year_label'] ? htmlspecialchars($sec['school_year_label']) : '<span class="field-note">None</span>' ?></td>
                    <td data-label="Courses Offered"><span class="count-pill"><?= (int) $sec['course_count'] ?></span></td>
                    <td data-label="Enrolled Students"><span class="count-pill"><?= (int) $sec['student_count'] ?></span></td>
                    <td class="col-actions" data-label="Actions">
                        <div class="row-actions">
                            <?php if (empty($sec['is_archived'])): ?>
                            <button type="button" class="ra-btn" data-tip="Update section" aria-label="Update section"
                               data-section="<?= htmlspecialchars(json_encode([
                                   'section_id'   => (int) $sec['section_id'],
                                   'section_name' => $sec['section_name'],
                                   'grade_level'  => (int) $sec['grade_level'],
                                   'adviser_id'   => $sec['adviser_id'] ? (int) $sec['adviser_id'] : '',
                               ]), ENT_QUOTES, 'UTF-8') ?>"
                               onclick="openEditSectionModal(this)"><i class="fas fa-pen" aria-hidden="true"></i></button>
                            <?php if ($archiveLockedTerm !== null): ?>
                            <span class="ra-btn archive archive-disabled" data-tip="Archiving is disabled while <?= htmlspecialchars((string) $archiveLockedTerm) ?> is active" aria-disabled="true"><i class="fas fa-lock" aria-hidden="true"></i></span>
                            <?php else: ?>
                            <a class="ra-btn archive" data-tip="Archive section" aria-label="Archive section" href="courses.php?archive_section=<?= (int) $sec['section_id'] ?>&csrf=<?= urlencode($csrfToken) ?>"
                               onclick="return confirmArchive('section')"><i class="fas fa-box-archive" aria-hidden="true"></i></a>
                            <?php endif; ?>
                            <?php else: ?>
                            <a class="ra-btn restore" data-tip="Restore section" aria-label="Restore section" href="courses.php?restore_section=<?= (int) $sec['section_id'] ?>&csrf=<?= urlencode($csrfToken) ?>"
                               onclick="return confirmArchive('restore-section')"><i class="fas fa-rotate-left" aria-hidden="true"></i></a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <div class="list-panel-footer">
            <span class="count-note"><?= htmlspecialchars(count($sectionsList)) ?> sections total</span>
        </div>
    </div>
    </div>
    <!-- /Sections List -->

    <!-- Subjects List -->
    <div class="view-panel <?= $openView === 'subjects' ? 'open' : '' ?>" id="view-subjects">
    <div class="list-panel">
        <div class="list-panel-header">
            <div class="list-panel-title">
                <h2>Subjects List</h2>
                <span class="count-note"><?= htmlspecialchars(count($subjectsList)) ?> subjects</span>
            </div>
            <button type="button" class="btn-primary btn-sm" onclick="openAddSubjectModal()"><i class="fas fa-plus" aria-hidden="true"></i> New Subject</button>
        </div>

        <div class="course-table-wrap" tabindex="0" role="region" aria-label="Subjects table">
        <table class="course-table stack-table">
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
                        <div class="empty-state">
                            <div class="empty-icon"><i class="fas fa-book" aria-hidden="true"></i></div>
                            <strong>No subjects yet</strong>
                            <span>Use “New Subject” to add one.</span>
                        </div>
                    </td>
                </tr>
                <?php endif; ?>

                <?php foreach ($subjectsList as $subj):
                    $colors = $subjectColors[$subj['subject_name']] ?? ['bg' => '#e5e7eb', 'text' => '#374151', 'bar' => '#9ca3af'];
                ?>
                <tr class="<?= !empty($subj['is_archived']) ? 'archived-row' : '' ?>">
                    <td data-label="Subject">
                        <div class="course-cell">
                            <span class="subject-tag" style="background: <?= $colors['bg'] ?>; color: <?= $colors['text'] ?>;">
                                <?= htmlspecialchars($subj['subject_name']) ?>
                            </span>
                            <?= !empty($subj['is_archived']) ? '<span class="archive-badge">Archived</span>' : '' ?>
                        </div>
                    </td>
                    <td data-label="Description" class="cell-wrap"><?= $subj['description'] ? htmlspecialchars($subj['description']) : '<span class="field-note">No description</span>' ?></td>
                    <td data-label="Courses Offered"><span class="count-pill"><?= (int) $subj['offering_count'] ?></span></td>
                    <td data-label="Sections Covered"><span class="count-pill"><?= (int) $subj['section_count'] ?></span></td>
                    <td class="col-actions" data-label="Actions">
                        <div class="row-actions">
                            <?php if (empty($subj['is_archived'])): ?>
                            <button type="button" class="ra-btn" data-tip="Update subject" aria-label="Update subject"
                               data-subject="<?= htmlspecialchars(json_encode([
                                   'subject_id'   => (int) $subj['subject_id'],
                                   'subject_name' => $subj['subject_name'],
                                   'description'  => $subj['description'] ?? '',
                               ]), ENT_QUOTES, 'UTF-8') ?>"
                               onclick="openEditSubjectModal(this)"><i class="fas fa-pen" aria-hidden="true"></i></button>
                            <?php if ($archiveLockedTerm !== null): ?>
                            <span class="ra-btn archive archive-disabled" data-tip="Archiving is disabled while <?= htmlspecialchars((string) $archiveLockedTerm) ?> is active" aria-disabled="true"><i class="fas fa-lock" aria-hidden="true"></i></span>
                            <?php else: ?>
                            <a class="ra-btn archive" data-tip="Archive subject" aria-label="Archive subject" href="courses.php?archive_subject=<?= (int) $subj['subject_id'] ?>&csrf=<?= urlencode($csrfToken) ?>"
                               onclick="return confirmArchive('subject')"><i class="fas fa-box-archive" aria-hidden="true"></i></a>
                            <?php endif; ?>
                            <?php else: ?>
                            <a class="ra-btn restore" data-tip="Restore subject" aria-label="Restore subject" href="courses.php?restore_subject=<?= (int) $subj['subject_id'] ?>&csrf=<?= urlencode($csrfToken) ?>"
                               onclick="return confirmArchive('restore-subject')"><i class="fas fa-rotate-left" aria-hidden="true"></i></a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>

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
            <div class="list-panel-title">
                <h2>Search &amp; Export Classes</h2>
                <span class="count-note">Find a teacher, section, or student and export their classes to Excel</span>
            </div>
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
                            <?php foreach ($modalSubjectsAll as $s): ?>
                                <option value="<?= (int) $s['subject_id'] ?>"><?= clean($s['subject_name']) ?><?= !empty($s['is_archived']) ? ' (archived)' : '' ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-row">
                        <label for="e_section_id">Section</label>
                        <select id="e_section_id" name="section_id" required>
                            <option value="">Select a section</option>
                            <?php foreach ($modalSectionsAll as $sec): ?>
                                <option value="<?= (int) $sec['section_id'] ?>">
                                    Grade <?= clean($sec['grade_level']) ?> — <?= clean($sec['section_name']) ?><?= !empty($sec['is_archived']) ? ' (archived)' : '' ?>
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

                    <div class="form-row">
                        <span class="field-note">Term: <strong id="e_quarter_display">—</strong> (follows the school-wide current term — not set per class)</span>
                    </div>

                    <div class="form-row-split">
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
                            <input type="hidden" id="e_schedule_days" name="schedule_days" value="">
                            <fieldset class="schedule-day-picker" aria-label="Choose schedule days">
                                <legend>Select one or more days</legend>
                                <label class="schedule-day-option"><input type="checkbox" name="schedule_day_options[]" value="Monday"><span>Mon</span></label>
                                <label class="schedule-day-option"><input type="checkbox" name="schedule_day_options[]" value="Tuesday"><span>Tue</span></label>
                                <label class="schedule-day-option"><input type="checkbox" name="schedule_day_options[]" value="Wednesday"><span>Wed</span></label>
                                <label class="schedule-day-option"><input type="checkbox" name="schedule_day_options[]" value="Thursday"><span>Thu</span></label>
                                <label class="schedule-day-option"><input type="checkbox" name="schedule_day_options[]" value="Friday"><span>Fri</span></label>
                                <label class="schedule-day-option"><input type="checkbox" name="schedule_day_options[]" value="Saturday"><span>Sat</span></label>
                                <label class="schedule-day-option"><input type="checkbox" name="schedule_day_options[]" value="Sunday"><span>Sun</span></label>
                            </fieldset>
                            <span class="field-note">Click every day this class meets.</span>
                        </div>

                        <div class="form-row">
                            <label for="e_start_time">Start Time</label>
                            <input type="time" id="e_start_time" name="start_time" step="900">
                        </div>

                        <div class="form-row">
                            <label for="e_end_time">End Time</label>
                            <input type="time" id="e_end_time" name="end_time" step="900">
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

    <!-- Set Term Interval Modal -->
    <div class="modal-overlay" id="termIntervalOverlay" onclick="if (event.target === this) closeTermIntervalModal()">
        <div class="modal-box" style="max-width: 640px;">
            <div class="modal-header">
                <h2>Set Term Interval</h2>
                <button type="button" class="modal-close" onclick="closeTermIntervalModal()" aria-label="Close">&times;</button>
            </div>

            <div class="modal-errors" id="termIntervalErrors" hidden></div>

            <form id="termIntervalForm">
                <input type="hidden" name="csrf" value="<?= clean($csrfToken) ?>">

                <div class="modal-body">
                    <div class="form-row">
                        <span class="field-note">
                            New classes are automatically placed in whichever term covers today's date.
                            Currently active: <strong><?= $currentTerm ? clean($currentTerm) : 'None — not configured' ?></strong>.
                        </span>
                    </div>

                    <?php
                    $termNums   = ['TRM 1' => 1, 'TRM 2' => 2, 'TRM 3' => 3];
                    foreach ($termNums as $termLabel => $n):
                        $cfg = $termIntervals[$termLabel];
                    ?>
                    <fieldset style="border:1px solid var(--border,#e2e8f0); border-radius:8px; padding:14px 16px; margin-bottom:16px;">
                        <legend style="padding:0 6px; font-weight:600;"><?= clean($termLabel) ?></legend>

                        <div class="form-row-split" id="term_<?= $n ?>_date_fields">
                            <div class="form-row">
                                <label for="term_<?= $n ?>_start_date">Start Date</label>
                                <input type="date" id="term_<?= $n ?>_start_date" name="term_<?= $n ?>_start_date" value="<?= clean($cfg['start_date'] ?? '') ?>">
                            </div>
                            <div class="form-row">
                                <label for="term_<?= $n ?>_end_date">End Date</label>
                                <input type="date" id="term_<?= $n ?>_end_date" name="term_<?= $n ?>_end_date" value="<?= clean($cfg['end_date'] ?? '') ?>">
                            </div>
                        </div>
                    </fieldset>
                    <?php endforeach; ?>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeTermIntervalModal()">Cancel</button>
                    <button type="submit" class="btn-primary" id="termIntervalSubmitBtn"><i class="fas fa-check"></i> Save Term Intervals</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Students Modal -->
    <div class="modal-overlay" id="viewStudentsOverlay" data-csrf="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>" onclick="if (event.target === this) closeViewStudentsModal()">
        <div class="modal-box" style="max-width: 1100px; width: 92vw;">
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

                <div class="table-scroll">
                <table class="course-table">
                    <thead>
                        <tr>
                            <th style="width:40px; padding-right:0;">
                                <input type="checkbox" id="vsSelectAll" onchange="toggleVsSelectAll(this.checked)" aria-label="Select all students" disabled>
                            </th>
                            <th>LRN</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Gender</th>
                            <th>Strand</th>
                            <th>Status</th>
                            <th>Enrolled On</th>
                        </tr>
                    </thead>
                    <tbody id="vsStudentsTableBody">
                        <tr>
                            <td colspan="8" style="text-align:center; padding: 24px; color: var(--text-muted);">
                                Loading...
                            </td>
                        </tr>
                    </tbody>
                </table>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-secondary vs-unenroll-btn" id="vsUnenrollBtn" onclick="unenrollSelectedStudents()" disabled>
                    <i class="fas fa-user-minus"></i> <span id="vsUnenrollLabel">Unenroll</span>
                </button>
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
                        <input type="text" id="s_section_name" name="section_name" placeholder="e.g. Rizal" pattern="[\p{L}\p{N} \-]+" title="Letters, numbers, spaces and dashes (-) only" maxlength="255" required>
                    </div>

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
                        <input type="text" id="es_section_name" name="section_name" placeholder="e.g. Rizal" pattern="[\p{L}\p{N} \-]+" title="Letters, numbers, spaces and dashes (-) only" maxlength="255" required>
                    </div>

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
                        <input type="text" id="sub_subject_name" name="subject_name" placeholder="e.g. Research" pattern="[\p{L}\p{N} \-]+" title="Letters, numbers, spaces and dashes (-) only" maxlength="255" required>
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
                        <input type="text" id="esub_subject_name" name="subject_name" placeholder="e.g. Research" pattern="[\p{L}\p{N} \-]+" title="Letters, numbers, spaces and dashes (-) only" maxlength="255" required>
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

<script src="assests/js/courses.js"></script>

</body>
</html>