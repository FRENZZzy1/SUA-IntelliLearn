<?php
/**
 * Backend endpoint for the "Enroll Student" modal in enrollment.php.
 * Creates a new pending enrollment request. Called via fetch() —
 * always returns JSON, never renders a page.
 */

require_once __DIR__ . '/../../config/config.php';

requireAdmin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'errors' => ['Invalid request method.']]);
    exit();
}

$errors = [];

if (!validateCSRFToken($_POST['csrf'] ?? '')) {
    $errors[] = 'Your session expired. Please refresh the page and try again.';
}

$student_id  = $_POST['student_id'] ?? '';
$grade_level = $_POST['grade_level'] ?? '';
$subject_id  = $_POST['subject_id'] ?? '';
$strand      = trim($_POST['strand'] ?? '');
$offering_id = $_POST['offering_id'] ?? '';

if (!ctype_digit((string) $student_id)) $errors[] = 'Please choose a student.';
if (!in_array((string) $grade_level, ['7', '8', '9', '10', '11', '12'], true)) $errors[] = 'Please choose a grade level.';
if (!ctype_digit((string) $subject_id)) $errors[] = 'Please choose a course/subject.';
if ($strand !== '' && !in_array($strand, ['GAS', 'ABM', 'HUMSS', 'TVL'], true)) $errors[] = 'Invalid strand.';
if (!ctype_digit((string) $offering_id)) $errors[] = 'Please choose a section.';

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit();
}

$student_id  = (int) $student_id;
$grade_level = (int) $grade_level;
$subject_id  = (int) $subject_id;
$offering_id = (int) $offering_id;

// Re-validate the chosen section server-side: it must actually be an
// active offering for this subject + grade (+ strand) with an open seat,
// in case the form was tampered with or the class filled up meanwhile.
$sectionSql = "
    SELECT co.offering_id, co.capacity, co.quarter, sec.school_year_id,
        (SELECT COUNT(*) FROM enrollments e WHERE e.offering_id = co.offering_id AND e.status = 'active') AS enrolled_count
    FROM classofferings co
    JOIN sections sec ON sec.section_id = co.section_id
    WHERE co.offering_id = ? AND co.subject_id = ? AND sec.grade_level = ? AND co.status = 'active'
";
$sectionParams = [$offering_id, $subject_id, $grade_level];
if ($strand !== '') {
    $sectionSql .= " AND sec.strand = ?";
    $sectionParams[] = $strand;
}

// From here on, every DB call is wrapped in one try/catch so an
// unexpected PDOException always degrades to a JSON error response
// instead of an uncaught fatal error (which would return non-JSON
// output and show up to the user as "Something went wrong").
try {
    $sectionStmt = $pdo->prepare($sectionSql);
    $sectionStmt->execute($sectionParams);
    $offering = $sectionStmt->fetch();

    if (!$offering) {
        http_response_code(422);
        echo json_encode(['success' => false, 'errors' => ['That section no longer matches this request. Please refresh and pick again.']]);
        exit();
    }

    if ((int) $offering['enrolled_count'] >= (int) $offering['capacity']) {
        http_response_code(422);
        echo json_encode(['success' => false, 'errors' => ['That section just filled up. Please choose another section.']]);
        exit();
    }

    // The term (quarter + school year) of the section being requested. The
    // duplicate checks below are scoped to this term, so the same course/
    // section can be requested again for a different quarter or school year
    // without tripping over an earlier request/enrollment from another term.
    $offeringQuarter      = (int) $offering['quarter'];
    $offeringSchoolYearId = (int) $offering['school_year_id'];

    // Don't create a duplicate pending request for the same student+subject+grade
    // in the same quarter/school year.
    $dupStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM enrollment_requests er
        JOIN classofferings co ON co.offering_id = er.offering_id
        JOIN sections sec ON sec.section_id = co.section_id
        WHERE er.student_id = ? AND er.subject_id = ? AND er.grade_level = ? AND er.status = 'pending'
          AND co.quarter = ? AND sec.school_year_id = ?
    ");
    $dupStmt->execute([$student_id, $subject_id, $grade_level, $offeringQuarter, $offeringSchoolYearId]);

    if ((int) $dupStmt->fetchColumn() > 0) {
        http_response_code(422);
        echo json_encode(['success' => false, 'errors' => ['This student already has a pending request for that course, quarter, and school year.']]);
        exit();
    }

    // Don't allow a new request if the student is already ACTIVELY enrolled
    // in a class offering for this same subject + grade (+ strand) AND the
    // same quarter/school year. Without this check, an already-approved
    // enrollment doesn't stop a fresh request from being submitted (and later
    // denied/reopened) for the same course, since the earlier request is no
    // longer 'pending'. Scoping to quarter/school year lets the same course
    // and section be requested again for a different term.
    $activeSql = "
        SELECT COUNT(*)
        FROM enrollments en
        JOIN classofferings co ON co.offering_id = en.offering_id
        JOIN sections sec ON sec.section_id = co.section_id
        WHERE en.student_id = ?
          AND en.status = 'active'
          AND co.subject_id = ?
          AND sec.grade_level = ?
          AND co.quarter = ?
          AND sec.school_year_id = ?
    ";
    $activeParams = [$student_id, $subject_id, $grade_level, $offeringQuarter, $offeringSchoolYearId];
    if ($strand !== '') {
        $activeSql .= " AND sec.strand = ?";
        $activeParams[] = $strand;
    }

    $activeStmt = $pdo->prepare($activeSql);
    $activeStmt->execute($activeParams);

    if ((int) $activeStmt->fetchColumn() > 0) {
        http_response_code(422);
        echo json_encode(['success' => false, 'errors' => ['This student is already enrolled in that course for this quarter and school year.']]);
        exit();
    }

    // Don't let a fresh request slip in for a combo that was already DENIED
    // for the same quarter/school year. A denied decision should stick until
    // the admin explicitly reopens it from the table — it shouldn't be
    // possible to route around a denial by just submitting the same request
    // again through the modal. A denial in one term doesn't block requesting
    // the same course/section in a different term.
    $deniedSql = "
        SELECT COUNT(*)
        FROM enrollment_requests er
        JOIN classofferings co ON co.offering_id = er.offering_id
        JOIN sections sec ON sec.section_id = co.section_id
        WHERE er.student_id = ? AND er.subject_id = ? AND er.grade_level = ? AND er.status = 'denied'
          AND co.quarter = ? AND sec.school_year_id = ?
    ";
    $deniedParams = [$student_id, $subject_id, $grade_level, $offeringQuarter, $offeringSchoolYearId];
    if ($strand !== '') {
        $deniedSql .= " AND er.strand = ?";
        $deniedParams[] = $strand;
    } else {
        $deniedSql .= " AND er.strand IS NULL";
    }

    $deniedStmt = $pdo->prepare($deniedSql);
    $deniedStmt->execute($deniedParams);

    if ((int) $deniedStmt->fetchColumn() > 0) {
        http_response_code(422);
        echo json_encode(['success' => false, 'errors' => ['This student already has a denied request for that course, quarter, and school year. Reopen it from the table instead of submitting a new one.']]);
        exit();
    }

    $stmt = $pdo->prepare("
        INSERT INTO enrollment_requests (student_id, grade_level, subject_id, strand, offering_id, status)
        VALUES (?, ?, ?, ?, ?, 'pending')
    ");
    $stmt->execute([
        $student_id,
        $grade_level,
        $subject_id,
        $strand !== '' ? $strand : null,
        $offering_id,
    ]);

    setFlashMessage('success', 'Enrollment request submitted.');
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => ['Database error: ' . $e->getMessage()]]);
}