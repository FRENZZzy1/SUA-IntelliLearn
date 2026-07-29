<?php
// assests/api/export_section_classes.php?section_id=13[&quarter=1]
// Exports the weekly TIME x DAY class schedule for one section to a
// modern-styled .xlsx-compatible file, matching a printed timetable layout.
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

// ================= QUARTER SELECTION =================
// A section's grid is built from one quarter's classes at a time (subjects can
// change between quarters). If ?quarter= isn't given, default to the earliest
// quarter offered for this section.
$quarterStmt = $pdo->prepare("SELECT DISTINCT quarter FROM classofferings WHERE section_id = ? ORDER BY quarter ASC");
$quarterStmt->execute([$sectionId]);
$availableQuarters = $quarterStmt->fetchAll(PDO::FETCH_COLUMN);

$quarterParam = isset($_GET['quarter']) && ctype_digit((string) $_GET['quarter']) ? (int) $_GET['quarter'] : null;
$quarter = ($quarterParam !== null && in_array($quarterParam, $availableQuarters))
    ? $quarterParam
    : ($availableQuarters[0] ?? 1);

$stmt = $pdo->prepare("
    SELECT
        s.subject_name,
        t.firstname AS teacher_firstname,
        t.lastname  AS teacher_lastname,
        co.schedule_days,
        co.start_time,
        co.end_time,
        co.status
    FROM classofferings co
    JOIN subjects s      ON s.subject_id = co.subject_id
    LEFT JOIN teachers t ON t.teacher_id = co.teacher_id
    WHERE co.section_id = ? AND co.quarter = ?
    ORDER BY co.start_time ASC
");
$stmt->execute([$sectionId, $quarter]);
$classes = $stmt->fetchAll();

// ================= PARSE "M - W" / "MWF" / "TTh" INTO DAY NAMES =================
function parseScheduleDayNames(?string $raw): array
{
    $clean = preg_replace('/[^A-Za-z]/', '', (string) $raw);
    $days = [];
    $i = 0;
    $len = strlen($clean);
    while ($i < $len) {
        if ($i + 1 < $len && strtolower(substr($clean, $i, 2)) === 'th') {
            $days[] = 'Thursday';
            $i += 2;
            continue;
        }
        switch (strtoupper($clean[$i])) {
            case 'M': $days[] = 'Monday'; break;
            case 'T': $days[] = 'Tuesday'; break;
            case 'W': $days[] = 'Wednesday'; break;
            case 'F': $days[] = 'Friday'; break;
            case 'S': $days[] = 'Saturday'; break;
        }
        $i++;
    }
    return array_values(array_unique($days));
}

$dayColumns = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

// ================= GROUP CLASSES INTO TIME-SLOT ROWS =================
$slots = []; // key: "start|end" => ['start'=>, 'end'=>, 'cells'=>[day=>[subject,teacher]]]
$unscheduled = [];

foreach ($classes as $c) {
    $dayNames = parseScheduleDayNames($c['schedule_days']);
    $relevantDays = array_intersect($dayNames, $dayColumns);

    if (!$c['start_time'] || !$c['end_time'] || empty($relevantDays)) {
        $unscheduled[] = $c;
        continue;
    }

    $key = $c['start_time'] . '|' . $c['end_time'];
    if (!isset($slots[$key])) {
        $slots[$key] = [
            'start' => $c['start_time'],
            'end'   => $c['end_time'],
            'cells' => array_fill_keys($dayColumns, null),
        ];
    }

    $teacherName = $c['teacher_firstname'] ? trim($c['teacher_firstname'] . ' ' . $c['teacher_lastname']) : '— Unassigned —';

    foreach ($relevantDays as $day) {
        $slots[$key]['cells'][$day] = [
            'subject' => $c['subject_name'],
            'teacher' => $teacherName,
        ];
    }
}

usort($slots, fn($a, $b) => strtotime($a['start']) <=> strtotime($b['start']));

// ================= BUILD GRID ROWS (auto-insert a recess row into any gap) =================
$gridRows = [];
$prevEnd = null;

foreach ($slots as $slot) {
    if ($prevEnd !== null && strtotime($slot['start']) > strtotime($prevEnd)) {
        $gridRows[] = [
            'type'       => 'recess',
            'label'      => 'RECESS',
            'time_label' => date('g:i A', strtotime($prevEnd)) . ' - ' . date('g:i A', strtotime($slot['start'])),
        ];
    }

    $gridRows[] = [
        'type'       => 'class',
        'time_label' => date('g:i A', strtotime($slot['start'])) . ' - ' . date('g:i A', strtotime($slot['end'])),
        'cells'      => $slot['cells'],
    ];

    $prevEnd = $slot['end'];
}

$sectionLabel = 'GRADE ' . $section['grade_level'] . ': ' . strtoupper($section['section_name']) . ($section['strand'] ? ' (' . $section['strand'] . ')' : '');

$subtitleLines = [
    'Quarter ' . $quarter . ($section['school_year_label'] ? ' · School Year ' . $section['school_year_label'] : ''),
    'Generated: ' . date('F j, Y g:i A'),
];

if (!empty($unscheduled)) {
    $names = array_map(fn($c) => $c['subject_name'], $unscheduled);
    $subtitleLines[] = 'Not yet scheduled (no day/time set): ' . implode(', ', $names);
}

export_xlsx_schedule_grid([
    'filename'       => 'section_schedule_' . preg_replace('/[^A-Za-z0-9]+/', '_', $section['section_name']) . '_q' . $quarter . '.xls',
    'sheet_name'     => 'Schedule',
    'band_title'     => $sectionLabel,
    'subtitle_lines' => $subtitleLines,
    'days'           => $dayColumns,
    'rows'           => $gridRows,
    'footer_text'    => $adviserName ? 'CLASS ADVISER: ' . strtoupper($adviserName) : null,
]);
exit();
