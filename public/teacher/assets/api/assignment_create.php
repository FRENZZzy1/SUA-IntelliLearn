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
    'view'       => 'assignments',
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
    setFlashMessage('error', 'Please give the assignment a title.');
    header('Location: ' . $backUrl);
    exit();
}
$title = mb_substr($title, 0, 255);

$description = trim($_POST['description'] ?? '');
$description = $description === '' ? null : $description;

// ---- Points ---------------------------------------------------------
$points = filter_input(INPUT_POST, 'points', FILTER_VALIDATE_FLOAT);
if ($points === false || $points === null || $points <= 0) {
    $points = 100.00;
}
$points = round($points, 2);

// ---- Due date (optional) --------------------------------------------
$dueDateRaw = trim($_POST['due_date'] ?? '');
$dueDate = null;
if ($dueDateRaw !== '') {
    $parsed = DateTime::createFromFormat('Y-m-d\TH:i', $dueDateRaw) ?: DateTime::createFromFormat('Y-m-d H:i', $dueDateRaw);
    if ($parsed !== false) {
        $dueDate = $parsed->format('Y-m-d H:i:s');
    }
}

// ---- Optional instructions attachment --------------------------------
$instructionsPath = null;
if (!empty($_FILES['instructions_file']) && $_FILES['instructions_file']['error'] !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['instructions_file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        setFlashMessage('error', 'The attachment failed to upload. Please try again.');
        header('Location: ' . $backUrl);
        exit();
    }

    $maxBytes = 25 * 1024 * 1024; // 25MB
    if ($file['size'] > $maxBytes) {
        setFlashMessage('error', 'Attachment is too large. Max size is 25MB.');
        header('Location: ' . $backUrl);
        exit();
    }

    $allowedExtensions = ['pdf', 'ppt', 'pptx', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'zip'];
    $originalName = $file['name'];
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions, true)) {
        setFlashMessage('error', 'That attachment type is not allowed.');
        header('Location: ' . $backUrl);
        exit();
    }

    // Store under assets/uploads/assignments/{offering_id}/, one folder per class-term.
    $uploadDir = __DIR__ . '/../uploads/assignments/' . $offeringId . '/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $safeBaseName = preg_replace('/[^A-Za-z0-9_-]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
    $storedName   = $safeBaseName . '_' . bin2hex(random_bytes(6)) . '.' . $extension;
    $destPath     = $uploadDir . $storedName;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        setFlashMessage('error', 'Could not save the attachment.');
        header('Location: ' . $backUrl);
        exit();
    }

    // Relative path usable from class_overview.php (which lives one directory above assets/api/).
    $instructionsPath = 'assets/uploads/assignments/' . $offeringId . '/' . $storedName;
}

$stmt = $pdo->prepare("
    INSERT INTO assignments (offering_id, title, description, instructions_file_path, due_date, points, status, created_by)
    VALUES (?, ?, ?, ?, ?, ?, 'published', ?)
");
$stmt->execute([$offeringId, $title, $description, $instructionsPath, $dueDate, $points, $teacherId]);

setFlashMessage('success', 'Assignment posted.');
header('Location: ' . $backUrl);
exit();
