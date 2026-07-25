<?php
// assests/api/export_section_classes.php?section_id=13
// Exports every class (classoffering) taught within one section to a modern-styled .xlsx file.
require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/lib/xlsx_writer.php';

requireAdmin();

$sectionId = isset($_GET['section_id']) && ctype_digit($_GET['section_id']) ? (int) $_GET['section_id'] : 0;

if ($sectionId <= 0) {
    http_response_code(400);
    exit('Missing or invalid section_id.');
}

$sectionStmt = $pdo->prepare("
    SELECT sec.section_id, sec.section_name, sec.grade_level, sec.strand,
           t.firstname AS adviser_firstname, t.lastname AS adviser_lastname,
           sy.label AS school_year_label
    FROM sections sec
    LEFT JOIN teachers t     ON t.teacher_id = sec.adviser_id
    LEFT JOIN schoolyears sy ON sy.school_year_id = sec.school_year_id
    WHERE sec.section_id = ?
");
$sectionStmt->execute([$sectionId]);
$section = $sectionStmt->fetch();

if (!$section) {
    http_response_code(404);
    exit('Section not found.');
}

$adviserName = $section['adviser_firstname'] ? trim($section['adviser_firstname'] . ' ' . $section['adviser_lastname']) : null;

$stmt = $pdo->prepare("
    SELECT
        s.subject_name,
        t.firstname AS teacher_firstname,
        t.lastname  AS teacher_lastname,
        co.quarter,
        syco.label AS school_year_label,
        co.schedule_days,
        co.start_time,
        co.end_time,
        co.capacity,
        co.status,
        (SELECT COUNT(*) FROM enrollments e WHERE e.offering_id = co.offering_id AND e.status = 'active') AS enrolled_count
    FROM classofferings co
    JOIN subjects s      ON s.subject_id = co.subject_id
    LEFT JOIN teachers t ON t.teacher_id = co.teacher_id
    LEFT JOIN schoolyears syco ON syco.school_year_id = co.school_year_id
    WHERE co.section_id = ?
    ORDER BY s.subject_name ASC
");
$stmt->execute([$sectionId]);
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

    $teacherName = $c['teacher_firstname'] ? trim($c['teacher_firstname'] . ' ' . $c['teacher_lastname']) : '— Unassigned —';

    $rows[] = [
        $c['subject_name'],
        $teacherName,
        'Q' . $c['quarter'],
        $c['school_year_label'] ?? '—',
        $schedule,
        $c['enrolled_count'] . '/' . $c['capacity'],
        $c['status'],
    ];
}

$sectionLabel = $section['section_name'] . ' (Grade ' . $section['grade_level'] . ($section['strand'] ? ' · ' . $section['strand'] : '') . ')';

export_xlsx_modern([
    'filename'   => 'section_classes_' . preg_replace('/[^A-Za-z0-9]+/', '_', $section['section_name']) . '.xls',
    'sheet_name' => 'Section Classes',
    'band_title' => 'Section Class Export',
    'subtitle_lines' => [
        $sectionLabel . ($adviserName ? ' · Adviser: ' . $adviserName : ''),
        'Generated: ' . date('F j, Y g:i A') . ' · ' . count($rows) . ' class' . (count($rows) === 1 ? '' : 'es'),
    ],
    'columns' => [
        ['label' => 'Subject',     'width' => 22],
        ['label' => 'Teacher',     'width' => 22],
        ['label' => 'Quarter',     'width' => 10],
        ['label' => 'School Year', 'width' => 14],
        ['label' => 'Schedule',    'width' => 20],
        ['label' => 'Enrollment',  'width' => 12],
        ['label' => 'Status',      'width' => 12],
    ],
    'rows'       => $rows,
    'status_col' => 6,
    'footer_text' => 'Total classes offered in ' . $section['section_name'] . ': ' . count($rows),
]);
exit();