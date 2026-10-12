<?php
/**
 * Generate Report endpoint (Quick Actions widget)
 *
 * Builds an Excel (.xlsx) report from live database data and streams it
 * back as a download, using the same export_xlsx_modern() helper as the
 * other admin exports so the look matches.
 *
 * Expects: POST with
 *   csrf           - session CSRF token
 *   report_type    - enrollment | attendance | grades
 *   school_year_id - optional (defaults to the current school year)
 *   term           - optional: "TRM 1" | "TRM 2" | "TRM 3" (blank = all terms)
 *
 * Success: the .xlsx file (Content-Disposition: attachment).
 * Failure: JSON { success: false, errors: [...] } with a proper status code.
 */

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/lib/xlsx_writer.php';

requireAdmin();
requireAdminModule('dashboard', 'read');

function report_fail(int $status, string $message): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'errors' => [$message]]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    report_fail(405, 'Method not allowed.');
}

if (!validateCSRFToken($_POST['csrf'] ?? '')) {
    report_fail(403, 'Invalid or expired session. Please refresh and try again.');
}

// ================= INPUT VALIDATION =================
$reportType = $_POST['report_type'] ?? '';
if (!in_array($reportType, ['enrollment', 'attendance', 'grades'], true)) {
    report_fail(422, 'Please choose a report type.');
}

$term = trim($_POST['term'] ?? '');
if ($term !== '' && !in_array($term, ['TRM 1', 'TRM 2', 'TRM 3'], true)) {
    report_fail(422, 'Invalid term selected.');
}

$schoolYearId = $_POST['school_year_id'] ?? '';
if ($schoolYearId === '') {
    $schoolYearId = $pdo->query("SELECT school_year_id FROM schoolyears WHERE is_current = 1 LIMIT 1")->fetchColumn();
} elseif (!ctype_digit((string) $schoolYearId)) {
    report_fail(422, 'Invalid school year selected.');
}

$syStmt = $pdo->prepare("SELECT label FROM schoolyears WHERE school_year_id = ?");
$syStmt->execute([(int) $schoolYearId]);
$schoolYearLabel = $syStmt->fetchColumn();
if (!$schoolYearLabel) {
    report_fail(422, 'No school year found. Please set up a school year first.');
}

// ================= SHARED PIECES =================
$params = [(int) $schoolYearId];
$termSql = '';
if ($term !== '') {
    $termSql = ' AND co.quarter = ?';
    $params[] = $term;
}

$baseSelect = "
    co.class_code, s.subject_name, sec.section_name, sec.grade_level,
    co.quarter, co.status AS class_status,
    t.firstname AS teacher_firstname, t.lastname AS teacher_lastname
";
$baseFrom = "
    FROM classofferings co
    JOIN subjects s   ON s.subject_id = co.subject_id
    JOIN sections sec ON sec.section_id = co.section_id
    LEFT JOIN teachers t ON t.teacher_id = co.teacher_id
";
$baseOrder = " ORDER BY sec.grade_level, sec.section_name, s.subject_name, co.quarter";

function report_teacher(array $r): string
{
    $name = trim(($r['teacher_firstname'] ?? '') . ' ' . ($r['teacher_lastname'] ?? ''));
    return $name !== '' ? $name : '— None —';
}

function report_grade(array $r): string
{
    return 'Grade ' . $r['grade_level'];
}

function report_avg($v): string
{
    return $v === null ? '—' : number_format((float) $v, 2);
}

// ================= BUILD THE REPORT =================
$columns = [];
$rows = [];
$title = '';
$sheetName = '';
$statusCol = null;
$footer = null;
$note = null;

