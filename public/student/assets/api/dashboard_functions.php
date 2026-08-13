<?php
/**
 * dashboard_functions.php (student)
 *
 * Pulls the real data behind the student dashboard cards/panels:
 *   - Enrolled Subjects
 *   - Pending Assignments
 *   - Due Today
 *   - Assignments To-Do
 *   - School Announcements
 *
 * Same contract as public/teacher/assets/api/dashboard_functions.php:
 * assumes config.php has already been required (session started, $pdo
 * available) and that $studentId / $studentFullName have already been
 * resolved by the including page.
 */

// ---- Current school year ----------------------------------------------
$stmt = $pdo->query("SELECT school_year_id, label FROM schoolyears WHERE is_current = 1 LIMIT 1");
$schoolYear   = $stmt->fetch();
$schoolYearId = $schoolYear['school_year_id'] ?? null;

// ---- Active subjects / classes this student is enrolled in ------------
$stmt = $pdo->prepare("
    SELECT co.offering_id, sub.subject_name,
           sec.grade_level, sec.section_name,
           t.firstname AS teacher_first, t.lastname AS teacher_last
    FROM enrollments e
    JOIN classofferings co ON co.offering_id = e.offering_id
    JOIN subjects sub      ON sub.subject_id = co.subject_id
    JOIN sections sec      ON sec.section_id = co.section_id
    JOIN teachers t        ON t.teacher_id = co.teacher_id
    WHERE e.student_id = ?
      AND e.status = 'active'
      AND (co.school_year_id = ? OR ? IS NULL)
    ORDER BY sub.subject_name
");
$stmt->execute([$studentId, $schoolYearId, $schoolYearId]);
$activeOfferings = $stmt->fetchAll();
$offeringIds     = array_column($activeOfferings, 'offering_id');
$enrolledSubjects = count($activeOfferings);

// ---- Assignments across those offerings, with this student's submission
// status (if any) attached -----------------------------------------------
$pendingAssignments = [];
$dueTodayAssignments = [];

if ($offeringIds) {
    $placeholders = implode(',', array_fill(0, count($offeringIds), '?'));
    $stmt = $pdo->prepare("
        SELECT a.assignment_id, a.title, a.due_date,
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
        ORDER BY a.due_date ASC
    ");
    $stmt->execute(array_merge([$studentId], $offeringIds));
    $allAssignments = $stmt->fetchAll();

    $today = date('Y-m-d');
    foreach ($allAssignments as $row) {
        // "Pending" = no submission on file, or one marked missing.
        $isDone = $row['submission_status'] !== null && $row['submission_status'] !== 'missing';

        if (!$isDone) {
            $pendingAssignments[] = $row;
        }
        if ($row['due_date'] && substr($row['due_date'], 0, 10) === $today) {
            $dueTodayAssignments[] = $row;
        }
    }
}

$pendingCount  = count($pendingAssignments);
$dueTodayCount = count($dueTodayAssignments);

// ---- Quizzes across those offerings, with this student's latest attempt
// (if any) attached -------------------------------------------------------
$pendingQuizzes  = [];
$dueTodayQuizzes = [];

if ($offeringIds) {
    $placeholders = implode(',', array_fill(0, count($offeringIds), '?'));
    $stmt = $pdo->prepare("
        SELECT q.quiz_id, q.title, q.available_until AS due_date,
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
        ORDER BY q.available_until ASC
    ");
    $stmt->execute(array_merge([$studentId, $studentId], $offeringIds));
    $allQuizzes = $stmt->fetchAll();

    $today = date('Y-m-d');
    foreach ($allQuizzes as $row) {
        // "Pending" = no attempt on file yet, or an attempt still in progress.
        $isDone = in_array($row['attempt_status'], ['submitted', 'graded'], true);

        if (!$isDone) {
            $pendingQuizzes[] = $row;
        }
        if ($row['due_date'] && substr($row['due_date'], 0, 10) === $today) {
            $dueTodayQuizzes[] = $row;
        }
    }
}

$pendingQuizCount  = count($pendingQuizzes);
$dueTodayQuizCount = count($dueTodayQuizzes);

// ---- Merge assignments + quizzes for the dashboard's task panels --------
// Each merged item is tagged with 'type' so the dashboard can build the
// right link back into the My Courses module (assignments or quizzes tab).
function student_tag_items(array $items, string $type): array
{
    return array_map(function ($item) use ($type) {
        $item['type'] = $type;
        return $item;
    }, $items);
}

$dueTodayTasks = array_merge(
    student_tag_items($dueTodayAssignments, 'assignment'),
    student_tag_items($dueTodayQuizzes, 'quiz')
);
usort($dueTodayTasks, fn($a, $b) => strcmp($a['due_date'] ?? '', $b['due_date'] ?? ''));

$pendingTasks = array_merge(
    student_tag_items($pendingAssignments, 'assignment'),
    student_tag_items($pendingQuizzes, 'quiz')
);
usort($pendingTasks, fn($a, $b) => strcmp($a['due_date'] ?? '', $b['due_date'] ?? ''));

// To-Do panel — the nearest pending items (assignments + quizzes), capped at 5.
$todoTasks = array_slice($pendingTasks, 0, 5);

/** Link back into the My Courses module, landing on the right tab/item. */
function student_task_link(array $item): string
{
    $params = [
        'subject_id' => (int) $item['subject_id'],
        'view'       => $item['type'] === 'quiz' ? 'quizzes' : 'assignments',
    ];
    if ($item['type'] === 'quiz') {
        $params['quiz_id'] = (int) $item['quiz_id'];
    } else {
        $params['assignment_id'] = (int) $item['assignment_id'];
    }
    return 'course_view.php?' . http_build_query($params);
}

// ---- Announcements visible to students ---------------------------------
$stmt = $pdo->prepare("
    SELECT a.*, u.username AS poster_username,
           t.firstname AS t_first, t.lastname AS t_last
    FROM announcements a
    JOIN users u ON u.id = a.posted_by
    LEFT JOIN teachers t ON t.user_id = u.id
    WHERE a.status = 'published'
      AND (a.audience = 'all' OR a.audience = 'students')
    ORDER BY a.is_pinned DESC, a.created_at DESC
    LIMIT 4
");
$stmt->execute();
$visibleAnnouncements = $stmt->fetchAll();

// ---- Greeting / date header ---------------------------------------------
$greetingHour = (int) date('H');
$greeting     = $greetingHour < 12 ? 'Good morning' : ($greetingHour < 18 ? 'Good afternoon' : 'Good evening');
$todayLabel   = date('l, F j, Y');

// ---- Small display helpers ----------------------------------------------

/** Font Awesome icon class for a subject, based on its name. */
function student_subject_icon(string $subjectName): string
{
    $s = strtolower($subjectName);

    if (str_contains($s, 'math')) return 'fa-file-pen';
    if (str_contains($s, 'english') || str_contains($s, 'language')) return 'fa-file-lines';
    if (str_contains($s, 'science')) return 'fa-flask';
    if (str_contains($s, 'filipino')) return 'fa-comments';
    if (str_contains($s, 'computer') || str_contains($s, 'ict')) return 'fa-laptop-code';
    if (str_contains($s, 'art')) return 'fa-palette';
    if (str_contains($s, 'history') || str_contains($s, 'araling')) return 'fa-landmark';
    if (str_contains($s, 'physical') || $s === 'pe' || str_contains($s, 'p.e')) return 'fa-person-running';

    return 'fa-book';
}

/** Small Font Awesome badge icon distinguishing an assignment from a quiz. */
function student_task_type_icon(string $type): string
{
    return $type === 'quiz' ? 'fa-file-circle-question' : 'fa-file-pen';
}

/** Short uppercase chip label for a subject, e.g. "Mathematics" -> "MATH". */
function student_subject_chip(string $subjectName): string
{
    $clean = preg_replace('/[^A-Za-z]/', '', $subjectName);
    return strtoupper(substr($clean, 0, 4)) ?: 'GEN';
}

/** Human-friendly due time, e.g. "8:00 AM" or "End of Day" for midnight due-dates. */
function student_due_time(?string $dueDate): string
{
    if (!$dueDate) {
        return '—';
    }
    $ts = strtotime($dueDate);
    return date('H:i:s', $ts) === '00:00:00' ? 'End of Day' : date('g:i A', $ts);
}

/** "Due today, 8:00 AM" / "Due Mar 25, 8:00 AM" depending on how far off it is. */
function student_due_label(?string $dueDate): string
{
    if (!$dueDate) {
        return 'No due date';
    }
    $ts    = strtotime($dueDate);
    $today = date('Y-m-d');
    $time  = student_due_time($dueDate);

    if (date('Y-m-d', $ts) === $today) {
        return 'Due today, ' . $time;
    }
    if ($ts < strtotime($today)) {
        return 'Was due ' . date('M j', $ts);
    }
    return 'Due ' . date('M j', $ts) . ', ' . $time;
}

/** Badge class + label for an announcement's priority. */
function student_announcement_badge(string $priority): array
{
    return match ($priority) {
        'urgent'    => ['announcement-tag--urgent', 'Urgent'],
        'important' => ['announcement-tag--event', 'Important'],
        default     => ['announcement-tag--general', 'General'],
    };
}