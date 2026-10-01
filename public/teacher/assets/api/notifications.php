<?php
/**
 * notifications.php (teacher)
 *
 * Teacher counterpart of public/student/assets/api/notifications.php.
 * Same approach: the feed is built on the fly from tables the system
 * already stores (no notifications table), limited to the last 30 days,
 * and "read" state lives in the browser (localStorage, keyed by user id).
 *
 * Event types:
 *   submission   submissions.submitted_at       a student turned in an assignment
 *   quiz_taken   quiz_attempts.submitted_at     a student answered a quiz
 *   announcement announcements.published_at     posted by an admin (higher-up),
 *                                               audience = all/teachers
 *
 * Only this teacher's own class offerings (current school year) are
 * considered, so a teacher never sees activity from other teachers' classes.
 *
 * Returns: { success, notifications: [ { id, type, actor, message, detail,
 *            time_label, ts, url } ] }
 */

require_once __DIR__ . '/../../../../config/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!isLoggedIn() || ($_SESSION['role'] ?? '') !== 'teacher') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Session expired. Please log in again.']);
    exit();
}

// Keep MySQL TIMESTAMP columns in the same zone PHP uses (Asia/Manila).
try {
    $pdo->exec("SET time_zone = '+08:00'");
} catch (Throwable $e) {
    // Non-fatal: falls back to the server's zone.
}

const NOTIF_WINDOW_DAYS = 30;
const NOTIF_MAX = 40;

$base = '/SUA-INTELLILEARN/public/teacher/';

$userId = (int) $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT teacher_id FROM teachers WHERE user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$teacherId = (int) $stmt->fetchColumn();

if (!$teacherId) {
    echo json_encode(['success' => true, 'notifications' => []]);
    exit();
}

$now      = time();
$sinceSql = date('Y-m-d H:i:s', strtotime('-' . NOTIF_WINDOW_DAYS . ' days', $now));

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

function notif_num($n): string
{
    return rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');
}

/** Same URL shape as assignmentsUrl()/quizzesUrl() in class_overview_functions.php. */
function notif_overview_url(string $base, array $r, string $view, array $extra = []): string
{
    return $base . 'class_overview.php?' . http_build_query(array_merge([
        'subject_id' => $r['subject_id'],
        'section_id' => $r['section_id'],
        'view'       => $view,
        'term'       => $r['quarter'],
    ], $extra));
}

$items = [];

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

