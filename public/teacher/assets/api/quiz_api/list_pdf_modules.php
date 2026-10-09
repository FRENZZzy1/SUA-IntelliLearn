<?php
/**
 * List PDF learning materials available to the signed-in teacher for one of their classes.
 */
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/SUA-IntelliLearn/config/config.php';

if (!isLoggedIn() || ($_SESSION['role'] ?? '') !== 'teacher') {
    http_response_code(403);
    echo json_encode(['success' => false, 'errors' => ['Teacher access is required.']]);
    exit();
}

$offeringId = filter_input(INPUT_GET, 'offering_id', FILTER_VALIDATE_INT);
if (!$offeringId || $offeringId < 1) {
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => ['Choose a valid class.']]);
    exit();
}

$stmt = $pdo->prepare("SELECT teacher_id FROM teachers WHERE user_id = ? LIMIT 1");
$stmt->execute([(int) $_SESSION['user_id']]);
$teacherId = (int) ($stmt->fetchColumn() ?: 0);
if (!$teacherId) {
    http_response_code(403);
    echo json_encode(['success' => false, 'errors' => ['Teacher profile not found.']]);
    exit();
}

$stmt = $pdo->prepare("SELECT offering_id FROM classofferings WHERE offering_id = ? AND teacher_id = ? AND status = 'active' LIMIT 1");
$stmt->execute([$offeringId, $teacherId]);
if (!$stmt->fetchColumn()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'errors' => ['You are not assigned to this class.']]);
    exit();
}

$stmt = $pdo->prepare("
    SELECT material_id, title, file_size
    FROM learning_materials
    WHERE offering_id = ? AND type = 'pdf' AND file_path IS NOT NULL
    ORDER BY created_at DESC, material_id DESC
");
$stmt->execute([$offeringId]);
$modules = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $size = (int) ($row['file_size'] ?? 0);
    $modules[] = [
        'material_id' => (int) $row['material_id'],
        'title' => (string) $row['title'],
        'file_size' => $size,
        'file_size_label' => $size >= 1048576 ? round($size / 1048576, 1) . ' MB' : round($size / 1024) . ' KB',
    ];
}

echo json_encode(['success' => true, 'modules' => $modules]);
