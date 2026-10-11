<?php
/**
 * enrollment_request_decide.php (teacher)
 * -----------------------------------------------------------------
 * Backend endpoint for the Approve / Deny / Reopen buttons on the
 * "Enrollment Requests" tab of teacher/class_overview.php.
 *
 * There is no separate requests table any more: a student's request is an
 * `enrollments` row whose status starts as 'pending'. Approving it sets the
 * status to 'enrolled', denying sets 'denied', and reopening puts a denied
 * row back to 'pending'. (The POST field is still called `request_id`, but
 * its value is the enrollments.enrollment_id.)
 *
 * Teachers are the only ones who decide enrollment (admins only create
 * classes). A teacher can only act on requests that belong to one of THEIR OWN
 * class offerings. Approve rules: enrollment_open setting, capacity check,
 * and re-activating the student's dropped sibling-term enrollments.
 *
 * Always returns JSON.
 * -----------------------------------------------------------------
 */

require_once __DIR__ . '/../../../../config/config.php';

header('Content-Type: application/json');

function decideFail(int $status, string $message): void
{
    http_response_code($status);
    echo json_encode(['success' => false, 'errors' => [$message]]);
    exit();
}

if (!isLoggedIn() || ($_SESSION['role'] ?? '') !== 'teacher') {
    decideFail(401, 'Your session has expired. Please refresh the page and log in again.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    decideFail(405, 'Invalid request method.');
}

if (!validateCSRFToken($_POST['csrf'] ?? '')) {
    decideFail(419, 'Your session expired. Please refresh the page and try again.');
}

// ---- Resolve the logged-in teacher row --------------------------------
$stmt = $pdo->prepare("SELECT teacher_id FROM teachers WHERE user_id = ? LIMIT 1");
$stmt->execute([(int) $_SESSION['user_id']]);
$teacherId = (int) $stmt->fetchColumn();
if ($teacherId <= 0) {
    decideFail(403, 'Teacher record not found for this account.');
}

// ---- Input --------------------------------------------------------------
$requestId = $_POST['request_id'] ?? '';
$action    = $_POST['action'] ?? '';

if (!ctype_digit((string) $requestId) || !in_array($action, ['approve', 'deny', 'reopen'], true)) {
    decideFail(422, 'Invalid request.');
}
$requestId = (int) $requestId;

// ---- Load the request, scoped to this teacher's own offerings -------------
$stmt = $pdo->prepare("
    SELECT e.enrollment_id, e.student_id, e.offering_id, e.status,
           s.firstname, s.lastname
    FROM enrollments e
    JOIN classofferings co ON co.offering_id = e.offering_id
    JOIN students s ON s.student_id = e.student_id
    WHERE e.enrollment_id = ? AND co.teacher_id = ?
    LIMIT 1
");
$stmt->execute([$requestId, $teacherId]);
$req = $stmt->fetch();

if (!$req) {
    decideFail(404, 'Request not found or you do not have access to it.');
}
if ($action === 'reopen') {
    if ($req['status'] !== 'denied') {
        decideFail(422, 'Only denied requests can be reopened.');
    }
} elseif ($req['status'] !== 'pending') {
    decideFail(422, 'This request has already been decided.');
}

$studentName = trim($req['firstname'] . ' ' . $req['lastname']);
$offeringId  = (int) $req['offering_id'];
$studentId   = (int) $req['student_id'];
$userId      = (int) $_SESSION['user_id'];

try {
    // -------------------------------------------------------------- Reopen
    // Puts a denied request back in Pending so the teacher can decide again.
    if ($action === 'reopen') {
        $upd = $pdo->prepare("
            UPDATE enrollments
            SET status = 'pending', decided_at = NULL, decided_by = NULL
            WHERE enrollment_id = ? AND status = 'denied'
        ");
        $upd->execute([$requestId]);

        if ($upd->rowCount() === 0) {
            decideFail(422, 'Only denied requests can be reopened.');
        }

        setFlashMessage('success', "Reopened {$studentName}'s request. It's back in Pending.");
        echo json_encode(['success' => true, 'status' => 'pending']);
        exit();
    }

    // ---------------------------------------------------------------- Deny
    if ($action === 'deny') {
        $upd = $pdo->prepare("
            UPDATE enrollments
            SET status = 'denied', decided_at = NOW(), decided_by = ?
            WHERE enrollment_id = ? AND status = 'pending'
        ");
        $upd->execute([$userId, $requestId]);

        if ($upd->rowCount() === 0) {
            decideFail(422, 'This request has already been decided.');
        }

        setFlashMessage('success', "Denied {$studentName}'s enrollment request.");
        echo json_encode(['success' => true, 'status' => 'denied']);
        exit();
    }

    // ------------------------------------------------------------- Approve
    // Closing enrollment in Admin Settings also blocks approvals.
    $enrollmentOpen = true;
    try {
        $openStmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'enrollment_open' LIMIT 1");
        $openStmt->execute();
        $enrollmentOpen = $openStmt->fetchColumn() !== '0';
    } catch (PDOException $e) {
        // Preserve legacy behavior if the settings table/key is unavailable.
    }
    if (!$enrollmentOpen) {
        decideFail(423, 'Enrollment is currently closed. Please contact your administrator.');
    }

    $pdo->beginTransaction();

    // Lock the offering and re-check capacity so two simultaneous approvals
    // can't push the class past its limit.
    $lockStmt = $pdo->prepare("
        SELECT co.offering_id, co.capacity,
            (SELECT COUNT(*) FROM enrollments e WHERE e.offering_id = co.offering_id AND e.status = 'enrolled') AS enrolled_count
        FROM classofferings co
        WHERE co.offering_id = ? AND co.teacher_id = ? AND co.status = 'active'
        FOR UPDATE
    ");
    $lockStmt->execute([$offeringId, $teacherId]);
    $offering = $lockStmt->fetch();

    if (!$offering) {
        $pdo->rollBack();
        decideFail(422, 'This class no longer exists or is inactive.');
    }

    if ((int) $offering['enrolled_count'] >= (int) $offering['capacity']) {
        $pdo->rollBack();
        decideFail(422, 'This class is at full capacity.');
    }

    // Flip Pending -> Enrolled; if someone else decided it meanwhile, stop here.
    $upd = $pdo->prepare("
        UPDATE enrollments
        SET status = 'enrolled', enrolled_at = NOW(), decided_at = NOW(), decided_by = ?
        WHERE enrollment_id = ? AND status = 'pending'
    ");
    $upd->execute([$userId, $requestId]);
    if ($upd->rowCount() === 0) {
        $pdo->rollBack();
        decideFail(422, 'This request has already been decided.');
    }

    // Re-enrolling brings back the student's other dropped term offerings
    // of this same class (subject + section + school year).
    $pdo->prepare("
        UPDATE enrollments e
        JOIN classofferings co     ON co.offering_id = e.offering_id
        JOIN classofferings target ON target.offering_id = ?
        SET e.status = 'enrolled'
        WHERE e.student_id = ?
          AND e.status = 'dropped'
          AND co.subject_id     = target.subject_id
          AND co.section_id     = target.section_id
          AND co.school_year_id = target.school_year_id
    ")->execute([$offeringId, $studentId]);

    $pdo->commit();

    setFlashMessage('success', "Approved {$studentName}'s enrollment request.");
    echo json_encode(['success' => true, 'status' => 'approved']);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    decideFail(422, 'Database error: ' . $e->getMessage());
}