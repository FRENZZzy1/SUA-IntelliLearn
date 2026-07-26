<?php
// config.php already calls session_start(), opens $conn (MySQLi) and $pdo (PDO),
// and defines requireAdmin() / clean() / CSRF + flash helpers.
require_once __DIR__ . '/../../config/config.php';

requireAdmin();

// ================= FLASH MESSAGE =================
$flash = getFlashMessage();

$csrfToken = generateCSRFToken();

// ================= TAB / FILTER INPUTS =================
$tab          = $_GET['tab'] ?? 'pending';                // pending | approved | denied | all
if (!in_array($tab, ['pending', 'approved', 'denied', 'all'], true)) {
    $tab = 'pending';
}
$gradeFilter  = $_GET['grade']  ?? 'all';                 // all | 7..12
$courseFilter = $_GET['course'] ?? 'all';                 // all | subject_id
$strandFilter = $_GET['strand'] ?? 'all';                 // all | STEM | ABM | HUMSS | TVL
$quarterFilter    = $_GET['quarter'] ?? 'all';             // all | 1..4
$schoolYearFilter = $_GET['school_year'] ?? 'all';         // all | school_year_id
$searchQuery  = trim($_GET['q'] ?? '');

if ($quarterFilter !== 'all' && !in_array($quarterFilter, ['1', '2', '3', '4'], true)) {
    $quarterFilter = 'all';
}
if ($schoolYearFilter !== 'all' && !ctype_digit((string) $schoolYearFilter)) {
    $schoolYearFilter = 'all';
}

$hasActiveFilters = $gradeFilter !== 'all'
    || $courseFilter !== 'all'
    || $strandFilter !== 'all'
    || $quarterFilter !== 'all'
    || $schoolYearFilter !== 'all'
    || $searchQuery !== '';

// ================= BUILD MAIN QUERY =================
$where  = [];
$params = [];

if ($tab !== 'all') {
    $where[] = 'er.status = ?';
    $params[] = $tab;
}

if ($gradeFilter !== 'all' && ctype_digit($gradeFilter)) {
    $where[] = 'er.grade_level = ?';
    $params[] = (int) $gradeFilter;
}

if ($courseFilter !== 'all' && ctype_digit($courseFilter)) {
    $where[] = 'er.subject_id = ?';
    $params[] = (int) $courseFilter;
}

if ($strandFilter !== 'all') {
    $where[] = 'er.strand = ?';
    $params[] = $strandFilter;
}

if ($quarterFilter !== 'all') {
    $where[] = 'co2.quarter = ?';
    $params[] = (int) $quarterFilter;
}

if ($schoolYearFilter !== 'all') {
    $where[] = 'sec2.school_year_id = ?';
    $params[] = (int) $schoolYearFilter;
}

