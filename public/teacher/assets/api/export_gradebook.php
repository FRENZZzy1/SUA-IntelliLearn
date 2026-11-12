<?php
// public/teacher/assets/api/export_gradebook.php?offering_id=12
// Exports one class's gradebook (one term) to a styled .xlsx file.
//
// Layout:
//   - one row per student, ONE "Student Name" column (Firstname Lastname)
//   - one column per activity, grouped under Written Works / Performance Tasks / Exam,
//     each header showing the activity's maximum points ("/ 20 pts")
//   - each cell holds the student's SCORE ("—" = not submitted, "Pending" = submitted
//     but not yet scored)
//   - styled with the system theme (dark green / gold), not the blue admin export theme
require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../admin/assests/api/lib/xlsx_writer.php';
require_once __DIR__ . '/gradebook_status_functions.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

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
    WHERE co.section_id NOT IN (SELECT section_id FROM sections WHERE is_archived = 1) AND co.offering_id = ? AND co.teacher_id = ? AND co.status = 'active'
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
$scores = $loaded['scores'];

// ---- Theme (same tokens as the system CSS) --------------------------------------
const GBX_DARK   = '10281E'; // --primary-dark
const GBX_PRIMARY = '1B4332'; // --primary
const GBX_LIGHT  = '2D6A4F'; // --primary-light
const GBX_ACCENT = 'F4A300'; // --accent (gold)
const GBX_MINT   = 'E6F1EB';
const GBX_ZEBRA  = 'F5F9F7';
const GBX_BORDER = 'E3E9E6';

const ITEM_COL_WIDTH = 18;

function gbx_fmt($n): string
{
    return rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');
}

/** Score cell colours = same bands as the on-screen student score page. */
function gbx_score_style(?float $pct): array
{
    if ($pct === null) return ['bg' => 'FFFFFF', 'fg' => '334155'];
    if ($pct >= 90) return ['bg' => 'ECFDF5', 'fg' => '047857'];
    if ($pct >= 75) return ['bg' => 'EFF6FF', 'fg' => '2563EB'];
    if ($pct >= 60) return ['bg' => 'FFFBEB', 'fg' => 'B45309'];
    return ['bg' => 'FEF2F2', 'fg' => 'DC2626'];
}

// ---- Column map ---------------------------------------------------------------------
// A = Student Name (Firstname Lastname), B = LRN, then one column per activity.
$FIXED = 2;
$colIdx = $FIXED;               // last used column index
$groups = [];                   // category => [first, last, count, points]
$itemCols = [];                 // list of ['col' => idx, 'item' => item]
foreach (GB_CATEGORIES as $key => $label) {
    if (!$items[$key]) continue;
    $first = $colIdx + 1;
    $points = 0.0;
    foreach ($items[$key] as $it) {
        $colIdx++;
        $itemCols[] = ['col' => $colIdx, 'item' => $it];
        $points += (float) $it['max'];
    }
    $groups[$key] = ['label' => $label, 'first' => $first, 'last' => $colIdx, 'count' => count($items[$key]), 'points' => $points];
}
$statusFirst = $colIdx + 1;
$colIdx += count(GB_CATEGORIES);   // completion columns (one per category)
$lastColIdx = $colIdx;
$L = fn(int $i): string => Coordinate::stringFromColumnIndex($i);
$lastCol = $L($lastColIdx);

// ---- Titles / filename ---------------------------------------------------------------
$termNo = (int) substr($class['quarter'], -1);

$subtitle = 'Grade ' . $class['grade_level']
    . ' · Term ' . $termNo
    . ' · School Year ' . ($class['school_year_label'] ?: '—')
    . ' · Teacher ' . $teacherName;

$namePart = preg_replace('/[^A-Za-z0-9]+/', '', $class['subject_name'])
    . '-Grade' . $class['grade_level']
    . preg_replace('/[^A-Za-z0-9]+/', '', $class['section_name']);
$filename = $namePart . '_gradebook_Term' . $termNo . '_' . date('Y-m-d') . '.xlsx';

$thin = ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => GBX_BORDER]];

// ---- Build the workbook ---------------------------------------------------------------
$book  = new Spreadsheet();
$sheet = $book->getActiveSheet();
$sheet->setTitle('Gradebook');
$sheet->setShowGridlines(false);

// Row 1: title band
$sheet->mergeCells("A1:{$lastCol}1");
ps_export_text($sheet, 'A1', $class['subject_name'] . ' - ' . $class['section_name']);
$sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
    'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => GBX_PRIMARY]],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
    'borders' => ['bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => GBX_ACCENT]]],
]);
$sheet->getRowDimension(1)->setRowHeight(32);

