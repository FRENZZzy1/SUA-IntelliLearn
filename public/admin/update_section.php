<?php
/**
 * Backend endpoint for the "Update Section" modal in courses.php.
 * Called via fetch() — always returns JSON, never renders a page.
 * Mirrors add_section.php but UPDATEs an existing sections row instead
 * of inserting a new one. School year is intentionally left untouched —
 * sections stay pinned to whichever school year they were created under.
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

$section_id   = $_POST['section_id'] ?? '';
$section_name = trim($_POST['section_name'] ?? '');
$grade_level  = $_POST['grade_level'] ?? '';
$strand       = trim($_POST['strand'] ?? '');
$adviser_id   = $_POST['adviser_id'] ?? '';

if (!ctype_digit((string) $section_id)) $errors[] = 'Missing or invalid section reference.';
if ($section_name === '') $errors[] = 'Please enter a section name.';
if (!in_array((string) $grade_level, ['7', '8', '9', '10', '11', '12'], true)) $errors[] = 'Please choose a grade level.';
if ($strand !== '' && !in_array($strand, ['STEM', 'ABM', 'HUMSS', 'TVL'], true)) $errors[] = 'Invalid strand.';
if ($adviser_id !== '' && !ctype_digit((string) $adviser_id)) $errors[] = 'Invalid adviser selected.';

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit();
}

// Confirm the section actually exists before attempting the update.
$check = $pdo->prepare("SELECT section_id, school_year_id FROM sections WHERE section_id = ?");
$check->execute([(int) $section_id]);
$existing = $check->fetch();
if (!$existing) {
    http_response_code(404);
    echo json_encode(['success' => false, 'errors' => ['That section no longer exists. Please refresh the page.']]);
    exit();
}

$school_year_id = (int) $existing['school_year_id'];
$adviserValue   = $adviser_id !== '' ? (int) $adviser_id : null;
$strandValue    = $strand !== '' ? $strand : null;

// Friendly, specific checks before we hit the DB unique constraints.
$dupStmt = $pdo->prepare("
    SELECT section_id FROM sections
    WHERE section_name = ? AND grade_level = ? AND school_year_id = ? AND section_id != ?
");
$dupStmt->execute([$section_name, (int) $grade_level, $school_year_id, (int) $section_id]);
if ($dupStmt->fetch()) {
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => ['A section with that name already exists for that grade level this school year.']]);
    exit();
}

if ($adviserValue !== null) {
    $advStmt = $pdo->prepare("SELECT section_id FROM sections WHERE adviser_id = ? AND section_id != ?");
    $advStmt->execute([$adviserValue, (int) $section_id]);
    if ($advStmt->fetch()) {
        http_response_code(422);
        echo json_encode(['success' => false, 'errors' => ['That teacher is already the adviser of another section.']]);
        exit();
    }
}

try {
    $stmt = $pdo->prepare("
        UPDATE sections
        SET section_name = ?, grade_level = ?, strand = ?, adviser_id = ?
        WHERE section_id = ?
    ");
    $stmt->execute([
        $section_name,
        (int) $grade_level,
        $strandValue,
        $adviserValue,
        (int) $section_id,
    ]);

    setFlashMessage('success', 'Section updated successfully.');
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(422);
    if ($e->getCode() === '23000') {
        echo json_encode(['success' => false, 'errors' => ['That section name/adviser conflicts with another section.']]);
    } else {
        echo json_encode(['success' => false, 'errors' => ['Database error: ' . $e->getMessage()]]);
    }
}
