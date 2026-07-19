<?php
/**
 * Backend endpoint for the Approve action on enrollment.php.
 * Called via fetch() — always returns JSON, never renders a page.
 *
 * The section (offering_id) is normally already on the request —
 * the admin picks it up front in the Enroll Student modal when the
 * request is created. Approving here just re-checks that section is
 * still open and inserts into `enrollments`. courses.php reads its
 * "Enrollment" / "Enrolled Students" numbers straight out of
 * `enrollments`, so those figures update automatically the next
 * time that page loads — no extra sync step needed.
 *
 * Older requests created before sections were chosen up front may
 * still have a null offering_id; those fall back to matching by
 * subject + grade level (+ strand).
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

$requestId = $_POST['request_id'] ?? '';

if (!ctype_digit((string) $requestId)) {
    echo json_encode(['success' => false, 'errors' => ['Invalid request.']]);
    exit();
}
$requestId = (int) $requestId;

$stmt = $pdo->prepare("SELECT * FROM enrollment_requests WHERE request_id = ?");
$stmt->execute([$requestId]);
$req = $stmt->fetch();

if (!$req) {
    echo json_encode(['success' => false, 'errors' => ['Request not found.']]);
    exit();
}

if ($req['status'] !== 'pending') {
    echo json_encode(['success' => false, 'errors' => ['This request has already been decided.']]);
    exit();
}

$selected = null;

if ($req['offering_id']) {
    // ---- Section was already chosen when the request was created ----
    $stmt = $pdo->prepare("
        SELECT co.offering_id, co.capacity,
            (SELECT COUNT(*) FROM enrollments e WHERE e.offering_id = co.offering_id AND e.status = 'active') AS enrolled_count
        FROM classofferings co
        WHERE co.offering_id = ? AND co.status = 'active'
    ");
    $stmt->execute([(int) $req['offering_id']]);
    $selected = $stmt->fetch();

    if (!$selected) {
        echo json_encode(['success' => false, 'errors' => ['The section on this request no longer exists or is inactive.']]);
        exit();
    }
    if ((int) $selected['enrolled_count'] >= (int) $selected['capacity']) {
        echo json_encode(['success' => false, 'errors' => ['That section is now full.']]);
        exit();
    }
} else {
    // ---- Legacy request with no section on file — fall back to matching ----
    $sql = "
        SELECT co.offering_id, co.capacity, sec.section_name, sec.grade_level, sec.strand,
            (SELECT COUNT(*) FROM enrollments e WHERE e.offering_id = co.offering_id AND e.status = 'active') AS enrolled_count
        FROM classofferings co
        JOIN sections sec ON sec.section_id = co.section_id
        WHERE co.subject_id = ? AND sec.grade_level = ? AND co.status = 'active'
    ";
    $params = [(int) $req['subject_id'], (int) $req['grade_level']];

    if ($req['strand']) {
        $sql .= " AND sec.strand = ?";
        $params[] = $req['strand'];
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $candidates = $stmt->fetchAll();

    if (empty($candidates)) {
        echo json_encode(['success' => false, 'errors' => ['No matching class offering exists for this request yet. Create one in Courses & Subjects first.']]);
        exit();
    }

    $open = array_values(array_filter($candidates, fn($c) => (int) $c['enrolled_count'] < (int) $c['capacity']));

    if (empty($open)) {
        echo json_encode(['success' => false, 'errors' => ['The matching class is already at full capacity.']]);
        exit();
    }

    if (count($open) === 1) {
        $selected = $open[0];
    } else {
        echo json_encode(['success' => false, 'errors' => ['This older request matches more than one open section. Deny it and re-submit through Enroll Student so a section can be chosen up front.']]);
        exit();
    }
}

// ---- Avoid a duplicate active enrollment for the same student+offering ----
$dupStmt = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE student_id = ? AND offering_id = ? AND status = 'active'");
$dupStmt->execute([(int) $req['student_id'], (int) $selected['offering_id']]);

if ((int) $dupStmt->fetchColumn() > 0) {
    $pdo->prepare("UPDATE enrollment_requests SET status = 'approved', offering_id = ?, decided_at = NOW(), decided_by = ? WHERE request_id = ?")
        ->execute([(int) $selected['offering_id'], $_SESSION['user_id'] ?? null, $requestId]);
    echo json_encode(['success' => true, 'note' => 'Student was already enrolled in this class; request marked approved.']);
    exit();
}

try {
    $pdo->beginTransaction();

    $pdo->prepare("INSERT INTO enrollments (student_id, offering_id, status) VALUES (?, ?, 'active')")
        ->execute([(int) $req['student_id'], (int) $selected['offering_id']]);

    $pdo->prepare("UPDATE enrollment_requests SET status = 'approved', offering_id = ?, decided_at = NOW(), decided_by = ? WHERE request_id = ?")
        ->execute([(int) $selected['offering_id'], $_SESSION['user_id'] ?? null, $requestId]);

    $pdo->commit();

    setFlashMessage('success', 'Enrollment request approved.');
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => ['Database error: ' . $e->getMessage()]]);
}