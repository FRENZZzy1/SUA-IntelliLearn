<?php
require_once '../../config/config.php';

// ---- Access control -------------------------------------------------
if (!isLoggedIn() || ($_SESSION['role'] ?? '') !== 'student') {
    header('Location: ../../public/login.php');
    exit();
}

// ---- Resolve the logged-in student's real name (for header/sidebar) ---
$userId = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT student_id, firstname, lastname FROM students WHERE user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$student = $stmt->fetch();

if (!$student) {
    die('Student record not found for this account.');
}
$studentFullName = trim($student['firstname'] . ' ' . $student['lastname']);

$stmt = $pdo->prepare("
    SELECT sec.grade_level, sec.section_name
    FROM enrollments e
    JOIN classofferings co ON co.offering_id = e.offering_id AND co.is_archived = 0 AND co.section_id NOT IN (SELECT section_id FROM sections WHERE is_archived = 1)
    JOIN sections sec ON sec.section_id = co.section_id
    JOIN schoolyears sy ON sy.school_year_id = co.school_year_id
    WHERE e.student_id = ? AND e.status = 'enrolled' AND sy.is_current = 1
    LIMIT 1
");
$stmt->execute([(int) $student['student_id']]);
$sectionRow = $stmt->fetch();

$studentGradeSection = $sectionRow
    ? "Grade {$sectionRow['grade_level']} - {$sectionRow['section_name']}"
    : null;

// ---- Filter / search inputs --------------------------------------------
$allowedFilters = ['all', 'admin', 'teachers'];
$filter = $_GET['filter'] ?? 'all';
if (!in_array($filter, $allowedFilters, true)) {
    $filter = 'all';
}
$q = trim($_GET['q'] ?? '');

/** Build a filter-tab URL that preserves the current search query. */
function announcements_filter_url(string $filter, string $q): string
{
    $params = ['filter' => $filter];
    if ($q !== '') {
        $params['q'] = $q;
    }
    return '?' . http_build_query($params);
}

// ---- Announcements visible to this student, filtered + searched -------
$sql = "
    SELECT a.*, u.username AS poster_username, u.role AS poster_role,
           t.firstname AS t_first, t.lastname AS t_last,
           csub.subject_name AS class_subject
    FROM announcements a
    JOIN users u ON u.id = a.posted_by
    LEFT JOIN teachers t ON t.user_id = u.id
    LEFT JOIN classofferings cco ON cco.offering_id = a.offering_id
    LEFT JOIN subjects csub ON csub.subject_id = cco.subject_id
    WHERE a.status = 'published'
      AND (a.audience = 'all' OR a.audience = 'students')
      AND (a.offering_id IS NULL OR a.offering_id IN (
            SELECT e.offering_id FROM enrollments e
            JOIN classofferings aco ON aco.offering_id = e.offering_id
                 AND aco.is_archived = 0
                 AND aco.section_id NOT IN (SELECT section_id FROM sections WHERE is_archived = 1)
            WHERE e.student_id = :sid AND e.status = 'enrolled'))
";
$params = [':sid' => (int) $student['student_id']];

if ($filter === 'admin') {
    $sql .= " AND u.role = 'admin'";
} elseif ($filter === 'teachers') {
    $sql .= " AND u.role = 'teacher'";
}
if ($q !== '') {
    // Two distinct placeholders: $pdo runs with ATTR_EMULATE_PREPARES = false,
    // so a real prepared statement can't bind the same named parameter twice.
    $sql .= " AND (a.title LIKE :q1 OR a.body LIKE :q2)";
    $params[':q1'] = '%' . $q . '%';
    $params[':q2'] = '%' . $q . '%';
}

$sql .= " ORDER BY a.is_pinned DESC, a.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$announcements = $stmt->fetchAll();

// Counts per source for the filter tabs (audience-scoped, not search-scoped).
$countStmt = $pdo->prepare("
    SELECT u.role, COUNT(*) AS total
    FROM announcements a
    JOIN users u ON u.id = a.posted_by
    WHERE a.status = 'published'
      AND (a.audience = 'all' OR a.audience = 'students')
      AND (a.offering_id IS NULL OR a.offering_id IN (
            SELECT e.offering_id FROM enrollments e
            JOIN classofferings aco ON aco.offering_id = e.offering_id
                 AND aco.is_archived = 0
                 AND aco.section_id NOT IN (SELECT section_id FROM sections WHERE is_archived = 1)
            WHERE e.student_id = :sid AND e.status = 'enrolled'))
    GROUP BY u.role
");
$countStmt->execute([':sid' => (int) $student['student_id']]);
$sourceCounts = ['admin' => 0, 'teachers' => 0];
foreach ($countStmt->fetchAll() as $row) {
    if ($row['role'] === 'admin') {
        $sourceCounts['admin'] += (int) $row['total'];
    } elseif ($row['role'] === 'teacher') {
        $sourceCounts['teachers'] += (int) $row['total'];
    }
}
$totalCount = array_sum($sourceCounts);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements · SUA IntelliLearn</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/announcements.css">
</head>

<body>

    <?php include '../../includes/student_sidebar.php'; ?>

    <main class="main-content" id="dashMain">

        <?php include '../../includes/student_header.php'; ?>

        <div class="dash-page-title">
            <h1 class="dash-title">Announcements</h1>
            <p class="dash-subtitle">Everything posted by your teachers and the school administration.</p>
        </div>

        <div class="announcements-toolbar">
            <div class="filter-tabs">
                <a href="<?= announcements_filter_url('all', $q) ?>" class="filter-tab <?= $filter === 'all' ? 'active' : '' ?>">
                    All <span class="filter-count"><?= $totalCount ?></span>
                </a>
                <a href="<?= announcements_filter_url('admin', $q) ?>" class="filter-tab <?= $filter === 'admin' ? 'active' : '' ?>">
                    From Admin <span class="filter-count"><?= $sourceCounts['admin'] ?></span>
                </a>
                <a href="<?= announcements_filter_url('teachers', $q) ?>" class="filter-tab <?= $filter === 'teachers' ? 'active' : '' ?>">
                    From Teachers <span class="filter-count"><?= $sourceCounts['teachers'] ?></span>
                </a>
            </div>

            <form class="announcements-search" method="get">
                <?php if ($filter !== 'all'): ?>
                    <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
                <?php endif; ?>
                <i class="fas fa-search"></i>
                <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search announcements...">
            </form>
        </div>

        <section class="panel announcements-panel">
            <?php if (empty($announcements)): ?>
                <div class="panel-empty">
                    <i class="fas fa-bullhorn"></i>
                    <?php if ($q !== '' || $filter !== 'all'): ?>
                        <p>No announcements match your filters.</p>
                        <span>Try a different search term, or switch back to "All".</span>
                    <?php else: ?>
                        <p>No announcements yet.</p>
                        <span>Anything your teachers or the administration post will show up here.</span>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="announcement-list announcement-list--full">
                    <?php foreach ($announcements as $a): ?>
                        <?php
                            $postedBy = $a['t_first']
                                ? trim($a['t_first'] . ' ' . $a['t_last'])
                                : 'Administration';
                            $audienceLabel = !empty($a['class_subject'])
                                ? $a['class_subject']
                                : ($a['audience'] === 'all' ? 'All School' : 'Students');
                        ?>
                        <div class="announcement-item<?= $a['is_pinned'] ? ' pinned' : '' ?>">
                            <div class="announcement-item-head">
                                <h4>
                                    <?php if ($a['is_pinned']): ?><i class="fas fa-thumbtack pin-icon" title="Pinned"></i><?php endif; ?>
                                    <?= htmlspecialchars($a['title']) ?>
                                </h4>
                            </div>
                            <p><?= nl2br(htmlspecialchars($a['body'])) ?></p>
                            <div class="announcement-meta">
                                <i class="fas fa-thumbtack"></i>
                                Posted by <?= htmlspecialchars($postedBy) ?> · <?= date('F j, Y · g:i A', strtotime($a['created_at'])) ?>
                                <span class="audience-chip"><?= $audienceLabel ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

    </main>

</body>

</html>