// Rows 2-3: subtitle lines
$subLines = [
    $subtitle,
    'Generated: ' . date('F j, Y g:i A') . ' · ' . count($students) . ' student' . (count($students) === 1 ? '' : 's'),
];
$r = 2;
foreach ($subLines as $line) {
    $sheet->mergeCells("A{$r}:{$lastCol}{$r}");
    ps_export_text($sheet, "A{$r}", $line);
    $sheet->getStyle("A{$r}:{$lastCol}{$r}")->applyFromArray([
        'font' => ['italic' => true, 'color' => ['rgb' => GBX_LIGHT]],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => GBX_MINT]],
        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
    ]);
    $sheet->getRowDimension($r)->setRowHeight(20);
    $r++;
}
$sheet->getRowDimension(4)->setRowHeight(8); // spacer

$groupRow  = 5;
$headerRow = 6;
$dataStart = 7;

// Row 5: category band (Written Works / Performance Tasks / Exam / Completion)
$sheet->mergeCells("A{$groupRow}:B{$groupRow}");
ps_export_text($sheet, "A{$groupRow}", 'STUDENT');
$sheet->getStyle("A{$groupRow}:B{$groupRow}")->applyFromArray([
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => GBX_DARK]],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
]);

$bandColors = ['ww' => [GBX_LIGHT, 'FFFFFF'], 'pt' => ['3B8061', 'FFFFFF'], 'exam' => [GBX_ACCENT, GBX_DARK]];
foreach ($groups as $key => $g) {
    $a = $L($g['first']) . $groupRow;
    $b = $L($g['last']) . $groupRow;
    if ($g['first'] !== $g['last']) $sheet->mergeCells("{$a}:{$b}");
    $pts = $g['points'] > 0 ? ' · ' . gbx_fmt($g['points']) . ' pts' : '';
    ps_export_text($sheet, $a, strtoupper($g['label']) . "\n" . $g['count'] . ($g['count'] === 1 ? ' item' : ' items') . $pts);
    [$bg, $fg] = $bandColors[$key];
    $sheet->getStyle("{$a}:{$b}")->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => $fg]],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bg]],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        'borders' => ['left' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']]],
    ]);
}
$a = $L($statusFirst) . $groupRow;
$sheet->mergeCells("{$a}:{$lastCol}{$groupRow}");
ps_export_text($sheet, $a, 'COMPLETION');
$sheet->getStyle("{$a}:{$lastCol}{$groupRow}")->applyFromArray([
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => GBX_DARK]],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders' => ['left' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']]],
]);
$sheet->getRowDimension($groupRow)->setRowHeight(36);

// Row 6: column headers (activity title + max points)
ps_export_text($sheet, "A{$headerRow}", 'Student Name');
ps_export_text($sheet, "B{$headerRow}", 'LRN');
$sheet->getColumnDimension('A')->setWidth(30);
$sheet->getColumnDimension('B')->setWidth(16);
foreach ($itemCols as $ic) {
    $c = $L($ic['col']);
    $max = (float) $ic['item']['max'];
    ps_export_text($sheet, "{$c}{$headerRow}", $ic['item']['title'] . ($max > 0 ? "\n/ " . gbx_fmt($max) . ' pts' : ''));
    $sheet->getColumnDimension($c)->setWidth(ITEM_COL_WIDTH);
}
$i = 0;
foreach (GB_CATEGORIES as $label) {
    $c = $L($statusFirst + $i++);
    ps_export_text($sheet, "{$c}{$headerRow}", $label);
    $sheet->getColumnDimension($c)->setWidth(15);
}
$sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->applyFromArray([
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => GBX_PRIMARY]],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
    'borders' => [
        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => GBX_LIGHT]],
        'bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => GBX_ACCENT]],
    ],
]);
// Header height grows with the longest activity title (wrapped) + the "/ N pts" line
$headerLines = 2;
foreach ($itemCols as $ic) {
    $headerLines = max($headerLines, (int) ceil(mb_strlen($ic['item']['title']) / (ITEM_COL_WIDTH - 3)) + 1);
}
$sheet->getRowDimension($headerRow)->setRowHeight(min(160, max(48, $headerLines * 15 + 8)));

