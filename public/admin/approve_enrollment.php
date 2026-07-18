<?php
/**
 * Backend endpoint for the Approve action on enrollment.php.
 * Called via fetch() — always returns JSON, never renders a page.
 *
 * Finds the classofferings row that matches the request's
 * subject + grade level (+ strand, if set), inserts into
 * `enrollments`, and marks the request approved. courses.php reads
 * its "Enrollment" / "Enrolled Students" numbers straight out of
 * `enrollments`, so those figures update automatically the next
 * time that page loads — no extra sync step needed.
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

$requestId         = $_POST['request_id'] ?? '';
$chosenOfferingId  = $_POST['offering_id'] ?? null;

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

// ---- Find candidate class offerings (active, matching subject+grade+strand) ----
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

$selected = null;

if ($chosenOfferingId !== null && ctype_digit((string) $chosenOfferingId)) {
    foreach ($open as $c) {
        if ((int) $c['offering_id'] === (int) $chosenOfferingId) {
            $selected = $c;
            break;
        }
    }
    if (!$selected) {
        echo json_encode(['success' => false, 'errors' => ['Selected section is no longer available.']]);
        exit();
    }
} elseif (count($open) === 1) {
    $selected = $open[0];
} else {
    echo json_encode([
        'success' => false,
        'needs_selection' => true,
        'options' => array_map(fn($c) => [
            'offering_id' => (int) $c['offering_id'],
            'label' => 'Grade ' . $c['grade_level'] . ' — ' . $c['section_name']
                . ($c['strand'] ? ' (' . $c['strand'] . ')' : '')
                . ' · ' . ((int) $c['capacity'] - (int) $c['enrolled_count']) . ' seats left',
        ], $open),
    ]);
    exit();
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
