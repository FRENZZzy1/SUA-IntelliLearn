<?php
// __DIR__ makes this independent of which script is the actual request
// entry point. Path: public/teacher/assets/api/ -> up 4 -> project root -> config/config.php
require_once __DIR__ . '/../../../../config/config.php';

// ---- Access control -------------------------------------------------
if (!isLoggedIn() || ($_SESSION['role'] ?? '') !== 'teacher') {
    header('Location: ../../login.php');
    exit();
}

$userId = (int) $_SESSION['user_id'];

// ---- Resolve the logged-in teacher row -------------------------------
$stmt = $pdo->prepare("SELECT teacher_id, firstname, lastname FROM teachers WHERE user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$teacher = $stmt->fetch();

if (!$teacher) {
    die('Teacher record not found for this account.');
}
$teacherId = (int) $teacher['teacher_id'];

// ---- Validate subject_id / section_id from the query string --------------
$subjectId = filter_input(INPUT_GET, 'subject_id', FILTER_VALIDATE_INT);
$sectionId = filter_input(INPUT_GET, 'section_id', FILTER_VALIDATE_INT);
if (!$subjectId || !$sectionId) {
    header('Location: courses.php');
    exit();
}

// ---- Current school year ---------------------------------------------
$stmt = $pdo->query("SELECT school_year_id, label FROM schoolyears WHERE is_current = 1 LIMIT 1");
$schoolYear      = $stmt->fetch();
$schoolYearId    = $schoolYear['school_year_id'] ?? null;
$schoolYearLabel = $schoolYear['label'] ?? null;

// ---- All term-offerings for this subject+section+school year -------------
// A subject/section pair can have up to 3 rows here (classofferings is
// unique on subject_id + section_id + quarter + school_year_id — one row
// per term). This query, scoped to this teacher, doubles as the
// authorization check: if nothing comes back, this teacher doesn't teach
// this subject in this section and we bounce them out.
$stmt = $pdo->prepare("
    SELECT
        co.offering_id, co.quarter, co.schedule_days, co.start_time, co.end_time,
        co.capacity, co.teacher_id,
        sub.subject_id, sub.subject_name,
        sec.section_id, sec.section_name, sec.grade_level, sec.strand,
        COUNT(e.student_id) AS enrolled_count
    FROM classofferings co
    JOIN subjects sub ON sub.subject_id = co.subject_id
    JOIN sections sec ON sec.section_id = co.section_id
    LEFT JOIN enrollments e ON e.offering_id = co.offering_id AND e.status = 'active'
    WHERE co.teacher_id = ?
      AND co.subject_id = ?
      AND co.section_id = ?
      AND co.status = 'active'
      AND (co.school_year_id = ? OR ? IS NULL)
    GROUP BY co.offering_id, co.quarter, co.schedule_days, co.start_time, co.end_time,
             co.capacity, co.teacher_id, sub.subject_id, sub.subject_name,
             sec.section_id, sec.section_name, sec.grade_level, sec.strand
");
$stmt->execute([$teacherId, $subjectId, $sectionId, $schoolYearId, $schoolYearId]);
$offeringRows = $stmt->fetchAll();

if (empty($offeringRows)) {
    header('Location: courses.php');
    exit();
}

// Class-level info (subject name, section name, grade, strand) is the same
// across all term rows — take it from whichever row we found first.
$classInfo = $offeringRows[0];

// ---- Build Term 1 / 2 / 3 tabs, filling in gaps for terms with no offering yet ----
$termLabels = ['TRM 1' => 'Term 1', 'TRM 2' => 'Term 2', 'TRM 3' => 'Term 3'];
$terms = [];
foreach ($termLabels as $key => $label) {
    $terms[$key] = ['key' => $key, 'label' => $label, 'offering' => null];
}
foreach ($offeringRows as $row) {
    if (isset($terms[$row['quarter']])) {
        $terms[$row['quarter']]['offering'] = $row;
    }
}

// ---- Active term (defaults to the first term that has an offering) -------
$requestedTerm = $_GET['term'] ?? null;
if (isset($terms[$requestedTerm]) && $terms[$requestedTerm]['offering']) {
    $activeTerm = $requestedTerm;
} else {
    $activeTerm = null;
    foreach ($terms as $key => $t) {
        if ($t['offering']) {
            $activeTerm = $key;
            break;
        }
    }
}
$activeOffering   = $activeTerm ? $terms[$activeTerm]['offering'] : null;
$activeOfferingId = $activeOffering['offering_id'] ?? null;

// ---- Active nav view (Overview / Students / Attendance / Grading) --------
$allowedViews = ['overview', 'students', 'attendance', 'grading'];
$activeView   = $_GET['view'] ?? 'overview';
if (!in_array($activeView, $allowedViews, true)) {
    $activeView = 'overview';
}

// ---- Flash message (set by material_upload.php / material_delete.php) ----
$flash = getFlashMessage();

// ---- Learning materials for the active term's offering -------------------
$materials = [];
if ($activeOfferingId) {
    $stmt = $pdo->prepare("
        SELECT lm.material_id, lm.title, lm.type, lm.file_path, lm.external_url,
               lm.file_size, lm.created_at, t.firstname, t.lastname
        FROM learning_materials lm
        JOIN teachers t ON t.teacher_id = lm.uploaded_by
        WHERE lm.offering_id = ?
        ORDER BY lm.created_at DESC
    ");
    $stmt->execute([$activeOfferingId]);
    $materials = $stmt->fetchAll();
}

// ---- Students enrolled in the active term's offering (Students & Attendance views) -----
$students = [];
if ($activeOfferingId && ($activeView === 'students' || $activeView === 'attendance')) {
    $stmt = $pdo->prepare("
        SELECT s.student_id, s.student_lrn, s.firstname, s.lastname, s.middlename, s.email
        FROM enrollments e
        JOIN students s ON s.student_id = e.student_id
        WHERE e.offering_id = ? AND e.status = 'active'
        ORDER BY s.lastname, s.firstname
    ");
    $stmt->execute([$activeOfferingId]);
    $students = $stmt->fetchAll();
}

// ---- Attendance for the active term's offering ---------------------------
$attendanceDate = null;
$attendanceByStudent = [];
$attendanceSummary = ['Present' => 0, 'Absent' => 0, 'Late' => 0, 'Excused' => 0, 'Unmarked' => 0];
if ($activeOfferingId && $activeView === 'attendance') {
    // Date defaults to today; validate anything passed in the query string
    // so a malformed ?date= can't cause a DB error.
    $requestedDate = $_GET['date'] ?? null;
    if ($requestedDate && DateTime::createFromFormat('Y-m-d', $requestedDate) !== false) {
        $attendanceDate = $requestedDate;
    } else {
        $attendanceDate = date('Y-m-d');
    }

    $stmt = $pdo->prepare("
        SELECT student_id, status, remarks
        FROM attendance
        WHERE offering_id = ? AND attendance_date = ?
    ");
    $stmt->execute([$activeOfferingId, $attendanceDate]);
    foreach ($stmt->fetchAll() as $row) {
        $attendanceByStudent[$row['student_id']] = $row;
    }

    foreach ($students as $s) {
        $status = $attendanceByStudent[$s['student_id']]['status'] ?? null;
        if ($status && isset($attendanceSummary[$status])) {
            $attendanceSummary[$status]++;
        } else {
            $attendanceSummary['Unmarked']++;
        }
    }
}

$csrfToken = generateCSRFToken();

/**
 * Helper: build a link back to this page preserving subject/section
 * and swapping in a given term/view.
 */
function classOverviewUrl(int $subjectId, int $sectionId, ?string $term = null, string $view = 'overview'): string
{
    $params = ['subject_id' => $subjectId, 'section_id' => $sectionId, 'view' => $view];
    if ($term) {
        $params['term'] = $term;
    }
    return 'class_overview.php?' . http_build_query($params);
}

/**
 * Helper: build a link to the Attendance view for a specific date,
 * preserving subject/section/term.
 */
function attendanceUrl(int $subjectId, int $sectionId, ?string $term, string $date): string
{
    $params = [
        'subject_id' => $subjectId,
        'section_id' => $sectionId,
        'view'       => 'attendance',
        'date'       => $date,
    ];
    if ($term) {
        $params['term'] = $term;
    }
    return 'class_overview.php?' . http_build_query($params);
}

/**
 * Helper: human-readable file size.
 */
function formatFileSize(?int $bytes): string
{
    if (!$bytes) {
        return '';
    }
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    $size = (float) $bytes;
    while ($size >= 1024 && $i < count($units) - 1) {
        $size /= 1024;
        $i++;
    }
    return round($size, $size < 10 ? 1 : 0) . ' ' . $units[$i];
}

/**
 * Helper: FontAwesome icon class per material type.
 */
function materialIcon(string $type): string
{
    return match ($type) {
        'pdf'    => 'fa-file-pdf',
        'video'  => 'fa-file-video',
        'slides' => 'fa-file-powerpoint',
        'link'   => 'fa-link',
        default  => 'fa-file',
    };
}