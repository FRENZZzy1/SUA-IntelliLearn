<?php
/**
 * join_class.php
 * -----------------------------------------------------------------
 * Backend endpoint for the "Enroll with a Class Code" modal in
 * student/courses.php. Lets a logged-in student join a class
 * themselves by typing the class_code their teacher/admin gave them,
 * instead of an admin creating the enrollment for them.
 *
 * Joining always creates an `enrollments` row with status 'pending' (a
 * previously dropped row for the same class is reset to 'pending'). Only the
 * class's teacher can approve it (-> 'enrolled') or deny it (-> 'denied') from the
 * Enrollment Requests tab in teacher/class_overview.php;
 * admins only create the classes. Rules applied here: the enrollment_open
 * setting, capacity check, and the pending/active/denied duplicate checks.
 *
 * Always returns JSON.
 * -----------------------------------------------------------------
 */

require_once __DIR__ . '/../../../../config/config.php';

header('Content-Type: application/json');

if (!isLoggedIn() || ($_SESSION['role'] ?? '') !== 'student') {
    http_response_code(401);
    echo json_encode(['success' => false, 'errors' => ['Your session has expired. Please refresh the page and log in again.']]);
    exit();
}

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

// ---- Resolve the logged-in student row --------------------------------
$stmt = $pdo->prepare("SELECT student_id FROM students WHERE user_id = ? LIMIT 1");
$stmt->execute([(int) $_SESSION['user_id']]);
$studentId = (int) $stmt->fetchColumn();
if ($studentId <= 0) {
    http_response_code(403);
    echo json_encode(['success' => false, 'errors' => ['Student record not found for this account.']]);
    exit();
}

// ---- Class code input ---------------------------------------------------
$classCode = strtoupper(trim($_POST['class_code'] ?? ''));
if ($classCode === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => ['Please enter a class code.']]);
    exit();
}

// Enrollment can be disabled from Admin Settings. Never rely on the disabled
// button alone because this endpoint can also be called directly.
$enrollmentOpen = true;
try {
    $openStmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'enrollment_open' LIMIT 1");
    $openStmt->execute();
    $enrollmentOpen = $openStmt->fetchColumn() !== '0';
} catch (PDOException $e) {
    // Preserve legacy behavior if the settings table/key is unavailable.
}
if (!$enrollmentOpen) {
    http_response_code(423);
    echo json_encode(['success' => false, 'errors' => ['Enrollment is currently closed. Please contact your administrator.']]);
    exit();
}

