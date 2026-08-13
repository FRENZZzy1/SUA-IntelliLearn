<?php
/**
 * course_view_functions.php (student)
 *
 * Backend for public/student/course_view.php — the student-facing
 * equivalent of the teacher's class_overview.php, scoped read-only to
 * a single subject this student is enrolled in.
 *
 * Path: public/student/assets/api/ -> up 4 -> project root -> config/config.php
 */
require_once __DIR__ . '/../../../../config/config.php';

// ---- Access control -------------------------------------------------
if (!isLoggedIn() || ($_SESSION['role'] ?? '') !== 'student') {
    header('Location: ../../login.php');
    exit();
}

$userId = (int) $_SESSION['user_id'];

// ---- Resolve the logged-in student row --------------------------------
$stmt = $pdo->prepare("SELECT student_id, firstname, lastname FROM students WHERE user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$student = $stmt->fetch();

if (!$student) {
    die('Student record not found for this account.');
}
$studentId       = (int) $student['student_id'];
$studentFullName = trim($student['firstname'] . ' ' . $student['lastname']);

// ---- Validate subject_id from the query string -------------------------
$subjectId = filter_input(INPUT_GET, 'subject_id', FILTER_VALIDATE_INT);
if (!$subjectId) {
    header('Location: courses.php');
    exit();
}

// ---- Current school year ---------------------------------------------
$stmt = $pdo->query("SELECT school_year_id, label FROM schoolyears WHERE is_current = 1 LIMIT 1");
$schoolYear      = $stmt->fetch();
$schoolYearId    = $schoolYear['school_year_id'] ?? null;
$schoolYearLabel = $schoolYear['label'] ?? null;

// ---- All term-offerings for this subject that THIS student is enrolled in --
// Scoped to (student_id, subject_id) together: this doubles as the
// authorization check. If nothing comes back, this student isn't enrolled
// in this subject and we bounce them back to My Courses rather than
// leaking another student's class by URL guessing.
$stmt = $pdo->prepare("
    SELECT
        e.enrollment_id, co.offering_id, co.quarter, co.schedule_days, co.start_time, co.end_time,
        co.capacity,
        sub.subject_id, sub.subject_name,
        sec.section_id, sec.section_name, sec.grade_level, sec.strand,
        t.teacher_id, t.firstname AS teacher_first, t.lastname AS teacher_last, t.email AS teacher_email
    FROM enrollments e
    JOIN classofferings co ON co.offering_id = e.offering_id
    JOIN subjects sub      ON sub.subject_id = co.subject_id
    JOIN sections sec      ON sec.section_id = co.section_id
    JOIN teachers t        ON t.teacher_id = co.teacher_id
    WHERE e.student_id = ?
      AND co.subject_id = ?
      AND e.status = 'active'
      AND co.status = 'active'
      AND (co.school_year_id = ? OR ? IS NULL)
");
$stmt->execute([$studentId, $subjectId, $schoolYearId, $schoolYearId]);
$offeringRows = $stmt->fetchAll();

if (empty($offeringRows)) {
    header('Location: courses.php');
    exit();
}

// Class-level info (subject name, section, teacher) is the same across
// all term rows — take it from whichever row we found first.
$classInfo = $offeringRows[0];

$studentGradeSection = "Grade {$classInfo['grade_level']} - {$classInfo['section_name']}";

// ---- Build Term 1 / 2 / 3 tabs, filling in gaps for terms the student isn't enrolled in ----
$termLabels = ['TRM 1' => 'Term 1', 'TRM 2' => 'Term 2', 'TRM 3' => 'Term 3'];
$terms = [];
foreach ($termLabels as $key => $label) {
    $terms[$key] = ['key' => $key, 'label' => $label, 'offering' => null];
}
foreach ($offeringRows as $row) {
    if (isset($terms[$row['quarter']])) {
        $terms[$row['quarter']]['offering'] = $row;
    }
}

// ---- Active term (defaults to the first term the student is enrolled in) ----
$requestedTerm = $_GET['term'] ?? null;
if (isset($terms[$requestedTerm]) && $terms[$requestedTerm]['offering']) {
    $activeTerm = $requestedTerm;
} else {
    $activeTerm = null;
    foreach ($terms as $key => $t) {
        if ($t['offering']) {
            $activeTerm = $key;
            break;
        }
    }
}
$activeOffering   = $activeTerm ? $terms[$activeTerm]['offering'] : null;
$activeOfferingId = $activeOffering['offering_id'] ?? null;

// ---- Active nav view (Overview / Materials / Assignments / Quizzes) --------
// Attendance moved out to its own module (public/student/attendance.php),
// listed per-subject there instead of living inside a single course's tabs.
$allowedViews = ['overview', 'materials', 'assignments', 'quizzes'];
$activeView   = $_GET['view'] ?? 'overview';
if (!in_array($activeView, $allowedViews, true)) {
    $activeView = 'overview';
}

// ---- CSRF token (for the assignment-submission form) + flash message
// (set by assignment_submit.php after a submit/resubmit) ----------------
$csrfToken = generateCSRFToken();
$flash     = getFlashMessage();

// ---- Learning materials for the active term's offering -------------------
$materials = [];
if ($activeOfferingId && in_array($activeView, ['overview', 'materials'], true)) {
    $stmt = $pdo->prepare("
        SELECT lm.material_id, lm.title, lm.type, lm.file_path, lm.external_url,
               lm.file_size, lm.created_at, t.firstname, t.lastname
        FROM learning_materials lm
        JOIN teachers t ON t.teacher_id = lm.uploaded_by
        WHERE lm.offering_id = ?
        ORDER BY lm.created_at DESC
    ");
    $stmt->execute([$activeOfferingId]);
    $materials = $stmt->fetchAll();
}

// ---- Assignments for the active term's offering, with this student's own
// (latest) submission attached ---------------------------------------------
$assignments = [];
if ($activeOfferingId && in_array($activeView, ['overview', 'assignments'], true)) {
    $stmt = $pdo->prepare("
        SELECT
            a.assignment_id, a.title, a.description, a.instructions_file_path,
            a.due_date, a.points, a.max_attempts, a.status AS assignment_status, a.created_at,
            s.submission_id, s.status AS submission_status, s.score, s.feedback,
            s.submitted_at, s.file_path AS submission_file_path, s.external_url AS submission_url,
            s.submission_text,
            (SELECT COUNT(*) FROM submissions s3
              WHERE s3.assignment_id = a.assignment_id AND s3.student_id = ?) AS attempts_used
        FROM assignments a
        LEFT JOIN submissions s
               ON s.submission_id = (
                    SELECT s2.submission_id
                    FROM submissions s2
                    WHERE s2.assignment_id = a.assignment_id AND s2.student_id = ?
                    ORDER BY s2.attempt_number DESC
                    LIMIT 1
               )
        WHERE a.offering_id = ? AND a.status = 'published'
        ORDER BY a.due_date IS NULL, a.due_date ASC
    ");
    $stmt->execute([$studentId, $studentId, $activeOfferingId]);
    $assignments = $stmt->fetchAll();
}

// ---- Selected assignment detail (read-only) --------------------------------
$selectedAssignment = null;
if ($activeOfferingId && $activeView === 'assignments') {
    $requestedAssignmentId = filter_input(INPUT_GET, 'assignment_id', FILTER_VALIDATE_INT);
    if ($requestedAssignmentId) {
        $stmt = $pdo->prepare("
            SELECT
                a.assignment_id, a.title, a.description, a.instructions_file_path,
                a.due_date, a.points, a.max_attempts, a.status AS assignment_status,
                s.submission_id, s.status AS submission_status, s.score, s.feedback,
                s.submitted_at, s.file_path AS submission_file_path, s.external_url AS submission_url,
                s.submission_text,
                (SELECT COUNT(*) FROM submissions s3
                  WHERE s3.assignment_id = a.assignment_id AND s3.student_id = ?) AS attempts_used
            FROM assignments a
            LEFT JOIN submissions s
                   ON s.submission_id = (
                        SELECT s2.submission_id
                        FROM submissions s2
                        WHERE s2.assignment_id = a.assignment_id AND s2.student_id = ?
                        ORDER BY s2.attempt_number DESC
                        LIMIT 1
                   )
            WHERE a.assignment_id = ? AND a.offering_id = ? AND a.status = 'published'
            LIMIT 1
        ");
        $stmt->execute([$studentId, $studentId, $requestedAssignmentId, $activeOfferingId]);
        $selectedAssignment = $stmt->fetch() ?: null;

        // ---- Files attached to that submission (a submission can have several) ----
        if ($selectedAssignment) {
            $selectedAssignment['files'] = [];
            if ($selectedAssignment['submission_id']) {
                $stmt = $pdo->prepare("
                    SELECT original_name, file_path, file_size
                    FROM submission_files
                    WHERE submission_id = ?
                    ORDER BY file_id ASC
                ");
                $stmt->execute([$selectedAssignment['submission_id']]);
                $selectedAssignment['files'] = $stmt->fetchAll();
            }
        }
    }
}

// ---- Every attempt this student has made on the selected assignment (not
// just the latest one), each with the files that were attached to that
// specific attempt — lets the student review what they turned in each time --
$assignmentAttempts = [];
if ($selectedAssignment) {
    $stmt = $pdo->prepare("
        SELECT submission_id, attempt_number, status AS submission_status, score, feedback,
               submitted_at, file_path AS submission_file_path, external_url AS submission_url,
               submission_text
        FROM submissions
        WHERE assignment_id = ? AND student_id = ?
        ORDER BY attempt_number DESC
    ");
    $stmt->execute([$selectedAssignment['assignment_id'], $studentId]);
    $assignmentAttempts = $stmt->fetchAll();

    if ($assignmentAttempts) {
        // One query for every file across all attempts, then group by submission_id,
        // instead of one query per attempt.
        $submissionIds = array_column($assignmentAttempts, 'submission_id');
        $placeholders  = implode(',', array_fill(0, count($submissionIds), '?'));
        $stmt = $pdo->prepare("
            SELECT submission_id, original_name, file_path, file_size
            FROM submission_files
            WHERE submission_id IN ($placeholders)
            ORDER BY file_id ASC
        ");
        $stmt->execute($submissionIds);
        $filesBySubmission = [];
        foreach ($stmt->fetchAll() as $f) {
            $filesBySubmission[$f['submission_id']][] = $f;
        }

        foreach ($assignmentAttempts as &$attempt) {
            $attempt['files'] = $filesBySubmission[$attempt['submission_id']] ?? [];
        }
        unset($attempt);
    }
}

// ---- Quizzes for the active term's offering, with this student's own
// (latest) attempt attached --------------------------------------------------
$quizzes = [];
if ($activeOfferingId && in_array($activeView, ['overview', 'quizzes'], true)) {
    $stmt = $pdo->prepare("
        SELECT
            q.quiz_id, q.title, q.description, q.status AS quiz_status,
            q.time_limit_minutes, q.max_attempts, q.available_from, q.available_until,
            (SELECT COALESCE(SUM(points), 0) FROM quiz_questions WHERE quiz_id = q.quiz_id) AS total_points,
            (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = q.quiz_id) AS question_count,
            qa.attempt_id, qa.attempt_number, qa.status AS attempt_status, qa.score, qa.max_score, qa.submitted_at
        FROM quizzes q
        LEFT JOIN quiz_attempts qa
               ON qa.quiz_id = q.quiz_id
              AND qa.student_id = ?
              AND qa.attempt_number = (
                    SELECT MAX(qa2.attempt_number)
                    FROM quiz_attempts qa2
                    WHERE qa2.quiz_id = q.quiz_id AND qa2.student_id = ?
              )
        WHERE q.offering_id = ? AND q.status = 'published'
        ORDER BY q.created_at DESC
    ");
    $stmt->execute([$studentId, $studentId, $activeOfferingId]);
    $quizzes = $stmt->fetchAll();
}

// ---- Selected quiz detail (read-only) ---------------------------------------
$selectedQuiz = null;
$quizAttempts = [];
if ($activeOfferingId && $activeView === 'quizzes') {
    $requestedQuizId = filter_input(INPUT_GET, 'quiz_id', FILTER_VALIDATE_INT);
    if ($requestedQuizId) {
        $stmt = $pdo->prepare("
            SELECT
                quiz_id, title, description, status AS quiz_status, max_attempts,
                time_limit_minutes, available_from, available_until,
                (SELECT COALESCE(SUM(points), 0) FROM quiz_questions WHERE quiz_id = quizzes.quiz_id) AS total_points
            FROM quizzes
            WHERE quiz_id = ? AND offering_id = ? AND status = 'published'
            LIMIT 1
        ");
        $stmt->execute([$requestedQuizId, $activeOfferingId]);
        $selectedQuiz = $stmt->fetch() ?: null;

        if ($selectedQuiz) {
            $stmt = $pdo->prepare("
                SELECT attempt_id, attempt_number, status AS attempt_status, score, max_score, started_at, submitted_at
                FROM quiz_attempts
                WHERE quiz_id = ? AND student_id = ?
                ORDER BY attempt_number DESC
            ");
            $stmt->execute([$requestedQuizId, $studentId]);
            $quizAttempts = $stmt->fetchAll();
        }
    }
}

$materialsCount    = count($materials);
$assignmentsCount  = count($assignments);
$quizzesCount      = count($quizzes);

/**
 * Helper: build a link back to this page preserving subject and
 * swapping in a given term/view.
 */
function courseViewUrl(int $subjectId, ?string $term = null, string $view = 'overview'): string
{
    $params = ['subject_id' => $subjectId, 'view' => $view];
    if ($term) {
        $params['term'] = $term;
    }
    return 'course_view.php?' . http_build_query($params);
}

function assignmentsUrlStudent(int $subjectId, ?string $term, ?int $assignmentId = null): string
{
    $params = ['subject_id' => $subjectId, 'view' => 'assignments'];
    if ($term) {
        $params['term'] = $term;
    }
    if ($assignmentId) {
        $params['assignment_id'] = $assignmentId;
    }
    return 'course_view.php?' . http_build_query($params);
}

function quizzesUrlStudent(int $subjectId, ?string $term, ?int $quizId = null): string
{
    $params = ['subject_id' => $subjectId, 'view' => 'quizzes'];
    if ($term) {
        $params['term'] = $term;
    }
    if ($quizId) {
        $params['quiz_id'] = $quizId;
    }
    return 'course_view.php?' . http_build_query($params);
}

/**
 * Helper: resolve a learning material's/assignment attachment's openable
 * URL. Files are uploaded and stored (as a path relative to
 * public/teacher/) by the teacher side, so a relative file_path needs the
 * app-root-relative teacher prefix to resolve correctly from
 * public/student/. External links are used as-is.
 */
function resolveFileUrl(?string $filePath, ?string $externalUrl = null): ?string
{
    if (!empty($externalUrl)) {
        return $externalUrl;
    }
    if (!empty($filePath)) {
        return '/SUA-INTELLILEARN/public/teacher/' . ltrim($filePath, '/');
    }
    return null;
}

/** Human-readable file size. */
function formatFileSize(?int $bytes): string
{
    if (!$bytes) {
        return '';
    }
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    $size = (float) $bytes;
    while ($size >= 1024 && $i < count($units) - 1) {
        $size /= 1024;
        $i++;
    }
    return round($size, $size < 10 ? 1 : 0) . ' ' . $units[$i];
}

/** FontAwesome icon class per material type. */
function materialIcon(string $type): string
{
    return match ($type) {
        'pdf'    => 'fa-file-pdf',
        'video'  => 'fa-file-video',
        'slides' => 'fa-file-powerpoint',
        'link'   => 'fa-link',
        default  => 'fa-file',
    };
}

/** Short, human-friendly due-date label, flagging overdue items. */
function dueDateLabel(?string $dueDate): array
{
    if (!$dueDate) {
        return ['label' => 'No due date', 'overdue' => false];
    }
    $ts = strtotime($dueDate);
    return [
        'label'   => date('M j, Y g:i A', $ts),
        'overdue' => $ts < time(),
    ];
}

/**
 * Student-facing submission status, accounting for "missing" (no
 * submission on file) and "late" (submitted after the due date).
 */
function submissionStatusInfo(?string $status, ?string $submittedAt, ?string $dueDate): array
{
    if (!$status) {
        return ['label' => 'Not submitted', 'class' => 'missing'];
    }
    if ($status === 'graded') {
        return ['label' => 'Graded', 'class' => 'graded'];
    }
    if ($status === 'returned') {
        return ['label' => 'Returned', 'class' => 'late'];
    }
    if ($dueDate && $submittedAt && strtotime($submittedAt) > strtotime($dueDate)) {
        return ['label' => 'Submitted late', 'class' => 'late'];
    }
    return ['label' => 'Submitted', 'class' => 'submitted'];
}

/** Display label/class for a quiz's availability. */
function quizStatusInfo(string $status): array
{
    return match ($status) {
        'closed' => ['label' => 'Closed', 'class' => 'closed'],
        default  => ['label' => 'Available', 'class' => 'published'],
    };
}

/** A student's quiz-attempt status, accounting for "not attempted". */
function quizAttemptStatusInfo(?string $status): array
{
    if (!$status) {
        return ['label' => 'Not attempted', 'class' => 'missing'];
    }
    return match ($status) {
        'graded'      => ['label' => 'Graded', 'class' => 'graded'],
        'submitted'   => ['label' => 'Submitted', 'class' => 'submitted'],
        'in_progress' => ['label' => 'In progress', 'class' => 'late'],
        default       => ['label' => ucfirst($status), 'class' => 'missing'],
    };
}