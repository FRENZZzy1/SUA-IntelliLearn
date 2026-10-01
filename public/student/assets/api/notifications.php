<?php
/**
 * notifications.php (student)
 *
 * Builds the student's notification feed on the fly from data the system
 * already stores — no notifications table. Each event type is a query
 * over an existing table, limited to the last 30 days:
 *
 *   graded       submissions.graded_at         "Graded: assignment 'X'"
 *   due_soon     assignments.due_date          due in the next 3 days, not yet submitted
 *   assignment   assignments.created_at        new published assignment
 *   quiz         quizzes.created_at            new published quiz
 *   material     learning_materials.created_at new learning material
 *   announcement announcements.published_at    audience = all/students
 *   enrollment   enrollment_requests.decided_at approved / denied
 *   term_grade   grades.updated_at             a term grade was posted
 *
 * "Read" state is NOT stored in the database. The header keeps it in the
 * browser (localStorage, keyed by user id), so it is per-device.
 *
 * Returns: { success, unread_hint, notifications: [ { id, type, actor,
 *            message, detail, time_label, ts, url } ] }
 */

require_once __DIR__ . '/../../../../config/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!isLoggedIn() || ($_SESSION['role'] ?? '') !== 'student') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Session expired. Please log in again.']);
    exit();
}

// Keep MySQL TIMESTAMP columns in the same zone PHP uses (Asia/Manila),
// so the times we format below match what the student sees on the wall clock.
try {
    $pdo->exec("SET time_zone = '+08:00'");
} catch (Throwable $e) {
    // Non-fatal: falls back to the server's zone.
}

const NOTIF_WINDOW_DAYS = 30;
const NOTIF_DUE_SOON_DAYS = 3;
const NOTIF_MAX = 40;

$base = '/SUA-INTELLILEARN/public/student/';

$userId = (int) $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT student_id FROM students WHERE user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$studentId = (int) $stmt->fetchColumn();

if (!$studentId) {
    echo json_encode(['success' => true, 'notifications' => []]);
    exit();
}

$now      = time();
$nowSql   = date('Y-m-d H:i:s', $now);
$sinceSql = date('Y-m-d H:i:s', strtotime('-' . NOTIF_WINDOW_DAYS . ' days', $now));
$dueSql   = date('Y-m-d H:i:s', strtotime('+' . NOTIF_DUE_SOON_DAYS . ' days', $now));

/** "1:21 pm" today, "Yesterday, 7:15 am", otherwise "Sep 30, 9:04 am". */
function notif_time_label(int $ts, int $now): string
{
    $clock = strtolower(date('g:i a', $ts));
    $day   = date('Y-m-d', $ts);
    if ($day === date('Y-m-d', $now)) {
        return $clock;
    }
    if ($day === date('Y-m-d', strtotime('-1 day', $now))) {
        return 'Yesterday, ' . $clock;
    }
    return date('M j', $ts) . ', ' . $clock;
}

/** "Due today, 5:00 pm" / "Due tomorrow, ..." / "Due Oct 3, 5:00 pm". */
function notif_due_label(int $ts, int $now): string
{
    $clock = strtolower(date('g:i a', $ts));
    $day   = date('Y-m-d', $ts);
    if ($day === date('Y-m-d', $now)) {
        return 'Due today, ' . $clock;
    }
    if ($day === date('Y-m-d', strtotime('+1 day', $now))) {
        return 'Due tomorrow, ' . $clock;
    }
    return 'Due ' . date('M j', $ts) . ', ' . $clock;
}

function notif_person(?string $first, ?string $last, string $fallback): string
{
    $name = trim(($first ?? '') . ' ' . ($last ?? ''));
    return $name !== '' ? $name : $fallback;
}

function notif_clip(string $text, int $max = 70): string
{
    $text = trim(preg_replace('/\s+/', ' ', $text));
    if (function_exists('mb_strlen')) {
        return mb_strlen($text) > $max ? mb_substr($text, 0, $max - 1) . '…' : $text;
    }
    return strlen($text) > $max ? substr($text, 0, $max - 1) . '…' : $text;
}

$items = [];

