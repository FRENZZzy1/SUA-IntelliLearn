<?php
/**
 * calendar_functions.php (teacher)
 *
 * Backend for public/teacher/calendar.php ("Calendar").
 *
 * Recycled from the student calendar: same month grid + agenda panel, but
 * each date marker is a deadline in one of the teacher's classes
 * (assignments keyed on `due_date`, quizzes keyed on `available_until`)
 * and carries a GRADING status instead of a "did I finish it" status:
 *
 *   turned_in  = enrolled students whose latest attempt/submission is in
 *   to_grade   = latest attempt turned in but not yet scored
 *   enrolled   = active roster size of that class offering
 *
 * No new tables — everything comes from assignments, submissions, quizzes,
 * quiz_attempts, classofferings and enrollments. The "to grade" rule for
 * assignments is identical to the dashboard's "Assignments to Grade" metric.
 */
require_once __DIR__ . '/../../../../config/config.php';

// ---- Access control -------------------------------------------------
if (!isLoggedIn() || ($_SESSION['role'] ?? '') !== 'teacher') {
    header('Location: ../login.php');
    exit();
}

$userId = (int) $_SESSION['user_id'];

// ---- Resolve the logged-in teacher row --------------------------------
$stmt = $pdo->prepare("SELECT teacher_id, firstname, lastname FROM teachers WHERE user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$teacher = $stmt->fetch();

if (!$teacher) {
    die('Teacher record not found for this account.');
}
$teacherId   = (int) $teacher['teacher_id'];
$teacherName = trim($teacher['firstname'] . ' ' . $teacher['lastname']);

// ---- Which month are we viewing? ----------------------------------------
$today = new DateTime('today');

$viewYear  = filter_input(INPUT_GET, 'year', FILTER_VALIDATE_INT) ?: (int) $today->format('Y');
$viewMonth = filter_input(INPUT_GET, 'month', FILTER_VALIDATE_INT) ?: (int) $today->format('n');

$viewMonth = max(1, min(12, $viewMonth));
$viewYear  = max(2000, min(2100, $viewYear));

$monthStart  = new DateTime("$viewYear-$viewMonth-01");
$monthEnd    = (clone $monthStart)->modify('last day of this month');

$prevMonthDate = (clone $monthStart)->modify('-1 month');
$nextMonthDate = (clone $monthStart)->modify('+1 month');

$monthLabel    = $monthStart->format('F Y');
$monthStartStr = $monthStart->format('Y-m-d');
$monthEndStr   = $monthEnd->format('Y-m-d');

// ---- Current school year + this teacher's active offerings ---------------
$stmt = $pdo->query("SELECT school_year_id FROM schoolyears WHERE is_current = 1 LIMIT 1");
$schoolYearId = $stmt->fetchColumn() ?: null;

$stmt = $pdo->prepare("
    SELECT co.offering_id
    FROM classofferings co
    WHERE co.teacher_id = ?
      AND co.status = 'active'
      AND (co.school_year_id = ? OR ? IS NULL)
");
$stmt->execute([$teacherId, $schoolYearId, $schoolYearId]);
$offeringIds = array_map('intval', array_column($stmt->fetchAll(), 'offering_id'));

$allItems = [];

/** Link into the teacher's class page, on the right term/tab/item. */
function calendar_grading_link(array $row, string $view, string $idKey, int $id): string
{
    return 'class_overview.php?' . http_build_query([
        'subject_id' => (int) $row['subject_id'],
        'section_id' => (int) $row['section_id'],
        'term'       => $row['quarter'],
        'view'       => $view,
        $idKey       => $id,
    ]);
}

if ($offeringIds) {
    $placeholders = implode(',', array_fill(0, count($offeringIds), '?'));

    // ---- Assignments (Activity/Exam), drafts excluded ---------------------
    $stmt = $pdo->prepare("
        SELECT a.assignment_id, a.title, a.due_date, a.type,
               co.quarter, sub.subject_id, sub.subject_name,
               sec.section_id, sec.section_name, sec.grade_level,
               (SELECT COUNT(*) FROM enrollments e
                 WHERE e.offering_id = a.offering_id AND e.status = 'enrolled') AS enrolled_count,
               (SELECT COUNT(*) FROM submissions s
                  JOIN enrollments e ON e.offering_id = a.offering_id
                                    AND e.student_id = s.student_id AND e.status = 'enrolled'
                 WHERE s.assignment_id = a.assignment_id
                   AND s.status IN ('submitted','late','graded','returned')
                   AND s.attempt_number = (SELECT MAX(s2.attempt_number) FROM submissions s2
                                            WHERE s2.assignment_id = s.assignment_id
                                              AND s2.student_id = s.student_id)) AS turned_in,
               (SELECT COUNT(*) FROM submissions s
                  JOIN enrollments e ON e.offering_id = a.offering_id
                                    AND e.student_id = s.student_id AND e.status = 'enrolled'
                 WHERE s.assignment_id = a.assignment_id
                   AND s.status IN ('submitted','late')
                   AND s.score IS NULL
                   AND s.attempt_number = (SELECT MAX(s2.attempt_number) FROM submissions s2
                                            WHERE s2.assignment_id = s.assignment_id
                                              AND s2.student_id = s.student_id)) AS to_grade
        FROM assignments a
        JOIN classofferings co ON co.offering_id = a.offering_id
        JOIN subjects sub      ON sub.subject_id = co.subject_id
        JOIN sections sec      ON sec.section_id = co.section_id
        WHERE a.offering_id IN ($placeholders)
          AND a.status <> 'draft'
        ORDER BY a.due_date ASC
    ");
    $stmt->execute($offeringIds);
    foreach ($stmt->fetchAll() as $row) {
        $allItems[] = [
            'date'         => $row['due_date'] ? substr($row['due_date'], 0, 10) : null,
            'due_date'     => $row['due_date'],
            'type'         => $row['type'] === 'Exam' ? 'exam' : 'activity',
            'title'        => $row['title'],
            'subject_name' => $row['subject_name'],
            'class_label'  => 'Grade ' . $row['grade_level'] . ' - ' . $row['section_name'],
            'enrolled'     => (int) $row['enrolled_count'],
            'turned_in'    => (int) $row['turned_in'],
            'to_grade'     => (int) $row['to_grade'],
            'is_done'      => (int) $row['turned_in'] > 0 && (int) $row['to_grade'] === 0,
            'link'         => calendar_grading_link($row, 'assignments', 'assignment_id', (int) $row['assignment_id']),
        ];
    }

    // ---- Quizzes (available_until is the deadline), drafts excluded ------
    $stmt = $pdo->prepare("
        SELECT q.quiz_id, q.title, q.available_until,
               co.quarter, sub.subject_id, sub.subject_name,
               sec.section_id, sec.section_name, sec.grade_level,
               (SELECT COUNT(*) FROM enrollments e
                 WHERE e.offering_id = q.offering_id AND e.status = 'enrolled') AS enrolled_count,
               (SELECT COUNT(*) FROM quiz_attempts qa
                  JOIN enrollments e ON e.offering_id = q.offering_id
                                    AND e.student_id = qa.student_id AND e.status = 'enrolled'
                 WHERE qa.quiz_id = q.quiz_id
                   AND qa.status IN ('submitted','graded')
                   AND qa.attempt_number = (SELECT MAX(y.attempt_number) FROM quiz_attempts y
                                             WHERE y.quiz_id = qa.quiz_id AND y.student_id = qa.student_id
                                               AND y.status IN ('submitted','graded'))) AS turned_in,
               (SELECT COUNT(*) FROM quiz_attempts qa
                  JOIN enrollments e ON e.offering_id = q.offering_id
                                    AND e.student_id = qa.student_id AND e.status = 'enrolled'
                 WHERE qa.quiz_id = q.quiz_id
                   AND qa.status = 'submitted'
                   AND qa.attempt_number = (SELECT MAX(y.attempt_number) FROM quiz_attempts y
                                             WHERE y.quiz_id = qa.quiz_id AND y.student_id = qa.student_id
                                               AND y.status IN ('submitted','graded'))) AS to_grade
        FROM quizzes q
        JOIN classofferings co ON co.offering_id = q.offering_id
        JOIN subjects sub      ON sub.subject_id = co.subject_id
        JOIN sections sec      ON sec.section_id = co.section_id
        WHERE q.offering_id IN ($placeholders)
          AND q.status <> 'draft'
        ORDER BY q.available_until ASC
    ");
    $stmt->execute($offeringIds);
    foreach ($stmt->fetchAll() as $row) {
        $allItems[] = [
            'date'         => $row['available_until'] ? substr($row['available_until'], 0, 10) : null,
            'due_date'     => $row['available_until'],
            'type'         => 'quiz',
            'title'        => $row['title'],
            'subject_name' => $row['subject_name'],
            'class_label'  => 'Grade ' . $row['grade_level'] . ' - ' . $row['section_name'],
            'enrolled'     => (int) $row['enrolled_count'],
            'turned_in'    => (int) $row['turned_in'],
            'to_grade'     => (int) $row['to_grade'],
            'is_done'      => (int) $row['turned_in'] > 0 && (int) $row['to_grade'] === 0,
            'link'         => calendar_grading_link($row, 'quizzes', 'quiz_id', (int) $row['quiz_id']),
        ];
    }
}

// ---- Split into: this month's dated items / undated / grading backlog -----
$dueItems     = [];
$undatedItems = [];
$backlogItems = [];   // anything (any month) still waiting to be graded

foreach ($allItems as $item) {
    if ($item['date'] === null) {
        $undatedItems[] = $item;
    } elseif ($item['date'] >= $monthStartStr && $item['date'] <= $monthEndStr) {
        $dueItems[] = $item;
    }
    if ($item['to_grade'] > 0) {
        $backlogItems[] = $item;
    }
}

usort($dueItems, fn($a, $b) => strcmp($a['due_date'] ?? '', $b['due_date'] ?? ''));
usort($undatedItems, fn($a, $b) => strcmp($a['subject_name'] ?? '', $b['subject_name'] ?? ''));
// Oldest deadline first so the most overdue grading floats to the top.
usort($backlogItems, fn($a, $b) => strcmp($a['due_date'] ?? '9999', $b['due_date'] ?? '9999'));
$undatedCount = count($undatedItems);

// ---- Group items by date (Y-m-d) so the calendar grid can mark cells -----
$itemsByDate = [];
foreach ($dueItems as $item) {
    $itemsByDate[$item['date']][] = $item;
}

// ---- Summary stats --------------------------------------------------------
$todayStr          = $today->format('Y-m-d');
$totalDueMonth     = count($dueItems);
$dueTodayCount     = count($itemsByDate[$todayStr] ?? []);
$fullyGradedCount  = count(array_filter($dueItems, fn($i) => $i['is_done']));
$totalToGrade      = array_sum(array_column($backlogItems, 'to_grade'));   // matches dashboard metric (assignments) + pending quiz attempts
$backlogItemCount  = count($backlogItems);

// ---- Build the grid of cells for this month (incl. adjacent-month days) ---
$firstWeekday = (int) $monthStart->format('w');
$gridStart    = (clone $monthStart)->modify("-$firstWeekday days");

$calendarCells = [];
$cursor = clone $gridStart;
for ($i = 0; $i < 42; $i++) {
    $cellDateStr = $cursor->format('Y-m-d');
    $calendarCells[] = [
        'date'     => $cellDateStr,
        'day'      => (int) $cursor->format('j'),
        'in_month' => (int) $cursor->format('n') === $viewMonth,
        'is_today' => $cellDateStr === $todayStr,
        'items'    => $itemsByDate[$cellDateStr] ?? [],
    ];
    $cursor->modify('+1 day');
}

// Trim trailing rows made entirely of next-month days.
while (count($calendarCells) > 35) {
    $lastRow = array_slice($calendarCells, -7);
    $rowHasCurrentMonth = false;
    foreach ($lastRow as $cell) {
        if ($cell['in_month']) {
            $rowHasCurrentMonth = true;
            break;
        }
    }
    if ($rowHasCurrentMonth) break;
    array_splice($calendarCells, -7);
}

/** Small label + CSS class for an item's type. */
function calendar_item_badge(string $type): array
{
    return match ($type) {
        'exam'  => ['dot--exam', 'Exam'],
        'quiz'  => ['dot--quiz', 'Quiz'],
        default => ['dot--activity', 'Activity'],
    };
}

/** "8:00 AM" or "End of Day" for a midnight due-time. */
function calendar_due_time(?string $dueDate): string
{
    if (!$dueDate) return '—';
    $ts = strtotime($dueDate);
    return date('H:i:s', $ts) === '00:00:00' ? 'End of Day' : date('g:i A', $ts);
}
