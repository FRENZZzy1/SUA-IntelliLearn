<?php
/**
 * Backup Data endpoint (Quick Actions widget)
 *
 * Generates a full SQL dump of the application database using plain
 * mysqli calls (no shell_exec / mysqldump binary required — works on
 * shared hosting and XAMPP alike) and streams it back as a downloadable
 * .sql file.
 *
 * Expects: POST, with a "csrf" field matching the session token.
 * On success: streams the .sql file (Content-Disposition: attachment).
 * On failure: responds with JSON { success: false, errors: [...] }.
 */

require_once '../../../../config/config.php';
requireAdmin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'errors' => ['Method not allowed.']]);
    exit;
}

// ================= CSRF CHECK =================
// NOTE: adjust the session key below ($_SESSION['csrf_token']) if your
// config.php stores/verifies the CSRF token under a different name —
// this mirrors the pattern already used by delete_course_offering.php etc.
if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'errors' => ['Invalid or expired session. Please refresh and try again.']]);
    exit;
}

if (!($conn instanceof mysqli)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'errors' => ['Database connection unavailable.']]);
    exit;
}

// Large databases can take a while / use a lot of memory.
set_time_limit(300);

$dbNameResult = $conn->query('SELECT DATABASE() AS db');
$dbName = $dbNameResult ? ($dbNameResult->fetch_assoc()['db'] ?? 'database') : 'database';

$tablesResult = $conn->query('SHOW TABLES');
if (!$tablesResult) {
    http_response_code(500);
    echo json_encode(['success' => false, 'errors' => ['Could not list tables: ' . $conn->error]]);
    exit;
}

$tables = [];
while ($row = $tablesResult->fetch_array(MYSQLI_NUM)) {
    $tables[] = $row[0];
}

// ================= STREAM THE DUMP =================
$filename = 'backup_' . preg_replace('/[^A-Za-z0-9_-]/', '', $dbName) . '_' . date('Y-m-d_His') . '.sql';

// Clear anything already buffered so headers are the first bytes sent.
while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/sql; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('X-Content-Type-Options: nosniff');

echo "-- SUA IntelliLearn database backup\n";
echo "-- Database: `{$dbName}`\n";
echo "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
echo "SET FOREIGN_KEY_CHECKS=0;\n";
echo "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
echo "SET NAMES utf8mb4;\n\n";

foreach ($tables as $table) {
    $escapedTable = $conn->real_escape_string($table);

    // --- Structure ---
    $createResult = $conn->query("SHOW CREATE TABLE `{$escapedTable}`");
    if (!$createResult) {
        continue;
    }
    $createRow = $createResult->fetch_assoc();
    $createSql = $createRow['Create Table'] ?? null;
    if ($createSql === null) {
        continue;
    }

    echo "-- --------------------------------------------------------\n";
    echo "-- Table structure for `{$table}`\n";
    echo "-- --------------------------------------------------------\n\n";
    echo "DROP TABLE IF EXISTS `{$table}`;\n";
    echo $createSql . ";\n\n";

    // --- Data ---
    $dataResult = $conn->query("SELECT * FROM `{$escapedTable}`");
    if (!$dataResult || $dataResult->num_rows === 0) {
        continue;
    }

    echo "-- Data for `{$table}`\n\n";

    $fields = $dataResult->fetch_fields();
    $columnNames = array_map(fn($f) => "`{$f->name}`", $fields);
    $columnList = implode(', ', $columnNames);

    $rowsBuffer = [];
    while ($row = $dataResult->fetch_assoc()) {
        $values = [];
        foreach ($row as $value) {
            if ($value === null) {
                $values[] = 'NULL';
            } elseif (is_int($value) || is_float($value)) {
                $values[] = $value;
            } else {
                $values[] = "'" . $conn->real_escape_string((string) $value) . "'";
            }
        }
        $rowsBuffer[] = '(' . implode(', ', $values) . ')';

        // Flush every 200 rows to keep memory usage low on big tables.
        if (count($rowsBuffer) >= 200) {
            echo "INSERT INTO `{$table}` ({$columnList}) VALUES\n" . implode(",\n", $rowsBuffer) . ";\n";
            $rowsBuffer = [];
            flush();
        }
    }
    if ($rowsBuffer) {
        echo "INSERT INTO `{$table}` ({$columnList}) VALUES\n" . implode(",\n", $rowsBuffer) . ";\n";
    }
    echo "\n";
    flush();
}

echo "SET FOREIGN_KEY_CHECKS=1;\n";
exit;