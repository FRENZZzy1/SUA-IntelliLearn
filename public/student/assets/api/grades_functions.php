<?php
/**
 * grades_functions.php (student)
 *
 * Backend for public/student/grades.php ("My Submissions").
 *
 * Lets a student see, in one place, every Written Work / Performance Task /
 * Exam item posted in each class they are enrolled in, whether they
 * submitted it, and their score - organized by term:
 *
 *   School Year -> Term (TRM 1 / TRM 2 / TRM 3) -> enrolled class (subject)
 *               -> Written Works / Performance Tasks / Exam
 *
 * The category rules are the ones the teacher's Grade Book and Grade Details
 * page use (gradebook_status_functions.php), so the student and the teacher
 * always see the same list and the same Submitted / Not Submitted status:
 *
 *   Written Works     = every quiz + assignments of type 'Written Work'
 *   Performance Tasks = assignments of type 'Activity'
 *   Exam              = assignments of type 'Exam'
 *
 * Only the logged-in student's own data is ever loaded.
 */
require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../teacher/assets/api/gradebook_status_functions.php';

// ---- Access control -------------------------------------------------
if (!isLoggedIn() || ($_SESSION['role'] ?? '') !== 'student') {
    header('Location: ../../login.php');
    exit();
}

const TERM_ORDER = ['TRM 1', 'TRM 2', 'TRM 3'];

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

