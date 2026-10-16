<?php
/**
 * notifications.php (admin)
 *
 * Admin counterpart of public/teacher/assets/api/notifications.php.
 * Same approach: the feed is built on the fly from tables the system
 * already stores (NO notifications table), limited to the last 30 days,
 * and "read"/"deleted" state lives in the browser (localStorage, keyed by
 * user id).
 *
 * Event types (each gated by the admin's module permission, so a Limited /
 * Read-only admin only sees what they are allowed to open):
 *
 *   new_users     users.created_at                    [users]
 *                 student / teacher accounts created. Several accounts made
 *                 on the same day (e.g. a bulk import) collapse into one row.
 *   announcement  announcements.published_at          [announcements]
 *                 published by ANOTHER admin (your own posts are skipped).
 *   eval_open     teacher_evaluation_rounds.opened_at [analytics]
 *   eval_soon     teacher_evaluation_rounds.closes_on [analytics]
 *                 an open round that closes within 3 days.
 *   eval_closed   teacher_evaluation_rounds.closed_at [analytics]
 *   quiz_failed   quiz_generation_jobs (status=failed) [dashboard]
 *                 an AI quiz generation a teacher ran that failed.
 *
 * Returns: { success, notifications: [ { id, type, actor, message, detail,
 *            time_label, ts, url } ] }
 */

require_once __DIR__ . '/../../../../config/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!isLoggedIn() || ($_SESSION['role'] ?? '') !== 'admin') {
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

$base   = '/SUA-INTELLILEARN/public/admin/';
$userId = (int) $_SESSION['user_id'];

$now      = time();
$today    = date('Y-m-d', $now);
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