// ---- Data rows --------------------------------------------------------------------------
$completeCnt = ['ww' => 0, 'pt' => 0, 'exam' => 0];
$n = count($students);
foreach ($students as $ri => $s) {
    $row  = $dataStart + $ri;
    $sid  = (int) $s['student_id'];
    $mine = $done[$sid] ?? [];
    $myScores = $scores[$sid] ?? [];
    $zebra = ($ri % 2 === 0) ? 'FFFFFF' : GBX_ZEBRA;

    // base style for the whole row
    $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $zebra]],
        'font' => ['color' => ['rgb' => '334155']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => $thin],
    ]);
    $sheet->getRowDimension($row)->setRowHeight(22);

    // Firstname + Lastname merged in one column
    ps_export_text($sheet, "A{$row}", trim($s['firstname'] . ' ' . $s['lastname']));
    ps_export_text($sheet, "B{$row}", (string) $s['student_lrn']);
    $sheet->getStyle("A{$row}")->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => GBX_DARK]],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'indent' => 1],
    ]);

    // Scores
    foreach ($itemCols as $ic) {
        $it  = $ic['item'];
        $uid = $it['uid'];
        $cell = $L($ic['col']) . $row;

        if (empty($mine[$uid])) {                       // not submitted
            ps_export_text($sheet, $cell, '—');
            $sheet->getStyle($cell)->getFont()->getColor()->setRGB('94A3B8');
            continue;
        }
        $sc = $myScores[$uid] ?? null;
        if ($sc === null || $sc['score'] === null) {    // submitted, not scored yet
            ps_export_text($sheet, $cell, 'Pending');
            $sheet->getStyle($cell)->applyFromArray(['font' => ['italic' => true, 'color' => ['rgb' => '94A3B8']]]);
            continue;
        }
        $max = $sc['max'] > 0 ? (float) $sc['max'] : (float) $it['max'];
        $pct = $max > 0 ? ($sc['score'] / $max) * 100 : null;
        $st  = gbx_score_style($pct);
        $sheet->setCellValue($cell, (float) $sc['score']);
        $sheet->getStyle($cell)->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $st['bg']]],
            'font' => ['bold' => true, 'color' => ['rgb' => $st['fg']]],
        ]);
    }

    // Completion per category
    $i = 0;
    foreach (GB_CATEGORIES as $key => $label) {
        $cell = $L($statusFirst + $i++) . $row;
        $stt = gb_category_status($items[$key], $mine);
        if ($stt === 'complete') $completeCnt[$key]++;
        ps_export_text($sheet, $cell, gb_status_label($stt));
        if ($stt === 'complete') {
            $sheet->getStyle($cell)->applyFromArray(['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DCFCE7']], 'font' => ['bold' => true, 'color' => ['rgb' => '15803D']]]);
        } elseif ($stt === 'incomplete') {
            $sheet->getStyle($cell)->applyFromArray(['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FEE2E2']], 'font' => ['bold' => true, 'color' => ['rgb' => 'B91C1C']]]);
        } else {
            $sheet->getStyle($cell)->getFont()->getColor()->setRGB('94A3B8');
        }
    }
}

$lastDataRow = max($headerRow, $dataStart + $n - 1);
if ($n === 0) {
    $sheet->mergeCells("A{$dataStart}:{$lastCol}{$dataStart}");
    ps_export_text($sheet, "A{$dataStart}", 'No enrolled students in this class.');
    $sheet->getStyle("A{$dataStart}")->applyFromArray(['font' => ['italic' => true, 'color' => ['rgb' => '64748B']], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]]);
    $lastDataRow = $dataStart;
}

// ---- Footer ------------------------------------------------------------------------------
$f = $lastDataRow + 2;
$footerLines = [
    'Complete - Written Works: ' . $completeCnt['ww'] . '/' . $n
        . ' · Performance Tasks: ' . $completeCnt['pt'] . '/' . $n
        . ' · Exam: ' . $completeCnt['exam'] . '/' . $n,
    'Legend: number = score earned · — = not submitted · Pending = submitted, waiting to be scored',
];
foreach ($footerLines as $k => $line) {
    $rr = $f + $k;
    $sheet->mergeCells("A{$rr}:{$lastCol}{$rr}");
    ps_export_text($sheet, "A{$rr}", $line);
    $sheet->getStyle("A{$rr}")->applyFromArray(['font' => ['italic' => true, 'bold' => $k === 0, 'color' => ['rgb' => GBX_LIGHT]]]);
}

// ---- View / print setup ----------------------------------------------------------------
$sheet->freezePane('C' . $dataStart);   // keep name + LRN and the headers visible while scrolling
$sheet->setAutoFilter("A{$headerRow}:{$lastCol}{$lastDataRow}");
$sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
$sheet->getPageSetup()->setFitToWidth(1);
$sheet->getPageSetup()->setFitToHeight(0);
$sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd($groupRow, $headerRow);
$sheet->getPageMargins()->setTop(.5)->setBottom(.5)->setLeft(.35)->setRight(.35);

while (ob_get_level() > 0) {
    ob_end_clean();
}
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

(new Xlsx($book))->save('php://output');
$book->disconnectWorksheets();
exit();