// ---- This teacher's active offerings (current school year) ---------------
$stmt = $pdo->prepare("
    SELECT co.offering_id
    FROM classofferings co
    JOIN schoolyears sy ON sy.school_year_id = co.school_year_id
    WHERE co.teacher_id = ? AND co.status = 'active' AND sy.is_current = 1
");
$stmt->execute([$teacherId]);
$offeringIds = array_map('intval', array_column($stmt->fetchAll(), 'offering_id'));

if ($offeringIds) {
    $in = implode(',', array_fill(0, count($offeringIds), '?'));

    // ---- 1. Assignment submissions (latest attempt per student+assignment) --
    $stmt = $pdo->prepare("
        SELECT s.submission_id, s.assignment_id, s.student_id, s.status, s.submitted_at,
               a.title, a.due_date, co.quarter, co.section_id,
               sub.subject_id, sub.subject_name,
               st.firstname, st.lastname
        FROM submissions s
        JOIN assignments a     ON a.assignment_id = s.assignment_id
        JOIN classofferings co ON co.offering_id = a.offering_id
        JOIN subjects sub      ON sub.subject_id = co.subject_id
        JOIN students st       ON st.student_id = s.student_id
        WHERE a.offering_id IN ($in)
          AND s.status <> 'missing'
          AND s.submitted_at >= ?
        ORDER BY s.submitted_at DESC
    ");
    $stmt->execute(array_merge($offeringIds, [$sinceSql]));
    $seen = [];
    $count = 0;
    foreach ($stmt->fetchAll() as $r) {
        $key = $r['assignment_id'] . '-' . $r['student_id'];
        if (isset($seen[$key])) {
            continue; // newest attempt only
        }
        $seen[$key] = true;
        if (++$count > 25) {
            break;
        }
        $ts   = strtotime($r['submitted_at']);
        $late = $r['status'] === 'late'
            || ($r['due_date'] && $ts > strtotime($r['due_date']));
        $add(
            "s-{$r['submission_id']}", 'submission',
            notif_person($r['firstname'], $r['lastname'], 'A student'),
            "Submitted assignment '" . notif_clip($r['title']) . "'",
            $r['subject_name'] . ($late ? ' · Late' : ''),
            notif_time_label($ts, $now), $ts,
            notif_overview_url($base, $r, 'assignments', ['assignment_id' => $r['assignment_id']])
        );
    }

    // ---- 2. Quiz attempts (submitted by a student) -------------------------
    $stmt = $pdo->prepare("
        SELECT qa.attempt_id, qa.quiz_id, qa.student_id, qa.score, qa.max_score, qa.submitted_at,
               q.title, co.quarter, co.section_id,
               sub.subject_id, sub.subject_name,
               st.firstname, st.lastname
        FROM quiz_attempts qa
        JOIN quizzes q         ON q.quiz_id = qa.quiz_id
        JOIN classofferings co ON co.offering_id = q.offering_id
        JOIN subjects sub      ON sub.subject_id = co.subject_id
        JOIN students st       ON st.student_id = qa.student_id
        WHERE q.offering_id IN ($in)
          AND qa.status IN ('submitted', 'graded')
          AND qa.submitted_at IS NOT NULL
          AND qa.submitted_at >= ?
        ORDER BY qa.submitted_at DESC
        LIMIT 25
    ");
    $stmt->execute(array_merge($offeringIds, [$sinceSql]));
    foreach ($stmt->fetchAll() as $r) {
        $ts    = strtotime($r['submitted_at']);
        $score = ($r['score'] !== null && $r['max_score'] !== null)
            ? ' · Score ' . notif_num($r['score']) . '/' . notif_num($r['max_score'])
            : '';
        $add(
            "qa-{$r['attempt_id']}", 'quiz_taken',
            notif_person($r['firstname'], $r['lastname'], 'A student'),
            "Answered quiz '" . notif_clip($r['title']) . "'",
            $r['subject_name'] . $score,
            notif_time_label($ts, $now), $ts,
            notif_overview_url($base, $r, 'quizzes', [
                'quiz_id' => $r['quiz_id'], 'attempt_id' => $r['attempt_id'],
            ])
        );
    }
}

// ---- 3. Announcements posted by an admin (higher-up) ----------------------
// Audience 'all' or 'teachers'. Posts tied to an offering are only shown if
// it's one of this teacher's classes. The teacher's own posts are excluded.
$annSql = "
    SELECT a.announcement_id, a.title, a.priority,
           COALESCE(a.published_at, a.created_at) AS posted_at
    FROM announcements a
    JOIN users u ON u.id = a.posted_by
    WHERE a.status = 'published'
      AND u.role = 'admin'
      AND a.audience IN ('all', 'teachers')
      AND COALESCE(a.published_at, a.created_at) >= ?
";
$annParams = [$sinceSql];
if ($offeringIds) {
    $in = implode(',', array_fill(0, count($offeringIds), '?'));
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
        'School Administration',
        "{$label}: '" . notif_clip($r['title']) . "'",
        null,
        notif_time_label($ts, $now), $ts,
        $base . 'dashboard.php'
    );
}

// ---- Newest first, capped --------------------------------------------------
usort($items, fn($a, $b) => $b['ts'] <=> $a['ts']);
$items = array_slice($items, 0, NOTIF_MAX);

echo json_encode(['success' => true, 'notifications' => $items], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);