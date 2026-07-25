<?php
// assests/api/export_teacher_classes.php?teacher_id=3
// Exports every class (classoffering) handled by one teacher to a modern-styled .xlsx file.
require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/lib/xlsx_writer.php';

requireAdmin();

$teacherId = isset($_GET['teacher_id']) && ctype_digit($_GET['teacher_id']) ? (int) $_GET['teacher_id'] : 0;

if ($teacherId <= 0) {
    http_response_code(400);
    exit('Missing or invalid teacher_id.');
}

$teacherStmt = $pdo->prepare("SELECT teacher_id, firstname, lastname, department, specialization, employment_status FROM teachers WHERE teacher_id = ?");
$teacherStmt->execute([$teacherId]);
$teacher = $teacherStmt->fetch();

if (!$teacher) {
    http_response_code(404);
    exit('Teacher not found.');
}

$teacherName = trim($teacher['firstname'] . ' ' . $teacher['lastname']);

$stmt = $pdo->prepare("
    SELECT
        s.subject_name,
        sec.section_name,
        sec.grade_level,
        sec.strand,
        co.quarter,
        syco.label AS school_year_label,
        co.schedule_days,
        co.start_time,
        co.end_time,
        co.capacity,
        co.status,
        (SELECT COUNT(*) FROM enrollments e WHERE e.offering_id = co.offering_id AND e.status = 'active') AS enrolled_count
    FROM classofferings co
    JOIN subjects s     ON s.subject_id = co.subject_id
    JOIN sections sec   ON sec.section_id = co.section_id
    LEFT JOIN schoolyears syco ON syco.school_year_id = co.school_year_id
    WHERE co.teacher_id = ?
    ORDER BY sec.grade_level ASC, s.subject_name ASC
");
$stmt->execute([$teacherId]);
$classes = $stmt->fetchAll();

$rows = [];
foreach ($classes as $c) {
    $schedule = '—';
    if ($c['schedule_days'] || $c['start_time']) {
        $time = '';
        if ($c['start_time'] && $c['end_time']) {
            $time = date('g A', strtotime($c['start_time'])) . '-' . date('g A', strtotime($c['end_time']));
        }
        $schedule = trim(trim((string) $c['schedule_days']) . ' ' . $time);
    }

    $rows[] = [
        $c['subject_name'],
        $c['section_name'],
        'Grade ' . $c['grade_level'] . ($c['strand'] ? ' · ' . $c['strand'] : ''),
        'Q' . $c['quarter'],
        $c['school_year_label'] ?? '—',
        $schedule,
        $c['enrolled_count'] . '/' . $c['capacity'],
        $c['status'],
    ];
}

$subtitle = $teacherName;
if ($teacher['department']) {
    $subtitle .= ' · ' . $teacher['department'] . ' Department';
}

export_xlsx_modern([
    'filename'   => 'teacher_classes_' . preg_replace('/[^A-Za-z0-9]+/', '_', $teacherName) . '.xls',
    'sheet_name' => 'Teacher Classes',
    'band_title' => 'Teacher Class Export',
    'subtitle_lines' => [
        $subtitle,
        'Generated: ' . date('F j, Y g:i A') . ' · ' . count($rows) . ' class' . (count($rows) === 1 ? '' : 'es'),
    ],
    'columns' => [
        ['label' => 'Subject',     'width' => 22],
        ['label' => 'Section',     'width' => 18],
        ['label' => 'Grade/Strand', 'width' => 20],
        ['label' => 'Quarter',     'width' => 10],
        ['label' => 'School Year', 'width' => 14],
        ['label' => 'Schedule',    'width' => 20],
        ['label' => 'Enrollment',  'width' => 12],
        ['label' => 'Status',      'width' => 12],
    ],
    'rows'       => $rows,
    'status_col' => 7,
    'footer_text' => 'Total classes handled by ' . $teacherName . ': ' . count($rows),
]);
exit();