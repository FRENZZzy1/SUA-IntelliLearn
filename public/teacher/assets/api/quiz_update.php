<?php
// Direct POST target for the "Edit quiz" modal on class_overview.php (Quizzes tab).
// Lets a teacher change the title, instructions, open/due dates, attempts allowed,
// time limit and status of a quiz that already exists. Questions are not touched here.
//
// The student side reads available_from / available_until / max_attempts / status
// straight from the quizzes table, so changes take effect immediately.
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
$subjectId  = filter_input(INPUT_POST, 'subject_id', FILTER_VALIDATE_INT);
$sectionId  = filter_input(INPUT_POST, 'section_id', FILTER_VALIDATE_INT);
$term       = $_POST['term'] ?? null;
$quizId     = filter_input(INPUT_POST, 'quiz_id', FILTER_VALIDATE_INT);
$offeringId = filter_input(INPUT_POST, 'offering_id', FILTER_VALIDATE_INT);
$fromDetail = !empty($_POST['from_detail']);

$backParams = [
    'subject_id' => $subjectId,
    'section_id' => $sectionId,
    'term'       => $term,
    'view'       => 'quizzes',
];
if ($fromDetail && $quizId) {
    $backParams['quiz_id'] = $quizId;
}
$backUrl = '../../class_overview.php?' . http_build_query($backParams);

function quiz_update_fail(string $message, string $backUrl): void
{
    setFlashMessage('error', $message);
    header('Location: ' . $backUrl);
    exit();
}

// ---- CSRF -----------------------------------------------------------
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    quiz_update_fail('Your session expired. Please try again.', $backUrl);
}

if (!$quizId || !$offeringId) {
    quiz_update_fail('Invalid request.', $backUrl);
}

// ---- Authorization: the quiz must belong to one of this teacher's active classes ----
$stmt = $pdo->prepare("
    SELECT q.quiz_id
    FROM quizzes q
    JOIN classofferings co ON co.offering_id = q.offering_id AND co.section_id NOT IN (SELECT section_id FROM sections WHERE is_archived = 1) AND co.is_archived = 0
    WHERE q.quiz_id = ? AND q.offering_id = ? AND co.teacher_id = ?
    LIMIT 1
");
$stmt->execute([$quizId, $offeringId, $teacherId]);
if (!$stmt->fetch()) {
    quiz_update_fail('Quiz not found or you do not have access to it.', $backUrl);
}

// ---- Title / instructions ---------------------------------------------
$title = trim($_POST['title'] ?? '');
if ($title === '') {
    quiz_update_fail('Please give the quiz a title.', $backUrl);
}
$title = mb_substr($title, 0, 255);

$description = trim($_POST['description'] ?? '');
$description = $description === '' ? null : $description;

// ---- Status -----------------------------------------------------------
$status = $_POST['status'] ?? 'draft';
if (!in_array($status, ['draft', 'published', 'closed'], true)) {
    quiz_update_fail('That status is not valid.', $backUrl);
}

// ---- Attempts allowed: cannot drop below what a student already used ----
$stmt = $pdo->prepare("
    SELECT COALESCE(MAX(cnt), 0) FROM (
        SELECT COUNT(*) AS cnt FROM quiz_attempts WHERE quiz_id = ? GROUP BY student_id
    ) t
");
$stmt->execute([$quizId]);
$highestAttemptsUsed = (int) $stmt->fetchColumn();

$maxAttempts = filter_input(INPUT_POST, 'max_attempts', FILTER_VALIDATE_INT);
if ($maxAttempts === false || $maxAttempts === null || $maxAttempts < 1) {
    quiz_update_fail('Attempts allowed must be at least 1.', $backUrl);
}
if ($maxAttempts < $highestAttemptsUsed) {
    quiz_update_fail("Attempts allowed cannot be lower than {$highestAttemptsUsed}, because a student has already used that many.", $backUrl);
}

// ---- Time limit (blank = no limit) -----------------------------------
$timeLimitRaw = trim($_POST['time_limit_minutes'] ?? '');
$timeLimit = null;
if ($timeLimitRaw !== '') {
    $timeLimit = filter_var($timeLimitRaw, FILTER_VALIDATE_INT);
    if ($timeLimit === false || $timeLimit < 1) {
        quiz_update_fail('Time limit must be a whole number of minutes (or left blank for no limit).', $backUrl);
    }
}

// ---- Dates (blank = open immediately / no due date) --------------------
$parseDate = function (string $raw): ?string {
    $raw = trim($raw);
    if ($raw === '') {
        return null;
    }
    $parsed = DateTime::createFromFormat('Y-m-d\TH:i', $raw) ?: DateTime::createFromFormat('Y-m-d H:i', $raw);
    if ($parsed === false) {
        throw new InvalidArgumentException('invalid date');
    }
    return $parsed->format('Y-m-d H:i:s');
};

try {
    $availFrom  = $parseDate($_POST['available_from'] ?? '');
    $availUntil = $parseDate($_POST['available_until'] ?? '');
} catch (InvalidArgumentException $e) {
    quiz_update_fail('One of the dates is not valid.', $backUrl);
}
if ($availFrom && $availUntil && strtotime($availUntil) <= strtotime($availFrom)) {
    quiz_update_fail('The due date must be after the available-from date.', $backUrl);
}

$stmt = $pdo->prepare("
    UPDATE quizzes
       SET title = ?, description = ?, available_from = ?, available_until = ?,
           max_attempts = ?, time_limit_minutes = ?, status = ?
     WHERE quiz_id = ? AND offering_id = ?
");
$stmt->execute([$title, $description, $availFrom, $availUntil, $maxAttempts, $timeLimit, $status, $quizId, $offeringId]);

setFlashMessage('success', 'Quiz updated.');
header('Location: ' . $backUrl);
exit();
