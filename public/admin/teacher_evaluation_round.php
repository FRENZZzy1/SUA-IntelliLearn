<?php
/**
 * Backend for the "Teacher Evaluation" tab on settings.php.
 * Called via fetch(); always returns JSON.
 *
 *   POST action=open   title?, term, closes_on?   -> sends the survey to students
 *   POST action=close  round_id                   -> stops accepting responses
 *
 * Only admins whose access level is "Full Access" may do this. The button is
 * hidden for everyone else on the page, but that is only cosmetic, so the
 * check is enforced here too.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/teacher_evaluation.php';

requireAdmin();

header('Content-Type: application/json');

function teval_round_fail(int $code, string $message): void
{
    http_response_code($code);
    echo json_encode(['success' => false, 'errors' => [$message]]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    teval_round_fail(405, 'Invalid request method.');
}
if (!validateCSRFToken($_POST['csrf'] ?? '')) {
    teval_round_fail(419, 'Your session expired. Please refresh the page and try again.');
}
if (adminAccessLevel() !== 'full') {
    teval_round_fail(403, 'Only administrators with Full Access can send or close a teacher evaluation survey.');
}
if (!teval_tables_ready($pdo)) {
    teval_round_fail(500, 'The teacher evaluation tables are missing. Run teacher_evaluation_migration.sql on the database first.');
}

$action = $_POST['action'] ?? '';

try {
    // =================================================================
    if ($action === 'open') {
        $term = $_POST['term'] ?? '';
        if (!in_array($term, TEACHER_EVAL_TERMS, true)) {
            teval_round_fail(422, 'Please choose a valid term.');
        }

        $closesOn = trim($_POST['closes_on'] ?? '');
        if ($closesOn !== '') {
            $d = DateTime::createFromFormat('Y-m-d', $closesOn);
            if (!$d || $d->format('Y-m-d') !== $closesOn) {
                teval_round_fail(422, 'The deadline is not a valid date.');
            }
            if ($closesOn < date('Y-m-d')) {
                teval_round_fail(422, 'The deadline cannot be in the past.');
            }
        } else {
            $closesOn = null;
        }

        $sy = $pdo->query("SELECT school_year_id, label FROM schoolyears WHERE is_current = 1 LIMIT 1")->fetch();
        if (!$sy) {
            teval_round_fail(422, 'There is no current school year. Set one under School Year & Terms first.');
        }

        if (teval_get_open_round($pdo)) {
            teval_round_fail(422, 'A survey is already open. Close it before sending a new one.');
        }

        // Nobody to survey? Say so instead of opening an empty round.
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM enrollments e
            JOIN classofferings co ON co.offering_id = e.offering_id
            WHERE e.status = 'active' AND co.status = 'active'
              AND co.school_year_id = ? AND co.quarter = ?
        ");
        $stmt->execute([(int) $sy['school_year_id'], $term]);
        if ((int) $stmt->fetchColumn() === 0) {
            teval_round_fail(422, "No students are actively enrolled in any {$term} class for the current school year, so there is nobody to survey.");
        }

        $title = trim($_POST['title'] ?? '');
        if ($title === '') {
            $title = 'Teacher Evaluation - ' . trim($sy['label']) . ' ' . $term;
        }
        if (mb_strlen($title) > 150) {
            teval_round_fail(422, 'The title must be 150 characters or fewer.');
        }

        $pdo->beginTransaction();
        // Tidy up any round whose deadline passed but is still flagged open.
        $pdo->exec("UPDATE teacher_evaluation_rounds SET status = 'closed', closed_at = NOW() WHERE status = 'open'");
        $stmt = $pdo->prepare("
            INSERT INTO teacher_evaluation_rounds (school_year_id, term, title, status, closes_on, opened_by)
            VALUES (?, ?, ?, 'open', ?, ?)
        ");
        $stmt->execute([(int) $sy['school_year_id'], $term, $title, $closesOn, (int) $_SESSION['user_id']]);
        $roundId = (int) $pdo->lastInsertId();
        $pdo->commit();

        setFlashMessage('success', 'Survey sent. Students can now evaluate their teachers.');
        echo json_encode(['success' => true, 'round_id' => $roundId]);
        exit();
    }

    // =================================================================
    if ($action === 'close') {
        $roundId = (int) ($_POST['round_id'] ?? 0);
        if ($roundId <= 0) {
            teval_round_fail(422, 'Missing survey id.');
        }

        $stmt = $pdo->prepare("UPDATE teacher_evaluation_rounds SET status = 'closed', closed_at = NOW() WHERE round_id = ? AND status = 'open'");
        $stmt->execute([$roundId]);
        if ($stmt->rowCount() === 0) {
            teval_round_fail(422, 'That survey is already closed or does not exist.');
        }

        setFlashMessage('success', 'Survey closed. Results are available under System Analytics.');
        echo json_encode(['success' => true]);
        exit();
    }

    teval_round_fail(422, 'Unknown action.');

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    teval_round_fail(500, 'Database error: ' . $e->getMessage());
}