/** Push one notification. $sortTs decides ordering (newest first). */
$add = function (string $id, string $type, string $actor, string $message, ?string $detail,
                 string $timeLabel, int $sortTs, string $url) use (&$items): void {
    $items[] = [
        'id'         => $id,
        'type'       => $type,
        'actor'      => $actor,
        'message'    => $message,
        'detail'     => $detail,
        'time_label' => $timeLabel,
        'ts'         => $sortTs,
        'url'        => $url,
    ];
};

// ---- This student's active offerings (current school year) ---------------
$stmt = $pdo->prepare("
    SELECT co.offering_id
    FROM enrollments e
    JOIN classofferings co ON co.offering_id = e.offering_id
    JOIN schoolyears sy    ON sy.school_year_id = co.school_year_id
    WHERE e.student_id = ? AND e.status = 'active' AND sy.is_current = 1
");
$stmt->execute([$studentId]);
$offeringIds = array_map('intval', array_column($stmt->fetchAll(), 'offering_id'));

if ($offeringIds) {
    $in = implode(',', array_fill(0, count($offeringIds), '?'));

    // ---- 1. Graded assignments (latest graded attempt per assignment) ----
    $stmt = $pdo->prepare("
        SELECT s.assignment_id, s.score, s.graded_at,
               a.title, a.points, co.quarter,
               sub.subject_id, sub.subject_name,
               t.firstname, t.lastname
        FROM submissions s
        JOIN assignments a     ON a.assignment_id = s.assignment_id
        JOIN classofferings co ON co.offering_id = a.offering_id
        JOIN subjects sub      ON sub.subject_id = co.subject_id
        JOIN teachers t        ON t.teacher_id = co.teacher_id
        WHERE s.student_id = ?
          AND s.status IN ('graded', 'returned')
          AND s.graded_at IS NOT NULL
          AND s.graded_at >= ?
        ORDER BY s.graded_at DESC
    ");
    $stmt->execute([$studentId, $sinceSql]);
    $seen = [];
    foreach ($stmt->fetchAll() as $r) {
        if (isset($seen[$r['assignment_id']])) {
            continue;
        }
        $seen[$r['assignment_id']] = true;
        $ts    = strtotime($r['graded_at']);
        $score = $r['score'] !== null
            ? rtrim(rtrim(number_format((float) $r['score'], 2, '.', ''), '0'), '.') . '/' .
              rtrim(rtrim(number_format((float) $r['points'], 2, '.', ''), '0'), '.')
            : null;
        $add(
            "g-{$r['assignment_id']}-{$ts}", 'graded',
            notif_person($r['firstname'], $r['lastname'], 'Your teacher'),
            "Graded: assignment '" . notif_clip($r['title']) . "'",
            $r['subject_name'] . ($score ? " · Score {$score}" : ''),
            notif_time_label($ts, $now), $ts,
            $base . 'course_view.php?' . http_build_query([
                'subject_id' => $r['subject_id'], 'view' => 'assignments',
                'term' => $r['quarter'], 'assignment_id' => $r['assignment_id'],
            ])
        );
    }

    // ---- 2. Due soon (published, not yet submitted) ----------------------
    $stmt = $pdo->prepare("
        SELECT a.assignment_id, a.title, a.due_date, co.quarter,
               sub.subject_id, sub.subject_name, t.firstname, t.lastname
        FROM assignments a
        JOIN classofferings co ON co.offering_id = a.offering_id
        JOIN subjects sub      ON sub.subject_id = co.subject_id
        JOIN teachers t        ON t.teacher_id = co.teacher_id
        WHERE a.offering_id IN ($in)
          AND a.status = 'published'
          AND a.due_date IS NOT NULL
          AND a.due_date >= ? AND a.due_date <= ?
          AND NOT EXISTS (
                SELECT 1 FROM submissions s
                WHERE s.assignment_id = a.assignment_id AND s.student_id = ?
          )
        ORDER BY a.due_date ASC
    ");
    $stmt->execute(array_merge($offeringIds, [$nowSql, $dueSql, $studentId]));
    $dueSoonIds = [];
    foreach ($stmt->fetchAll() as $r) {
        $dueSoonIds[$r['assignment_id']] = true;
        $dueTs = strtotime($r['due_date']);
        // Sorted as "now" so actionable deadlines stay on top of the list.
        $add(
            "d-{$r['assignment_id']}", 'due_soon',
            notif_person($r['firstname'], $r['lastname'], 'Your teacher'),
            "Due soon: assignment '" . notif_clip($r['title']) . "'",
            $r['subject_name'],
            notif_due_label($dueTs, $now), $now,
            $base . 'course_view.php?' . http_build_query([
                'subject_id' => $r['subject_id'], 'view' => 'assignments',
                'term' => $r['quarter'], 'assignment_id' => $r['assignment_id'],
            ])
        );
    }

    // ---- 3. New assignments ----------------------------------------------
    $stmt = $pdo->prepare("
        SELECT a.assignment_id, a.title, a.created_at, co.quarter,
               sub.subject_id, sub.subject_name, t.firstname, t.lastname
        FROM assignments a
        JOIN classofferings co ON co.offering_id = a.offering_id
        JOIN subjects sub      ON sub.subject_id = co.subject_id
        JOIN teachers t        ON t.teacher_id = co.teacher_id
        WHERE a.offering_id IN ($in)
          AND a.status = 'published'
          AND a.created_at >= ?
        ORDER BY a.created_at DESC
        LIMIT 15
    ");
    $stmt->execute(array_merge($offeringIds, [$sinceSql]));
    foreach ($stmt->fetchAll() as $r) {
        // A brand-new assignment that is already due soon is covered by its
        // "Due soon" notification — don't list it twice.
        if (isset($dueSoonIds[$r['assignment_id']])) {
            continue;
        }
        $ts = strtotime($r['created_at']);
        $add(
            "a-{$r['assignment_id']}", 'assignment',
            notif_person($r['firstname'], $r['lastname'], 'Your teacher'),
            "New assignment: '" . notif_clip($r['title']) . "'",
            $r['subject_name'],
            notif_time_label($ts, $now), $ts,
            $base . 'course_view.php?' . http_build_query([
                'subject_id' => $r['subject_id'], 'view' => 'assignments',
                'term' => $r['quarter'], 'assignment_id' => $r['assignment_id'],
            ])
        );
    }

    // ---- 4. New quizzes --------------------------------------------------
    $stmt = $pdo->prepare("
        SELECT q.quiz_id, q.title, q.created_at, co.quarter,
               sub.subject_id, sub.subject_name, t.firstname, t.lastname
        FROM quizzes q
        JOIN classofferings co ON co.offering_id = q.offering_id
        JOIN subjects sub      ON sub.subject_id = co.subject_id
        JOIN teachers t        ON t.teacher_id = co.teacher_id
        WHERE q.offering_id IN ($in)
          AND q.status = 'published'
          AND q.created_at >= ?
        ORDER BY q.created_at DESC
        LIMIT 10
    ");
    $stmt->execute(array_merge($offeringIds, [$sinceSql]));
    foreach ($stmt->fetchAll() as $r) {
        $ts = strtotime($r['created_at']);
        $add(
            "q-{$r['quiz_id']}", 'quiz',
            notif_person($r['firstname'], $r['lastname'], 'Your teacher'),
            "New quiz: '" . notif_clip($r['title']) . "'",
            $r['subject_name'],
            notif_time_label($ts, $now), $ts,
            $base . 'course_view.php?' . http_build_query([
                'subject_id' => $r['subject_id'], 'view' => 'quizzes',
                'term' => $r['quarter'], 'quiz_id' => $r['quiz_id'],
            ])
        );
    }

    // ---- 5. New learning materials ---------------------------------------
    $stmt = $pdo->prepare("
        SELECT lm.material_id, lm.title, lm.created_at, co.quarter,
               sub.subject_id, sub.subject_name, t.firstname, t.lastname
        FROM learning_materials lm
        JOIN classofferings co ON co.offering_id = lm.offering_id
        JOIN subjects sub      ON sub.subject_id = co.subject_id
        JOIN teachers t        ON t.teacher_id = co.teacher_id
        WHERE lm.offering_id IN ($in)
          AND lm.created_at >= ?
        ORDER BY lm.created_at DESC
        LIMIT 10
    ");
    $stmt->execute(array_merge($offeringIds, [$sinceSql]));
    foreach ($stmt->fetchAll() as $r) {
        $ts = strtotime($r['created_at']);
        $add(
            "m-{$r['material_id']}", 'material',
            notif_person($r['firstname'], $r['lastname'], 'Your teacher'),
            "New material: '" . notif_clip($r['title']) . "'",
            $r['subject_name'],
            notif_time_label($ts, $now), $ts,
            $base . 'course_view.php?' . http_build_query([
                'subject_id' => $r['subject_id'], 'view' => 'materials', 'term' => $r['quarter'],
            ])
        );
    }
}

// ---- 6. Announcements (all-school, student-wide, or for this student's classes)
$annSql = "
    SELECT a.announcement_id, a.title, a.priority,
           COALESCE(a.published_at, a.created_at) AS posted_at,
           u.username, t.firstname, t.lastname
    FROM announcements a
    JOIN users u ON u.id = a.posted_by
    LEFT JOIN teachers t ON t.user_id = u.id
    WHERE a.status = 'published'
      AND a.audience IN ('all', 'students')
      AND COALESCE(a.published_at, a.created_at) >= ?
";
$annParams = [$sinceSql];
if ($offeringIds) {
    $annSql .= " AND (a.offering_id IS NULL OR a.offering_id IN ($in))";
    $annParams = array_merge($annParams, $offeringIds);
} else {
    $annSql .= " AND a.offering_id IS NULL";
}
$annSql .= " ORDER BY posted_at DESC LIMIT 10";
$stmt = $pdo->prepare($annSql);
$stmt->execute($annParams);
foreach ($stmt->fetchAll() as $r) {
    $ts    = strtotime($r['posted_at']);
    $label = $r['priority'] === 'urgent' ? 'Urgent announcement' : 'New announcement';
    $add(
        "n-{$r['announcement_id']}", 'announcement',
        notif_person($r['firstname'], $r['lastname'], 'School Administration'),
        "{$label}: '" . notif_clip($r['title']) . "'",
        null,
        notif_time_label($ts, $now), $ts,
        $base . 'announcements.php'
    );
}

// ---- 7. Enrollment request decisions -------------------------------------
$stmt = $pdo->prepare("
    SELECT er.request_id, er.status, er.decided_at, sub.subject_name
    FROM enrollment_requests er
    JOIN subjects sub ON sub.subject_id = er.subject_id
    WHERE er.student_id = ?
      AND er.status IN ('approved', 'denied')
      AND er.decided_at IS NOT NULL
      AND er.decided_at >= ?
    ORDER BY er.decided_at DESC
    LIMIT 10
");
$stmt->execute([$studentId, $sinceSql]);
foreach ($stmt->fetchAll() as $r) {
    $ts       = strtotime($r['decided_at']);
    $approved = $r['status'] === 'approved';
    $add(
        "e-{$r['request_id']}-{$ts}", $approved ? 'enroll_ok' : 'enroll_no',
        'Enrollment',
        $approved ? "Request approved: {$r['subject_name']}" : "Request denied: {$r['subject_name']}",
        null,
        notif_time_label($ts, $now), $ts,
        $base . 'courses.php'
    );
}

// ---- 8. Term grades posted -------------------------------------------------
$stmt = $pdo->prepare("
    SELECT g.grade_id, g.quarter AS grade_term, g.grade, g.updated_at,
           sub.subject_name, t.firstname, t.lastname
    FROM grades g
    JOIN enrollments e     ON e.enrollment_id = g.enrollment_id
    JOIN classofferings co ON co.offering_id = e.offering_id
    JOIN subjects sub      ON sub.subject_id = co.subject_id
    JOIN teachers t        ON t.teacher_id = co.teacher_id
    WHERE e.student_id = ?
      AND g.updated_at >= ?
    ORDER BY g.updated_at DESC
    LIMIT 10
");
$stmt->execute([$studentId, $sinceSql]);
foreach ($stmt->fetchAll() as $r) {
    $ts = strtotime($r['updated_at']);
    $add(
        "t-{$r['grade_id']}-{$ts}", 'term_grade',
        notif_person($r['firstname'], $r['lastname'], 'Your teacher'),
        "{$r['grade_term']} grade posted",
        $r['subject_name'] . ' · ' . rtrim(rtrim(number_format((float) $r['grade'], 2, '.', ''), '0'), '.'),
        notif_time_label($ts, $now), $ts,
        $base . 'grades.php'
    );
}

// ---- Newest first, capped ------------------------------------------------
usort($items, fn($a, $b) => $b['ts'] <=> $a['ts']);
$items = array_slice($items, 0, NOTIF_MAX);

echo json_encode(['success' => true, 'notifications' => $items], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);