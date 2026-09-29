<?php
/**
 * PhpSpreadsheet export helpers.
 *
 * Provides the same public functions previously exposed by xlsx_writer.php,
 * but now generates real .xlsx files instead of HTML tables disguised as .xls.
 */

require_once __DIR__ . '/../../../../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if (!function_exists('ps_export_text')) {
    function ps_export_text($sheet, string $cell, $value): void
    {
        $sheet->setCellValueExplicit($cell, (string) ($value ?? ''), DataType::TYPE_STRING);
    }
}

if (!function_exists('ps_apply_common_page_setup')) {
    function ps_apply_common_page_setup($sheet): void
    {
        $sheet->freezePane('A5');
        $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()->setFitToWidth(1);
        $sheet->getPageSetup()->setFitToHeight(0);
        $sheet->getPageMargins()->setTop(.5)->setBottom(.5)->setLeft(.35)->setRight(.35);
    }
}

function export_xlsx_modern(array $opts): void
{
    $filename      = $opts['filename'] ?? 'export.xlsx';
    $sheetName     = substr((string) ($opts['sheet_name'] ?? 'Sheet1'), 0, 31);
    $bandTitle     = $opts['band_title'] ?? 'Export';
    $subtitleLines = $opts['subtitle_lines'] ?? [];
    $columns       = $opts['columns'] ?? [];
    $rows          = $opts['rows'] ?? [];
    $statusCol     = $opts['status_col'] ?? null;
    $footerText    = $opts['footer_text'] ?? null;

    $filename = preg_replace('/\.[a-z0-9]+$/i', '', $filename) . '.xlsx';

    $book = new Spreadsheet();
    $sheet = $book->getActiveSheet();
    $sheet->setTitle($sheetName);

    $colCount = max(count($columns), 1);
    $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colCount);

    // Title band.
    $sheet->mergeCells("A1:{$lastCol}1");
    ps_export_text($sheet, 'A1', $bandTitle);
    $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
        'font' => ['bold' => true, 'size' => 15, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A8A']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
    ]);
    $sheet->getRowDimension(1)->setRowHeight(28);

    // Subtitle lines.
    $row = 2;
    foreach ($subtitleLines as $line) {
        $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
        ps_export_text($sheet, "A{$row}", $line);
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
            'font' => ['italic' => true, 'color' => ['rgb' => '475569']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EFF4FF']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $row++;
    }

    $headerRow = $row + 1;
    foreach ($columns as $i => $column) {
        $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
        ps_export_text($sheet, "{$col}{$headerRow}", $column['label'] ?? '');
        $sheet->getColumnDimension($col)->setWidth((float) ($column['width'] ?? 16));
    }

    $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2563EB']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']]],
    ]);

    $statusColors = [
        'active' => ['bg' => 'DCFCE7', 'fg' => '15803D'],
        'enrolled' => ['bg' => 'DCFCE7', 'fg' => '15803D'],
        'completed' => ['bg' => 'DBEAFE', 'fg' => '1D4ED8'],
        'inactive' => ['bg' => 'FEE2E2', 'fg' => 'B91C1C'],
        'dropped' => ['bg' => 'FEE2E2', 'fg' => 'B91C1C'],
        'pending' => ['bg' => 'FEF3C7', 'fg' => 'B45309'],
    ];

    $dataStart = $headerRow + 1;
    foreach ($rows as $ri => $data) {
        $currentRow = $dataStart + $ri;
        $rowBg = ($ri % 2 === 0) ? 'FFFFFF' : 'F8FAFC';

        foreach ($columns as $ci => $_column) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ci + 1);
            $value = $data[$ci] ?? '';

            // Preserve identifiers such as LRNs, usernames, and formatted values as text.
            if (is_int($value) || is_float($value)) {
                $sheet->setCellValue("{$col}{$currentRow}", $value);
            } else {
                ps_export_text($sheet, "{$col}{$currentRow}", $value);
            }

            $sheet->getStyle("{$col}{$currentRow}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $rowBg]],
                'font' => ['color' => ['rgb' => '334155']],
                'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']]],
            ]);

            if ($statusCol !== null && $ci === $statusCol) {
                $key = strtolower(trim((string) $value));
                $sheet->getStyle("{$col}{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                if (isset($statusColors[$key])) {
                    $sheet->getStyle("{$col}{$currentRow}")->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $statusColors[$key]['bg']]],
                        'font' => ['bold' => true, 'color' => ['rgb' => $statusColors[$key]['fg']]],
                    ]);
                    ps_export_text($sheet, "{$col}{$currentRow}", ucfirst($key));
                }
            }
        }
    }

    $lastDataRow = max($headerRow, $dataStart + count($rows) - 1);
    $sheet->setAutoFilter("A{$headerRow}:{$lastCol}{$lastDataRow}");
    if ($footerText !== null) {
        $footerRow = $lastDataRow + 2;
        $sheet->mergeCells("A{$footerRow}:{$lastCol}{$footerRow}");
        ps_export_text($sheet, "A{$footerRow}", $footerText);
        $sheet->getStyle("A{$footerRow}:{$lastCol}{$footerRow}")->applyFromArray([
            'font' => ['italic' => true, 'bold' => true, 'color' => ['rgb' => '64748B']],
        ]);
    }

    ps_apply_common_page_setup($sheet);

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    (new Xlsx($book))->save('php://output');
    $book->disconnectWorksheets();
    exit;
}

