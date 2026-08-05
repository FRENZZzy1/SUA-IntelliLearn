<?php
    require_once '../../config/config.php'; // adjust path to your actual config.php location

// ---- Access control -------------------------------------------------
if (!isLoggedIn() || ($_SESSION['role'] ?? '') !== 'teacher') {
    header('Location: ../../login.php');
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
$teacherId   = (int) $teacher['teacher_id'];
$teacherName = trim($teacher['firstname'] . ' ' . $teacher['lastname']);

// ---- Current school year ---------------------------------------------
$stmt = $pdo->query("SELECT school_year_id, label FROM schoolyears WHERE is_current = 1 LIMIT 1");
$schoolYear   = $stmt->fetch();
$schoolYearId = $schoolYear['school_year_id'] ?? null;

// ---- Active classes / subjects for this teacher -----------------------
$stmt = $pdo->prepare("
    SELECT co.offering_id, co.schedule_days, co.start_time, co.end_time,
           sub.subject_name, sec.section_name, sec.grade_level
    FROM classofferings co
    JOIN subjects sub  ON sub.subject_id = co.subject_id
    JOIN sections sec  ON sec.section_id = co.section_id
    WHERE co.teacher_id = ?
      AND co.status = 'active'
      AND (co.school_year_id = ? OR ? IS NULL)
    ORDER BY sec.grade_level, sub.subject_name
");
$stmt->execute([$teacherId, $schoolYearId, $schoolYearId]);
$activeOfferings = $stmt->fetchAll();
$offeringIds     = array_column($activeOfferings, 'offering_id');
$totalSubjects   = count($activeOfferings);

// ---- Total students enrolled across this teacher's active classes -----
$totalStudents = 0;
if ($offeringIds) {
    $placeholders = implode(',', array_fill(0, count($offeringIds), '?'));
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT student_id) AS total
        FROM enrollments
        WHERE status = 'active'
          AND offering_id IN ($placeholders)
    ");
    $stmt->execute($offeringIds);
    $totalStudents = (int) $stmt->fetchColumn();
}

// ---- Placeholder metrics (no backing table yet) ------------------------
// TODO: replace with a real query once an `assignments` table exists.
$assignmentsToGrade = null;
// TODO: replace with a real query once grades/attendance-based risk
// scoring is implemented (REQ018–REQ021 in the proposal).
$atRiskCount = null;
$atRiskStudents = []; // TODO: populate once at-risk detection exists.
// TODO: replace once an `attendance` table exists.
$attendanceRate = null;

// ---- Recent activity feed (announcements + materials + enrollments) ---
$recentActivity = [];

if ($offeringIds) {
    $placeholders = implode(',', array_fill(0, count($offeringIds), '?'));

    // Materials this teacher uploaded
    $stmt = $pdo->prepare("
        SELECT title AS label, type, created_at
        FROM learning_materials
        WHERE uploaded_by = ?
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$teacherId]);
    foreach ($stmt->fetchAll() as $row) {
        $recentActivity[] = [
            'icon' => 'fa-file-arrow-up',
            'text' => 'Uploaded material — ' . $row['label'],
            'time' => $row['created_at'],
        ];
    }

    // Recent enrollments into this teacher's classes
    $stmt = $pdo->prepare("
        SELECT s.firstname, s.lastname, e.enrolled_at, sub.subject_name
        FROM enrollments e
        JOIN students s   ON s.student_id = e.student_id
        JOIN classofferings co ON co.offering_id = e.offering_id
        JOIN subjects sub ON sub.subject_id = co.subject_id
        WHERE e.offering_id IN ($placeholders)
          AND e.status = 'active'
        ORDER BY e.enrolled_at DESC
        LIMIT 5
    ");
    $stmt->execute($offeringIds);
    foreach ($stmt->fetchAll() as $row) {
        $recentActivity[] = [
            'icon' => 'fa-user-plus',
            'text' => $row['firstname'] . ' ' . $row['lastname'] . ' enrolled in ' . $row['subject_name'],
            'time' => $row['enrolled_at'],
        ];
    }
}

// Announcements this teacher posted
$stmt = $pdo->prepare("
    SELECT title, created_at
    FROM announcements
    WHERE posted_by = ?
    ORDER BY created_at DESC
    LIMIT 5
");
$stmt->execute([$userId]);
foreach ($stmt->fetchAll() as $row) {
    $recentActivity[] = [
        'icon' => 'fa-bullhorn',
        'text' => 'Posted announcement — ' . $row['title'],
        'time' => $row['created_at'],
    ];
}

// Sort combined feed by timestamp, newest first, cap at 6
usort($recentActivity, fn($a, $b) => strtotime($b['time']) <=> strtotime($a['time']));
$recentActivity = array_slice($recentActivity, 0, 6);

/** Small helper: "2 hours ago" style relative time. */
function time_ago(string $datetime): string
{
    $diff = time() - strtotime($datetime);
    if ($diff < 60)     return 'Just now';
    if ($diff < 3600)   return floor($diff / 60) . ' min ago';
    if ($diff < 86400)  return floor($diff / 3600) . ' hr ago';
    if ($diff < 604800) return floor($diff / 86400) . ' day' . (floor($diff / 86400) > 1 ? 's' : '') . ' ago';
    return date('M j', strtotime($datetime));
}

$greetingHour = (int) date('H');
$greeting = $greetingHour < 12 ? 'Good morning' : ($greetingHour < 18 ? 'Good afternoon' : 'Good evening');
$todayLabel = date('l, F j, Y');


?>