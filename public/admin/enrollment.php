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
$searchQuery  = trim($_GET['q'] ?? '');

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
        sec2.section_name AS matched_section_name
    FROM enrollment_requests er
    JOIN students st  ON st.student_id = er.student_id
    JOIN subjects subj ON subj.subject_id = er.subject_id
    LEFT JOIN classofferings co2 ON co2.offering_id = er.offering_id
    LEFT JOIN sections sec2      ON sec2.section_id  = co2.section_id
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

// ================= DATA FOR "ENROLL STUDENT" MODAL =================
$allStudents = $pdo->query("
    SELECT student_id, firstname, lastname, student_lrn
    FROM students
    ORDER BY lastname, firstname
")->fetchAll();

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

<div class="main-content" id="mainContent">

    <?php if ($flash): ?>
    <div class="flash-message flash-<?= clean($flash['type']) ?>">
        <?= clean($flash['message']) ?>
    </div>
    <?php endif; ?>

    <!-- Page Header -->
    <div class="page-header">
        <h1>Enrollment</h1>
        <form class="header-actions" method="get" action="enrollment.php">
            <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
            <input type="hidden" name="grade" value="<?= htmlspecialchars($gradeFilter) ?>">
            <input type="hidden" name="course" value="<?= htmlspecialchars($courseFilter) ?>">
            <div class="header-search">
                <i class="fas fa-search"></i>
                <input type="text" name="q" value="<?= htmlspecialchars($searchQuery) ?>" placeholder="Search students, courses..." onchange="this.form.submit()">
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
    <div class="toolbar-row">
        <div class="toolbar-left">
            <a class="filter-pill <?= $tab === 'pending' ? 'active' : '' ?>"
               href="?<?= http_build_query(array_filter(['tab' => 'pending', 'grade' => $gradeFilter, 'course' => $courseFilter, 'q' => $searchQuery])) ?>">Pending (<?= $tabCounts['pending'] ?>)</a>
            <a class="filter-pill <?= $tab === 'approved' ? 'active' : '' ?>"
               href="?<?= http_build_query(array_filter(['tab' => 'approved', 'grade' => $gradeFilter, 'course' => $courseFilter, 'q' => $searchQuery])) ?>">Approved</a>
            <a class="filter-pill <?= $tab === 'denied' ? 'active' : '' ?>"
               href="?<?= http_build_query(array_filter(['tab' => 'denied', 'grade' => $gradeFilter, 'course' => $courseFilter, 'q' => $searchQuery])) ?>">Denied</a>
            <a class="filter-pill <?= $tab === 'all' ? 'active' : '' ?>"
               href="?<?= http_build_query(array_filter(['tab' => 'all', 'grade' => $gradeFilter, 'course' => $courseFilter, 'q' => $searchQuery])) ?>">All Records</a>
        </div>
        <div class="toolbar-right">
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
            </form>
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
                    <th>Grade Level</th>
                    <th>Course Requested</th>
                    <th>Date Submitted</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="enrollmentTableBody">
                <?php if (empty($requests)): ?>
                <tr>
                    <td colspan="7" style="text-align:center; padding: 40px; color: var(--text-muted);">
                        No <?= $tab === 'all' ? 'enrollment records' : $tab . ' requests' ?> found.
                    </td>
                </tr>
                <?php endif; ?>

                <?php foreach ($requests as $r):
                    $studentName = trim($r['firstname'] . ' ' . $r['lastname']);
                    $courseRequested = $r['strand']
                        ? $r['subject_name'] . ' (' . $r['strand'] . ')'
                        : $r['subject_name'] . ' ' . $r['grade_level'];
                    $dateSubmitted = date('F j, Y', strtotime($r['submitted_at']));
                ?>
                <tr data-request-id="<?= (int) $r['request_id'] ?>" data-status="<?= clean($r['status']) ?>">
                    <td class="checkbox-col">
                        <?php if ($r['status'] === 'pending'): ?>
                        <input type="checkbox" class="row-check" data-request-id="<?= (int) $r['request_id'] ?>">
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($studentName) ?></td>
                    <td>Grade <?= (int) $r['grade_level'] ?></td>
                    <td>
                        <div class="course-requested">
                            <span><?= htmlspecialchars($courseRequested) ?></span>
                            <?php if ($r['status'] === 'approved' && $r['matched_section_name']): ?>
                            <span class="matched-note">Enrolled &middot; <?= htmlspecialchars($r['matched_section_name']) ?></span>
                            <?php endif; ?>
                        </div>
                    </td>
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
                        <label for="es_student_id">Student</label>
                        <select id="es_student_id" name="student_id" required>
                            <option value="">Select a student</option>
                            <?php foreach ($allStudents as $s): ?>
                                <option value="<?= (int) $s['student_id'] ?>">
                                    <?= clean($s['lastname'] . ', ' . $s['firstname']) ?><?= $s['student_lrn'] ? ' — LRN ' . clean($s['student_lrn']) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
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
                        <label for="es_subject_id">Course / Subject Requested</label>
                        <select id="es_subject_id" name="subject_id" required>
                            <option value="">Select a subject</option>
                            <?php foreach ($allSubjects as $s): ?>
                                <option value="<?= (int) $s['subject_id'] ?>"><?= clean($s['subject_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="field-note">Creates a pending request — approve it to enroll the student into a matching class.</span>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeEnrollStudentModal()">Cancel</button>
                    <button type="submit" class="btn-primary" id="enrollStudentSubmitBtn"><i class="fas fa-plus"></i> Submit Request</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Section Picker Modal (shown only when a request matches more than one open section) -->
    <div class="modal-overlay" id="pickSectionOverlay" onclick="if (event.target === this) closePickSectionModal()">
        <div class="modal-box">
            <div class="modal-header">
                <h2>Choose a Section</h2>
                <button type="button" class="modal-close" onclick="closePickSectionModal()" aria-label="Close">&times;</button>
            </div>

            <div class="modal-body">
                <p class="pick-section-hint">More than one class matches this request. Pick which one to enroll the student into.</p>
                <div class="form-row">
                    <label for="pickSectionSelect">Section</label>
                    <select id="pickSectionSelect"></select>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closePickSectionModal()">Cancel</button>
                <button type="button" class="btn-primary" id="pickSectionConfirmBtn" onclick="confirmPickedSection()"><i class="fas fa-check"></i> Approve</button>
            </div>
        </div>
    </div>

</div>

<script>
    const CSRF_TOKEN = <?= json_encode($csrfToken) ?>;

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
        document.getElementById('enrollStudentOverlay').classList.add('open');
        document.getElementById('enrollStudentErrors').hidden = true;
    }

    function closeEnrollStudentModal() {
        document.getElementById('enrollStudentOverlay').classList.remove('open');
    }

    document.getElementById('enrollStudentForm').addEventListener('submit', function (e) {
        e.preventDefault();
        const submitBtn = document.getElementById('enrollStudentSubmitBtn');
        const errorBox = document.getElementById('enrollStudentErrors');
        errorBox.hidden = true;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';

        fetch('add_enrollment_request.php', {
            method: 'POST',
            headers: { 'Accept': 'application/json' },
            body: new FormData(this)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                window.location.href = 'enrollment.php?tab=pending';
            } else {
                errorBox.innerHTML = data.errors.map(err => '<div>' + err + '</div>').join('');
                errorBox.hidden = false;
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-plus"></i> Submit Request';
            }
        })
        .catch(() => {
            errorBox.innerHTML = '<div>Something went wrong. Please try again.</div>';
            errorBox.hidden = false;
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-plus"></i> Submit Request';
        });
    });

    // ---- Approve / Deny / Reopen ----
    let pendingPickRequestId = null;

    function approveRequest(id, btnEl) {
        if (btnEl) { btnEl.disabled = true; btnEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Approving...'; }
        doApprove(id, null, btnEl);
    }

    function doApprove(id, offeringId, btnEl) {
        const fd = new FormData();
        fd.append('csrf', CSRF_TOKEN);
        fd.append('request_id', id);
        if (offeringId) fd.append('offering_id', offeringId);

        return fetch('approve_enrollment.php', {
            method: 'POST',
            headers: { 'Accept': 'application/json' },
            body: fd
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else if (data.needs_selection) {
                openPickSectionModal(id, data.options);
                if (btnEl) { btnEl.disabled = false; btnEl.innerHTML = '<i class="fas fa-check"></i> Approve'; }
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

    // ---- Section picker (ambiguous approve match) ----
    function openPickSectionModal(requestId, options) {
        pendingPickRequestId = requestId;
        const select = document.getElementById('pickSectionSelect');
        select.innerHTML = options.map(o => `<option value="${o.offering_id}">${o.label}</option>`).join('');
        document.getElementById('pickSectionOverlay').classList.add('open');
    }

    function closePickSectionModal() {
        document.getElementById('pickSectionOverlay').classList.remove('open');
        pendingPickRequestId = null;
    }

    function confirmPickedSection() {
        if (!pendingPickRequestId) return;
        const offeringId = document.getElementById('pickSectionSelect').value;
        const btn = document.getElementById('pickSectionConfirmBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Approving...';

        doApprove(pendingPickRequestId, offeringId, null).then(data => {
            if (!data.success) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check"></i> Approve';
            }
        });
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
        if (!confirm(`Approve ${ids.length} enrollment request(s)? Requests with more than one matching section will be skipped for manual review.`)) return;

        approveSequential(ids, 0, { approved: 0, needsSelection: 0, failed: 0 });
    }

    function approveSequential(ids, idx, summary) {
        if (idx >= ids.length) {
            let msg = `${summary.approved} approved.`;
            if (summary.needsSelection) msg += ` ${summary.needsSelection} need manual section selection.`;
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
            else if (data.needs_selection) summary.needsSelection++;
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
        const rows = [['Student Name', 'Grade Level', 'Course Requested', 'Date Submitted', 'Status']];
        document.querySelectorAll('#enrollmentTableBody tr[data-request-id]').forEach(tr => {
            const cells = tr.querySelectorAll('td');
            rows.push([
                cells[1].innerText.trim(),
                cells[2].innerText.trim(),
                cells[3].innerText.trim().split('\n')[0],
                cells[4].innerText.trim(),
                cells[5].innerText.trim(),
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

    // ---- Escape closes whichever modal is open ----
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        closeEnrollStudentModal();
        closePickSectionModal();
    });
</script>

</body>
</html>
