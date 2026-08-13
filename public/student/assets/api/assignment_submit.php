<?php
// This file is a direct POST target (the <form action="...">), so PHP's
// relative-path resolution for require/include is NOT reliable here — it
// depends on the calling script's cwd, not this file's location. __DIR__
// always points at this file's real folder on disk, so use that instead.
// Path: public/student/assets/api/ -> up 4 levels -> project root -> config/config.php
require_once __DIR__ . '/../../../../config/config.php';

// ---- Access control -------------------------------------------------
if (!isLoggedIn() || ($_SESSION['role'] ?? '') !== 'student') {
    header('Location: ../../login.php');
    exit();
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../courses.php');
    exit();
}

$userId = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT student_id FROM students WHERE user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$student = $stmt->fetch();
if (!$student) {
    die('Student record not found for this account.');
}
$studentId = (int) $student['student_id'];

// ---- Params carried through so we can redirect back to the right place ----
$subjectId    = filter_input(INPUT_POST, 'subject_id', FILTER_VALIDATE_INT);
$term         = $_POST['term'] ?? null;
$assignmentId = filter_input(INPUT_POST, 'assignment_id', FILTER_VALIDATE_INT);
$backUrl      = '../../course_view.php?' . http_build_query([
    'subject_id'    => $subjectId,
    'term'          => $term,
    'view'          => 'assignments',
    'assignment_id' => $assignmentId,
]);

// ---- CSRF -----------------------------------------------------------
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    setFlashMessage('error', 'Your session expired. Please try again.');
    header('Location: ' . $backUrl);
    exit();
}

if (!$assignmentId) {
    setFlashMessage('error', 'Invalid assignment.');
    header('Location: ../../courses.php');
    exit();
}

// ---- Verify this assignment is published and this student is actively
// enrolled in the offering it belongs to (doubles as the authorization
// check — students can't submit to a class/assignment they don't have) ----
$stmt = $pdo->prepare("
    SELECT a.assignment_id, a.due_date, a.offering_id, a.max_attempts
    FROM assignments a
    JOIN enrollments e ON e.offering_id = a.offering_id
    WHERE a.assignment_id = ?
      AND a.status = 'published'
      AND e.student_id = ?
      AND e.status = 'active'
    LIMIT 1
");
$stmt->execute([$assignmentId, $studentId]);
$assignment = $stmt->fetch();

if (!$assignment) {
    setFlashMessage('error', 'Assignment not found or you do not have access to it.');
    header('Location: ../../courses.php');
    exit();
}
$offeringId   = (int) $assignment['offering_id'];
$maxAttempts  = (int) $assignment['max_attempts'];

// ---- How many attempts has this student already used, and is a new one
// still allowed? Each submit consumes one attempt (no overwriting a past
// attempt), same model as quizzes. -------------------------------------
$stmt = $pdo->prepare("SELECT COUNT(*) FROM submissions WHERE assignment_id = ? AND student_id = ?");
$stmt->execute([$assignmentId, $studentId]);
$attemptsUsed = (int) $stmt->fetchColumn();

if ($attemptsUsed >= $maxAttempts) {
    setFlashMessage('error', "You've already used all {$maxAttempts} allowed attempt" . ($maxAttempts === 1 ? '' : 's') . " for this assignment.");
    header('Location: ' . $backUrl);
    exit();
}
$attemptNumber = $attemptsUsed + 1;

// ---- Optional note the student can submit alongside (or instead of) a file --
$submissionText = trim($_POST['submission_text'] ?? '');
$submissionText = $submissionText === '' ? null : mb_substr($submissionText, 0, 5000);

// ---- File upload (optional if a text note was given, but at least one of
// the two is required). Multiple files are allowed per submission — each
// gets its own row in submission_files, linked to this attempt. ----------
$uploadedFiles = [];
if (!empty($_FILES['submission_file']) && is_array($_FILES['submission_file']['name'] ?? null)) {
    $rawFiles = $_FILES['submission_file'];
    $fileCount = count($rawFiles['name']);
    for ($i = 0; $i < $fileCount; $i++) {
        if ($rawFiles['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue; // empty slot — nothing was chosen there
        }
        $uploadedFiles[] = [
            'name'     => $rawFiles['name'][$i],
            'type'     => $rawFiles['type'][$i],
            'tmp_name' => $rawFiles['tmp_name'][$i],
            'error'    => $rawFiles['error'][$i],
            'size'     => $rawFiles['size'][$i],
        ];
    }
}
$hasFile = count($uploadedFiles) > 0;

if (!$hasFile && $submissionText === null) {
    setFlashMessage('error', 'Please attach a file or write a note before submitting.');
    header('Location: ' . $backUrl);
    exit();
}

$maxFiles = 10;
if (count($uploadedFiles) > $maxFiles) {
    setFlashMessage('error', "You can attach at most {$maxFiles} files per submission.");
    header('Location: ' . $backUrl);
    exit();
}

// ---- Validate every file up front before saving any of them, so a bad
// file in the batch doesn't leave a partial set of uploads on disk -------
$maxBytes = 25 * 1024 * 1024; // 25MB, per file
$allowedExtensions = ['pdf', 'png', 'jpg', 'jpeg', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'zip'];
foreach ($uploadedFiles as $file) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        setFlashMessage('error', 'One of the files failed to upload. Please try again.');
        header('Location: ' . $backUrl);
        exit();
    }
    if ($file['size'] > $maxBytes) {
        setFlashMessage('error', "\"{$file['name']}\" is too large. Max size is 25MB per file.");
        header('Location: ' . $backUrl);
        exit();
    }
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions, true)) {
        setFlashMessage('error', "\"{$file['name']}\" is not an allowed file type.");
        header('Location: ' . $backUrl);
        exit();
    }
}

