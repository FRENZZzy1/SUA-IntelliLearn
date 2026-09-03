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
    ORDER BY e.status = 'active' DESC, st.lastname ASC, st.firstname ASC
");
$studentsStmt->execute([(int) $offering_id]);
$students = $studentsStmt->fetchAll();

function h($v) {
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

function displayValue($v) {
    return ($v !== null && trim((string) $v) !== '') ? h($v) : '&mdash; None &mdash;';
}

function formatDateValue($v) {
    if (!$v) return '&mdash; None &mdash;';
    $timestamp = strtotime($v);
    return $timestamp ? h(date('F j, Y', $timestamp)) : h($v);
}

function formatDateTimeValue($v) {
    if (!$v) return '&mdash; None &mdash;';
    $timestamp = strtotime($v);
    return $timestamp ? h(date('F j, Y g:i A', $timestamp)) : h($v);
}

function formatSchedule($days, $start, $end) {
    $days = trim((string) $days);
    $parts = [];
    if ($days !== '') $parts[] = $days;
    if ($start && $end) {
        $parts[] = date('g:i A', strtotime($start)) . ' - ' . date('g:i A', strtotime($end));
    } elseif ($start) {
        $parts[] = date('g:i A', strtotime($start));
    }
    return $parts ? h(implode(' : ', $parts)) : '&mdash; None &mdash;';
}

$namePart = preg_replace('/[^A-Za-z0-9]+/', '', $course['subject_name'])
    . '-Grade' . $course['grade_level']
    . preg_replace('/[^A-Za-z0-9]+/', '', $course['section_name']);
$filename = $namePart . '_students_' . date('Y-m-d') . '.xls';

header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

echo "\xEF\xBB\xBF";
?>
<html xmlns:x="urn:schemas-microsoft-com:office:excel">
<head>
<!--[if gte mso 9]>
<xml>
<x:ExcelWorkbook>
  <x:ExcelWorksheets>
    <x:ExcelWorksheet>
      <x:Name>Course Students</x:Name>
      <x:WorksheetOptions>
        <x:DisplayGridlines/>
      </x:WorksheetOptions>
    </x:ExcelWorksheet>
  </x:ExcelWorksheets>
</x:ExcelWorkbook>
</xml>
<![endif]-->
<style>
    table { border-collapse: collapse; font-family: Calibri, Arial, sans-serif; font-size: 11pt; }
    .title { font-size: 14pt; font-weight: bold; color: #1f2937; }
    .subtitle { font-size: 10pt; color: #6b7280; }
    th { background-color: #2563eb; color: #ffffff; font-weight: bold; text-align: left; padding: 6px 10px; border: 1px solid #1d4ed8; white-space: nowrap; }
    td { padding: 6px 10px; border: 1px solid #d1d5db; vertical-align: top; mso-number-format: '\@'; }
    .row-even { background-color: #ffffff; }
    .row-odd { background-color: #f3f4f6; }
    .status-active { color: #15803d; font-weight: bold; }
    .status-inactive { color: #b91c1c; font-weight: bold; }
</style>
</head>
<body>
<table>
    <tr><td class="title" colspan="14"><?= h($course['subject_name']) ?> - <?= h($course['section_name']) ?></td></tr>
    <tr>
        <td class="subtitle" colspan="14">
            Grade <?= h($course['grade_level']) ?><?= $course['strand'] ? ' &middot; ' . h($course['strand']) : '' ?>
            &nbsp;&middot;&nbsp; Term <?= h($course['quarter']) ?>
            &nbsp;&middot;&nbsp; School Year <?= displayValue($course['school_year_label']) ?>
            &nbsp;&middot;&nbsp; Schedule <?= formatSchedule($course['schedule_days'], $course['start_time'], $course['end_time']) ?>
            &nbsp;&middot;&nbsp; Teacher <?= displayValue(trim(($course['teacher_firstname'] ?? '') . ' ' . ($course['teacher_lastname'] ?? ''))) ?>
        </td>
    </tr>
    <tr><td colspan="14" style="border:none; padding:4px;"></td></tr>
    <tr>
        <th>Student ID</th>
        <th>LRN</th>
        <th>Last Name</th>
        <th>First Name</th>
        <th>Middle Name</th>
        <th>Email</th>
        <th>Gender</th>
        <th>Birthdate</th>
        <th>Address</th>
        <th>Guardian Name</th>
        <th>Guardian Contact</th>
        <th>Enrollment Status</th>
        <th>Enrolled On</th>
        <th>Student Record Updated</th>
    </tr>

    <?php if (count($students) === 0): ?>
    <tr>
        <td colspan="14" style="text-align:center; color:#6b7280; padding:14px;">No students enrolled in this course yet.</td>
    </tr>
    <?php else: ?>
        <?php foreach ($students as $i => $s): ?>
            <?php
                $rowClass = ($i % 2 === 0) ? 'row-even' : 'row-odd';
                $status = $s['enrollment_status'] ? ucfirst($s['enrollment_status']) : '';
                $statusClass = ($s['enrollment_status'] === 'active') ? 'status-active' : 'status-inactive';
            ?>
            <tr class="<?= $rowClass ?>">
                <td><?= displayValue($s['student_id']) ?></td>
                <td><?= displayValue($s['student_lrn']) ?></td>
                <td><?= displayValue($s['lastname']) ?></td>
                <td><?= displayValue($s['firstname']) ?></td>
                <td><?= displayValue($s['middlename']) ?></td>
                <td><?= displayValue($s['email']) ?></td>
                <td><?= displayValue($s['gender']) ?></td>
                <td><?= formatDateValue($s['birthdate']) ?></td>
                <td><?= displayValue($s['address']) ?></td>
                <td><?= displayValue($s['guardian_name']) ?></td>
                <td><?= displayValue($s['guardian_contact']) ?></td>
                <td class="<?= $statusClass ?>"><?= displayValue($status) ?></td>
                <td><?= formatDateTimeValue($s['enrolled_at']) ?></td>
                <td><?= formatDateTimeValue($s['student_updated_at']) ?></td>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
</table>
</body>
</html>
<?php
exit();