switch ($reportType) {
    case 'enrollment':
        $sql = "SELECT $baseSelect, co.capacity,
                    (SELECT COUNT(*) FROM enrollments e WHERE e.offering_id = co.offering_id AND e.status = 'enrolled') AS enrolled_count,
                    (SELECT COUNT(*) FROM enrollments e WHERE e.offering_id = co.offering_id AND e.status = 'pending')  AS pending_count,
                    (SELECT COUNT(*) FROM enrollments e WHERE e.offering_id = co.offering_id AND e.status = 'dropped')  AS dropped_count
                $baseFrom
                WHERE co.school_year_id = ? $termSql $baseOrder";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll();

        $title = 'Enrollment Summary Report';
        $sheetName = 'Enrollment Summary';
        $columns = [
            ['label' => 'Class Code', 'width' => 14],
            ['label' => 'Subject', 'width' => 28],
            ['label' => 'Section', 'width' => 20],
            ['label' => 'Grade Level', 'width' => 20],
            ['label' => 'Term', 'width' => 10],
            ['label' => 'Teacher', 'width' => 24],
            ['label' => 'Capacity', 'width' => 11],
            ['label' => 'Enrolled', 'width' => 11],
            ['label' => 'Pending', 'width' => 11],
            ['label' => 'Dropped', 'width' => 11],
            ['label' => 'Seats Left', 'width' => 12],
            ['label' => 'Fill Rate', 'width' => 11],
            ['label' => 'Class Status', 'width' => 14],
        ];
        $statusCol = 12;

        $totalEnrolled = 0;
        $totalCapacity = 0;
        foreach ($data as $r) {
            $cap = (int) $r['capacity'];
            $enr = (int) $r['enrolled_count'];
            $totalEnrolled += $enr;
            $totalCapacity += $cap;
            $rows[] = [
                $r['class_code'], $r['subject_name'], $r['section_name'], report_grade($r),
                $r['quarter'], report_teacher($r), $cap, $enr,
                (int) $r['pending_count'], (int) $r['dropped_count'],
                max(0, $cap - $enr),
                $cap > 0 ? round($enr / $cap * 100) . '%' : '—',
                $r['class_status'],
            ];
        }
        $footer = 'Total classes: ' . count($rows) . ' · Total enrolled: ' . $totalEnrolled
            . ' of ' . $totalCapacity . ' seats';
        break;

    case 'attendance':
        $sql = "SELECT $baseSelect,
                    COALESCE(a.total, 0)   AS total,
                    COALESCE(a.present, 0) AS present,
                    COALESCE(a.late, 0)    AS late,
                    COALESCE(a.excused, 0) AS excused,
                    COALESCE(a.absent, 0)  AS absent
                $baseFrom
                LEFT JOIN (
                    SELECT offering_id,
                           COUNT(*) AS total,
                           SUM(status = 'Present') AS present,
                           SUM(status = 'Late')    AS late,
                           SUM(status = 'Excused') AS excused,
                           SUM(status = 'Absent')  AS absent
                    FROM attendance
                    GROUP BY offering_id
                ) a ON a.offering_id = co.offering_id
                WHERE co.school_year_id = ? $termSql $baseOrder";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll();

        $title = 'Attendance Summary Report';
        $sheetName = 'Attendance Summary';
        $columns = [
            ['label' => 'Class Code', 'width' => 14],
            ['label' => 'Subject', 'width' => 28],
            ['label' => 'Section', 'width' => 20],
            ['label' => 'Grade Level', 'width' => 20],
            ['label' => 'Term', 'width' => 10],
            ['label' => 'Teacher', 'width' => 24],
            ['label' => 'Records', 'width' => 11],
            ['label' => 'Present', 'width' => 11],
            ['label' => 'Late', 'width' => 10],
            ['label' => 'Excused', 'width' => 11],
            ['label' => 'Absent', 'width' => 11],
            ['label' => 'Attendance Rate', 'width' => 17],
        ];
        $note = 'Attendance Rate = (Present + Late + Excused) ÷ total records.';

        $sumTotal = 0;
        $sumAttended = 0;
        foreach ($data as $r) {
            $total = (int) $r['total'];
            $attended = (int) $r['present'] + (int) $r['late'] + (int) $r['excused'];
            $sumTotal += $total;
            $sumAttended += $attended;
            $rows[] = [
                $r['class_code'], $r['subject_name'], $r['section_name'], report_grade($r),
                $r['quarter'], report_teacher($r), $total,
                (int) $r['present'], (int) $r['late'], (int) $r['excused'], (int) $r['absent'],
                $total > 0 ? round($attended / $total * 100, 1) . '%' : '— no records —',
            ];
        }
        $footer = 'Total classes: ' . count($rows) . ' · Overall attendance rate: '
            . ($sumTotal > 0 ? round($sumAttended / $sumTotal * 100, 1) . '%' : '—');
        break;

    case 'grades':
        $sql = "SELECT $baseSelect,
                    (SELECT COUNT(*) FROM enrollments e WHERE e.offering_id = co.offering_id AND e.status IN ('enrolled','completed')) AS student_count,
                    COALESCE(g.graded, 0) AS graded_count,
                    g.prelim, g.midterm, g.prefinal, g.final_grade, g.overall
                $baseFrom
                LEFT JOIN (
                    SELECT e.offering_id,
                           COUNT(DISTINCT gr.enrollment_id) AS graded,
                           AVG(CASE WHEN gr.quarter = 'Prelim'   THEN gr.grade END) AS prelim,
                           AVG(CASE WHEN gr.quarter = 'Midterm'  THEN gr.grade END) AS midterm,
                           AVG(CASE WHEN gr.quarter = 'Prefinal' THEN gr.grade END) AS prefinal,
                           AVG(CASE WHEN gr.quarter = 'Final'    THEN gr.grade END) AS final_grade,
                           AVG(gr.grade) AS overall
                    FROM grades gr
                    JOIN enrollments e ON e.enrollment_id = gr.enrollment_id
                    GROUP BY e.offering_id
                ) g ON g.offering_id = co.offering_id
                WHERE co.school_year_id = ? $termSql $baseOrder";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll();

        $title = 'Grades Summary Report';
        $sheetName = 'Grades Summary';
        $columns = [
            ['label' => 'Class Code', 'width' => 14],
            ['label' => 'Subject', 'width' => 28],
            ['label' => 'Section', 'width' => 20],
            ['label' => 'Grade Level', 'width' => 20],
            ['label' => 'Term', 'width' => 10],
            ['label' => 'Teacher', 'width' => 24],
            ['label' => 'Students', 'width' => 11],
            ['label' => 'Graded', 'width' => 11],
            ['label' => 'Prelim Avg', 'width' => 13],
            ['label' => 'Midterm Avg', 'width' => 13],
            ['label' => 'Prefinal Avg', 'width' => 13],
            ['label' => 'Final Avg', 'width' => 12],
            ['label' => 'Overall Avg', 'width' => 13],
        ];
        $note = 'Averages use the official grades recorded per student (Prelim, Midterm, Prefinal, Final). "—" means no grades recorded yet.';

        foreach ($data as $r) {
            $rows[] = [
                $r['class_code'], $r['subject_name'], $r['section_name'], report_grade($r),
                $r['quarter'], report_teacher($r),
                (int) $r['student_count'], (int) $r['graded_count'],
                report_avg($r['prelim']), report_avg($r['midterm']),
                report_avg($r['prefinal']), report_avg($r['final_grade']),
                report_avg($r['overall']),
            ];
        }
        $footer = 'Total classes: ' . count($rows);
        break;
}

if (!$rows) {
    report_fail(404, 'No classes found for ' . $schoolYearLabel . ($term !== '' ? ' (' . $term . ')' : '') . '. Try a different school year or term.');
}

$subtitle = [
    'School Year ' . $schoolYearLabel . ' · ' . ($term !== '' ? $term : 'All terms'),
    'Generated: ' . date('F j, Y g:i A'),
];
if ($note !== null) {
    $subtitle[] = $note;
}

export_xlsx_modern([
    'filename'       => 'SUA_' . $reportType . '_report_' . preg_replace('/[^A-Za-z0-9-]/', '', $schoolYearLabel) . '_' . date('Y-m-d') . '.xlsx',
    'sheet_name'     => $sheetName,
    'band_title'     => 'St. Uriel Academy — ' . $title,
    'subtitle_lines' => $subtitle,
    'columns'        => $columns,
    'rows'           => $rows,
    'status_col'     => $statusCol,
    'footer_text'    => $footer,
]);
