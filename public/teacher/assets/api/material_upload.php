<?php
// This file is a direct POST target (the <form action="...">), so PHP's
// relative-path resolution for require/include is NOT reliable here — it
// depends on the calling script's cwd, not this file's location. __DIR__
// always points at this file's real folder on disk, so use that instead.
// Path: public/teacher/assets/api/ -> up 4 levels -> project root -> config/config.php
require_once __DIR__ . '/../../../../config/config.php';

// ---- Access control -------------------------------------------------
if (!isLoggedIn() || ($_SESSION['role'] ?? '') !== 'teacher') {
    header('Location: ../../login.php');
    exit();
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../courses.php');
    exit();
}

$userId = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT teacher_id FROM teachers WHERE user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$teacher = $stmt->fetch();
if (!$teacher) {
    die('Teacher record not found for this account.');
}
$teacherId = (int) $teacher['teacher_id'];

// ---- Params carried through so we can redirect back to the right place ----
$subjectId = filter_input(INPUT_POST, 'subject_id', FILTER_VALIDATE_INT);
$sectionId = filter_input(INPUT_POST, 'section_id', FILTER_VALIDATE_INT);
$term      = $_POST['term'] ?? null;
$backUrl   = '../../class_overview.php?' . http_build_query([
    'subject_id' => $subjectId,
    'section_id' => $sectionId,
    'term'       => $term,
    'view'       => 'overview',
]);

// ---- CSRF -----------------------------------------------------------
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    setFlashMessage('error', 'Your session expired. Please try again.');
    header('Location: ' . $backUrl);
    exit();
}

// ---- Validate the offering belongs to this teacher (authorization) --------
$offeringId = filter_input(INPUT_POST, 'offering_id', FILTER_VALIDATE_INT);
if (!$offeringId) {
    setFlashMessage('error', 'Missing or invalid class.');
    header('Location: ' . $backUrl);
    exit();
}
$stmt = $pdo->prepare("SELECT offering_id FROM classofferings WHERE offering_id = ? AND teacher_id = ? AND status = 'active' LIMIT 1");
$stmt->execute([$offeringId, $teacherId]);
if (!$stmt->fetch()) {
    setFlashMessage('error', 'You do not have access to that class.');
    header('Location: ../../courses.php');
    exit();
}

// ---- Validate title ---------------------------------------------------
$title = trim($_POST['title'] ?? '');
if ($title === '') {
    setFlashMessage('error', 'Please give the material a title.');
    header('Location: ' . $backUrl);
    exit();
}
$title = mb_substr($title, 0, 255);

$source = $_POST['source'] ?? 'file';

// ============================================================
// Source: external link
// ============================================================
if ($source === 'link') {
    $url = trim($_POST['external_url'] ?? '');
    if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
        setFlashMessage('error', 'Please enter a valid URL.');
        header('Location: ' . $backUrl);
        exit();
    }

    $stmt = $pdo->prepare("
        INSERT INTO learning_materials (offering_id, title, type, file_path, external_url, file_size, uploaded_by)
        VALUES (?, ?, 'link', NULL, ?, NULL, ?)
    ");
    $stmt->execute([$offeringId, $title, $url, $teacherId]);

    setFlashMessage('success', 'Link added.');
    header('Location: ' . $backUrl);
    exit();
}

// ============================================================
// Source: file upload
// ============================================================
$allowedTypes = ['pdf', 'video', 'slides', 'other'];
$materialType = $_POST['material_type'] ?? 'other';
if (!in_array($materialType, $allowedTypes, true)) {
    $materialType = 'other';
}

if (empty($_FILES['material_file']) || $_FILES['material_file']['error'] === UPLOAD_ERR_NO_FILE) {
    setFlashMessage('error', 'Please choose a file to upload, or switch to "Add a link".');
    header('Location: ' . $backUrl);
    exit();
}

$file = $_FILES['material_file'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    setFlashMessage('error', 'Upload failed. Please try again.');
    header('Location: ' . $backUrl);
    exit();
}

$maxBytes = 25 * 1024 * 1024; // 25MB
if ($file['size'] > $maxBytes) {
    setFlashMessage('error', 'File is too large. Max size is 25MB.');
    header('Location: ' . $backUrl);
    exit();
}

$allowedExtensions = ['pdf', 'ppt', 'pptx', 'doc', 'docx', 'xls', 'xlsx', 'mp4', 'mov', 'jpg', 'jpeg', 'png', 'zip'];
$originalName = $file['name'];
$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
if (!in_array($extension, $allowedExtensions, true)) {
    setFlashMessage('error', 'That file type is not allowed.');
    header('Location: ' . $backUrl);
    exit();
}

// Store under assets/uploads/materials/{offering_id}/, one folder per class-term.
$uploadDir = __DIR__ . '/../uploads/materials/' . $offeringId . '/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Random-ish filename on disk; the human-readable name lives in the DB `title` column.
$safeBaseName = preg_replace('/[^A-Za-z0-9_-]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
$storedName   = $safeBaseName . '_' . bin2hex(random_bytes(6)) . '.' . $extension;
$destPath     = $uploadDir . $storedName;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    setFlashMessage('error', 'Could not save the uploaded file.');
    header('Location: ' . $backUrl);
    exit();
}

// Relative path usable from class_overview.php (which lives one directory above assets/api/).
$relativePath = 'assets/uploads/materials/' . $offeringId . '/' . $storedName;

$stmt = $pdo->prepare("
    INSERT INTO learning_materials (offering_id, title, type, file_path, external_url, file_size, uploaded_by)
    VALUES (?, ?, ?, ?, NULL, ?, ?)
");
$stmt->execute([$offeringId, $title, $materialType, $relativePath, $file['size'], $teacherId]);

setFlashMessage('success', 'Material uploaded.');
header('Location: ' . $backUrl);
exit();