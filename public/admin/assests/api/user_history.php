<?php
/**
 * User activity history (admin > User Management > eye icon > History).
 *
 * GET assests/api/user_history.php?user_id=12&category=all&offset=0
 *
 * Returns the newest-first activity of one user as JSON. The history is derived
 * from the tables that already exist (no extra logging table needed), so it also
 * works for activity that happened before this feature was added.
 *
 * Categories per role
 *   teacher : enrollment, materials, assignments, quizzes, grading, attendance, announcements
 *   student : enrollment, submissions, quizzes, grades, attendance, evaluations
 *   admin   : announcements, enrollment, evaluations
 */

require_once __DIR__ . '/../../../../config/config.php';

// Same permission the User Management page itself requires.
requireAdminModule('users', 'read');

header('Content-Type: application/json');

const HISTORY_PAGE_SIZE = 20;

function historyFail(int $code, string $msg): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'errors' => [$msg]]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    historyFail(405, 'Invalid request method.');
}

$userId = $_GET['user_id'] ?? '';
if (!ctype_digit((string) $userId)) {
    historyFail(422, 'Invalid user.');
}
$userId = (int) $userId;

$offset = isset($_GET['offset']) && ctype_digit((string) $_GET['offset']) ? (int) $_GET['offset'] : 0;
$offset = min($offset, 2000); // keep the merge cheap
$limit  = HISTORY_PAGE_SIZE;
$fetch  = $offset + $limit + 1; // per-source cap; the +1 tells us if there is a next page

