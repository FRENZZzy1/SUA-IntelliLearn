<?php
/**
 * calendar_functions.php (student)
 *
 * Backend for public/student/calendar.php ("Calendar").
 *
 * Builds a month-view calendar for the logged-in student and marks
 * every date that has an activity due: assignments (Activity/Exam,
 * keyed on `due_date`) and quizzes (keyed on `available_until`),
 * across every class offering the student is actively enrolled in —
 * same "due item" sources as dashboard_functions.php's To-Do panel,
 * just windowed to a calendar month instead of "pending only".
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

// ---- Which month are we viewing? ----------------------------------------
$today = new DateTime('today');

$viewYear  = filter_input(INPUT_GET, 'year', FILTER_VALIDATE_INT) ?: (int) $today->format('Y');
$viewMonth = filter_input(INPUT_GET, 'month', FILTER_VALIDATE_INT) ?: (int) $today->format('n');

// Clamp to a sane range so a bad query string can't blow up DateTime.
$viewMonth = max(1, min(12, $viewMonth));
$viewYear  = max(2000, min(2100, $viewYear));

$monthStart = new DateTime("$viewYear-$viewMonth-01");
$monthEnd   = (clone $monthStart)->modify('last day of this month');
$daysInMonth = (int) $monthEnd->format('j');

$prevMonthDate = (clone $monthStart)->modify('-1 month');
$nextMonthDate = (clone $monthStart)->modify('+1 month');

$monthLabel = $monthStart->format('F Y');

// ---- Active offerings this student is enrolled in ------------------------
$stmt = $pdo->prepare("
    SELECT co.offering_id, sub.subject_name,
           t.firstname AS teacher_first, t.lastname AS teacher_last
    FROM enrollments e
    JOIN classofferings co ON co.offering_id = e.offering_id
    JOIN subjects sub      ON sub.subject_id = co.subject_id
    JOIN teachers t        ON t.teacher_id = co.teacher_id
    WHERE e.student_id = ? AND e.status = 'active'
");
$stmt->execute([$studentId]);
$offerings = $stmt->fetchAll();
$offeringIds = array_column($offerings, 'offering_id');

$monthStartStr = $monthStart->format('Y-m-d 00:00:00');
$monthEndStr   = $monthEnd->format('Y-m-d 23:59:59');

$dueItems = [];
$undatedItems = [];

if ($offeringIds) {
    $placeholders = implode(',', array_fill(0, count($offeringIds), '?'));

    // ---- Assignments (Activity/Exam) due this month ---------------------
    $stmt = $pdo->prepare("
        SELECT a.assignment_id, a.title, a.due_date, a.type,
               sub.subject_id, sub.subject_name,
               t.firstname AS teacher_first, t.lastname AS teacher_last,
               sub2.status AS submission_status
        FROM assignments a
        JOIN classofferings co ON co.offering_id = a.offering_id
        JOIN subjects sub      ON sub.subject_id = co.subject_id
        JOIN teachers t        ON t.teacher_id = co.teacher_id
        LEFT JOIN submissions sub2
               ON sub2.assignment_id = a.assignment_id
              AND sub2.student_id = ?
              AND sub2.attempt_number = (
                    SELECT MAX(sub2b.attempt_number)
                    FROM submissions sub2b
                    WHERE sub2b.assignment_id = sub2.assignment_id AND sub2b.student_id = sub2.student_id
              )
        WHERE a.offering_id IN ($placeholders)
          AND a.status = 'published'
          AND a.due_date BETWEEN ? AND ?
        ORDER BY a.due_date ASC
    ");
    $stmt->execute(array_merge([$studentId], $offeringIds, [$monthStartStr, $monthEndStr]));
    foreach ($stmt->fetchAll() as $row) {
        $isDone = $row['submission_status'] !== null && $row['submission_status'] !== 'missing';
        $dueItems[] = [
            'date'          => substr($row['due_date'], 0, 10),
            'due_date'      => $row['due_date'],
            'type'          => $row['type'] === 'Exam' ? 'exam' : 'activity',
            'title'         => $row['title'],
            'subject_id'    => (int) $row['subject_id'],
            'subject_name'  => $row['subject_name'],
            'teacher_name'  => trim($row['teacher_first'] . ' ' . $row['teacher_last']),
            'is_done'       => $isDone,
            'link'          => 'course_view.php?' . http_build_query([
                'subject_id'    => (int) $row['subject_id'],
                'view'          => 'assignments',
                'assignment_id' => (int) $row['assignment_id'],
            ]),
        ];
    }

    // ---- Assignments with NO due date set — can't be placed on any
    // calendar cell, so they're listed separately instead of being
    // silently dropped. Not scoped to the viewed month since there's no
    // date to scope by. --------------------------------------------------
    $stmt = $pdo->prepare("
        SELECT a.assignment_id, a.title, a.type,
               sub.subject_id, sub.subject_name,
               t.firstname AS teacher_first, t.lastname AS teacher_last,
               sub2.status AS submission_status
        FROM assignments a
        JOIN classofferings co ON co.offering_id = a.offering_id
        JOIN subjects sub      ON sub.subject_id = co.subject_id
        JOIN teachers t        ON t.teacher_id = co.teacher_id
        LEFT JOIN submissions sub2
               ON sub2.assignment_id = a.assignment_id
              AND sub2.student_id = ?
              AND sub2.attempt_number = (
                    SELECT MAX(sub2b.attempt_number)
                    FROM submissions sub2b
                    WHERE sub2b.assignment_id = sub2.assignment_id AND sub2b.student_id = sub2.student_id
              )
        WHERE a.offering_id IN ($placeholders)
          AND a.status = 'published'
          AND a.due_date IS NULL
        ORDER BY sub.subject_name, a.title
    ");
    $stmt->execute(array_merge([$studentId], $offeringIds));
    foreach ($stmt->fetchAll() as $row) {
        $isDone = $row['submission_status'] !== null && $row['submission_status'] !== 'missing';
        $undatedItems[] = [
            'date'          => null,
            'due_date'      => null,
            'type'          => $row['type'] === 'Exam' ? 'exam' : 'activity',
            'title'         => $row['title'],
            'subject_id'    => (int) $row['subject_id'],
            'subject_name'  => $row['subject_name'],
            'teacher_name'  => trim($row['teacher_first'] . ' ' . $row['teacher_last']),
            'is_done'       => $isDone,
            'link'          => 'course_view.php?' . http_build_query([
                'subject_id'    => (int) $row['subject_id'],
                'view'          => 'assignments',
                'assignment_id' => (int) $row['assignment_id'],
            ]),
        ];
    }

    // ---- Quizzes due (available_until) this month ------------------------
    $stmt = $pdo->prepare("
        SELECT q.quiz_id, q.title, q.available_until,
               sub.subject_id, sub.subject_name,
               t.firstname AS teacher_first, t.lastname AS teacher_last,
               qa.status AS attempt_status
        FROM quizzes q
        JOIN classofferings co ON co.offering_id = q.offering_id
        JOIN subjects sub      ON sub.subject_id = co.subject_id
        JOIN teachers t        ON t.teacher_id = co.teacher_id
        LEFT JOIN quiz_attempts qa
               ON qa.quiz_id = q.quiz_id
              AND qa.student_id = ?
              AND qa.attempt_number = (
                    SELECT MAX(qa2.attempt_number)
                    FROM quiz_attempts qa2
                    WHERE qa2.quiz_id = q.quiz_id AND qa2.student_id = ?
              )
        WHERE q.offering_id IN ($placeholders)
          AND q.status = 'published'
          AND q.available_until BETWEEN ? AND ?
        ORDER BY q.available_until ASC
    ");
    $stmt->execute([$studentId, $studentId, ...$offeringIds, $monthStartStr, $monthEndStr]);
    foreach ($stmt->fetchAll() as $row) {
        $isDone = in_array($row['attempt_status'], ['submitted', 'graded'], true);
        $dueItems[] = [
            'date'          => substr($row['available_until'], 0, 10),
            'due_date'      => $row['available_until'],
            'type'          => 'quiz',
            'title'         => $row['title'],
            'subject_id'    => (int) $row['subject_id'],
            'subject_name'  => $row['subject_name'],
            'teacher_name'  => trim($row['teacher_first'] . ' ' . $row['teacher_last']),
            'is_done'       => $isDone,
            'link'          => 'course_view.php?' . http_build_query([
                'subject_id' => (int) $row['subject_id'],
                'view'       => 'quizzes',
                'quiz_id'    => (int) $row['quiz_id'],
            ]),
        ];
    }

    // ---- Quizzes with NO available_until set — same reasoning as the
    // undated assignments above. ------------------------------------------
    $stmt = $pdo->prepare("
        SELECT q.quiz_id, q.title,
               sub.subject_id, sub.subject_name,
               t.firstname AS teacher_first, t.lastname AS teacher_last,
               qa.status AS attempt_status
        FROM quizzes q
        JOIN classofferings co ON co.offering_id = q.offering_id
        JOIN subjects sub      ON sub.subject_id = co.subject_id
        JOIN teachers t        ON t.teacher_id = co.teacher_id
        LEFT JOIN quiz_attempts qa
               ON qa.quiz_id = q.quiz_id
              AND qa.student_id = ?
              AND qa.attempt_number = (
                    SELECT MAX(qa2.attempt_number)
                    FROM quiz_attempts qa2
                    WHERE qa2.quiz_id = q.quiz_id AND qa2.student_id = ?
              )
        WHERE q.offering_id IN ($placeholders)
          AND q.status = 'published'
          AND q.available_until IS NULL
        ORDER BY sub.subject_name, q.title
    ");
    $stmt->execute([$studentId, $studentId, ...$offeringIds]);
    foreach ($stmt->fetchAll() as $row) {
        $isDone = in_array($row['attempt_status'], ['submitted', 'graded'], true);
        $undatedItems[] = [
            'date'          => null,
            'due_date'      => null,
            'type'          => 'quiz',
            'title'         => $row['title'],
            'subject_id'    => (int) $row['subject_id'],
            'subject_name'  => $row['subject_name'],
            'teacher_name'  => trim($row['teacher_first'] . ' ' . $row['teacher_last']),
            'is_done'       => $isDone,
            'link'          => 'course_view.php?' . http_build_query([
                'subject_id' => (int) $row['subject_id'],
                'view'       => 'quizzes',
                'quiz_id'    => (int) $row['quiz_id'],
            ]),
        ];
    }
}

usort($dueItems, fn($a, $b) => strcmp($a['due_date'] ?? '', $b['due_date'] ?? ''));
usort($undatedItems, fn($a, $b) => strcmp($a['subject_name'] ?? '', $b['subject_name'] ?? ''));
$undatedCount = count($undatedItems);

// ---- Group items by date (Y-m-d) so the calendar grid can mark cells -----
$itemsByDate = [];
foreach ($dueItems as $item) {
    $itemsByDate[$item['date']][] = $item;
}

// ---- Month summary stats --------------------------------------------------
$todayStr       = $today->format('Y-m-d');
$totalDueMonth  = count($dueItems);
$overdueCount   = count(array_filter($dueItems, fn($i) => !$i['is_done'] && $i['date'] < $todayStr));
$dueTodayCount  = count($itemsByDate[$todayStr] ?? []);
$completedCount = count(array_filter($dueItems, fn($i) => $i['is_done']));

// ---- Build the 6x7 grid of cells for this month, including the leading/
// trailing days from adjacent months so every week row is full -----------
$firstWeekday = (int) $monthStart->format('w'); // 0 (Sun) - 6 (Sat)
$gridStart = (clone $monthStart)->modify("-$firstWeekday days");

$calendarCells = [];
$cursor = clone $gridStart;
for ($i = 0; $i < 42; $i++) {
    $cellDateStr = $cursor->format('Y-m-d');
    $calendarCells[] = [
        'date'          => $cellDateStr,
        'day'           => (int) $cursor->format('j'),
        'in_month'      => (int) $cursor->format('n') === $viewMonth,
        'is_today'      => $cellDateStr === $todayStr,
        'items'         => $itemsByDate[$cellDateStr] ?? [],
    ];
    $cursor->modify('+1 day');
}

// Trim trailing rows that are entirely from the next month (keeps a 5-row
// grid for months that fit in 5 weeks instead of always showing 6).
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

/** Small label + CSS class for a due-item's type. */
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

// ---- Sidebar/header context ---------------------------------------------
$stmt = $pdo->prepare("
    SELECT sec.grade_level, sec.section_name
    FROM enrollments e
    JOIN classofferings co ON co.offering_id = e.offering_id
    JOIN sections sec ON sec.section_id = co.section_id
    JOIN schoolyears sy ON sy.school_year_id = co.school_year_id
    WHERE e.student_id = ? AND e.status = 'active' AND sy.is_current = 1
    LIMIT 1
");
$stmt->execute([$studentId]);
$sectionRow = $stmt->fetch();
$studentGradeSection = $sectionRow
    ? "Grade {$sectionRow['grade_level']} - {$sectionRow['section_name']}"
    : null;