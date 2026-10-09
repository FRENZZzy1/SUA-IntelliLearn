<?php
/**
 * Excel export for the "View Students" modal in courses.php.
 * Exports the enrolled students together with the complete student profile
 * information available in the students table, plus enrollment information.
 *
 * Usage: window.location = 'export_course_students.php?offering_id=' + id
 */

require_once __DIR__ . '/../../../../config/config.php';

requireAdmin();

$offering_id = $_GET['offering_id'] ?? '';

if (!ctype_digit((string) $offering_id)) {
    http_response_code(422);
    header('Content-Type: text/plain');
    echo 'Missing or invalid course reference.';
    exit();
}

// ---- Course header info ----
$courseStmt = $pdo->prepare("
    SELECT
        co.offering_id,
        co.capacity,
        co.quarter,
        co.school_year_id,
        co.schedule_days,
        co.start_time,
        co.end_time,
        s.subject_name,
        sec.section_name,
        sec.grade_level,
        sec.strand,
        sy.label AS school_year_label,
        t.firstname AS teacher_firstname,
        t.lastname AS teacher_lastname
    FROM classofferings co
    JOIN subjects s ON s.subject_id = co.subject_id
    JOIN sections sec ON sec.section_id = co.section_id
    LEFT JOIN schoolyears sy ON sy.school_year_id = co.school_year_id
    LEFT JOIN teachers t ON t.teacher_id = co.teacher_id
    WHERE co.offering_id = ?
");
$courseStmt->execute([(int) $offering_id]);
$course = $courseStmt->fetch();

if (!$course) {
    http_response_code(404);
    header('Content-Type: text/plain');
    echo 'That course no longer exists. Please refresh the page.';
    exit();
}

// ---- Enrolled students ----
// The students table currently contains the complete student profile fields
// used by the system: LRN, name, email, gender, birthdate, address, guardian
// name/contact, and timestamps. Passwords are intentionally NOT exported.
$studentsStmt = $pdo->prepare("
    SELECT
        st.student_id,
        st.student_lrn,
        st.firstname,
        st.lastname,
        st.middlename,
        st.email,
        st.gender,
        st.birthdate,
        st.address,
        st.guardian_name,
        st.guardian_contact,
        st.created_at AS student_created_at,
        st.updated_at AS student_updated_at,
        e.status AS enrollment_status,
        e.enrolled_at
    FROM enrollments e
    JOIN students st ON st.student_id = e.student_id
    WHERE e.offering_id = ?
      AND e.status NOT IN ('pending','denied')
    ORDER BY e.status = 'enrolled' DESC, st.lastname ASC, st.firstname ASC
");
$studentsStmt->execute([(int) $offering_id]);
$students = $studentsStmt->fetchAll();

require_once __DIR__ . '/lib/xlsx_writer.php';

$offering_id = $_GET['offering_id'] ?? '';
if (!ctype_digit((string) $offering_id)) {
    http_response_code(422);
    header('Content-Type: text/plain');
    exit('Missing or invalid course reference.');
}

$courseStmt = $pdo->prepare("
    SELECT
        co.offering_id, co.capacity, co.quarter, co.school_year_id,
        co.schedule_days, co.start_time, co.end_time,
        s.subject_name, sec.section_name, sec.grade_level, sec.strand,
        sy.label AS school_year_label,
        t.firstname AS teacher_firstname, t.lastname AS teacher_lastname
    FROM classofferings co
    JOIN subjects s ON s.subject_id = co.subject_id
    JOIN sections sec ON sec.section_id = co.section_id
    LEFT JOIN schoolyears sy ON sy.school_year_id = co.school_year_id
    LEFT JOIN teachers t ON t.teacher_id = co.teacher_id
    WHERE co.offering_id = ?
");
$courseStmt->execute([(int) $offering_id]);
$course = $courseStmt->fetch();

if (!$course) {
    http_response_code(404);
    header('Content-Type: text/plain');
    exit('That course no longer exists. Please refresh the page.');
}

$studentsStmt = $pdo->prepare("
    SELECT
        st.student_id, st.student_lrn, st.firstname, st.lastname, st.middlename,
        st.email, st.gender, st.birthdate, st.address, st.guardian_name,
        st.guardian_contact, st.created_at AS student_created_at,
        st.updated_at AS student_updated_at, e.status AS enrollment_status,
        e.enrolled_at
    FROM enrollments e
    JOIN students st ON st.student_id = e.student_id
    WHERE e.offering_id = ?
      AND e.status NOT IN ('pending','denied')
    ORDER BY e.status = 'enrolled' DESC, st.lastname ASC, st.firstname ASC
");
$studentsStmt->execute([(int) $offering_id]);
$students = $studentsStmt->fetchAll();

function exportDateValue($v): string {
    if (!$v) return '— None —';
    $timestamp = strtotime($v);
    return $timestamp ? date('F j, Y', $timestamp) : (string) $v;
}

function exportDateTimeValue($v): string {
    if (!$v) return '— None —';
    $timestamp = strtotime($v);
    return $timestamp ? date('F j, Y g:i A', $timestamp) : (string) $v;
}

function exportScheduleValue($days, $start, $end): string {
    $days = trim((string) $days);
    $parts = [];
    if ($days !== '') $parts[] = $days;
    if ($start && $end) {
        $parts[] = date('g:i A', strtotime($start)) . ' - ' . date('g:i A', strtotime($end));
    } elseif ($start) {
        $parts[] = date('g:i A', strtotime($start));
    }
    return $parts ? implode(' : ', $parts) : '— None —';
}

$teacherName = trim(($course['teacher_firstname'] ?? '') . ' ' . ($course['teacher_lastname'] ?? ''));
$subtitle = 'Grade ' . $course['grade_level']
    . ($course['strand'] ? ' · ' . $course['strand'] : '')
    . ' · Term ' . $course['quarter']
    . ' · School Year ' . ($course['school_year_label'] ?: '—')
    . ' · Schedule ' . exportScheduleValue($course['schedule_days'], $course['start_time'], $course['end_time'])
    . ' · Teacher ' . ($teacherName ?: '— None —');

$rows = [];
foreach ($students as $s) {
    $rows[] = [
        $s['student_id'],
        $s['student_lrn'],
        $s['lastname'],
        $s['firstname'],
        $s['middlename'],
        $s['email'],
        $s['gender'],
        exportDateValue($s['birthdate']),
        $s['address'],
        $s['guardian_name'],
        $s['guardian_contact'],
        $s['enrollment_status'] ? ucfirst($s['enrollment_status']) : '— None —',
        exportDateTimeValue($s['enrolled_at']),
        exportDateTimeValue($s['student_updated_at']),
    ];
}

$namePart = preg_replace('/[^A-Za-z0-9]+/', '', $course['subject_name'])
    . '-Grade' . $course['grade_level']
    . preg_replace('/[^A-Za-z0-9]+/', '', $course['section_name']);

export_xlsx_modern([
    'filename' => $namePart . '_students_' . date('Y-m-d') . '.xlsx',
    'sheet_name' => 'Course Students',
    'band_title' => $course['subject_name'] . ' - ' . $course['section_name'],
    'subtitle_lines' => [
        $subtitle,
        'Generated: ' . date('F j, Y g:i A') . ' · ' . count($students) . ' student' . (count($students) === 1 ? '' : 's'),
    ],
    'columns' => [
        ['label' => 'Student ID', 'width' => 12],
        ['label' => 'LRN', 'width' => 18],
        ['label' => 'Last Name', 'width' => 18],
        ['label' => 'First Name', 'width' => 18],
        ['label' => 'Middle Name', 'width' => 18],
        ['label' => 'Email', 'width' => 30],
        ['label' => 'Gender', 'width' => 12],
        ['label' => 'Birthdate', 'width' => 16],
        ['label' => 'Address', 'width' => 34],
        ['label' => 'Guardian Name', 'width' => 24],
        ['label' => 'Guardian Contact', 'width' => 20],
        ['label' => 'Enrollment Status', 'width' => 18],
        ['label' => 'Enrolled On', 'width' => 22],
        ['label' => 'Student Record Updated', 'width' => 24],
    ],
    'rows' => $rows,
    'status_col' => 11,
    'footer_text' => 'Total enrolled records: ' . count($students),
]);
exit();
