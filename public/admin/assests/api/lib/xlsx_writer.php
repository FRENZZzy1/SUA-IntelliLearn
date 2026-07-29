<?php
/**
 * xlsx_writer.php  (zero-dependency version)
 * ------------------------------------------------------------------
 * Builds a modern-styled Excel-compatible export WITHOUT PHP's
 * ZipArchive extension. Uses the same trick most simple PHP export
 * scripts use: an HTML table served with the classic Excel MIME type
 * and a .xls filename. Excel, LibreOffice, and Google Sheets all open
 * this correctly and keep the colors/borders/fonts.
 *
 * Keeps the exact same function name + $opts shape as before, so
 * export_teacher_classes.php, export_section_classes.php, and
 * export_student_classes.php do NOT need any changes.
 *
 * Usage:
 *
 *   require_once __DIR__ . '/lib/xlsx_writer.php';
 *
 *   export_xlsx_modern([
 *       'filename'       => 'teacher_classes_rose.xls',
 *       'sheet_name'     => 'Classes',
 *       'band_title'     => 'Teacher Class Export',
 *       'subtitle_lines' => [
 *           'Teacher: Rose Dela Cruz · English Department',
 *           'Generated: July 25, 2026 3:45 PM · 6 classes',
 *       ],
 *       'columns' => [
 *           ['label' => 'Subject', 'width' => 22],
 *           ['label' => 'Section', 'width' => 18],
 *           ['label' => 'Status',  'width' => 12],
 *       ],
 *       'rows' => [
 *           ['English 7', 'Rizal', 'active'],
 *           ['English 8', 'Mabini', 'inactive'],
 *       ],
 *       'status_col' => 2, // 0-based index into columns
 *   ]);
 *   exit();
 */