try {
    // Look up the class by its code. Must be an active offering.
    $offeringStmt = $pdo->prepare("
        SELECT co.offering_id, co.subject_id, co.capacity, co.quarter,
            sec.grade_level, sec.strand, sec.school_year_id,
            (SELECT COUNT(*) FROM enrollments e WHERE e.offering_id = co.offering_id AND e.status = 'enrolled') AS enrolled_count
        FROM classofferings co
        JOIN sections sec ON sec.section_id = co.section_id
        WHERE co.class_code = ? AND co.status = 'active'
        LIMIT 1
    ");
    $offeringStmt->execute([$classCode]);
    $offering = $offeringStmt->fetch();

    if (!$offering) {
        http_response_code(422);
        echo json_encode(['success' => false, 'errors' => ['That class code is invalid. Please check it and try again.']]);
        exit();
    }

    $offeringId           = (int) $offering['offering_id'];
    $subjectId             = (int) $offering['subject_id'];
    $gradeLevel            = (int) $offering['grade_level'];
    $strand                = $offering['strand'];
    $offeringTerm          = $offering['quarter'];
    $offeringSchoolYearId  = (int) $offering['school_year_id'];

    if ((int) $offering['enrolled_count'] >= (int) $offering['capacity']) {
        http_response_code(422);
        echo json_encode(['success' => false, 'errors' => ['That class is already full. Please contact your teacher.']]);
        exit();
    }

    // Don't create a duplicate pending request for the same student+subject+grade
    // in the same term/school year.
    $dupStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM enrollments er
        JOIN classofferings co ON co.offering_id = er.offering_id
        JOIN sections sec ON sec.section_id = co.section_id
        WHERE er.student_id = ? AND co.subject_id = ? AND sec.grade_level = ? AND er.status = 'pending'
          AND co.quarter = ? AND sec.school_year_id = ?
    ");
    $dupStmt->execute([$studentId, $subjectId, $gradeLevel, $offeringTerm, $offeringSchoolYearId]);

    if ((int) $dupStmt->fetchColumn() > 0) {
        http_response_code(422);
        echo json_encode(['success' => false, 'errors' => ['You already have a pending request for that class.']]);
        exit();
    }

    // Don't allow a new request if the student is already ACTIVELY enrolled
    // in a class offering for this same subject + grade (+ strand) AND the
    // same term/school year.
    $activeSql = "
        SELECT COUNT(*)
        FROM enrollments en
        JOIN classofferings co ON co.offering_id = en.offering_id
        JOIN sections sec ON sec.section_id = co.section_id
        WHERE en.student_id = ?
          AND en.status = 'enrolled'
          AND co.subject_id = ?
          AND sec.grade_level = ?
          AND co.quarter = ?
          AND sec.school_year_id = ?
    ";
    $activeParams = [$studentId, $subjectId, $gradeLevel, $offeringTerm, $offeringSchoolYearId];
    if ($strand !== null && $strand !== '') {
        $activeSql .= " AND sec.strand = ?";
        $activeParams[] = $strand;
    }

    $activeStmt = $pdo->prepare($activeSql);
    $activeStmt->execute($activeParams);

    if ((int) $activeStmt->fetchColumn() > 0) {
        http_response_code(422);
        echo json_encode(['success' => false, 'errors' => ['You are already enrolled in that class.']]);
        exit();
    }

    // Don't let a fresh request slip in for a combo that was already DENIED
    // for the same term/school year. A denial should stick until the teacher
    // explicitly reopens it.
    $deniedSql = "
        SELECT COUNT(*)
        FROM enrollments er
        JOIN classofferings co ON co.offering_id = er.offering_id
        JOIN sections sec ON sec.section_id = co.section_id
        WHERE er.student_id = ? AND co.subject_id = ? AND sec.grade_level = ? AND er.status = 'denied'
          AND co.quarter = ? AND sec.school_year_id = ?
    ";
    $deniedParams = [$studentId, $subjectId, $gradeLevel, $offeringTerm, $offeringSchoolYearId];
    if ($strand !== null && $strand !== '') {
        $deniedSql .= " AND sec.strand = ?";
        $deniedParams[] = $strand;
    } else {
        $deniedSql .= " AND sec.strand IS NULL";
    }

    $deniedStmt = $pdo->prepare($deniedSql);
    $deniedStmt->execute($deniedParams);

    if ((int) $deniedStmt->fetchColumn() > 0) {
        http_response_code(422);
        echo json_encode(['success' => false, 'errors' => ['Your previous request for that class was denied. Please ask your teacher to reopen it.']]);
        exit();
    }

    // Every request starts as a 'pending' enrollment. The class's teacher decides it.
    // (student_id, offering_id) is unique, so a previously dropped row for this
    // exact class is reset to pending instead of inserting a duplicate.
    $stmt = $pdo->prepare("
        INSERT INTO enrollments (student_id, offering_id, status)
        VALUES (?, ?, 'pending')
        ON DUPLICATE KEY UPDATE
            enrolled_at = IF(status = 'dropped', NOW(), enrolled_at),
            decided_at  = IF(status = 'dropped', NULL, decided_at),
            decided_by  = IF(status = 'dropped', NULL, decided_by),
            status      = IF(status = 'dropped', 'pending', status)
    ");
    $stmt->execute([$studentId, $offeringId]);

    // If a record for this exact class already existed in some other state
    // (e.g. completed), nothing was changed, so don't report a new request.
    $chk = $pdo->prepare("SELECT status FROM enrollments WHERE student_id = ? AND offering_id = ? LIMIT 1");
    $chk->execute([$studentId, $offeringId]);
    if ($chk->fetchColumn() !== 'pending') {
        http_response_code(422);
        echo json_encode(['success' => false, 'errors' => ['You already have a record for that class.']]);
        exit();
    }

    setFlashMessage('success', 'Request sent! Your teacher will review it and approve or deny it.');
    echo json_encode(['success' => true, 'status' => 'pending']);
} catch (PDOException $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => ['Database error: ' . $e->getMessage()]]);
}