// ---- School years this student has an enrollment in ---------------------
// Enrollments the admin has unenrolled ('dropped') are excluded everywhere on
// this page, so a school year that only contains dropped classes isn't listed.
$stmt = $pdo->prepare("
    SELECT DISTINCT sy.school_year_id, sy.label, sy.is_current, sy.start_date
    FROM enrollments e
    JOIN classofferings co ON co.offering_id = e.offering_id
    JOIN schoolyears sy    ON sy.school_year_id = co.school_year_id
    WHERE e.student_id = ?
      AND e.status IN ('enrolled','completed')
      AND ((co.is_archived = 0 AND co.section_id NOT IN (SELECT section_id FROM sections WHERE is_archived = 1)) OR sy.is_archived = 1)
    ORDER BY sy.start_date DESC
");
$stmt->execute([$studentId]);
$schoolYears = $stmt->fetchAll();

// ---- Which school year is selected? (defaults to the current one, or
//      the most recent one this student has records for) -----------------
$requestedYearId = filter_input(INPUT_GET, 'school_year_id', FILTER_VALIDATE_INT) ?: null;
$selectedYearId  = null;
foreach ($schoolYears as $sy) {
    if ($requestedYearId && (int) $sy['school_year_id'] === $requestedYearId) {
        $selectedYearId = (int) $sy['school_year_id'];
        break;
    }
}
if ($selectedYearId === null) {
    foreach ($schoolYears as $sy) {
        if ((int) $sy['is_current'] === 1) {
            $selectedYearId = (int) $sy['school_year_id'];
            break;
        }
    }
}
if ($selectedYearId === null && !empty($schoolYears)) {
    $selectedYearId = (int) $schoolYears[0]['school_year_id'];
}
$selectedYearLabel = null;
foreach ($schoolYears as $sy) {
    if ((int) $sy['school_year_id'] === $selectedYearId) {
        $selectedYearLabel = $sy['label'];
        break;
    }
}

// ---- Enrolled classes for the selected school year -----------------------
$enrollmentRows = [];
if ($selectedYearId !== null) {
    $stmt = $pdo->prepare("
        SELECT
            e.enrollment_id,
            co.offering_id, co.quarter,
            sub.subject_id, sub.subject_name,
            sec.section_name, sec.grade_level,
            t.firstname AS teacher_first, t.lastname AS teacher_last
        FROM enrollments e
        JOIN classofferings co ON co.offering_id = e.offering_id
        JOIN schoolyears sy    ON sy.school_year_id = co.school_year_id
        JOIN subjects sub      ON sub.subject_id = co.subject_id
        JOIN sections sec      ON sec.section_id = co.section_id
        JOIN teachers t        ON t.teacher_id = co.teacher_id
        WHERE e.student_id = ? AND co.school_year_id = ?
          AND e.status IN ('enrolled','completed')
          AND ((co.is_archived = 0 AND co.section_id NOT IN (SELECT section_id FROM sections WHERE is_archived = 1)) OR sy.is_archived = 1)
        ORDER BY sub.subject_name, co.quarter
    ");
    $stmt->execute([$studentId, $selectedYearId]);
    $enrollmentRows = $stmt->fetchAll();
}

// ---- Score formatting helper ----------------------------------------------
function ss_fmt($n): string
{
    return rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');
}

// Items can only be opened from this page when the course view can show them:
// that page only loads the CURRENT school year and only 'published' items.
$yearIsCurrent = false;
foreach ($schoolYears as $sy) {
    if ((int) $sy['school_year_id'] === $selectedYearId && (int) $sy['is_current'] === 1) {
        $yearIsCurrent = true;
        break;
    }
}

// ---- One entry per enrolled class, each with its three category tables ----
$mySubjects = [];
foreach ($enrollmentRows as $row) {
    $loaded = gb_load_items($pdo, (int) $row['offering_id'], $studentId);
    $items  = $loaded['items'];
    $done   = $loaded['done'][$studentId]   ?? [];
    $scores = $loaded['scores'][$studentId] ?? [];

    $categories = [];
    $totalItems = 0;
    $submitted  = 0;
    foreach (GB_CATEGORIES as $key => $label) {
        $rows = [];
        foreach ($items[$key] as $it) {
            $isDone = !empty($done[$it['uid']]);
            $sc     = $scores[$it['uid']] ?? null;

            // Score cell: '—' = not submitted, 'Pending' = submitted but not
            // graded yet, otherwise "score / max" plus a % band for colouring.
            $scoreText  = '—';
            $scoreClass = 'none';
            if ($isDone) {
                if ($sc === null || $sc['score'] === null) {
                    $scoreText = 'Pending';
                } else {
                    $max       = (float) $sc['max'];
                    $scoreText = ss_fmt($sc['score']) . ($max > 0 ? ' / ' . ss_fmt($max) : '');
                    $pct       = $max > 0 ? ($sc['score'] / $max) * 100 : null;
                    $scoreClass = $pct === null ? 'none'
                        : ($pct >= 90 ? 'excellent' : ($pct >= 75 ? 'good' : ($pct >= 60 ? 'fair' : 'low')));
                }
            }

            // Where the item lives: course_view.php detail page for that item.
            $url = null;
            if ($yearIsCurrent && ($it['status'] ?? '') === 'published') {
                $url = 'course_view.php?' . http_build_query([
                    'subject_id' => (int) $row['subject_id'],
                    'view'       => $it['kind'] === 'Quiz' ? 'quizzes' : 'assignments',
                    'term'       => $row['quarter'],
                    ($it['kind'] === 'Quiz' ? 'quiz_id' : 'assignment_id') => (int) $it['id'],
                ]);
            }

            $rows[] = [
                'url'         => $url,
                'title'       => $it['title'],
                'kind'        => $it['kind'],
                'submitted'   => $isDone,
                'score_text'  => $scoreText,
                'score_class' => $scoreClass,
            ];
            $totalItems++;
            if ($isDone) $submitted++;
        }
        $categories[$key] = ['label' => $label, 'rows' => $rows];
    }

    $mySubjects[] = [
        'enrollment_id' => (int) $row['enrollment_id'],
        'offering_id'   => (int) $row['offering_id'],
        'quarter'       => $row['quarter'],
        'subject_name'  => $row['subject_name'],
        'teacher_name'  => trim($row['teacher_first'] . ' ' . $row['teacher_last']),
        'section_name'  => $row['section_name'],
        'grade_level'   => $row['grade_level'],
        'categories'    => $categories,
        'total_items'   => $totalItems,
        'submitted'     => $submitted,
    ];
}
$totalClasses = count($mySubjects);

// ---- Group classes by term, in TRM 1 -> TRM 2 -> TRM 3 order --------------
$termGroups = [];
foreach (TERM_ORDER as $term) {
    $termGroups[$term] = [];
}
// Any legacy/unexpected quarter label (e.g. 'Prelim') simply gets its own
// group after TRM 1/2/3 instead of being dropped.
foreach ($mySubjects as $s) {
    $termGroups[$s['quarter']][] = $s;
}

// ---- Sidebar/header context ---------------------------------------------
$firstWithSection = null;
foreach ($mySubjects as $s) {
    if (!empty($s['section_name'])) {
        $firstWithSection = $s;
        break;
    }
}
$studentGradeSection = $firstWithSection
    ? "Grade {$firstWithSection['grade_level']} - {$firstWithSection['section_name']}"
    : null;
