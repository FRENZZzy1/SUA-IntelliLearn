<?php
/**
 * Export student account distribution list as a proper CSV file.
 * Includes student name, username, and stored year level.
 * Optional filter: ?year_level=7..12
 */
require_once __DIR__ . '/../../../../config/config.php';
requireAdmin();

$yearLevel = trim($_GET['year_level'] ?? '');
$params = [];
$where = "u.role = 'student'";

if ($yearLevel !== '') {
    if (!in_array($yearLevel, ['7', '8', '9', '10', '11', '12'], true)) {
        http_response_code(400);
        exit('Invalid year level.');
    }

    $where .= " AND s.year_level = ?";
    $params[] = $yearLevel;
}

$stmt = $pdo->prepare("
    SELECT s.year_level, s.firstname, s.middlename, s.lastname, u.username
    FROM students s
    JOIN users u ON u.id = s.user_id
    WHERE $where
    ORDER BY s.year_level ASC, s.lastname ASC, s.firstname ASC
");
$stmt->execute($params);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

$filename = 'student-account-distribution'
    . ($yearLevel !== '' ? '-grade-' . $yearLevel : '-all-grades')
    . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// UTF-8 BOM helps Excel correctly recognize the CSV as UTF-8.
echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');

if ($output === false) {
    http_response_code(500);
    exit('Unable to create CSV output.');
}

// CSV header row.
fputcsv($output, ['Year Level', 'Student Name', 'Username']);

// CSV data rows.
foreach ($students as $student) {
    $studentName = trim(
        $student['lastname']
        . ', '
        . $student['firstname']
        . ' '
        . ($student['middlename'] ?? '')
    );

    fputcsv($output, [
        'Grade ' . $student['year_level'],
        $studentName,
        $student['username'],
    ]);
}

fclose($output);
exit();