function export_xlsx_schedule_grid(array $opts): void
{
    $filename      = preg_replace('/\.[a-z0-9]+$/i', '', (string) ($opts['filename'] ?? 'schedule.xlsx')) . '.xlsx';
    $sheetName     = substr((string) ($opts['sheet_name'] ?? 'Schedule'), 0, 31);
    $bandTitle     = $opts['band_title'] ?? 'Class Schedule';
    $subtitleLines = $opts['subtitle_lines'] ?? [];
    $days          = $opts['days'] ?? ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
    $rows          = $opts['rows'] ?? [];
    $footerText    = $opts['footer_text'] ?? null;

    $book = new Spreadsheet();
    $sheet = $book->getActiveSheet();
    $sheet->setTitle($sheetName);

    $colCount = count($days) + 1;
    $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colCount);

    // Title.
    $sheet->mergeCells("A1:{$lastCol}1");
    ps_export_text($sheet, 'A1', $bandTitle);
    $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
        'font' => ['bold' => true, 'size' => 15, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '14532D']],
        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
    ]);
    $sheet->getRowDimension(1)->setRowHeight(28);

    $row = 2;
    foreach ($subtitleLines as $line) {
        $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
        ps_export_text($sheet, "A{$row}", $line);
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
            'font' => ['italic' => true, 'color' => ['rgb' => '475569']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E8F5EE']],
            'alignment' => ['wrapText' => true, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $row++;
    }

    $headerRow = $row + 1;
    $headers = array_merge(['TIME'], $days);
    foreach ($headers as $i => $label) {
        $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
        ps_export_text($sheet, "{$col}{$headerRow}", strtoupper($label));
        $sheet->getColumnDimension($col)->setWidth($i === 0 ? 18 : 23);
    }
    $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '14532D']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']]],
    ]);

    $currentRow = $headerRow + 1;
    foreach ($rows as $ri => $gridRow) {
        $isRecess = ($gridRow['type'] ?? 'class') === 'recess';

        if ($isRecess) {
            ps_export_text($sheet, "A{$currentRow}", $gridRow['time_label'] ?? '');
            $sheet->mergeCells("B{$currentRow}:{$lastCol}{$currentRow}");
            ps_export_text($sheet, "B{$currentRow}", $gridRow['label'] ?? 'RECESS');
            $sheet->getStyle("A{$currentRow}:{$lastCol}{$currentRow}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            ]);
            $sheet->getStyle("A{$currentRow}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '14532D']],
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            ]);
            $sheet->getStyle("B{$currentRow}:{$lastCol}{$currentRow}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FEF3C7']],
                'font' => ['bold' => true, 'color' => ['rgb' => 'B45309']],
            ]);
            $sheet->getRowDimension($currentRow)->setRowHeight(24);
            $currentRow++;
            continue;
        }

        $subjectRow = $currentRow;
        $teacherRow = $currentRow + 1;
        ps_export_text($sheet, "A{$subjectRow}", $gridRow['time_label'] ?? '');
        $sheet->mergeCells("A{$subjectRow}:A{$teacherRow}");

        $rowBg = ($ri % 2 === 0) ? 'FFFFFF' : 'E8F5EE';
        foreach ($days as $di => $day) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($di + 2);
            $cell = $gridRow['cells'][$day] ?? null;
            if ($cell) {
                ps_export_text($sheet, "{$col}{$subjectRow}", $cell['subject'] ?? '');
                ps_export_text($sheet, "{$col}{$teacherRow}", $cell['teacher'] ?? '');
                $sheet->getStyle("{$col}{$subjectRow}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $rowBg]],
                    'font' => ['bold' => true, 'color' => ['rgb' => '14532D']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                    'borders' => ['left' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']], 'right' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']], 'top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']]],
                ]);
                $sheet->getStyle("{$col}{$teacherRow}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $rowBg]],
                    'font' => ['size' => 10, 'color' => ['rgb' => '14532D']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                    'borders' => ['left' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']], 'right' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']], 'bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']]],
                ]);
            } else {
                $sheet->mergeCells("{$col}{$subjectRow}:{$col}{$teacherRow}");
                ps_export_text($sheet, "{$col}{$subjectRow}", '');
                $sheet->getStyle("{$col}{$subjectRow}:{$col}{$teacherRow}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $rowBg]],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']]],
                ]);
            }
        }

        $sheet->getStyle("A{$subjectRow}:A{$teacherRow}")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '14532D']],
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']]],
        ]);

        $sheet->getRowDimension($subjectRow)->setRowHeight(24);
        $sheet->getRowDimension($teacherRow)->setRowHeight(22);
        $currentRow += 2;
    }

    if ($footerText !== null) {
        $footerRow = $currentRow + 1;
        $sheet->mergeCells("A{$footerRow}:{$lastCol}{$footerRow}");
        ps_export_text($sheet, "A{$footerRow}", $footerText);
        $sheet->getStyle("A{$footerRow}:{$lastCol}{$footerRow}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '14532D']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
        ]);
    }

    $sheet->freezePane("B" . ($headerRow + 1));
    $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
    $sheet->getPageSetup()->setFitToWidth(1);
    $sheet->getPageSetup()->setFitToHeight(0);
    $sheet->getPageMargins()->setTop(.5)->setBottom(.5)->setLeft(.35)->setRight(.35);

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    (new Xlsx($book))->save('php://output');
    $book->disconnectWorksheets();
    exit;
}
