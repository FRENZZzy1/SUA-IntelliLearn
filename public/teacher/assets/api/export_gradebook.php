<?php
// public/teacher/assets/api/export_gradebook.php?offering_id=12
// Exports one class's gradebook (one term) to a modern-styled .xlsx file.
// Uses the same writer/format as the admin exports (export_xlsx_modern), and
// Complete/Incomplete + Submitted/Not Submitted labels only (no percentages).
require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../admin/assests/api/lib/xlsx_writer.php';
require_once __DIR__ . '/gradebook_status_functions.php';

requireTeacher();

$userId     = (int) $_SESSION['user_id'];
$offeringId = isset($_GET['offering_id']) && ctype_digit($_GET['offering_id']) ? (int) $_GET['offering_id'] : 0;

if ($offeringId <= 0) {
    http_response_code(400);
    exit('Missing or invalid offering_id.');
}

// ---- Resolve the logged-in teacher -----------------------------------------
$stmt = $pdo->prepare("SELECT teacher_id, firstname, lastname FROM teachers WHERE user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$teacher = $stmt->fetch();
if (!$teacher) {
    http_response_code(403);
    exit('Teacher record not found for this account.');
}
$teacherId   = (int) $teacher['teacher_id'];
$teacherName = trim($teacher['firstname'] . ' ' . $teacher['lastname']);

// ---- The class must belong to this teacher (authorization) ------------------
$stmt = $pdo->prepare("
    SELECT co.offering_id, co.quarter,
           sub.subject_name, sec.section_name, sec.grade_level,
           sy.label AS school_year_label
    FROM classofferings co
    JOIN subjects sub ON sub.subject_id = co.subject_id
    JOIN sections sec ON sec.section_id = co.section_id
    LEFT JOIN schoolyears sy ON sy.school_year_id = co.school_year_id
    WHERE co.offering_id = ? AND co.teacher_id = ? AND co.status = 'active'
    LIMIT 1
");
$stmt->execute([$offeringId, $teacherId]);
$class = $stmt->fetch();
if (!$class) {
    http_response_code(403);
    exit('Class not found or you do not have access to it.');
}

// ---- Active students ---------------------------------------------------------
$stmt = $pdo->prepare("
    SELECT e.enrollment_id, s.student_id, s.student_lrn, s.firstname, s.lastname, s.middlename
    FROM enrollments e
    JOIN students s ON s.student_id = e.student_id
    WHERE e.offering_id = ? AND e.status = 'enrolled'
    ORDER BY s.lastname, s.firstname
");
$stmt->execute([$offeringId]);
$students = $stmt->fetchAll();

// ---- Items per category + who submitted what ---------------------------------
$loaded = gb_load_items($pdo, $offeringId);
$items  = $loaded['items'];
$done   = $loaded['done'];

// ---- Build the rows ----------------------------------------------------------------
$rows = [];
$completeCnt = ['ww' => 0, 'pt' => 0, 'exam' => 0];
foreach ($students as $s) {
    $sid = (int) $s['student_id'];
    $mine = $done[$sid] ?? [];
    $cells = [];
    $summary = [];
    foreach (GB_CATEGORIES as $key => $label) {
        foreach ($items[$key] as $it) {
            $cells[] = !empty($mine[$it['uid']]) ? 'Submitted' : 'Not Submitted';
        }
        $st = gb_category_status($items[$key], $mine);
        if ($st === 'complete') $completeCnt[$key]++;
        $summary[] = gb_status_label($st);
    }
    $rows[] = array_merge(
        [(string) $s['student_lrn'], $s['lastname'], $s['firstname'], $s['middlename'] ?? ''],
        $summary,
        $cells
    );
}

// ---- Columns -----------------------------------------------------------------------
$columns = [
    ['label' => 'LRN',         'width' => 18],
    ['label' => 'Last Name',   'width' => 18],
    ['label' => 'First Name',  'width' => 18],
    ['label' => 'Middle Name', 'width' => 16],
];
foreach (GB_CATEGORIES as $label) {
    $columns[] = ['label' => $label, 'width' => 18];
}
foreach (GB_CATEGORIES as $key => $label) {
    foreach ($items[$key] as $it) {
        $columns[] = ['label' => $it['title'] . "\n(" . $label . ')', 'width' => 18];
    }
}

// ---- Titles / filename ---------------------------------------------------------------
$termNo = (int) substr($class['quarter'], -1);

$subtitle = 'Grade ' . $class['grade_level']
    . ' · Term ' . $termNo
    . ' · School Year ' . ($class['school_year_label'] ?: '—')
    . ' · Teacher ' . $teacherName;

$namePart = preg_replace('/[^A-Za-z0-9]+/', '', $class['subject_name'])
    . '-Grade' . $class['grade_level']
    . preg_replace('/[^A-Za-z0-9]+/', '', $class['section_name']);

export_xlsx_modern([
    'filename'   => $namePart . '_gradebook_Term' . $termNo . '_' . date('Y-m-d') . '.xlsx',
    'sheet_name' => 'Gradebook',
    'band_title' => $class['subject_name'] . ' - ' . $class['section_name'],
    'subtitle_lines' => [
        $subtitle,
        'Generated: ' . date('F j, Y g:i A') . ' · ' . count($students) . ' student' . (count($students) === 1 ? '' : 's'),
    ],
    'columns'    => $columns,
    'rows'       => $rows,
    'footer_text' => 'Complete - Written Works: ' . $completeCnt['ww'] . '/' . count($students)
        . ' · Performance Tasks: ' . $completeCnt['pt'] . '/' . count($students)
        . ' · Exam: ' . $completeCnt['exam'] . '/' . count($students),
]);
exit();