<?php
/**
 * Backend endpoint for the "New Section" modal in courses.php.
 * Called via fetch() — always returns JSON, never renders a page.
 * Always inserts into the currently-active school year.
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

$section_name = trim($_POST['section_name'] ?? '');
$grade_level  = $_POST['grade_level'] ?? '';
$strand       = trim($_POST['strand'] ?? '');
$adviser_id   = $_POST['adviser_id'] ?? '';

if ($section_name === '') $errors[] = 'Please enter a section name.';
if (!in_array((string) $grade_level, ['7', '8', '9', '10', '11', '12'], true)) $errors[] = 'Please choose a grade level.';
if ($strand !== '' && !in_array($strand, ['STEM', 'ABM', 'HUMSS', 'TVL'], true)) $errors[] = 'Invalid strand.';
if ($adviser_id !== '' && !ctype_digit((string) $adviser_id)) $errors[] = 'Invalid adviser selected.';

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit();
}

// Sections always belong to whichever school year is marked current.
$syStmt = $pdo->query("SELECT school_year_id FROM schoolyears WHERE is_current = 1 LIMIT 1");
$schoolYear = $syStmt->fetch();

if (!$schoolYear) {
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => ['No active school year is set up. Please contact the system administrator.']]);
    exit();
}

$school_year_id = (int) $schoolYear['school_year_id'];
$adviserValue   = $adviser_id !== '' ? (int) $adviser_id : null;
$strandValue    = $strand !== '' ? $strand : null;

// Friendly, specific checks before we hit the DB unique constraints.
$dupStmt = $pdo->prepare("SELECT section_id FROM sections WHERE section_name = ? AND grade_level = ? AND school_year_id = ?");
$dupStmt->execute([$section_name, (int) $grade_level, $school_year_id]);
if ($dupStmt->fetch()) {
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => ['A section with that name already exists for that grade level this school year.']]);
    exit();
}

if ($adviserValue !== null) {
    $advStmt = $pdo->prepare("SELECT section_id FROM sections WHERE adviser_id = ?");
    $advStmt->execute([$adviserValue]);
    if ($advStmt->fetch()) {
        http_response_code(422);
        echo json_encode(['success' => false, 'errors' => ['That teacher is already the adviser of another section.']]);
        exit();
    }
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO sections (section_name, grade_level, strand, adviser_id, school_year_id)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $section_name,
        (int) $grade_level,
        $strandValue,
        $adviserValue,
        $school_year_id,
    ]);

    setFlashMessage('success', 'Section added successfully.');
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => ['Database error: ' . $e->getMessage()]]);
}