$stmt = $pdo->prepare("SELECT id, role FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();
if (!$user) {
    historyFail(404, 'User not found.');
}
$role = $user['role'];

/** Profile ids used by the activity tables (teachers/students have their own ids). */
$teacherId = null;
$studentId = null;
if ($role === 'teacher') {
    $s = $pdo->prepare("SELECT teacher_id FROM teachers WHERE user_id = ?");
    $s->execute([$userId]);
    $teacherId = $s->fetchColumn() ?: null;
} elseif ($role === 'student') {
    $s = $pdo->prepare("SELECT student_id FROM students WHERE user_id = ?");
    $s->execute([$userId]);
    $studentId = $s->fetchColumn() ?: null;
}

/** Strip trailing zeros from a DECIMAL so 85.00 shows as 85 and 85.50 as 85.5. */
function num(string $col): string {
    return "IFNULL(TRIM(TRAILING '.' FROM TRIM(TRAILING '0' FROM CAST($col AS CHAR))), '-')";
}

/** "Subject · CLASSCODE" label. Requires aliases `sub` (subjects) and `co` (classofferings). */
const CLASS_LABEL = "CONCAT(sub.subject_name, ' · ', co.class_code)";

/** Joins from a table that has an offering_id column ($alias) to its class + subject. */
function classJoin(string $alias): string {
    return "JOIN classofferings co ON co.offering_id = $alias.offering_id
            JOIN subjects sub ON sub.subject_id = co.subject_id";
}

// Each source: category => list of [sql, params]. Every SQL returns
// action, title, detail, occurred_at.
$labels  = [];
$sources = [];

// Shared by teachers and admins: who accepted / denied enrollment requests.
$enrollmentDecisions = [
    "SELECT CASE e.status WHEN 'denied' THEN 'Denied enrollment request' ELSE 'Accepted student' END AS action,
            CONCAT(s.firstname, ' ', s.lastname) AS title,
            " . CLASS_LABEL . " AS detail,
            e.decided_at AS occurred_at
     FROM enrollments e
     JOIN students s ON s.student_id = e.student_id
     " . classJoin('e') . "
     WHERE e.decided_by = ? AND e.decided_at IS NOT NULL
     ORDER BY e.decided_at DESC LIMIT $fetch",
    [$userId],
];

$announcements = [
    "SELECT CASE an.status WHEN 'published' THEN 'Published announcement' ELSE 'Saved draft announcement' END AS action,
            an.title AS title,
            CONCAT('Audience: ', an.audience, IF(an.priority = 'normal', '', CONCAT(' · ', an.priority))) AS detail,
            COALESCE(an.published_at, an.created_at) AS occurred_at
     FROM announcements an
     WHERE an.posted_by = ?
     ORDER BY occurred_at DESC LIMIT $fetch",
    [$userId],
];

if ($role === 'teacher') {
    $labels = [
        'enrollment'    => 'Enrollment',
        'materials'     => 'Materials',
        'assignments'   => 'Assignments',
        'quizzes'       => 'Quizzes',
        'grading'       => 'Grading',
        'attendance'    => 'Attendance',
        'announcements' => 'Announcements',
    ];

    if ($teacherId) {
        $sources['enrollment'] = [$enrollmentDecisions];

        $sources['materials'] = [[
            "SELECT 'Uploaded material' AS action, lm.title AS title,
                    CONCAT(" . CLASS_LABEL . ", ' · ', UPPER(lm.type)) AS detail,
                    lm.created_at AS occurred_at
             FROM learning_materials lm
             " . classJoin('lm') . "
             WHERE lm.uploaded_by = ?
             ORDER BY lm.created_at DESC LIMIT $fetch",
            [$teacherId],
        ]];

        $sources['assignments'] = [[
            "SELECT CASE a.type WHEN 'Exam' THEN 'Created exam' ELSE 'Created activity' END AS action,
                    a.title AS title,
                    CONCAT(" . CLASS_LABEL . ", IF(a.status = 'draft', ' · Draft', '')) AS detail,
                    a.created_at AS occurred_at
             FROM assignments a
             " . classJoin('a') . "
             WHERE a.created_by = ?
             ORDER BY a.created_at DESC LIMIT $fetch",
            [$teacherId],
        ]];

        $sources['quizzes'] = [[
            "SELECT 'Created quiz' AS action, q.title AS title,
                    CONCAT(" . CLASS_LABEL . ", ' · ', UPPER(LEFT(q.status, 1)), SUBSTRING(q.status, 2)) AS detail,
                    q.created_at AS occurred_at
             FROM quizzes q
             " . classJoin('q') . "
             WHERE q.created_by = ?
             ORDER BY q.created_at DESC LIMIT $fetch",
            [$teacherId],
        ]];

        $sources['grading'] = [
            [
                "SELECT 'Graded submission' AS action, a.title AS title,
                        CONCAT(s.firstname, ' ', s.lastname, ' · ', " . num('sb.score') . ", '/', " . num('a.points') . ") AS detail,
                        sb.graded_at AS occurred_at
                 FROM submissions sb
                 JOIN assignments a ON a.assignment_id = sb.assignment_id
                 JOIN students s ON s.student_id = sb.student_id
                 WHERE sb.graded_by = ? AND sb.graded_at IS NOT NULL
                 ORDER BY sb.graded_at DESC LIMIT $fetch",
                [$teacherId],
            ],
            [
                "SELECT 'Recorded grade' AS action,
                        CONCAT(s.firstname, ' ', s.lastname) AS title,
                        CONCAT(" . CLASS_LABEL . ", ' · ', g.quarter, ': ', " . num('g.grade') . ") AS detail,
                        g.updated_at AS occurred_at
                 FROM grades g
                 JOIN enrollments e ON e.enrollment_id = g.enrollment_id
                 JOIN students s ON s.student_id = e.student_id
                 " . classJoin('e') . "
                 WHERE g.graded_by = ?
                 ORDER BY g.updated_at DESC LIMIT $fetch",
                [$teacherId],
            ],
        ];

        // One entry per class per day instead of one per student.
        $sources['attendance'] = [[
            "SELECT 'Recorded attendance' AS action,
                    " . CLASS_LABEL . " AS title,
                    CONCAT(COUNT(*), ' student', IF(COUNT(*) = 1, '', 's'), ' · ', DATE_FORMAT(att.attendance_date, '%b %e, %Y')) AS detail,
                    MAX(att.created_at) AS occurred_at
             FROM attendance att
             " . classJoin('att') . "
             WHERE att.recorded_by = ?
             GROUP BY att.offering_id, att.attendance_date, sub.subject_name, co.class_code
             ORDER BY occurred_at DESC LIMIT $fetch",
            [$teacherId],
        ]];
    }

    $sources['announcements'] = [$announcements];

} elseif ($role === 'student') {
    $labels = [
        'enrollment'  => 'Enrollment',
        'submissions' => 'Submissions',
        'quizzes'     => 'Quizzes',
        'grades'      => 'Grades',
        'attendance'  => 'Attendance',
        'evaluations' => 'Evaluations',
    ];

    if ($studentId) {
        $sources['enrollment'] = [[
            "SELECT CASE e.status
                        WHEN 'pending'   THEN 'Requested enrollment'
                        WHEN 'enrolled'  THEN 'Enrolled in class'
                        WHEN 'denied'    THEN 'Enrollment denied'
                        WHEN 'dropped'   THEN 'Dropped from class'
                        WHEN 'completed' THEN 'Completed class'
                    END AS action,
                    " . CLASS_LABEL . " AS title,
                    '' AS detail,
                    COALESCE(e.decided_at, e.enrolled_at) AS occurred_at
             FROM enrollments e
             " . classJoin('e') . "
             WHERE e.student_id = ?
             ORDER BY occurred_at DESC LIMIT $fetch",
            [$studentId],
        ]];

        $sources['submissions'] = [[
            "SELECT IF(sb.status = 'late', 'Submitted late', 'Submitted assignment') AS action,
                    a.title AS title,
                    " . CLASS_LABEL . " AS detail,
                    sb.submitted_at AS occurred_at
             FROM submissions sb
             JOIN assignments a ON a.assignment_id = sb.assignment_id
             " . classJoin('a') . "
             WHERE sb.student_id = ? AND sb.status <> 'missing'
             ORDER BY sb.submitted_at DESC LIMIT $fetch",
            [$studentId],
        ]];

        $sources['quizzes'] = [[
            "SELECT IF(qa.status = 'in_progress', 'Started quiz', 'Completed quiz') AS action,
                    q.title AS title,
                    CONCAT(" . CLASS_LABEL . ", IF(qa.score IS NULL, '', CONCAT(' · Score ', " . num('qa.score') . ", '/', " . num('qa.max_score') . "))) AS detail,
                    COALESCE(qa.submitted_at, qa.started_at) AS occurred_at
             FROM quiz_attempts qa
             JOIN quizzes q ON q.quiz_id = qa.quiz_id
             " . classJoin('q') . "
             WHERE qa.student_id = ?
             ORDER BY occurred_at DESC LIMIT $fetch",
            [$studentId],
        ]];

        $sources['grades'] = [
            [
                "SELECT 'Assignment graded' AS action, a.title AS title,
                        CONCAT(" . CLASS_LABEL . ", ' · Score ', " . num('sb.score') . ", '/', " . num('a.points') . ") AS detail,
                        sb.graded_at AS occurred_at
                 FROM submissions sb
                 JOIN assignments a ON a.assignment_id = sb.assignment_id
                 " . classJoin('a') . "
                 WHERE sb.student_id = ? AND sb.graded_at IS NOT NULL
                 ORDER BY sb.graded_at DESC LIMIT $fetch",
                [$studentId],
            ],
            [
                "SELECT 'Grade posted' AS action, " . CLASS_LABEL . " AS title,
                        CONCAT(g.quarter, ': ', " . num('g.grade') . ") AS detail,
                        g.updated_at AS occurred_at
                 FROM grades g
                 JOIN enrollments e ON e.enrollment_id = g.enrollment_id
                 " . classJoin('e') . "
                 WHERE e.student_id = ?
                 ORDER BY g.updated_at DESC LIMIT $fetch",
                [$studentId],
            ],
        ];

        $sources['attendance'] = [[
            "SELECT CONCAT('Marked ', att.status) AS action,
                    " . CLASS_LABEL . " AS title,
                    DATE_FORMAT(att.attendance_date, '%b %e, %Y') AS detail,
                    att.created_at AS occurred_at
             FROM attendance att
             " . classJoin('att') . "
             WHERE att.student_id = ?
             ORDER BY att.created_at DESC LIMIT $fetch",
            [$studentId],
        ]];

        $sources['evaluations'] = [[
            "SELECT 'Submitted teacher evaluation' AS action, r.title AS title,
                    " . CLASS_LABEL . " AS detail,
                    tes.submitted_at AS occurred_at
             FROM teacher_evaluation_submissions tes
             JOIN teacher_evaluation_rounds r ON r.round_id = tes.round_id
             " . classJoin('tes') . "
             WHERE tes.student_id = ?
             ORDER BY tes.submitted_at DESC LIMIT $fetch",
            [$studentId],
        ]];
    }

} else { // admin
    $labels = [
        'announcements' => 'Announcements',
        'enrollment'    => 'Enrollment',
        'evaluations'   => 'Evaluations',
    ];

    $sources['announcements'] = [$announcements];
    $sources['enrollment']    = [$enrollmentDecisions];
    $sources['evaluations']   = [[
        "SELECT 'Opened teacher evaluation' AS action, r.title AS title,
                CONCAT(r.term, IF(r.closes_on IS NULL, '', CONCAT(' · closes ', DATE_FORMAT(r.closes_on, '%b %e, %Y')))) AS detail,
                r.opened_at AS occurred_at
         FROM teacher_evaluation_rounds r
         WHERE r.opened_by = ?
         ORDER BY r.opened_at DESC LIMIT $fetch",
        [$userId],
    ]];
}

$category = $_GET['category'] ?? 'all';
if ($category !== 'all' && !isset($labels[$category])) {
    historyFail(422, 'Invalid category.');
}

// Gather rows from the requested sources, merge, sort newest first.
$rows = [];
foreach ($sources as $cat => $queries) {
    if ($category !== 'all' && $category !== $cat) continue;

    foreach ($queries as [$sql, $params]) {
        try {
            $q = $pdo->prepare($sql);
            $q->execute($params);
            foreach ($q->fetchAll() as $r) {
                if (empty($r['occurred_at'])) continue;
                $rows[] = [
                    'category'       => $cat,
                    'category_label' => $labels[$cat],
                    'action'         => $r['action'],
                    'title'          => $r['title'],
                    'detail'         => $r['detail'],
                    'occurred_at'    => $r['occurred_at'],
                ];
            }
        } catch (PDOException $e) {
            // One broken source (e.g. an older database missing a table) must not hide the rest.
            error_log('user_history source failed [' . $cat . ']: ' . $e->getMessage());
        }
    }
}

usort($rows, fn($a, $b) => strcmp($b['occurred_at'], $a['occurred_at']));

$hasMore = count($rows) > $offset + $limit;
$items   = array_slice($rows, $offset, $limit);

$categories = [['key' => 'all', 'label' => 'All activity']];
foreach ($labels as $key => $label) {
    $categories[] = ['key' => $key, 'label' => $label];
}

echo json_encode([
    'success'    => true,
    'role'       => $role,
    'category'   => $category,
    'categories' => $categories,
    'items'      => $items,
    'has_more'   => $hasMore,
    'next_offset'=> $offset + count($items),
]);