function notif_plural(int $n, string $one, string $many): string
{
    return $n . ' ' . ($n === 1 ? $one : $many);
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

// ---- 1. New student / teacher accounts  [users] --------------------------
if (adminCanRead('users')) {
    try {
        $stmt = $pdo->prepare("
            SELECT u.id, u.role, u.created_at,
                   COALESCE(st.firstname, t.firstname) AS firstname,
                   COALESCE(st.lastname,  t.lastname)  AS lastname
            FROM users u
            LEFT JOIN students st ON u.role = 'student' AND st.user_id = u.id
            LEFT JOIN teachers t  ON u.role = 'teacher' AND t.user_id  = u.id
            WHERE u.role IN ('student', 'teacher')
              AND u.created_at >= ?
            ORDER BY u.created_at DESC
            LIMIT 500
        ");
        $stmt->execute([$sinceSql]);

        // One row per (role, day) so a bulk import doesn't flood the bell.
        $groups = [];
        foreach ($stmt->fetchAll() as $r) {
            $ts  = strtotime($r['created_at']);
            $key = $r['role'] . '|' . date('Y-m-d', $ts);
            if (!isset($groups[$key])) {
                $groups[$key] = ['role' => $r['role'], 'day' => date('Y-m-d', $ts),
                                 'count' => 0, 'ts' => $ts, 'first' => null, 'last' => null];
            }
            $groups[$key]['count']++;
            if ($groups[$key]['count'] === 1) {          // rows are newest-first
                $groups[$key]['first'] = notif_person($r['firstname'], $r['lastname'], '');
            }
        }

        foreach (array_slice($groups, 0, 10) as $g) {
            $role = $g['role'];                           // 'student' | 'teacher'
            $n    = $g['count'];
            if ($n === 1) {
                $actor   = $g['first'] !== '' ? $g['first'] : 'New ' . $role;
                $message = 'New ' . $role . ' account was created';
                $detail  = null;
            } else {
                $actor   = 'User Management';
                $message = notif_plural($n, "new $role account was", "new $role accounts were") . ' created';
                $detail  = $g['first'] !== '' ? 'Latest: ' . $g['first'] : null;
            }
            // The count is part of the id so more accounts that same day
            // surface as a fresh unread notification.
            $add(
                "u-{$role}-{$g['day']}-{$n}", 'new_users', $actor, $message, $detail,
                notif_time_label($g['ts'], $now), $g['ts'],
                $base . 'user_management.php'
            );
        }
    } catch (Throwable $e) {
        // Skip this source; the rest of the feed still works.
    }
}

// ---- 2. Announcements published by another admin  [announcements] --------
if (adminCanRead('announcements')) {
    try {
        $stmt = $pdo->prepare("
            SELECT a.announcement_id, a.title, a.priority, a.audience,
                   COALESCE(a.published_at, a.created_at) AS posted_at,
                   u.username
            FROM announcements a
            JOIN users u ON u.id = a.posted_by
            WHERE a.status = 'published'
              AND u.role = 'admin'
              AND a.posted_by <> ?
              AND COALESCE(a.published_at, a.created_at) >= ?
            ORDER BY posted_at DESC
            LIMIT 10
        ");
        $stmt->execute([$userId, $sinceSql]);
        foreach ($stmt->fetchAll() as $r) {
            $ts    = strtotime($r['posted_at']);
            $label = $r['priority'] === 'urgent' ? 'Posted an urgent announcement' : 'Posted an announcement';
            $add(
                "n-{$r['announcement_id']}", 'announcement',
                $r['username'] ?: 'Another admin',
                "{$label}: '" . notif_clip($r['title']) . "'",
                'Audience: ' . ucfirst($r['audience']),
                notif_time_label($ts, $now), $ts,
                $base . 'announcement.php'
            );
        }
    } catch (Throwable $e) {
    }
}

// ---- 3. Teacher evaluation rounds  [analytics] ---------------------------
if (adminCanRead('analytics')) {
    try {
        $stmt = $pdo->prepare("
            SELECT r.round_id, r.title, r.term, r.status, r.closes_on, r.opened_at, r.closed_at,
                   (SELECT COUNT(*) FROM teacher_evaluation_submissions s
                     WHERE s.round_id = r.round_id) AS responses
            FROM teacher_evaluation_rounds r
            WHERE r.opened_at >= ? OR r.closed_at >= ? OR r.status = 'open'
            ORDER BY r.round_id DESC
            LIMIT 20
        ");
        $stmt->execute([$sinceSql, $sinceSql]);
        foreach ($stmt->fetchAll() as $r) {
            $rid   = (int) $r['round_id'];
            $title = notif_clip($r['title'] ?: ($r['term'] . ' evaluation'), 60);
            $url   = $base . 'analytics.php?round=' . $rid;
            $resp  = (int) $r['responses'];

            if ($r['opened_at'] && $r['opened_at'] >= $sinceSql) {
                $ts = strtotime($r['opened_at']);
                $add(
                    "er-open-{$rid}", 'eval_open', 'Teacher Evaluation',
                    "Round opened: '{$title}'",
                    $r['term'] . ' · ' . notif_plural($resp, 'response', 'responses') . ' so far',
                    notif_time_label($ts, $now), $ts, $url
                );
            }

            if ($r['status'] === 'closed' && $r['closed_at'] && $r['closed_at'] >= $sinceSql) {
                $ts = strtotime($r['closed_at']);
                $add(
                    "er-closed-{$rid}", 'eval_closed', 'Teacher Evaluation',
                    "Round closed: '{$title}'",
                    notif_plural($resp, 'response', 'responses') . ' collected',
                    notif_time_label($ts, $now), $ts, $url
                );
            }

            // Still open and closing within the next 3 days.
            if ($r['status'] === 'open' && $r['closes_on']
                && $r['closes_on'] >= $today
                && $r['closes_on'] <= date('Y-m-d', strtotime('+3 days', $now))) {
                $days = (int) round((strtotime($r['closes_on']) - strtotime($today)) / 86400);
                $when = $days === 0 ? 'today' : ($days === 1 ? 'tomorrow' : "in {$days} days");
                $add(
                    "er-soon-{$rid}-{$r['closes_on']}", 'eval_soon', 'Teacher Evaluation',
                    "'{$title}' closes {$when}",
                    notif_plural($resp, 'response', 'responses') . ' so far',
                    'Closes ' . date('M j', strtotime($r['closes_on'])), $now, $url
                );
            }
        }
    } catch (Throwable $e) {
        // Evaluation tables may not exist on older databases; just skip.
    }
}

// ---- 4. Failed AI quiz generations  [dashboard] --------------------------
// Grouped per day: one bad API key can fail many runs in a row, and that
// should read as one problem, not a wall of identical alerts.
if (adminCanRead('dashboard')) {
    try {
        $stmt = $pdo->prepare("
            SELECT j.job_id, j.error_message, j.created_at,
                   t.firstname, t.lastname, sub.subject_name
            FROM quiz_generation_jobs j
            LEFT JOIN teachers t        ON t.user_id = j.requested_by
            LEFT JOIN classofferings co ON co.offering_id = j.offering_id
            LEFT JOIN subjects sub      ON sub.subject_id = co.subject_id
            WHERE j.status = 'failed'
              AND j.created_at >= ?
            ORDER BY j.created_at DESC
            LIMIT 200
        ");
        $stmt->execute([$sinceSql]);

        $groups = [];
        foreach ($stmt->fetchAll() as $r) {            // newest first
            $ts  = strtotime($r['created_at']);
            $day = date('Y-m-d', $ts);
            if (!isset($groups[$day])) {
                $groups[$day] = ['day' => $day, 'count' => 0, 'ts' => $ts, 'latest' => $r];
            }
            $groups[$day]['count']++;
        }

        foreach (array_slice($groups, 0, 10) as $g) {
            $r = $g['latest'];
            $n = $g['count'];
            $err    = $r['error_message'] ? notif_clip($r['error_message'], 60) : '';
            $detail = trim(($r['subject_name'] ?? '') . ($err !== '' ? ' · ' . $err : ''), ' ·');

            if ($n === 1) {
                $actor   = notif_person($r['firstname'], $r['lastname'], 'A teacher');
                $message = 'AI quiz generation failed';
            } else {
                $actor   = 'AI Quiz Generator';
                $message = notif_plural($n, 'quiz generation', 'quiz generations') . ' failed';
                $detail  = $err !== '' ? 'Latest: ' . $err : null;
            }
            // Count in the id: more failures that day show up as unread again.
            $add(
                "qf-{$g['day']}-{$n}", 'quiz_failed', $actor, $message,
                $detail !== '' ? $detail : null,
                notif_time_label($g['ts'], $now), $g['ts'],
                $base . 'dashboard.php'
            );
        }
    } catch (Throwable $e) {
    }
}

// ---- Newest first, capped --------------------------------------------------
usort($items, fn($a, $b) => $b['ts'] <=> $a['ts']);
$items = array_slice($items, 0, NOTIF_MAX);

echo json_encode(['success' => true, 'notifications' => $items], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
