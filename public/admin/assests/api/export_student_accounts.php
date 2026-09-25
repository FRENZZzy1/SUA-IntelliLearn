<?php
/**
 * Export student account distribution list.
 * Includes student name, username, and stored year level.
 * Optional filter: ?year_level=7..12
 */
require_once __DIR__ . '/../../../../config/config.php';
requireAdmin();

$yearLevel = trim($_GET['year_level'] ?? '');
$params = [];
$where = "u.role = 'student'";
if ($yearLevel !== '') {
    if (!in_array($yearLevel, ['7','8','9','10','11','12'], true)) {
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

$filename = 'student-account-distribution' . ($yearLevel !== '' ? '-grade-' . $yearLevel : '-all-grades') . '.xls';

header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

function xls_escape($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>
<table border="1">
    <tr>
        <th>Year Level</th>
        <th>Student Name</th>
        <th>Username</th>
    </tr>
<?php foreach ($students as $student): ?>
    <tr>
        <td><?= xls_escape('Grade ' . $student['year_level']) ?></td>
        <td><?= xls_escape(trim($student['lastname'] . ', ' . $student['firstname'] . ' ' . ($student['middlename'] ?? ''))) ?></td>
        <td><?= xls_escape($student['username']) ?></td>
    </tr>
<?php endforeach; ?>
</table>
