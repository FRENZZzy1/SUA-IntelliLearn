<?php
/**
 * Backend endpoint for the "Set as Current" action on settings.php.
 * Called via fetch() — always returns JSON.
 *
 * Only one school year may be current at a time, so this clears the
 * flag on every row before setting it on the chosen one.
 */

require_once __DIR__ . '/../../config/config.php';

requireAdmin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'errors' => ['Invalid request method.']]);
    exit();
}

if (!validateCSRFToken($_POST['csrf'] ?? '')) {
    http_response_code(419);
    echo json_encode(['success' => false, 'errors' => ['Your session expired. Please refresh the page and try again.']]);
    exit();
}

$schoolYearId = $_POST['school_year_id'] ?? '';

if (!ctype_digit((string) $schoolYearId)) {
    echo json_encode(['success' => false, 'errors' => ['Invalid school year.']]);
    exit();
}
$schoolYearId = (int) $schoolYearId;

$stmt = $pdo->prepare("SELECT school_year_id, is_archived FROM schoolyears WHERE school_year_id = ?");
$stmt->execute([$schoolYearId]);
$yearRow = $stmt->fetch();
if (!$yearRow) {
    echo json_encode(['success' => false, 'errors' => ['School year not found.']]);
    exit();
}

// An archived year is restored first, so making it current also brings back the
// classes and sections that archiving hid. The year is then marked
// auto_archive_paused so the automatic archive / rollover leaves it alone until
// an admin archives it again, and the other school years are archived.
$restoredCounts = null;
if ((int) $yearRow['is_archived'] === 1) {
    try {
        $restoredCounts = restoreSchoolYear($pdo, $schoolYearId);
    } catch (InvalidArgumentException $e) {
        echo json_encode(['success' => false, 'errors' => [$e->getMessage()]]);
        exit();
    } catch (Throwable $e) {
        http_response_code(422);
        echo json_encode(['success' => false, 'errors' => ['Could not restore the school year: ' . $e->getMessage()]]);
        exit();
    }
}

try {
    $pdo->beginTransaction();
    $pdo->prepare("UPDATE schoolyears SET is_current = 0")->execute();
    // auto_archive_paused: only needed when the year has already ended, so the
    // automatic archive / rollover does not undo the admin's choice. A year that
    // is still running keeps automatic archive + rollover switched on, so it hands
    // over to the next school year by itself when it ends (restoring pauses it,
    // so this also switches it back on for a restored year that has not ended).
    $pdo->prepare("UPDATE schoolyears SET is_current = 1, auto_archive_paused = IF(end_date < ?, 1, 0) WHERE school_year_id = ?")
        ->execute([date('Y-m-d'), $schoolYearId]);
    $pdo->commit();

    // The chosen year must be fully visible. Sections or classes of this year that were
    // archived on their own (not through a year archive) would otherwise stay hidden and
    // can no longer be restored by hand, so bring them back here.
    $leftSec = $pdo->prepare("UPDATE sections SET is_archived = 0, archived_at = NULL WHERE school_year_id = ? AND is_archived = 1");
    $leftSec->execute([$schoolYearId]);
    $leftCls = $pdo->prepare("UPDATE classofferings SET is_archived = 0, archived_at = NULL WHERE school_year_id = ? AND is_archived = 1");
    $leftCls->execute([$schoolYearId]);
    if ($restoredCounts === null && ($leftSec->rowCount() > 0 || $leftCls->rowCount() > 0)) {
        $restoredCounts = ['classes' => $leftCls->rowCount(), 'sections' => $leftSec->rowCount()];
    }

    // Archive every other school year that is not archived yet - including years
    // that have not started - so only the chosen year's classes and sections are
    // visible. Nothing is deleted.
    $archivedLabels = [];
    $others = $pdo->prepare(
        "SELECT school_year_id, label FROM schoolyears
         WHERE school_year_id <> ? AND is_archived = 0
         ORDER BY start_date"
    );
    $others->execute([$schoolYearId]);
    foreach ($others->fetchAll() as $other) {
        try {
            archiveSchoolYear($pdo, (int) $other['school_year_id']);
            $archivedLabels[] = trim($other['label']);
        } catch (Throwable $e) {
            // Skip a year that cannot be archived; the chosen year is already current.
        }
    }

    $message = $restoredCounts !== null
        ? sprintf('School year set as current: %d class(es) and %d section(s) are visible again.', $restoredCounts['classes'], $restoredCounts['sections'])
        : 'Active school year updated.';
    if ($archivedLabels) {
        $message .= ' Archived: ' . implode(', ', $archivedLabels) . '. Nothing was deleted.';
    }
    $message .= ' This year will not be archived automatically again; use Archive when you want to archive it.';
    setFlashMessage('success', $message);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => ['Database error: ' . $e->getMessage()]]);
}