if ($searchQuery !== '') {
    $where[] = '(CONCAT(st.firstname, " ", st.lastname) LIKE ? OR subj.subject_name LIKE ?)';
    $like = '%' . $searchQuery . '%';
    $params[] = $like;
    $params[] = $like;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$sql = "
    SELECT
        er.request_id,
        er.student_id,
        er.grade_level,
        er.subject_id,
        er.strand,
        er.offering_id,
        er.status,
        er.submitted_at,
        er.decided_at,
        st.firstname,
        st.lastname,
        subj.subject_name,
        sec2.section_name AS matched_section_name,
        co2.quarter AS matched_quarter,
        sy2.label AS matched_school_year,
        co2.schedule_days AS matched_schedule_days,
        co2.start_time AS matched_start_time,
        co2.end_time AS matched_end_time
    FROM enrollment_requests er
    JOIN students st  ON st.student_id = er.student_id
    JOIN subjects subj ON subj.subject_id = er.subject_id
    LEFT JOIN classofferings co2 ON co2.offering_id = er.offering_id
    LEFT JOIN sections sec2      ON sec2.section_id  = co2.section_id
    LEFT JOIN schoolyears sy2    ON sy2.school_year_id = sec2.school_year_id
    {$whereSql}
    ORDER BY er.submitted_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll();

$totalShown = count($requests);

// ================= TAB COUNTS (respect grade/course/search filters, not the tab itself) =================
$countWhere  = [];
$countParams = [];

if ($gradeFilter !== 'all' && ctype_digit($gradeFilter)) {
    $countWhere[] = 'er.grade_level = ?';
    $countParams[] = (int) $gradeFilter;
}
if ($courseFilter !== 'all' && ctype_digit($courseFilter)) {
    $countWhere[] = 'er.subject_id = ?';
    $countParams[] = (int) $courseFilter;
}
if ($strandFilter !== 'all') {
    $countWhere[] = 'er.strand = ?';
    $countParams[] = $strandFilter;
}
if ($quarterFilter !== 'all') {
    $countWhere[] = 'co2.quarter = ?';
    $countParams[] = (int) $quarterFilter;
}
if ($schoolYearFilter !== 'all') {
    $countWhere[] = 'sec2.school_year_id = ?';
    $countParams[] = (int) $schoolYearFilter;
}
if ($searchQuery !== '') {
    $countWhere[] = '(CONCAT(st.firstname, " ", st.lastname) LIKE ? OR subj.subject_name LIKE ?)';
    $like = '%' . $searchQuery . '%';
    $countParams[] = $like;
    $countParams[] = $like;
}
$countWhereSql = $countWhere ? ('WHERE ' . implode(' AND ', $countWhere)) : '';

$countSql = "
    SELECT er.status, COUNT(*) AS n
    FROM enrollment_requests er
    JOIN students st   ON st.student_id = er.student_id
    JOIN subjects subj ON subj.subject_id = er.subject_id
    LEFT JOIN classofferings co2 ON co2.offering_id = er.offering_id
    LEFT JOIN sections sec2      ON sec2.section_id  = co2.section_id
    {$countWhereSql}
    GROUP BY er.status
";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($countParams);
$tabCounts = ['pending' => 0, 'approved' => 0, 'denied' => 0];
foreach ($countStmt->fetchAll() as $row) {
    $tabCounts[$row['status']] = (int) $row['n'];
}
$tabCounts['all'] = array_sum($tabCounts);

// ================= STATS (whole school, unaffected by filters) =================
$pendingCount        = (int) $pdo->query("SELECT COUNT(*) FROM enrollment_requests WHERE status = 'pending'")->fetchColumn();
$pendingNewThisWeek   = (int) $pdo->query("SELECT COUNT(*) FROM enrollment_requests WHERE status = 'pending' AND submitted_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)")->fetchColumn();

$totalEnrolled        = (int) $pdo->query("SELECT COUNT(*) FROM enrollments WHERE status = 'active'")->fetchColumn();
$enrolledNewThisWeek  = (int) $pdo->query("SELECT COUNT(*) FROM enrollments WHERE status = 'active' AND enrolled_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)")->fetchColumn();

$approvedThisWeek     = (int) $pdo->query("SELECT COUNT(*) FROM enrollment_requests WHERE status = 'approved' AND decided_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)")->fetchColumn();

$deniedCount          = (int) $pdo->query("SELECT COUNT(*) FROM enrollment_requests WHERE status = 'denied'")->fetchColumn();
$deniedThisWeek       = (int) $pdo->query("SELECT COUNT(*) FROM enrollment_requests WHERE status = 'denied' AND decided_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)")->fetchColumn();

// ================= DATA FOR FILTER DROPDOWNS =================
$allSubjects = $pdo->query("SELECT subject_id, subject_name FROM subjects ORDER BY subject_name")->fetchAll();
$allSchoolYears = $pdo->query("SELECT school_year_id, label FROM schoolyears ORDER BY start_date DESC")->fetchAll();

// ================= DATA FOR "ENROLL STUDENT" MODAL =================
$allStudents = $pdo->query("
    SELECT student_id, firstname, lastname, student_lrn
    FROM students
    ORDER BY lastname, firstname
")->fetchAll();

$allStudentsJson = json_encode(array_map(function ($s) {
    return [
        'id'    => (int) $s['student_id'],
        'label' => $s['lastname'] . ', ' . $s['firstname'] . ($s['student_lrn'] ? ' — LRN ' . $s['student_lrn'] : ''),
        'lrn'   => (string) $s['student_lrn'],
        'name'  => $s['firstname'] . ' ' . $s['lastname'],
    ];
}, $allStudents));

// Combine schedule_days + start_time/end_time into one display string,
// e.g. "M - W • 7:00 AM - 10:00 AM". Returns null if nothing is set
// (legacy/unmatched requests), same as the other matched_* fields.
function formatSchedule(?string $days, ?string $start, ?string $end): ?string
{
    $parts = [];
    if ($days) $parts[] = $days;
    if ($start && $end) {
        $parts[] = date('g:i A', strtotime($start)) . ' - ' . date('g:i A', strtotime($end));
    }
    return $parts ? implode(' &bull; ', $parts) : null;
}

$panelTitles = [
    'pending'  => 'Pending Enrollment Requests',
    'approved' => 'Approved Enrollments',
    'denied'   => 'Denied Enrollments',
    'all'      => 'All Enrollment Records',
];
$panelIcons = [
    'pending'  => 'fa-hourglass-half',
    'approved' => 'fa-check-circle',
    'denied'   => 'fa-circle-xmark',
    'all'      => 'fa-list',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Enrollment | SUA IntelliLearn Admin</title>

<!-- Fonts & Icons -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<!-- Shared Courses/Modal stylesheets (variables + list-panel/table/modal classes), then page-specific -->
<link rel="stylesheet" href="assests/css/courses.css">
<link rel="stylesheet" href="assests/css/add_course.css">
<link rel="stylesheet" href="assests/css/enrollment.css">

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
            <h1>Enrollment</h1>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-trend">▲ <?= $pendingNewThisWeek ?></div>
            <div class="stat-value"><?= $pendingCount ?></div>
            <div class="stat-label">Pending Requests</div>
        </div>
        <div class="stat-card">
            <div class="stat-trend">▲ <?= $enrolledNewThisWeek ?></div>
            <div class="stat-value"><?= $totalEnrolled ?></div>
            <div class="stat-label">Total Enrolled</div>
        </div>
        <div class="stat-card">
            <div class="stat-trend info">This Week</div>
            <div class="stat-value"><?= $approvedThisWeek ?></div>
            <div class="stat-label">Approved This Week</div>
        </div>
        <div class="stat-card">
            <div class="stat-trend danger">▼ <?= $deniedThisWeek ?></div>
            <div class="stat-value"><?= $deniedCount ?></div>
            <div class="stat-label">Denied / Withdrawn</div>
        </div>
    </div>

    <!-- Filter / Action Toolbar -->
    <div class="toolbar" style="flex-direction: row; align-items: center; justify-content: space-between; flex-wrap: wrap;">
        <div class="filter-bar">
            <a class="filter-pill <?= $tab === 'pending' ? 'active' : '' ?>"
               href="?<?= http_build_query(array_filter(['tab' => 'pending', 'grade' => $gradeFilter, 'course' => $courseFilter, 'strand' => $strandFilter, 'quarter' => $quarterFilter, 'school_year' => $schoolYearFilter, 'q' => $searchQuery])) ?>">Pending (<?= $tabCounts['pending'] ?>)</a>
            <a class="filter-pill <?= $tab === 'approved' ? 'active' : '' ?>"
               href="?<?= http_build_query(array_filter(['tab' => 'approved', 'grade' => $gradeFilter, 'course' => $courseFilter, 'strand' => $strandFilter, 'quarter' => $quarterFilter, 'school_year' => $schoolYearFilter, 'q' => $searchQuery])) ?>">Approved</a>
            <a class="filter-pill <?= $tab === 'denied' ? 'active' : '' ?>"
               href="?<?= http_build_query(array_filter(['tab' => 'denied', 'grade' => $gradeFilter, 'course' => $courseFilter, 'strand' => $strandFilter, 'quarter' => $quarterFilter, 'school_year' => $schoolYearFilter, 'q' => $searchQuery])) ?>">Denied</a>
            <a class="filter-pill <?= $tab === 'all' ? 'active' : '' ?>"
               href="?<?= http_build_query(array_filter(['tab' => 'all', 'grade' => $gradeFilter, 'course' => $courseFilter, 'strand' => $strandFilter, 'quarter' => $quarterFilter, 'school_year' => $schoolYearFilter, 'q' => $searchQuery])) ?>">All Records</a>

            <form method="get" action="enrollment.php" style="display:contents">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
                <input type="hidden" name="q" value="<?= htmlspecialchars($searchQuery) ?>">

                <select class="select-filter" name="grade" onchange="this.form.submit()">
                    <option value="all" <?= $gradeFilter === 'all' ? 'selected' : '' ?>>All Grade Levels</option>
                    <?php foreach ([7, 8, 9, 10, 11, 12] as $g): ?>
                        <option value="<?= $g ?>" <?= $gradeFilter == $g ? 'selected' : '' ?>>Grade <?= $g ?></option>
                    <?php endforeach; ?>
                </select>

                <select class="select-filter" name="course" onchange="this.form.submit()">
                    <option value="all" <?= $courseFilter === 'all' ? 'selected' : '' ?>>All Courses</option>
                    <?php foreach ($allSubjects as $s): ?>
                        <option value="<?= (int) $s['subject_id'] ?>" <?= $courseFilter == $s['subject_id'] ? 'selected' : '' ?>><?= clean($s['subject_name']) ?></option>
                    <?php endforeach; ?>
                </select>

                <select class="select-filter" name="strand" onchange="this.form.submit()">
                    <option value="all" <?= $strandFilter === 'all' ? 'selected' : '' ?>>All Strands</option>
                    <?php foreach (['GAS', 'ABM', 'HUMSS', 'TVL'] as $s): ?>
                        <option value="<?= $s ?>" <?= $strandFilter === $s ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>

                <select class="select-filter" name="quarter" onchange="this.form.submit()">
                    <option value="all" <?= $quarterFilter === 'all' ? 'selected' : '' ?>>All Quarters</option>
                    <?php foreach ([1, 2, 3, 4] as $q): ?>
                        <option value="<?= $q ?>" <?= $quarterFilter == $q ? 'selected' : '' ?>>Quarter <?= $q ?></option>
                    <?php endforeach; ?>
                </select>

                <select class="select-filter" name="school_year" onchange="this.form.submit()">
                    <option value="all" <?= $schoolYearFilter === 'all' ? 'selected' : '' ?>>All School Years</option>
                    <?php foreach ($allSchoolYears as $sy): ?>
                        <option value="<?= (int) $sy['school_year_id'] ?>" <?= $schoolYearFilter == $sy['school_year_id'] ? 'selected' : '' ?>><?= clean($sy['label']) ?></option>
                    <?php endforeach; ?>
                </select>

                <?php if ($hasActiveFilters): ?>
                <a class="btn-clear-filter" href="?tab=<?= urlencode($tab) ?>" title="Clear all filters"><i class="fas fa-xmark"></i> Clear Filters</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="action-bar" style="justify-content: flex-end; border-top: none; padding-top: 0;">
            <button class="btn-primary" onclick="openEnrollStudentModal()"><i class="fas fa-user-plus"></i> Enroll Student</button>
        </div>
    </div>

    <!-- Enrollment List -->
    <div class="list-panel">
        <div class="list-panel-header">
            <h2 class="panel-title"><i class="fas <?= $panelIcons[$tab] ?>"></i> <?= $panelTitles[$tab] ?></h2>
            <div class="header-right">
                <span class="count-note">Showing <?= $totalShown ?> <?= $tab === 'pending' ? 'pending requests' : 'record' . ($totalShown === 1 ? '' : 's') ?></span>
                <?php if ($tab === 'pending' && $totalShown > 0): ?>
                <button class="btn-secondary" onclick="approveAllVisible()"><i class="fas fa-check-double"></i> Approve All</button>
                <?php endif; ?>
            </div>
        </div>

        <table class="course-table">
            <thead>
                <tr>
                    <th class="checkbox-col"><input type="checkbox" class="select-all-check" id="selectAllCheckbox" title="Select all"></th>
                    <th>Student Name</th>
                    <th>Course Requested</th>
                    <th>Section</th>
                    <th>Grade Level/Strand</th>
                    <th>Quarter</th>
                    <th>School Year</th>
                    <th>Schedule</th>
                    <th>Date Submitted</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="enrollmentTableBody">
                <?php if (empty($requests)): ?>
                <tr>
                    <td colspan="11" style="text-align:center; padding: 40px; color: var(--text-muted);">
                        No <?= $tab === 'all' ? 'enrollment records' : $tab . ' requests' ?> found.
                    </td>
                </tr>
                <?php endif; ?>

                <?php foreach ($requests as $r):
                    $studentName = trim($r['firstname'] . ' ' . $r['lastname']);
                    $courseRequested = $r['strand']
                        ? $r['subject_name'] . ' (' . $r['strand'] . ')'
                        : $r['subject_name'];
                    $dateSubmitted = date('F j, Y', strtotime($r['submitted_at']));
                ?>
                <tr data-request-id="<?= (int) $r['request_id'] ?>" data-status="<?= clean($r['status']) ?>">
                    <td class="checkbox-col">
                        <?php if ($r['status'] === 'pending'): ?>
                        <input type="checkbox" class="row-check" data-request-id="<?= (int) $r['request_id'] ?>">
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($studentName) ?></td>
                    <td>
                        <div class="course-requested">
                            <span><?= htmlspecialchars($courseRequested) ?></span>
                        </div>
                    </td>
                    <td><?= $r['matched_section_name'] ? htmlspecialchars($r['matched_section_name']) : '<span class="action-note">&mdash;</span>' ?></td>
                    <td>Grade <?= (int) $r['grade_level'] ?><?= $r['strand'] ? ' &middot; ' . htmlspecialchars($r['strand']) : '' ?></td>
                    <td><?= $r['matched_quarter'] ? (int) $r['matched_quarter'] : '<span class="action-note">&mdash;</span>' ?></td>
                    <td><?= $r['matched_school_year'] ? htmlspecialchars($r['matched_school_year']) : '<span class="action-note">&mdash;</span>' ?></td>
                    <?php $scheduleDisplay = formatSchedule($r['matched_schedule_days'], $r['matched_start_time'], $r['matched_end_time']); ?>
                    <td><?= $scheduleDisplay ? $scheduleDisplay : '<span class="action-note">&mdash;</span>' ?></td>
                    <td><?= htmlspecialchars($dateSubmitted) ?></td>
                    <td>
                        <span class="status-dot-badge <?= clean($r['status']) ?>">
                            <span class="dot"></span>
                            <?= ucfirst($r['status']) ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($r['status'] === 'pending'): ?>
                        <div class="enroll-actions">
                            <button class="btn-approve" onclick="approveRequest(<?= (int) $r['request_id'] ?>, this)"><i class="fas fa-check"></i> Approve</button>
                            <button class="btn-deny" onclick="denyRequest(<?= (int) $r['request_id'] ?>, this)">Deny</button>
                        </div>
                        <?php elseif ($r['status'] === 'denied'): ?>
                        <a class="link-reopen" href="javascript:void(0)" onclick="reopenRequest(<?= (int) $r['request_id'] ?>)">Reopen</a>
                        <?php else: ?>
                        <span class="action-note">&mdash;</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="list-panel-footer">
            <span class="count-note">Showing <?= $totalShown ?> of <?= $tabCounts['all'] ?> records</span>
            <button class="btn-secondary" onclick="exportCSV()"><i class="fas fa-file-export"></i> Export CSV</button>
        </div>
    </div>
    <!-- /Enrollment List -->

    <!-- Enroll Student Modal (creates a new pending request) -->
    <div class="modal-overlay" id="enrollStudentOverlay" onclick="if (event.target === this) closeEnrollStudentModal()">
        <div class="modal-box">
            <div class="modal-header">
                <h2>Enroll Student</h2>
                <button type="button" class="modal-close" onclick="closeEnrollStudentModal()" aria-label="Close">&times;</button>
            </div>

            <div class="modal-errors" id="enrollStudentErrors" hidden></div>

            <form id="enrollStudentForm">
                <input type="hidden" name="csrf" value="<?= clean($csrfToken) ?>">

                <div class="modal-body">
                    <div class="form-row">
                        <label for="es_student_search">Student</label>
                        <div class="searchable-select" id="es_student_picker">
                            <input type="text" id="es_student_search" class="searchable-select-input"
                                   placeholder="Search by name or LRN..." autocomplete="off" required>
                            <input type="hidden" id="es_student_id" name="student_id">
                            <div class="searchable-select-dropdown" id="es_student_dropdown"></div>
                        </div>
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

                        <div class="form-row">
                            <label for="es_quarter">Quarter</label>
                            <select id="es_quarter" name="quarter" required>
                                <option value="">Select</option>
                                <?php foreach ([1, 2, 3, 4] as $q): ?>
                                    <option value="<?= $q ?>">Quarter <?= $q ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <label for="es_section_id">Section</label>
                        <select id="es_section_id" name="section_id" required disabled>
                            <option value="">Select grade level, strand, and quarter first</option>
                        </select>
                        <span class="field-note" id="es_section_note">Pick a section to see the subjects offered in it.</span>
                    </div>

                    <div class="form-row">
                        <div class="subject-list-label-row">
                            <label>Course / Subject Requested</label>
                            <div class="subject-list-bulk-actions">
                                <button type="button" class="link-btn" id="es_subject_check_all">Check all</button>
                                <span class="subject-list-bulk-sep">&middot;</span>
                                <button type="button" class="link-btn" id="es_subject_uncheck_all">Uncheck all</button>
                            </div>
                        </div>
                        <div class="subject-checkbox-list" id="es_subject_list">
                            <div class="subject-checkbox-empty">Select grade level, strand, quarter, and section first</div>
                        </div>
                        <span class="field-note" id="es_subject_note">Check every subject to request for this student in this section. Creates one pending request per subject checked.</span>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeEnrollStudentModal()">Cancel</button>
                    <button type="submit" class="btn-primary" id="enrollStudentSubmitBtn"><i class="fas fa-plus"></i> Submit Request</button>
                </div>
            </form>
        </div>
    </div>

</div>

<script>
    const CSRF_TOKEN = <?= json_encode($csrfToken) ?>;
    const STUDENTS_DATA = <?= $allStudentsJson ?>;

    // ---- Sidebar collapse/expand (shared with sidebar module) ----
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('collapsed');
    }

    // ---- Sidebar nav active state (shared with sidebar module) ----
    function setActive(el) {
        document.querySelectorAll('.nav-item').forEach(item => item.classList.remove('active'));
        el.classList.add('active');
    }

    // ---- Select-all checkbox ----
    document.getElementById('selectAllCheckbox')?.addEventListener('change', function () {
        document.querySelectorAll('.row-check').forEach(cb => cb.checked = this.checked);
    });

    // ---- Enroll Student modal ----
    function openEnrollStudentModal() {
        resetEnrollStudentForm();
        document.getElementById('enrollStudentOverlay').classList.add('open');
        document.getElementById('enrollStudentErrors').hidden = true;
    }

    function closeEnrollStudentModal() {
        document.getElementById('enrollStudentOverlay').classList.remove('open');
    }

    function resetEnrollStudentForm() {
        document.getElementById('enrollStudentForm').reset();
        studentSearchInput.value = '';
        studentHiddenInput.value = '';
        closeStudentDropdown();
        resetSectionSelect('Select grade level, strand, and quarter first');
        resetSubjectList('Select grade level, strand, quarter, and section first');
    }

    // ---- Searchable student picker ----
    const studentPicker      = document.getElementById('es_student_picker');
    const studentSearchInput = document.getElementById('es_student_search');
    const studentHiddenInput = document.getElementById('es_student_id');
    const studentDropdown    = document.getElementById('es_student_dropdown');

    function renderStudentOptions(filter) {
        const term = filter.trim().toLowerCase();
        const matches = term === ''
            ? STUDENTS_DATA
            : STUDENTS_DATA.filter(s =>
                s.name.toLowerCase().includes(term) ||
                s.lrn.toLowerCase().includes(term)
              );

        if (matches.length === 0) {
            studentDropdown.innerHTML = '<div class="searchable-select-empty">No students match your search.</div>';
        } else {
            studentDropdown.innerHTML = matches.slice(0, 50).map(s =>
                `<div class="searchable-select-option" data-id="${s.id}" data-label="${s.label.replace(/"/g, '&quot;')}">${s.label}</div>`
            ).join('');
        }
        studentDropdown.classList.add('open');
    }

    function closeStudentDropdown() {
        studentDropdown.classList.remove('open');
    }

    studentSearchInput.addEventListener('focus', () => renderStudentOptions(studentSearchInput.value));
    studentSearchInput.addEventListener('input', () => {
        studentHiddenInput.value = '';
        renderStudentOptions(studentSearchInput.value);
    });

    studentDropdown.addEventListener('click', function (e) {
        const opt = e.target.closest('.searchable-select-option');
        if (!opt) return;
        studentHiddenInput.value = opt.dataset.id;
        studentSearchInput.value = opt.dataset.label;
        closeStudentDropdown();
    });

    document.addEventListener('click', function (e) {
        if (!studentPicker.contains(e.target)) closeStudentDropdown();
    });

    // ---- Section dropdown, driven by grade level + strand + quarter ----
    const gradeLevelSelect = document.getElementById('es_grade_level');
    const strandSelect     = document.getElementById('es_strand');
    const quarterSelect    = document.getElementById('es_quarter');
    const sectionSelect    = document.getElementById('es_section_id');
    const sectionNote      = document.getElementById('es_section_note');
    const subjectList      = document.getElementById('es_subject_list');
    const subjectNote      = document.getElementById('es_subject_note');

    const SECTION_DEFAULT_NOTE = 'Pick a section to see the subjects offered in it.';
    const SUBJECT_DEFAULT_NOTE = 'Check every subject to request for this student in this section. Creates one pending request per subject checked.';

    function resetSectionSelect(message) {
        sectionSelect.innerHTML = `<option value="">${message}</option>`;
        sectionSelect.disabled = true;
        sectionNote.textContent = SECTION_DEFAULT_NOTE;
    }

    function resetSubjectList(message) {
        subjectList.innerHTML = `<div class="subject-checkbox-empty">${message}</div>`;
        subjectNote.textContent = SUBJECT_DEFAULT_NOTE;
    }

    function refreshSectionOptions() {
        const gradeLevel = gradeLevelSelect.value;
        const strand     = strandSelect.value;
        const quarter    = quarterSelect.value;

        resetSubjectList('Select grade level, strand, quarter, and section first');

        if (!gradeLevel || !quarter) {
            resetSectionSelect('Select grade level, strand, and quarter first');
            return;
        }

        resetSectionSelect('Loading sections...');

        const params = new URLSearchParams({ grade_level: gradeLevel, quarter: quarter, strand: strand });

        fetch('get_offering_sections.php?' + params.toString(), {
            headers: { 'Accept': 'application/json' }
        })
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                resetSectionSelect('Unable to load sections');
                sectionNote.textContent = (data.errors || ['Something went wrong.']).join(' ');
                return;
            }
            if (data.options.length === 0) {
                sectionSelect.innerHTML = '<option value="">No open sections for this grade/strand/quarter</option>';
                sectionSelect.disabled = true;
                sectionNote.textContent = 'Create a class offering for this combination in Courses & Subjects first.';
                return;
            }
            sectionSelect.innerHTML = '<option value="">Select a section</option>' +
                data.options.map(o => `<option value="${o.section_id}">${o.label}</option>`).join('');
            sectionSelect.disabled = false;
            sectionNote.textContent = SECTION_DEFAULT_NOTE;
        })
        .catch(() => {
            resetSectionSelect('Unable to load sections');
            sectionNote.textContent = 'Something went wrong loading sections. Please try again.';
        });
    }

    // ---- Subject checkbox list, driven by the chosen section ----
    function refreshSubjectOptions() {
        const gradeLevel = gradeLevelSelect.value;
        const strand     = strandSelect.value;
        const quarter    = quarterSelect.value;
        const sectionId  = sectionSelect.value;

        if (!gradeLevel || !quarter || !sectionId) {
            resetSubjectList('Select grade level, strand, quarter, and section first');
            return;
        }

        resetSubjectList('Loading subjects...');

        const params = new URLSearchParams({
            section_id: sectionId, grade_level: gradeLevel, quarter: quarter, strand: strand
        });

        fetch('get_section_offerings.php?' + params.toString(), {
            headers: { 'Accept': 'application/json' }
        })
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                resetSubjectList((data.errors || ['Unable to load subjects.']).join(' '));
                return;
            }
            if (data.options.length === 0) {
                resetSubjectList('No class offerings exist for this section and quarter yet.');
                return;
            }
            subjectList.innerHTML = data.options.map(o => `
                <label class="subject-checkbox-item${o.full ? ' is-full' : ''}">
                    <input type="checkbox" name="subject_offering" value="${o.offering_id}"
                           data-subject-id="${o.subject_id}" ${o.full ? 'disabled' : ''}>
                    <span>${o.subject_name}</span>
                    <small>${o.full ? 'Full' : o.seats_left + ' seat' + (o.seats_left === 1 ? '' : 's') + ' left'}</small>
                </label>
            `).join('');
            subjectNote.textContent = SUBJECT_DEFAULT_NOTE;
        })
        .catch(() => {
            resetSubjectList('Something went wrong loading subjects. Please try again.');
        });
    }

    [gradeLevelSelect, strandSelect, quarterSelect].forEach(el => el.addEventListener('change', refreshSectionOptions));
    sectionSelect.addEventListener('change', refreshSubjectOptions);

    // ---- Check all / Uncheck all for the subject checkbox list ----
    document.getElementById('es_subject_check_all').addEventListener('click', function () {
        subjectList.querySelectorAll('input[name="subject_offering"]:not(:disabled)').forEach(cb => cb.checked = true);
    });

    document.getElementById('es_subject_uncheck_all').addEventListener('click', function () {
        subjectList.querySelectorAll('input[name="subject_offering"]').forEach(cb => cb.checked = false);
    });

    document.getElementById('enrollStudentForm').addEventListener('submit', function (e) {
        e.preventDefault();

        const form = this;
        const submitBtn = document.getElementById('enrollStudentSubmitBtn');
        const errorBox = document.getElementById('enrollStudentErrors');
        errorBox.hidden = true;

        if (!studentHiddenInput.value) {
            errorBox.innerHTML = '<div>Please select a student from the list.</div>';
            errorBox.hidden = false;
            return;
        }
        if (!sectionSelect.value) {
            errorBox.innerHTML = '<div>Please select a section.</div>';
            errorBox.hidden = false;
            return;
        }

        const checkedSubjects = Array.from(subjectList.querySelectorAll('input[name="subject_offering"]:checked'));

        if (checkedSubjects.length === 0) {
            errorBox.innerHTML = '<div>Please check at least one subject to request.</div>';
            errorBox.hidden = false;
            return;
        }

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';

        const gradeLevel = gradeLevelSelect.value;
        const strand     = strandSelect.value;
        const studentId  = studentHiddenInput.value;
        const csrf       = form.querySelector('input[name="csrf"]').value;

        // One subject checked = one pending request; submit each in turn so a
        // failure on one subject (e.g. a duplicate) doesn't stop the others.
        const submitOne = (checkbox) => {
            const fd = new FormData();
            fd.append('csrf', csrf);
            fd.append('student_id', studentId);
            fd.append('grade_level', gradeLevel);
            fd.append('strand', strand);
            fd.append('subject_id', checkbox.dataset.subjectId);
            fd.append('offering_id', checkbox.value);

            const subjectLabel = checkbox.closest('.subject-checkbox-item').querySelector('span').textContent;

            return fetch('add_enrollment_request.php', {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body: fd
            })
            .then(res => res.json())
            .then(data => ({ subjectLabel, success: !!data.success, errors: data.errors || ['Something went wrong.'] }))
            .catch(() => ({ subjectLabel, success: false, errors: ['Something went wrong. Please try again.'] }));
        };

        (async () => {
            const results = [];
            for (const checkbox of checkedSubjects) {
                results.push(await submitOne(checkbox));
            }

            const failures = results.filter(r => !r.success);

            if (failures.length === 0) {
                window.location.href = 'enrollment.php?tab=pending';
                return;
            }

            errorBox.innerHTML = failures
                .map(f => `<div><strong>${f.subjectLabel}:</strong> ${f.errors.join(' ')}</div>`)
                .join('');
            errorBox.hidden = false;
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-plus"></i> Submit Request';

            if (failures.length < results.length) {
                // Some subjects succeeded — refresh the list so the ones that
                // went through don't get resubmitted if the admin tries again.
                refreshSubjectOptions();
            }
        })();
    });

    // ---- Approve / Deny / Reopen ----
    function approveRequest(id, btnEl) {
        if (btnEl) { btnEl.disabled = true; btnEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Approving...'; }

        const fd = new FormData();
        fd.append('csrf', CSRF_TOKEN);
        fd.append('request_id', id);

        fetch('approve_enrollment.php', {
            method: 'POST',
            headers: { 'Accept': 'application/json' },
            body: fd
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert((data.errors || ['Something went wrong.']).join('\n'));
                if (btnEl) { btnEl.disabled = false; btnEl.innerHTML = '<i class="fas fa-check"></i> Approve'; }
            }
            return data;
        })
        .catch(() => {
            alert('Something went wrong. Please try again.');
            if (btnEl) { btnEl.disabled = false; btnEl.innerHTML = '<i class="fas fa-check"></i> Approve'; }
            return { success: false };
        });
    }

    function denyRequest(id, btnEl) {
        if (!confirm('Deny this enrollment request?')) return;
        if (btnEl) { btnEl.disabled = true; btnEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Denying...'; }

        const fd = new FormData();
        fd.append('csrf', CSRF_TOKEN);
        fd.append('request_id', id);

        fetch('deny_enrollment.php', {
            method: 'POST',
            headers: { 'Accept': 'application/json' },
            body: fd
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert((data.errors || ['Something went wrong.']).join('\n'));
                if (btnEl) { btnEl.disabled = false; btnEl.innerHTML = 'Deny'; }
            }
        })
        .catch(() => {
            alert('Something went wrong. Please try again.');
            if (btnEl) { btnEl.disabled = false; btnEl.innerHTML = 'Deny'; }
        });
    }

    function reopenRequest(id) {
        if (!confirm('Reopen this request and move it back to Pending?')) return;
        const fd = new FormData();
        fd.append('csrf', CSRF_TOKEN);
        fd.append('request_id', id);

        fetch('reopen_enrollment.php', {
            method: 'POST',
            headers: { 'Accept': 'application/json' },
            body: fd
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) location.reload();
            else alert((data.errors || ['Something went wrong.']).join('\n'));
        })
        .catch(() => alert('Something went wrong. Please try again.'));
    }

    // ---- Approve All (checked rows, or every visible pending row if none checked) ----
    function approveAllVisible() {
        const checked = Array.from(document.querySelectorAll('.row-check:checked')).map(cb => cb.dataset.requestId);
        const allPending = Array.from(document.querySelectorAll('tr[data-status="pending"]')).map(tr => tr.dataset.requestId);
        const ids = checked.length > 0 ? checked : allPending;

        if (ids.length === 0) {
            alert('No pending requests to approve.');
            return;
        }
        if (!confirm(`Approve ${ids.length} enrollment request(s)?`)) return;

        approveSequential(ids, 0, { approved: 0, failed: 0 });
    }

    function approveSequential(ids, idx, summary) {
        if (idx >= ids.length) {
            let msg = `${summary.approved} approved.`;
            if (summary.failed) msg += ` ${summary.failed} failed.`;
            alert(msg);
            location.reload();
            return;
        }

        const fd = new FormData();
        fd.append('csrf', CSRF_TOKEN);
        fd.append('request_id', ids[idx]);

        fetch('approve_enrollment.php', {
            method: 'POST',
            headers: { 'Accept': 'application/json' },
            body: fd
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) summary.approved++;
            else summary.failed++;
            approveSequential(ids, idx + 1, summary);
        })
        .catch(() => {
            summary.failed++;
            approveSequential(ids, idx + 1, summary);
        });
    }

    // ---- Export CSV (client-side, from the currently visible table) ----
    function exportCSV() {
        const rows = [['Student Name', 'Course Requested', 'Section', 'Grade Level', 'Quarter', 'School Year', 'Schedule', 'Date Submitted', 'Status']];
        document.querySelectorAll('#enrollmentTableBody tr[data-request-id]').forEach(tr => {
            const cells = tr.querySelectorAll('td');
            rows.push([
                cells[1].innerText.trim().split('\n')[0],
                cells[2].innerText.trim(),
                cells[3].innerText.trim(),
                cells[4].innerText.trim(),
                cells[5].innerText.trim(),
                cells[6].innerText.trim(),
                cells[7].innerText.trim(),
                cells[8].innerText.trim(),
                cells[9].innerText.trim(),
            ]);
        });

        const csv = rows.map(r => r.map(v => '"' + String(v).replace(/"/g, '""') + '"').join(',')).join('\n');
        const blob = new Blob([csv], { type: 'text/csv' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'enrollment_' + new Date().toISOString().slice(0, 10) + '.csv';
        a.click();
        URL.revokeObjectURL(url);
    }

    // ---- Escape closes the Enroll Student modal ----
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        closeEnrollStudentModal();
    });
</script>

</body>
</html>