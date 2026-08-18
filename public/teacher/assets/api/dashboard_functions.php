<?php
    require_once '../../config/config.php'; // adjust path to your actual config.php location
    require_once __DIR__ . '/at_risk_functions.php';

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

// ---- At-risk detection (attendance + assignments + quizzes, weighted) --
$atRiskRoster = $offeringIds ? get_at_risk_roster($pdo, $offeringIds) : [];

$atRiskCount = 0;
$atRiskStudents = [];
foreach ($atRiskRoster as $row) {
    if ($row['risk_label'] === 'High' || $row['risk_label'] === 'Medium') {
        $atRiskCount++;
    }
    if ($row['risk_label'] === 'High' && count($atRiskStudents) < 5) {
        $avg = $row['assignment_pct'] ?? $row['quiz_pct'] ?? $row['attendance_pct'];
        $atRiskStudents[] = [
            'name'      => $row['name'],
            'subject'   => $row['subject'],
            'avg_grade' => $avg !== null ? round($avg, 1) : 'N/A',
            'risk'      => $row['risk_label'],
        ];
    }
}

// ---- Placeholder metrics (no backing table yet) ------------------------
// TODO: replace with a real query once an `assignments` table exists.
$assignmentsToGrade = null;
// ---- Attendance rate across this teacher's active classes -------------
$attendanceRate = null;
if ($offeringIds) {
    $placeholders = implode(',', array_fill(0, count($offeringIds), '?'));
    $stmt = $pdo->prepare("
        SELECT
            SUM(status IN ('Present','Late','Excused')) AS credited,
            COUNT(*) AS total
        FROM attendance
        WHERE offering_id IN ($placeholders)
    ");
    $stmt->execute($offeringIds);
    $row = $stmt->fetch();
    if ($row && (int) $row['total'] > 0) {
        $attendanceRate = round(((int) $row['credited'] / (int) $row['total']) * 100, 1);
    }
}

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




//for teachers profile
function get_teacher_profile(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare(
        "SELECT t.teacher_id, t.user_id, t.firstname, t.lastname, t.middlename,
                t.email, t.employment_status, t.department, t.specialization,
                t.created_at,
                u.username, u.status
         FROM teachers t
         INNER JOIN users u ON u.id = t.user_id
         WHERE t.user_id = :user_id
         LIMIT 1"
    );
    $stmt->execute(['user_id' => $userId]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);

    return $profile ?: null;
}

/**
 * Update a teacher's editable info: name, email, department, specialization.
 * Returns ['success' => bool, 'errors' => string[]] to match the admin handler shape.
 */
function update_teacher_profile(
    PDO $pdo,
    int $teacherId,
    string $firstname,
    string $lastname,
    string $email,
    string $department,
    string $specialization
): array {
    $errors = [];

    if ($firstname === '') {
        $errors[] = 'First name is required.';
    }
    if ($lastname === '') {
        $errors[] = 'Last name is required.';
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email address is required.';
    }

    if (!empty($errors)) {
        return ['success' => false, 'errors' => $errors];
    }

    // Make sure the email isn't already used by another teacher.
    $check = $pdo->prepare(
        "SELECT teacher_id FROM teachers WHERE email = :email AND teacher_id != :teacher_id LIMIT 1"
    );
    $check->execute(['email' => $email, 'teacher_id' => $teacherId]);
    if ($check->fetch()) {
        return ['success' => false, 'errors' => ['That email address is already in use.']];
    }

    $stmt = $pdo->prepare(
        "UPDATE teachers
         SET firstname = :firstname,
             lastname = :lastname,
             email = :email,
             department = :department,
             specialization = :specialization,
             updated_at = CURRENT_TIMESTAMP
         WHERE teacher_id = :teacher_id"
    );

    $ok = $stmt->execute([
        'firstname'      => $firstname,
        'lastname'       => $lastname,
        'email'          => $email,
        'department'     => $department,
        'specialization' => $specialization,
        'teacher_id'     => $teacherId,
    ]);

    if (!$ok) {
        return ['success' => false, 'errors' => ['Failed to update profile. Please try again.']];
    }

    return ['success' => true, 'errors' => []];
}

function get_initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    $initials = '';
 
    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }
        $initials .= strtoupper($part[0]);
        if (strlen($initials) >= 2) {
            break;
        }
    }
 
    return $initials !== '' ? $initials : '?';
}
 
/**
 * Deterministically pick an avatar background color from a seed string
 * (e.g. username) so the same person always gets the same color.
 */
function get_avatar_color(string $seed): string
{
    $colors = [
        '#1B5E20', '#2E7D32', '#0D47A1', '#4527A0',
        '#AD1457', '#E65100', '#00695C', '#37474F',
    ];
 
    $index = crc32($seed) % count($colors);
 
    return $colors[$index];
}


?>