// Stored on disk under the TEACHER's folder, one directory per
// class-offering/assignment, so the teacher's grading view (which
// lives at public/teacher/) can link to it with a plain relative
// path, same as it does for materials/instructions attachments.
// Path: public/student/assets/api/ -> up 3 -> public/ -> teacher/...
$uploadDir = __DIR__ . '/../../../teacher/assets/student_submissions/' . $offeringId . '/' . $assignmentId . '/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$savedFiles = []; // [ [original_name, relative_path, size], ... ]
foreach ($uploadedFiles as $file) {
    $extension    = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $safeBaseName = preg_replace('/[^A-Za-z0-9_-]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
    $storedName   = 'student' . $studentId . '_' . $safeBaseName . '_' . bin2hex(random_bytes(6)) . '.' . $extension;
    $destPath     = $uploadDir . $storedName;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        setFlashMessage('error', 'Could not save one of the uploaded files.');
        header('Location: ' . $backUrl);
        exit();
    }

    $savedFiles[] = [
        'original_name' => mb_substr($file['name'], 0, 255),
        'relative_path' => 'assets/student_submissions/' . $offeringId . '/' . $assignmentId . '/' . $storedName,
        'size'          => (int) $file['size'],
    ];
}

// ---- Late or on-time? --------------------------------------------------
$isLate = $assignment['due_date'] && strtotime($assignment['due_date']) < time();
$status = $isLate ? 'late' : 'submitted';

// Each submit is its own attempt row (attempt_number increments per student
// per assignment, capped by max_attempts above) — a fresh attempt, not an
// overwrite of the last one, so past attempts and their grades stay intact.
// file_path/file_size stay NULL here; individual files live in
// submission_files, linked below by submission_id.
$stmt = $pdo->prepare("
    INSERT INTO submissions
        (assignment_id, student_id, attempt_number, submission_text, file_path, file_size, status, submitted_at)
    VALUES (?, ?, ?, ?, NULL, NULL, ?, NOW())
");
$stmt->execute([$assignmentId, $studentId, $attemptNumber, $submissionText, $status]);
$submissionId = (int) $pdo->lastInsertId();

if ($savedFiles) {
    $fileStmt = $pdo->prepare("
        INSERT INTO submission_files (submission_id, original_name, file_path, file_size)
        VALUES (?, ?, ?, ?)
    ");
    foreach ($savedFiles as $f) {
        $fileStmt->execute([$submissionId, $f['original_name'], $f['relative_path'], $f['size']]);
    }
}

$attemptsLeft = $maxAttempts - $attemptNumber;
$suffix = $attemptsLeft > 0 ? " ({$attemptsLeft} attempt" . ($attemptsLeft === 1 ? '' : 's') . " left)" : ' (final attempt used)';
setFlashMessage('success', ($isLate ? 'Submitted (marked late).' : 'Submitted!') . $suffix);
header('Location: ' . $backUrl);
exit();