<?php
// Direct POST target for the "Edit assignment" modal on class_overview.php.
// Lets a teacher change the deadline, the number of attempts allowed, and the
// other editable details of an assignment that is already posted.
//
// The student side (student/assets/api/assignment_submit.php) reads due_date and
// max_attempts straight from the assignments table on every submit, so updating
// them here takes effect immediately:
//   - moving the deadline forward re-opens the assignment for students
//   - raising max_attempts gives every student more tries
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
$subjectId    = filter_input(INPUT_POST, 'subject_id', FILTER_VALIDATE_INT);
$sectionId    = filter_input(INPUT_POST, 'section_id', FILTER_VALIDATE_INT);
$term         = $_POST['term'] ?? null;
$assignmentId = filter_input(INPUT_POST, 'assignment_id', FILTER_VALIDATE_INT);
$offeringId   = filter_input(INPUT_POST, 'offering_id', FILTER_VALIDATE_INT);
$fromDetail   = !empty($_POST['from_detail']);

$backParams = [
    'subject_id' => $subjectId,
    'section_id' => $sectionId,
    'term'       => $term,
    'view'       => 'assignments',
];
if ($fromDetail && $assignmentId) {
    $backParams['assignment_id'] = $assignmentId;
}
$backUrl = '../../class_overview.php?' . http_build_query($backParams);

// ---- CSRF -----------------------------------------------------------
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    setFlashMessage('error', 'Your session expired. Please try again.');
    header('Location: ' . $backUrl);
    exit();
}

if (!$assignmentId || !$offeringId) {
    setFlashMessage('error', 'Invalid request.');
    header('Location: ' . $backUrl);
    exit();
}

// ---- Authorization: the assignment must belong to one of this teacher's classes ----
$stmt = $pdo->prepare("
    SELECT a.assignment_id
    FROM assignments a
    JOIN classofferings co ON co.offering_id = a.offering_id
    WHERE a.assignment_id = ? AND a.offering_id = ? AND co.teacher_id = ?
    LIMIT 1
");
$stmt->execute([$assignmentId, $offeringId, $teacherId]);
if (!$stmt->fetch()) {
    setFlashMessage('error', 'Assignment not found or you do not have access to it.');
    header('Location: ' . $backUrl);
    exit();
}

// ---- Title ----------------------------------------------------------
$title = trim($_POST['title'] ?? '');
if ($title === '') {
    setFlashMessage('error', 'Please give the assignment a title.');
    header('Location: ' . $backUrl);
    exit();
}
$title = mb_substr($title, 0, 255);

$description = trim($_POST['description'] ?? '');
$description = $description === '' ? null : $description;

// ---- Current usage: protects existing student work from being invalidated ----
$stmt = $pdo->prepare("
    SELECT COALESCE(MAX(cnt), 0) FROM (
        SELECT COUNT(*) AS cnt FROM submissions WHERE assignment_id = ? GROUP BY student_id
    ) t
");
$stmt->execute([$assignmentId]);
$highestAttemptsUsed = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COALESCE(MAX(score), 0) FROM submissions WHERE assignment_id = ?");
$stmt->execute([$assignmentId]);
$highestScore = (float) $stmt->fetchColumn();

// ---- Points ---------------------------------------------------------
$points = filter_input(INPUT_POST, 'points', FILTER_VALIDATE_FLOAT);
if ($points === false || $points === null || $points <= 0) {
    setFlashMessage('error', 'Points must be greater than 0.');
    header('Location: ' . $backUrl);
    exit();
}
$points = round($points, 2);
if ($points < $highestScore) {
    setFlashMessage('error', 'Points cannot be lower than a score already given (' . rtrim(rtrim(number_format($highestScore, 2, '.', ''), '0'), '.') . ').');
    header('Location: ' . $backUrl);
    exit();
}

// ---- Attempts allowed -------------------------------------------------
$maxAttempts = filter_input(INPUT_POST, 'max_attempts', FILTER_VALIDATE_INT);
if ($maxAttempts === false || $maxAttempts === null || $maxAttempts < 1) {
    setFlashMessage('error', 'Attempts allowed must be at least 1.');
    header('Location: ' . $backUrl);
    exit();
}
if ($maxAttempts < $highestAttemptsUsed) {
    setFlashMessage('error', "Attempts allowed cannot be lower than {$highestAttemptsUsed}, because a student has already used that many.");
    header('Location: ' . $backUrl);
    exit();
}

// ---- Grading category -----------------------------------------------
$type = $_POST['type'] ?? 'Activity';
if (!in_array($type, ['Activity', 'Exam'], true)) {
    $type = 'Activity';
}

// ---- Due date (blank = remove the deadline) ---------------------------
$dueDateRaw = trim($_POST['due_date'] ?? '');
$dueDate = null;
if ($dueDateRaw !== '') {
    $parsed = DateTime::createFromFormat('Y-m-d\TH:i', $dueDateRaw) ?: DateTime::createFromFormat('Y-m-d H:i', $dueDateRaw);
    if ($parsed === false) {
        setFlashMessage('error', 'That due date is not valid.');
        header('Location: ' . $backUrl);
        exit();
    }
    $dueDate = $parsed->format('Y-m-d H:i:s');
}

$stmt = $pdo->prepare("
    UPDATE assignments
       SET title = ?, description = ?, due_date = ?, points = ?, type = ?, max_attempts = ?
     WHERE assignment_id = ? AND offering_id = ?
");
$stmt->execute([$title, $description, $dueDate, $points, $type, $maxAttempts, $assignmentId, $offeringId]);

setFlashMessage('success', 'Assignment updated.');
header('Location: ' . $backUrl);
exit();