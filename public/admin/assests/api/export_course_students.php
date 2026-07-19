<?php
/**
 * Excel export for the "View Students" modal in courses.php.
 * Same data/query as get_course_students.php, but streams a formatted
 * .xls file (HTML table understood natively by Excel) instead of JSON.
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
        s.subject_name,
        sec.section_name,
        sec.grade_level,
        sec.strand
    FROM classofferings co
    JOIN subjects s   ON s.subject_id = co.subject_id
    JOIN sections sec ON sec.section_id = co.section_id
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
$studentsStmt = $pdo->prepare("
    SELECT
        st.student_lrn,
        st.firstname,
        st.lastname,
        st.middlename,
        st.email,
        e.status,
        e.enrolled_at
    FROM enrollments e
    JOIN students st ON st.student_id = e.student_id
    WHERE e.offering_id = ?
    ORDER BY e.status = 'active' DESC, st.lastname ASC, st.firstname ASC
");
$studentsStmt->execute([(int) $offering_id]);
$students = $studentsStmt->fetchAll();

// ---- Filename, e.g. Math-Grade7-Einstein_2026-07-20.xls ----
$namePart = preg_replace('/[^A-Za-z0-9]+/', '', $course['subject_name'])
    . '-Grade' . $course['grade_level']
    . preg_replace('/[^A-Za-z0-9]+/', '', $course['section_name']);
$filename = $namePart . '_' . date('Y-m-d') . '.xls';

header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

function h($v) {
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

$colCount = 7;
$sheetTitle = $course['subject_name'] . ' - ' . $course['section_name'];
$enrolledCount = count($students);

echo "\xEF\xBB\xBF"; // UTF-8 BOM so Excel keeps accented characters intact
?>
<html xmlns:x="urn:schemas-microsoft-com:office:excel">
<head>
<!--[if gte mso 9]>
<xml>
<x:ExcelWorkbook>
  <x:ExcelWorksheets>
    <x:ExcelWorksheet>
      <x:Name>Enrolled Students</x:Name>
      <x:WorksheetOptions>
        <x:DisplayGridlines/>
      </x:WorksheetOptions>
    </x:ExcelWorksheet>
  </x:ExcelWorksheets>
</x:ExcelWorkbook>
</xml>
<![endif]-->
<style>
    table   { border-collapse: collapse; font-family: Calibri, Arial, sans-serif; font-size: 11pt; }
    .title  { font-size: 14pt; font-weight: bold; color: #1f2937; mso-number-format: '\@'; }
    .subtitle { font-size: 10pt; color: #6b7280; mso-number-format: '\@'; }
    th {
        background-color: #2563eb;
        color: #ffffff;
        font-weight: bold;
        text-align: left;
        padding: 6px 10px;
        border: 1px solid #1d4ed8;
        mso-number-format: '\@';
    }
    td {
        padding: 6px 10px;
        border: 1px solid #d1d5db;
        mso-number-format: '\@';
    }
    .row-even { background-color: #ffffff; }
    .row-odd  { background-color: #f3f4f6; }
    .status-active   { color: #15803d; font-weight: bold; }
    .status-inactive { color: #b91c1c; font-weight: bold; }
</style>
</head>
<body>
<table>
    <col style="width:120px">
    <col style="width:150px">
    <col style="width:150px">
    <col style="width:150px">
    <col style="width:220px">
    <col style="width:90px">
    <col style="width:110px">

    <tr><td class="title" colspan="<?= $colCount ?>"><?= h($sheetTitle) ?></td></tr>
    <tr>
        <td class="subtitle" colspan="<?= $colCount ?>">
            Grade <?= h($course['grade_level']) ?><?= $course['strand'] ? ' &middot; ' . h($course['strand']) : '' ?>
            &nbsp;&middot;&nbsp; <?= h($enrolledCount) ?>/<?= h($course['capacity']) ?> enrolled
            &nbsp;&middot;&nbsp; Exported <?= h(date('F j, Y')) ?>
        </td>
    </tr>
    <tr><td colspan="<?= $colCount ?>" style="border:none; padding: 4px;"></td></tr>

    <tr>
        <th>LRN</th>
        <th>Last Name</th>
        <th>First Name</th>
        <th>Middle Name</th>
        <th>Email</th>
        <th>Status</th>
        <th>Enrolled On</th>
    </tr>

    <?php if ($enrolledCount === 0): ?>
    <tr>
        <td colspan="<?= $colCount ?>" style="text-align:center; color:#6b7280; padding: 14px;">
            No students enrolled in this course yet.
        </td>
    </tr>
    <?php else: ?>
        <?php foreach ($students as $i => $s): ?>
            <?php
                $rowClass = ($i % 2 === 0) ? 'row-even' : 'row-odd';
                $status = $s['status'] ? ucfirst($s['status']) : '';
                $statusClass = ($s['status'] === 'active') ? 'status-active' : 'status-inactive';
                $enrolledOn = $s['enrolled_at'] ? date('Y-m-d', strtotime($s['enrolled_at'])) : '';
            ?>
            <tr class="<?= $rowClass ?>">
                <td><?= h($s['student_lrn']) ?></td>
                <td><?= h($s['lastname']) ?></td>
                <td><?= h($s['firstname']) ?></td>
                <td><?= h($s['middlename']) ?></td>
                <td><?= $s['email'] ? h($s['email']) : '&mdash; None &mdash;' ?></td>
                <td class="<?= $statusClass ?>"><?= h($status) ?></td>
                <td><?= h($enrolledOn) ?></td>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
</table>
</body>
</html>
<?php
exit();