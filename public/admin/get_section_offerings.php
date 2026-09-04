<?php
/**
 * Backend endpoint for the "Course / Subject" checkbox list in the
 * Enroll Student modal on enrollment.php. Once a section is chosen
 * (itself filtered by grade level + strand), this returns every active
 * class offering taught in that section for whichever term is currently
 * active per "Set Term Interval", so the admin can check off one or
 * more subjects to request at once. Called via fetch() — always returns
 * JSON, never renders a page.
 */

require_once __DIR__ . '/../../config/config.php';

requireAdmin();

header('Content-Type: application/json');

$sectionId  = $_GET['section_id'] ?? '';
$gradeLevel = $_GET['grade_level'] ?? '';
$strand     = trim($_GET['strand'] ?? '');

if (!ctype_digit((string) $sectionId)) {
    echo json_encode(['success' => false, 'errors' => ['A section is required.']]);
    exit();
}

if (!in_array((string) $gradeLevel, ['7', '8', '9', '10', '11', '12'], true)) {
    echo json_encode(['success' => false, 'errors' => ['Grade level is required.']]);
    exit();
}

if ($strand !== '' && !in_array($strand, ['STEM', 'ABM', 'HUMSS', 'TVL'], true)) {
    echo json_encode(['success' => false, 'errors' => ['Invalid strand.']]);
    exit();
}

// Term is no longer picked in the modal — every enrollment is matched
// against whichever term is currently active, same as new classes.
$term = resolveCurrentTerm(getTermIntervals($pdo));
if ($term === null) {
    echo json_encode(['success' => false, 'errors' => ['No term is currently active based on today\'s date. Set the term intervals first (Classes & Subjects > "Set Term Interval").']]);
    exit();
}

$sectionId  = (int) $sectionId;
$gradeLevel = (int) $gradeLevel;

// Re-validate the section actually matches the grade/strand/term filters
// (in case the form was tampered with or filters changed after load).
$sectionSql = "SELECT section_id FROM sections WHERE section_id = ? AND grade_level = ?";
$sectionParams = [$sectionId, $gradeLevel];
if ($strand !== '') {
    $sectionSql .= " AND strand = ?";
    $sectionParams[] = $strand;
}

try {
    $checkStmt = $pdo->prepare($sectionSql);
    $checkStmt->execute($sectionParams);
    if (!$checkStmt->fetch()) {
        echo json_encode(['success' => false, 'errors' => ['That section no longer matches the selected filters. Please refresh and pick again.']]);
        exit();
    }

    $sql = "
        SELECT co.offering_id, co.subject_id, subj.subject_name, co.capacity,
            (SELECT COUNT(*) FROM enrollments e WHERE e.offering_id = co.offering_id AND e.status = 'active') AS enrolled_count
        FROM classofferings co
        JOIN subjects subj ON subj.subject_id = co.subject_id
        WHERE co.section_id = ? AND co.quarter = ? AND co.status = 'active'
        ORDER BY subj.subject_name
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$sectionId, $term]);
    $rows = $stmt->fetchAll();

    $options = [];
    foreach ($rows as $c) {
        $seatsLeft = (int) $c['capacity'] - (int) $c['enrolled_count'];
        $options[] = [
            'offering_id'  => (int) $c['offering_id'],
            'subject_id'   => (int) $c['subject_id'],
            'subject_name' => $c['subject_name'],
            'seats_left'   => $seatsLeft,
            'full'         => $seatsLeft <= 0,
        ];
    }

    echo json_encode(['success' => true, 'options' => $options]);
} catch (PDOException $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => ['Database error: ' . $e->getMessage()]]);
}