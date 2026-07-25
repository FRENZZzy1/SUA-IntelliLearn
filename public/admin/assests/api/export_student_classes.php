<?php
/**
 * Export Student Classes to Excel
 * Endpoint: assets/api/export_student_classes.php?student_id=8
 * 
 * Exports every class enrollment for a single student into a modern-styled .xlsx file.
 */

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/lib/xlsx_writer.php';

requireAdmin();

// ─── VALIDATE INPUT ─────────────────────────────────────────────────────────

$studentId = filter_input(INPUT_GET, 'student_id', FILTER_VALIDATE_INT) ?: 0;

if ($studentId <= 0) {
    http_response_code(400);
    exit('Missing or invalid student_id.');
}

// ─── FETCH STUDENT RECORD ───────────────────────────────────────────────────

$studentStmt = $pdo->prepare("
    SELECT student_id, student_lrn, firstname, middlename, lastname, email 
    FROM students 
    WHERE student_id = ?
");
$studentStmt->execute([$studentId]);
$student = $studentStmt->fetch();

if (!$student) {
    http_response_code(404);
    exit('Student not found.');
}

$studentName = buildFullName(
    $student['firstname'],
    $student['middlename'],
    $student['lastname']
);

// ─── FETCH ENROLLMENTS ──────────────────────────────────────────────────────

$enrollmentStmt = $pdo->prepare("
    SELECT
        s.subject_name,
        sec.section_name,
        sec.grade_level,
        sec.strand,
        t.firstname AS teacher_firstname,
        t.lastname  AS teacher_lastname,
        co.quarter,
        syco.label AS school_year_label,
        co.schedule_days,
        co.start_time,
        co.end_time,
        e.status AS enrollment_status,
        e.enrolled_at
    FROM enrollments e
    JOIN classofferings co ON co.offering_id = e.offering_id
    JOIN subjects s         ON s.subject_id   = co.subject_id
    JOIN sections sec       ON sec.section_id = co.section_id
    LEFT JOIN teachers t    ON t.teacher_id   = co.teacher_id
    LEFT JOIN schoolyears syco ON syco.school_year_id = co.school_year_id
    WHERE e.student_id = ?
    ORDER BY e.enrolled_at DESC
");
$enrollmentStmt->execute([$studentId]);
$classes = $enrollmentStmt->fetchAll();

// ─── BUILD EXPORT ROWS ──────────────────────────────────────────────────────

$exportRows = [];
foreach ($classes as $class) {
    $exportRows[] = [
        /* Subject      */ $class['subject_name'],
        /* Section      */ formatSection($class),
        /* Teacher      */ formatTeacherName($class['teacher_firstname'], $class['teacher_lastname']),
        /* Quarter      */ 'Q' . $class['quarter'],
        /* School Year  */ $class['school_year_label'] ?? '—',
        /* Schedule     */ formatSchedule($class),
        /* Enrolled On  */ date('M j, Y', strtotime($class['enrolled_at'])),
        /* Status       */ $class['enrollment_status'],
    ];
}

// ─── CONFIGURE & EXPORT ─────────────────────────────────────────────────────

$classCount   = count($exportRows);
$pluralSuffix = $classCount === 1 ? '' : 'es';
$safeFilename = 'student_classes_' . preg_replace('/[^A-Za-z0-9]+/', '_', $studentName) . '.xls';

export_xlsx_modern([
    'filename'       => $safeFilename,
    'sheet_name'     => 'Student Classes',
    'band_title'     => 'Student Class Export',
    'subtitle_lines' => [
        "{$studentName} · LRN: {$student['student_lrn']}",
        'Generated: ' . date('F j, Y g:i A') . " · {$classCount} class{$pluralSuffix}",
    ],
    'columns' => [
        ['label' => 'Subject',     'width' => 22],
        ['label' => 'Section',     'width' => 26],
        ['label' => 'Teacher',     'width' => 22],
        ['label' => 'Quarter',     'width' => 10],
        ['label' => 'School Year', 'width' => 14],
        ['label' => 'Schedule',    'width' => 20],
        ['label' => 'Enrolled On', 'width' => 14],
        ['label' => 'Status',      'width' => 12],
    ],
    'rows'        => $exportRows,
    'status_col'  => 7,
    'footer_text' => "Total classes for {$studentName}: {$classCount}",
]);

exit();

// ─── HELPER FUNCTIONS ───────────────────────────────────────────────────────

/**
 * Build a full name from name parts.
 */
function buildFullName(string $first, ?string $middle, string $last): string
{
    $parts = [$first];
    
    if (!empty($middle)) {
        $parts[] = $middle;
    }
    
    $parts[] = $last;
    
    return implode(' ', $parts);
}

/**
 * Format section display string: "SectionName (Grade X · Strand)"
 */
function formatSection(array $class): string
{
    $section = $class['section_name'] . ' (Grade ' . $class['grade_level'];
    
    if (!empty($class['strand'])) {
        $section .= ' · ' . $class['strand'];
    }
    
    return $section . ')';
}

/**
 * Format teacher name or return placeholder.
 */
function formatTeacherName(?string $first, ?string $last): string
{
    if (empty($first)) {
        return '— Unassigned —';
    }
    
    return trim($first . ' ' . ($last ?? ''));
}

/**
 * Format schedule string from days and time range.
 */
function formatSchedule(array $class): string
{
    $hasDays  = !empty($class['schedule_days']);
    $hasStart = !empty($class['start_time']);
    
    if (!$hasDays && !$hasStart) {
        return '—';
    }
    
    $parts = [];
    
    if ($hasDays) {
        $parts[] = trim((string) $class['schedule_days']);
    }
    
    if ($hasStart && !empty($class['end_time'])) {
        $start = date('g A', strtotime($class['start_time']));
        $end   = date('g A', strtotime($class['end_time']));
        $parts[] = "{$start}–{$end}";
    }
    
    return implode(' ', $parts);
}