if (!function_exists('xlsx_html_escape')) {
    function xlsx_html_escape(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Builds + streams a modern-styled Excel export (HTML-table based .xls) to the
 * browser, then exits. Same $opts shape as the ZipArchive version.
 *
 * @param array $opts {
 *   string   filename        Download filename, e.g. "export.xls"
 *   string   sheet_name      Sheet tab name shown in Excel
 *   string   band_title      Big colored title band text
 *   string[] subtitle_lines  One or more lines shown under the title band
 *   array    columns         [['label'=>string, 'width'=>float], ...]  (width in ~characters)
 *   array    rows            Array of arrays; each inner array has one value per column
 *   int|null status_col      0-based column index to render as a colored status pill
 *   string   footer_text     Optional line shown after the table (e.g. total count)
 * }
 */
function export_xlsx_modern(array $opts): void
{
    $filename      = $opts['filename']       ?? 'export.xls';
    $sheetName     = $opts['sheet_name']      ?? 'Sheet1';
    $bandTitle     = $opts['band_title']      ?? 'Export';
    $subtitleLines = $opts['subtitle_lines']  ?? [];
    $columns       = $opts['columns']         ?? [];
    $rows          = $opts['rows']            ?? [];
    $statusCol     = $opts['status_col']      ?? null;
    $footerText    = $opts['footer_text']     ?? null;

    $colCount = max(count($columns), 1);

    // Force a .xls extension — this is an HTML table, not a real binary
    // .xls/.xlsx, but Excel has supported opening HTML saved as .xls for
    // decades, and it needs no PHP extensions at all.
    $filename = preg_replace('/\.[a-z0-9]+$/i', '', $filename) . '.xls';

    // ---- Status pill color map (lowercased match) ----
    $statusColors = [
        'active'    => ['bg' => '#dcfce7', 'fg' => '#15803d'],
        'enrolled'  => ['bg' => '#dcfce7', 'fg' => '#15803d'],
        'completed' => ['bg' => '#dbeafe', 'fg' => '#1d4ed8'],
        'inactive'  => ['bg' => '#fee2e2', 'fg' => '#b91c1c'],
        'dropped'   => ['bg' => '#fee2e2', 'fg' => '#b91c1c'],
        'pending'   => ['bg' => '#fef3c7', 'fg' => '#b45309'],
    ];

    // ================= COLUMN WIDTHS (<colgroup>) =================
    $colsHtml = '';
    foreach ($columns as $col) {
        // Excel's HTML importer reads <col width="..."> in roughly "character" units.
        $chars = (int) round($col['width'] ?? 16);
        $colsHtml .= '<col width="' . $chars . '">';
    }

    // ================= BUILD THE <table> BODY =================
    $body  = '<table border="0" cellspacing="0" cellpadding="0" '
        . 'style="border-collapse:collapse; font-family:Calibri,Arial,sans-serif; font-size:11pt;">';
    $body .= '<colgroup>' . $colsHtml . '</colgroup>';

    // Title band
    $body .= '<tr><td colspan="' . $colCount . '" height="34" '
        . 'style="background:#1e3a8a; color:#ffffff; font-size:15pt; font-weight:bold; padding:8px 10px;">'
        . xlsx_html_escape($bandTitle) . '</td></tr>';

    // Subtitle line(s)
    foreach ($subtitleLines as $line) {
        $body .= '<tr><td colspan="' . $colCount . '" height="20" '
            . 'style="background:#eff4ff; color:#475569; font-style:italic; padding:4px 10px;">'
            . xlsx_html_escape($line) . '</td></tr>';
    }

    // Spacer row
    $body .= '<tr><td colspan="' . $colCount . '" height="6" style="font-size:1pt; line-height:1pt;">&nbsp;</td></tr>';

    // Header row
    $body .= '<tr>';
    foreach ($columns as $col) {
        $body .= '<td style="background:#2563eb; color:#ffffff; font-weight:bold; text-align:center; '
            . 'padding:6px 8px; border:1px solid #e2e8f0;">' . xlsx_html_escape($col['label'] ?? '') . '</td>';
    }
    $body .= '</tr>';

    // Data rows (banded)
    if (empty($rows)) {
        $body .= '<tr><td colspan="' . $colCount . '" style="padding:10px; border:1px solid #e2e8f0; color:#64748b;">'
            . 'No records found.</td></tr>';
    }

    foreach ($rows as $ri => $row) {
        $isEven = ($ri % 2) === 0;
        $rowBg  = $isEven ? '#ffffff' : '#f8fafc';
        $body  .= '<tr>';

        foreach ($columns as $ci => $col) {
            $value     = $row[$ci] ?? '';
            $isNumeric = is_int($value) || is_float($value);
            // Force text formatting on non-numeric cells so LRNs / leading zeros /
            // fractions like "3/25" don't get auto-converted by Excel.
            $numFmt = $isNumeric ? '' : "mso-number-format:'\\@';";

            if ($statusCol !== null && $ci === $statusCol) {
                $key = strtolower(trim((string) $value));
                if (isset($statusColors[$key])) {
                    $c = $statusColors[$key];
                    $body .= '<td style="background:' . $c['bg'] . '; color:' . $c['fg'] . '; font-weight:bold; '
                        . 'text-align:center; padding:5px 8px; border:1px solid #e2e8f0; ' . $numFmt . '">'
                        . xlsx_html_escape(ucfirst($key)) . '</td>';
                    continue;
                }
                $body .= '<td style="background:' . $rowBg . '; text-align:center; padding:5px 8px; '
                    . 'border:1px solid #e2e8f0; color:#334155; ' . $numFmt . '">'
                    . xlsx_html_escape((string) $value) . '</td>';
                continue;
            }

            $align = $isNumeric ? 'right' : 'left';
            $body .= '<td style="background:' . $rowBg . '; text-align:' . $align . '; padding:5px 8px; '
                . 'border:1px solid #e2e8f0; color:#334155; ' . $numFmt . '">'
                . xlsx_html_escape((string) $value) . '</td>';
        }

        $body .= '</tr>';
    }

    // Footer note
    if ($footerText !== null) {
        $body .= '<tr><td colspan="' . $colCount . '" height="6" style="font-size:1pt; line-height:1pt;">&nbsp;</td></tr>';
        $body .= '<tr><td colspan="' . $colCount . '" style="font-style:italic; font-weight:bold; color:#64748b; padding:4px 10px;">'
            . xlsx_html_escape($footerText) . '</td></tr>';
    }

    $body .= '</table>';

    // ================= FULL DOCUMENT (Excel-flavored HTML) =================
    // The <xml> block (MSO conditional comment) tells Excel what to name the
    // worksheet tab; ignored harmlessly by everything else that opens it.
    $doc = '<html xmlns:o="urn:schemas-microsoft-com:office:office" '
        . 'xmlns:x="urn:schemas-microsoft-com:office:excel" '
        . 'xmlns="http://www.w3.org/TR/REC-html40">'
        . '<head><meta charset="UTF-8">'
        . '<!--[if gte mso 9]><xml>'
        . '<x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet>'
        . '<x:Name>' . xlsx_html_escape(substr($sheetName, 0, 31)) . '</x:Name>'
        . '<x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions>'
        . '</x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook>'
        . '</xml><![endif]-->'
        . '</head><body>'
        . $body
        . '</body></html>';

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    // UTF-8 BOM so special characters (·, —, ñ, etc.) render correctly in Excel.
    echo "\xEF\xBB\xBF";
    echo $doc;
}

/**
 * export_xlsx_schedule_grid()
 * ------------------------------------------------------------------
 * Builds + streams a weekly TIME x DAY timetable grid (like a printed
 * class schedule), instead of the flat list-style table that
 * export_xlsx_modern() produces. Same zero-dependency HTML-table-as-.xls
 * trick, styled to match a dark-green/white timetable look.
 *
 * @param array $opts {
 *   string   filename       Download filename, e.g. "section_schedule.xls"
 *   string   sheet_name     Sheet tab name shown in Excel
 *   string   band_title     Big title band text, e.g. "GRADE 12: HUMSS A"
 *   string[] subtitle_lines Lines shown under the title band
 *   string[] days           Column headers in order, e.g. ['Monday',...,'Friday']
 *   array    rows           [
 *       [
 *           'time_label' => '7:00 AM - 7:50 AM',
 *           'type'       => 'class' | 'recess',     // 'recess' spans+centers across all day columns
 *           'label'      => 'RECESS',                // only used when type === 'recess'
 *           'cells'      => [ dayName => ['subject' => string, 'teacher' => string] | null, ... ],
 *       ],
 *       ...
 *   ]
 *   string|null footer_text  Optional line under the grid (e.g. adviser name)
 * }
 */
function export_xlsx_schedule_grid(array $opts): void
{
    $filename      = $opts['filename']      ?? 'schedule.xls';
    $sheetName     = $opts['sheet_name']     ?? 'Schedule';
    $bandTitle     = $opts['band_title']     ?? 'Class Schedule';
    $subtitleLines = $opts['subtitle_lines'] ?? [];
    $days          = $opts['days']           ?? ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
    $rows          = $opts['rows']           ?? [];
    $footerText    = $opts['footer_text']    ?? null;

    $colCount = count($days) + 1; // +1 for the Time column

    $filename = preg_replace('/\.[a-z0-9]+$/i', '', $filename) . '.xls';

    $darkGreen  = '#14532d';
    $lightGreen = '#e8f5ee';
    $white      = '#ffffff';
    $recessBg   = '#fef3c7';
    $recessFg   = '#b45309';
    $subjectFg  = '#14532d';
    $teacherFg  = '#14532d';

    // Applied to every cell that can hold variable-length text so Excel wraps
    // the content onto extra lines (and grows the row) instead of clipping it
    // or letting it spill into the next column.
    $wrap = 'white-space:normal; word-wrap:break-word; mso-wrap-text:wrap; vertical-align:middle;';

    $body  = '<table border="0" cellspacing="0" cellpadding="0" '
        . 'style="border-collapse:collapse; table-layout:fixed; font-family:Calibri,Arial,sans-serif; font-size:11pt;">';

    // Column widths: Time column a bit narrower, day columns wide enough for
    // a subject name + teacher name to sit comfortably on their own lines.
    $body .= '<colgroup><col width="16">';
    for ($i = 0; $i < count($days); $i++) {
        $body .= '<col width="20">';
    }
    $body .= '</colgroup>';

    // Title band
    $body .= '<tr><td colspan="' . $colCount . '" '
        . 'style="background:' . $darkGreen . '; color:' . $white . '; font-size:15pt; font-weight:bold; padding:8px 10px; ' . $wrap . '">'
        . xlsx_html_escape($bandTitle) . '</td></tr>';

    // Subtitle line(s)
    foreach ($subtitleLines as $line) {
        $body .= '<tr><td colspan="' . $colCount . '" '
            . 'style="background:' . $lightGreen . '; color:#475569; font-style:italic; padding:4px 10px; ' . $wrap . '">'
            . xlsx_html_escape($line) . '</td></tr>';
    }

    $body .= '<tr><td colspan="' . $colCount . '" height="6" style="font-size:1pt; line-height:1pt;">&nbsp;</td></tr>';

    // Header row: TIME | day names
    $body .= '<tr>';
    $body .= '<td style="background:' . $darkGreen . '; color:' . $white . '; font-weight:bold; text-align:center; '
        . 'padding:6px 8px; border:1px solid #e2e8f0; ' . $wrap . '">TIME</td>';
    foreach ($days as $day) {
        $body .= '<td style="background:' . $darkGreen . '; color:' . $white . '; font-weight:bold; text-align:center; '
            . 'padding:6px 8px; border:1px solid #e2e8f0; ' . $wrap . '">' . xlsx_html_escape(strtoupper($day)) . '</td>';
    }
    $body .= '</tr>';

    if (empty($rows)) {
        $body .= '<tr><td colspan="' . $colCount . '" style="padding:10px; border:1px solid #e2e8f0; color:#64748b; ' . $wrap . '">'
            . 'No scheduled classes found.</td></tr>';
    }

    foreach ($rows as $ri => $row) {
        $isRecess = ($row['type'] ?? 'class') === 'recess';
        $isEven   = ($ri % 2) === 0;
        $rowBg    = $isEven ? $white : $lightGreen;

        if ($isRecess) {
            $body .= '<tr>';
            $body .= '<td style="background:' . $darkGreen . '; color:' . $white . '; font-weight:bold; text-align:center; '
                . 'padding:6px 8px; border:1px solid #e2e8f0; ' . $wrap . '">' . xlsx_html_escape($row['time_label'] ?? '') . '</td>';
            $label = $row['label'] ?? 'RECESS';
            $body .= '<td colspan="' . count($days) . '" style="background:' . $recessBg . '; color:' . $recessFg . '; '
                . 'font-weight:bold; text-align:center; padding:6px 8px; border:1px solid #e2e8f0; ' . $wrap . '">'
                . xlsx_html_escape($label) . '</td>';
            $body .= '</tr>';
            continue;
        }

        // Classic .xls can only apply ONE style (color/weight/italic) per cell —
        // nested spans/divs inside a single cell all render as one color. So each
        // time slot is TWO real rows (subject row, teacher row) sharing one
        // rowspan'd Time cell — each line gets its own cell, so its own color.
        $body .= '<tr>';
        $body .= '<td rowspan="2" style="background:' . $darkGreen . '; color:' . $white . '; font-weight:bold; text-align:center; '
            . 'padding:6px 8px; border:1px solid #e2e8f0; ' . $wrap . '">' . xlsx_html_escape($row['time_label'] ?? '') . '</td>';

        foreach ($days as $day) {
            $cell = $row['cells'][$day] ?? null;
            if ($cell) {
                $body .= '<td style="background:' . $rowBg . '; text-align:center; padding:5px 8px 2px; '
                    . 'border-left:1px solid #e2e8f0; border-right:1px solid #e2e8f0; border-top:1px solid #e2e8f0; border-bottom:none; '
                    . 'font-weight:bold; color:' . $subjectFg . '; ' . $wrap . '">'
                    . xlsx_html_escape($cell['subject'] ?? '') . '</td>';
            } else {
                // No class this day/slot — merge this blank cell across both sub-rows.
                $body .= '<td rowspan="2" style="background:' . $rowBg . '; padding:6px 8px; border:1px solid #e2e8f0;">&nbsp;</td>';
            }
        }
        $body .= '</tr>';

        $body .= '<tr>';
        foreach ($days as $day) {
            $cell = $row['cells'][$day] ?? null;
            if (!$cell) {
                continue; // covered by the rowspan="2" blank cell emitted above
            }
            $body .= '<td style="background:' . $rowBg . '; text-align:center; padding:2px 8px 5px; '
                . 'border-left:1px solid #e2e8f0; border-right:1px solid #e2e8f0; border-top:none; border-bottom:1px solid #e2e8f0; '
                . 'font-size:9.5pt; color:' . $teacherFg . '; mso-number-format:\'\\@\'; ' . $wrap . '">'
                . xlsx_html_escape($cell['teacher'] ?? '') . '</td>';
        }
        $body .= '</tr>';
    }

    if ($footerText !== null) {
        $body .= '<tr><td colspan="' . $colCount . '" height="10" style="font-size:1pt; line-height:1pt;">&nbsp;</td></tr>';
        $body .= '<tr><td colspan="' . $colCount . '" style="font-weight:bold; color:' . $darkGreen . '; '
            . 'font-size:12pt; text-align:right; padding:6px 10px; ' . $wrap . '">' . xlsx_html_escape($footerText) . '</td></tr>';
    }

    $body .= '</table>';

    $doc = '<html xmlns:o="urn:schemas-microsoft-com:office:office" '
        . 'xmlns:x="urn:schemas-microsoft-com:office:excel" '
        . 'xmlns="http://www.w3.org/TR/REC-html40">'
        . '<head><meta charset="UTF-8">'
        . '<!--[if gte mso 9]><xml>'
        . '<x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet>'
        . '<x:Name>' . xlsx_html_escape(substr($sheetName, 0, 31)) . '</x:Name>'
        . '<x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions>'
        . '</x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook>'
        . '</xml><![endif]-->'
        . '</head><body>'
        . $body
        . '</body></html>';

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    echo "\xEF\xBB\xBF";
    echo $doc;
}
