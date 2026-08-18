<?php
// __DIR__ makes this independent of which script is the actual request
// entry point. Path: public/teacher/assets/api/ -> up 4 -> project root -> config/config.php
require_once __DIR__ . '/../../../../config/config.php';

// ---- Access control -------------------------------------------------
if (!isLoggedIn() || ($_SESSION['role'] ?? '') !== 'teacher') {
    header('Location: ../login.php');
    exit();
}

$userId = (int) $_SESSION['user_id'];

// ---- Resolve the logged-in teacher row -------------------------------
$stmt = $pdo->prepare("SELECT teacher_id, firstname, lastname FROM teachers WHERE user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$teacher = $stmt->fetch();

if (!$teacher) {
    die('Teacher record not found for this account.');
}
$teacherId = (int) $teacher['teacher_id'];

// ---- Validate subject_id / section_id from the query string --------------
$subjectId = filter_input(INPUT_GET, 'subject_id', FILTER_VALIDATE_INT);
$sectionId = filter_input(INPUT_GET, 'section_id', FILTER_VALIDATE_INT);
if (!$subjectId || !$sectionId) {
    header('Location: courses.php');
    exit();
}

// ---- Current school year ---------------------------------------------
$stmt = $pdo->query("SELECT school_year_id, label FROM schoolyears WHERE is_current = 1 LIMIT 1");
$schoolYear      = $stmt->fetch();
$schoolYearId    = $schoolYear['school_year_id'] ?? null;
$schoolYearLabel = $schoolYear['label'] ?? null;

// ---- All term-offerings for this subject+section+school year -------------
// A subject/section pair can have up to 3 rows here (classofferings is
// unique on subject_id + section_id + quarter + school_year_id — one row
// per term). This query, scoped to this teacher, doubles as the
// authorization check: if nothing comes back, this teacher doesn't teach
// this subject in this section and we bounce them out.
$stmt = $pdo->prepare("
    SELECT
        co.offering_id, co.quarter, co.schedule_days, co.start_time, co.end_time,
        co.capacity, co.teacher_id,
        sub.subject_id, sub.subject_name,
        sec.section_id, sec.section_name, sec.grade_level, sec.strand,
        COUNT(e.student_id) AS enrolled_count
    FROM classofferings co
    JOIN subjects sub ON sub.subject_id = co.subject_id
    JOIN sections sec ON sec.section_id = co.section_id
    LEFT JOIN enrollments e ON e.offering_id = co.offering_id AND e.status = 'active'
    WHERE co.teacher_id = ?
      AND co.subject_id = ?
      AND co.section_id = ?
      AND co.status = 'active'
      AND (co.school_year_id = ? OR ? IS NULL)
    GROUP BY co.offering_id, co.quarter, co.schedule_days, co.start_time, co.end_time,
             co.capacity, co.teacher_id, sub.subject_id, sub.subject_name,
             sec.section_id, sec.section_name, sec.grade_level, sec.strand
");
$stmt->execute([$teacherId, $subjectId, $sectionId, $schoolYearId, $schoolYearId]);
$offeringRows = $stmt->fetchAll();

if (empty($offeringRows)) {
    header('Location: courses.php');
    exit();
}

// Class-level info (subject name, section name, grade, strand) is the same
// across all term rows — take it from whichever row we found first.
$classInfo = $offeringRows[0];

// ---- Build Term 1 / 2 / 3 tabs, filling in gaps for terms with no offering yet ----
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

// ---- Active term (defaults to the first term that has an offering) -------
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

// ---- Active nav view (Overview / Students / Attendance / Assignments / Quizzes) --------
$allowedViews = ['overview', 'students', 'attendance', 'assignments', 'quizzes'];
$activeView   = $_GET['view'] ?? 'overview';
if (!in_array($activeView, $allowedViews, true)) {
    $activeView = 'overview';
}

// ---- Flash message (set by material_upload.php / material_delete.php / assignment_*.php) ----
$flash = getFlashMessage();

// ---- Learning materials for the active term's offering -------------------
$materials = [];
if ($activeOfferingId) {
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

// ---- Students enrolled in the active term's offering (Students & Attendance views) -----
$students = [];
if ($activeOfferingId && ($activeView === 'students' || $activeView === 'attendance')) {
    $stmt = $pdo->prepare("
        SELECT s.student_id, s.student_lrn, s.firstname, s.lastname, s.middlename, s.email
        FROM enrollments e
        JOIN students s ON s.student_id = e.student_id
        WHERE e.offering_id = ? AND e.status = 'active'
        ORDER BY s.lastname, s.firstname
    ");
    $stmt->execute([$activeOfferingId]);
    $students = $stmt->fetchAll();
}

// ---- Attendance for the active term's offering ---------------------------
$attendanceDate = null;
$attendanceByStudent = [];
$attendanceSummary = ['Present' => 0, 'Absent' => 0, 'Late' => 0, 'Excused' => 0, 'Unmarked' => 0];
if ($activeOfferingId && $activeView === 'attendance') {
    // Date defaults to today; validate anything passed in the query string
    // so a malformed ?date= can't cause a DB error.
    $requestedDate = $_GET['date'] ?? null;
    if ($requestedDate && DateTime::createFromFormat('Y-m-d', $requestedDate) !== false) {
        $attendanceDate = $requestedDate;
    } else {
        $attendanceDate = date('Y-m-d');
    }

    $stmt = $pdo->prepare("
        SELECT student_id, status, remarks
        FROM attendance
        WHERE offering_id = ? AND attendance_date = ?
    ");
    $stmt->execute([$activeOfferingId, $attendanceDate]);
    foreach ($stmt->fetchAll() as $row) {
        $attendanceByStudent[$row['student_id']] = $row;
    }

    foreach ($students as $s) {
        $status = $attendanceByStudent[$s['student_id']]['status'] ?? null;
        if ($status && isset($attendanceSummary[$status])) {
            $attendanceSummary[$status]++;
        } else {
            $attendanceSummary['Unmarked']++;
        }
    }
}

// ---- Assignments for the active term's offering ---------------------------
$assignments = [];
if ($activeOfferingId && $activeView === 'assignments') {
    $stmt = $pdo->prepare("
        SELECT a.assignment_id, a.title, a.description, a.instructions_file_path,
               a.due_date, a.points, a.max_attempts, a.status, a.created_at,
               COUNT(sub.submission_id) AS submitted_count,
               SUM(CASE WHEN sub.status = 'graded' THEN 1 ELSE 0 END) AS graded_count
        FROM assignments a
        LEFT JOIN submissions sub
               ON sub.assignment_id = a.assignment_id
              AND sub.attempt_number = (
                    SELECT MAX(sub2.attempt_number)
                    FROM submissions sub2
                    WHERE sub2.assignment_id = sub.assignment_id AND sub2.student_id = sub.student_id
              )
        WHERE a.offering_id = ?
        GROUP BY a.assignment_id, a.title, a.description, a.instructions_file_path,
                 a.due_date, a.points, a.max_attempts, a.status, a.created_at
        ORDER BY a.created_at DESC
    ");
    $stmt->execute([$activeOfferingId]);
    $assignments = $stmt->fetchAll();
}

// ---- Selected assignment + its submissions/grading grid --------------------
$selectedAssignment = null;
$submissionRows = [];
$attemptsByStudent = [];
if ($activeOfferingId && $activeView === 'assignments') {
    $requestedAssignmentId = filter_input(INPUT_GET, 'assignment_id', FILTER_VALIDATE_INT);
    if ($requestedAssignmentId) {
        $stmt = $pdo->prepare("
            SELECT assignment_id, title, description, instructions_file_path, due_date, points, max_attempts, status
            FROM assignments
            WHERE assignment_id = ? AND offering_id = ?
            LIMIT 1
        ");
        $stmt->execute([$requestedAssignmentId, $activeOfferingId]);
        $selectedAssignment = $stmt->fetch() ?: null;

        if ($selectedAssignment) {
            // Enrolled students in this offering, left-joined to their (latest) submission
            // plus how many attempts they've used so far.
            $stmt = $pdo->prepare("
                SELECT s.student_id, s.firstname, s.lastname, s.middlename,
                       sub.submission_id, sub.attempt_number, sub.file_path, sub.external_url, sub.submission_text,
                       sub.status AS submission_status, sub.score, sub.feedback,
                       sub.submitted_at, sub.graded_at,
                       (SELECT COUNT(*) FROM submissions sub3
                         WHERE sub3.assignment_id = ? AND sub3.student_id = s.student_id) AS attempts_used
                FROM enrollments e
                JOIN students s ON s.student_id = e.student_id
                LEFT JOIN submissions sub
                       ON sub.student_id = s.student_id
                      AND sub.assignment_id = ?
                      AND sub.attempt_number = (
                            SELECT MAX(sub2.attempt_number)
                            FROM submissions sub2
                            WHERE sub2.assignment_id = sub.assignment_id AND sub2.student_id = sub.student_id
                      )
                WHERE e.offering_id = ? AND e.status = 'active'
                ORDER BY s.lastname, s.firstname
            ");
            $stmt->execute([$selectedAssignment['assignment_id'], $selectedAssignment['assignment_id'], $activeOfferingId]);
            $submissionRows = $stmt->fetchAll();

            // Every attempt (not just the latest) per student, so the teacher can
            // still open earlier files — e.g. the first submission — not only the
            // most recent one. Grouped by student_id for easy lookup in the view.
            $attemptsByStudent = [];
            $stmt = $pdo->prepare("
                SELECT submission_id, student_id, attempt_number, file_path, external_url, submission_text, status, submitted_at
                FROM submissions
                WHERE assignment_id = ?
                ORDER BY student_id, attempt_number ASC
            ");
            $stmt->execute([$selectedAssignment['assignment_id']]);
            $attemptRows = $stmt->fetchAll();

            // All the individual files for those attempts (a submission can have
            // several), grouped by submission_id so each attempt can list its own.
            $filesBySubmission = [];
            if ($attemptRows) {
                $submissionIds = array_column($attemptRows, 'submission_id');
                $placeholders = implode(',', array_fill(0, count($submissionIds), '?'));
                $stmt = $pdo->prepare("
                    SELECT submission_id, original_name, file_path, file_size
                    FROM submission_files
                    WHERE submission_id IN ($placeholders)
                    ORDER BY file_id ASC
                ");
                $stmt->execute($submissionIds);
                foreach ($stmt->fetchAll() as $fileRow) {
                    $filesBySubmission[(int) $fileRow['submission_id']][] = $fileRow;
                }
            }

            foreach ($attemptRows as $attemptRow) {
                $attemptRow['files'] = $filesBySubmission[(int) $attemptRow['submission_id']] ?? [];
                $attemptsByStudent[(int) $attemptRow['student_id']][] = $attemptRow;
            }
        }
    }
}

// ---- Quizzes for the active term's offering --------------------------------
$quizzes = [];
if ($activeOfferingId && $activeView === 'quizzes') {
    // Per-quiz aggregates are pulled as scalar subqueries rather than JOINs so
    // that the question count and the attempt count/average don't multiply
    // each other out (a JOIN across quiz_questions and quiz_attempts would
    // fan out and skew both numbers).
    $stmt = $pdo->prepare("
        SELECT
            q.quiz_id, q.title, q.description, q.status, q.max_attempts,
            q.time_limit_minutes, q.available_from, q.available_until, q.created_at,
            (SELECT COUNT(*) FROM quiz_questions qq WHERE qq.quiz_id = q.quiz_id) AS question_count,
            (SELECT COALESCE(SUM(qq.points), 0) FROM quiz_questions qq WHERE qq.quiz_id = q.quiz_id) AS total_points,
            (SELECT COUNT(*) FROM quiz_attempts qa WHERE qa.quiz_id = q.quiz_id AND qa.status IN ('submitted', 'graded')) AS attempts_submitted,
            (SELECT ROUND(AVG(qa.score), 1) FROM quiz_attempts qa WHERE qa.quiz_id = q.quiz_id AND qa.status IN ('submitted', 'graded')) AS avg_score
        FROM quizzes q
        WHERE q.offering_id = ?
        ORDER BY q.created_at DESC
    ");
    $stmt->execute([$activeOfferingId]);
    $quizzes = $stmt->fetchAll();
}

// ---- Selected quiz + per-student scores grid --------------------------------
$selectedQuiz = null;
$quizAttemptRows = [];
if ($activeOfferingId && $activeView === 'quizzes') {
    $requestedQuizId = filter_input(INPUT_GET, 'quiz_id', FILTER_VALIDATE_INT);
    if ($requestedQuizId) {
        $stmt = $pdo->prepare("
            SELECT
                quiz_id, title, description, status, max_attempts, time_limit_minutes,
                available_from, available_until,
                (SELECT COALESCE(SUM(points), 0) FROM quiz_questions WHERE quiz_id = quizzes.quiz_id) AS total_points
            FROM quizzes
            WHERE quiz_id = ? AND offering_id = ?
            LIMIT 1
        ");
        $stmt->execute([$requestedQuizId, $activeOfferingId]);
        $selectedQuiz = $stmt->fetch() ?: null;

        if ($selectedQuiz) {
            // Enrolled students left-joined to their latest attempt for this quiz
            // (mirrors the "latest attempt" pattern used for assignment submissions).
            $stmt = $pdo->prepare("
                SELECT s.student_id, s.firstname, s.lastname, s.middlename,
                       qa.attempt_id, qa.attempt_number, qa.status AS attempt_status,
                       qa.score, qa.max_score, qa.started_at, qa.submitted_at
                FROM enrollments e
                JOIN students s ON s.student_id = e.student_id
                LEFT JOIN quiz_attempts qa
                       ON qa.student_id = s.student_id
                      AND qa.quiz_id = ?
                      AND qa.attempt_number = (
                            SELECT MAX(qa2.attempt_number)
                            FROM quiz_attempts qa2
                            WHERE qa2.quiz_id = qa.quiz_id AND qa2.student_id = qa.student_id
                      )
                WHERE e.offering_id = ? AND e.status = 'active'
                ORDER BY s.lastname, s.firstname
            ");
            $stmt->execute([$selectedQuiz['quiz_id'], $activeOfferingId]);
            $quizAttemptRows = $stmt->fetchAll();
        }
    }
}

// ---- Selected attempt: full question-by-question answer review for one student ----
$selectedAttempt  = null;
$attemptQuestions = [];
if ($selectedQuiz) {
    $requestedAttemptId = filter_input(INPUT_GET, 'attempt_id', FILTER_VALIDATE_INT);
    if ($requestedAttemptId) {
        // Scoping to quiz_id = $selectedQuiz['quiz_id'] (which is itself already
        // scoped to $activeOfferingId above) keeps a teacher from pulling up an
        // attempt that belongs to a different class/offering.
        $stmt = $pdo->prepare("
            SELECT qa.attempt_id, qa.attempt_number, qa.status AS attempt_status,
                   qa.score, qa.max_score, qa.started_at, qa.submitted_at,
                   s.student_id, s.firstname, s.lastname, s.middlename
            FROM quiz_attempts qa
            JOIN students s ON s.student_id = qa.student_id
            WHERE qa.attempt_id = ? AND qa.quiz_id = ?
            LIMIT 1
        ");
        $stmt->execute([$requestedAttemptId, $selectedQuiz['quiz_id']]);
        $selectedAttempt = $stmt->fetch() ?: null;

        if ($selectedAttempt) {
            // Every question in the quiz, left-joined to whatever this attempt
            // answered (so unanswered questions still show up as "skipped").
            $stmt = $pdo->prepare("
                SELECT qq.question_id, qq.question_text, qq.question_type, qq.points, qq.order_index,
                       a.answer_id, a.selected_choice_id, a.answer_text, a.is_correct, a.points_awarded
                FROM quiz_questions qq
                LEFT JOIN quiz_answers a
                       ON a.question_id = qq.question_id AND a.attempt_id = ?
                WHERE qq.quiz_id = ?
                ORDER BY qq.order_index, qq.question_id
            ");
            $stmt->execute([$selectedAttempt['attempt_id'], $selectedQuiz['quiz_id']]);
            $attemptQuestions = $stmt->fetchAll();

            // Choices for the mcq/true_false questions are pulled in one batch
            // query and grouped in PHP, so each question can show both what the
            // student picked and which choice was actually correct.
            $mcqQuestionIds = array_column(
                array_filter($attemptQuestions, fn($q) => in_array($q['question_type'], ['mcq', 'true_false'], true)),
                'question_id'
            );
            $choicesByQuestion = [];
            if ($mcqQuestionIds) {
                $placeholders = implode(',', array_fill(0, count($mcqQuestionIds), '?'));
                $stmt = $pdo->prepare("
                    SELECT choice_id, question_id, choice_text, is_correct, order_index
                    FROM quiz_choices
                    WHERE question_id IN ($placeholders)
                    ORDER BY order_index, choice_id
                ");
                $stmt->execute($mcqQuestionIds);
                foreach ($stmt->fetchAll() as $choice) {
                    $choicesByQuestion[$choice['question_id']][] = $choice;
                }
            }
            foreach ($attemptQuestions as &$q) {
                $q['choices'] = $choicesByQuestion[$q['question_id']] ?? [];
            }
            unset($q);
        }
    }
}

$csrfToken = generateCSRFToken();

/**
 * Helper: build a link back to this page preserving subject/section
 * and swapping in a given term/view.
 */
function classOverviewUrl(int $subjectId, int $sectionId, ?string $term = null, string $view = 'overview'): string
{
    $params = ['subject_id' => $subjectId, 'section_id' => $sectionId, 'view' => $view];
    if ($term) {
        $params['term'] = $term;
    }
    return 'class_overview.php?' . http_build_query($params);
}

/**
 * Helper: build a link to the Attendance view for a specific date,
 * preserving subject/section/term.
 */
function attendanceUrl(int $subjectId, int $sectionId, ?string $term, string $date): string
{
    $params = [
        'subject_id' => $subjectId,
        'section_id' => $sectionId,
        'view'       => 'attendance',
        'date'       => $date,
    ];
    if ($term) {
        $params['term'] = $term;
    }
    return 'class_overview.php?' . http_build_query($params);
}

/**
 * Helper: human-readable file size.
 */
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

/**
 * Helper: FontAwesome icon class per material type.
 */
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

/**
 * Helper: build a link to the Assignments view, optionally deep-linking
 * to a specific assignment's submissions/grading grid.
 */
function assignmentsUrl(int $subjectId, int $sectionId, ?string $term, ?int $assignmentId = null): string
{
    $params = [
        'subject_id' => $subjectId,
        'section_id' => $sectionId,
        'view'       => 'assignments',
    ];
    if ($term) {
        $params['term'] = $term;
    }
    if ($assignmentId) {
        $params['assignment_id'] = $assignmentId;
    }
    return 'class_overview.php?' . http_build_query($params);
}

/**
 * Helper: a short, human-friendly due-date label, flagging overdue items.
 */
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
 * Helper: submission status for a student row, accounting for "missing"
 * (no submission row at all) and "late" (submitted after the due date).
 */
function submissionStatusInfo(?string $status, ?string $submittedAt, ?string $dueDate): array
{
    if (!$status) {
        return ['label' => 'Missing', 'class' => 'missing'];
    }
    if ($status === 'graded') {
        return ['label' => 'Graded', 'class' => 'graded'];
    }
    if ($dueDate && $submittedAt && strtotime($submittedAt) > strtotime($dueDate)) {
        return ['label' => 'Late', 'class' => 'late'];
    }
    return ['label' => 'Submitted', 'class' => 'submitted'];
}

/**
 * Helper: build a link to the Quizzes view, optionally deep-linking to a
 * specific quiz's score/grading grid.
 */
function quizzesUrl(int $subjectId, int $sectionId, ?string $term, ?int $quizId = null, ?int $attemptId = null): string
{
    $params = [
        'subject_id' => $subjectId,
        'section_id' => $sectionId,
        'view'       => 'quizzes',
    ];
    if ($term) {
        $params['term'] = $term;
    }
    if ($quizId) {
        $params['quiz_id'] = $quizId;
    }
    if ($attemptId) {
        $params['attempt_id'] = $attemptId;
    }
    return 'class_overview.php?' . http_build_query($params);
}

/**
 * Helper: display label/class for a quiz's publish status.
 */
function quizStatusInfo(string $status): array
{
    return match ($status) {
        'published' => ['label' => 'Published', 'class' => 'published'],
        'closed'    => ['label' => 'Closed', 'class' => 'closed'],
        default     => ['label' => 'Draft', 'class' => 'draft'],
    };
}

/**
 * Helper: a student's quiz-attempt status, accounting for "not attempted"
 * (no attempt row at all) the same way submissionStatusInfo() does for
 * assignments.
 */
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

/**
 * Helper: display label/class for a single answer within the answer-review
 * modal. Short-answer questions are auto-graded as NULL (is_correct) until a
 * teacher scores them, so that's surfaced as "Needs grading" rather than wrong.
 */
function quizAnswerStatusInfo(array $question): array
{
    if ($question['answer_id'] === null) {
        return ['label' => 'Skipped', 'class' => 'missing'];
    }
    if ($question['is_correct'] === null) {
        return ['label' => 'Needs grading', 'class' => 'late'];
    }
    return $question['is_correct']
        ? ['label' => 'Correct', 'class' => 'present']
        : ['label' => 'Incorrect', 'class' => 'absent'];
}