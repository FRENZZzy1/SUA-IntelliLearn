<?php
// assests/api/search_entities.php?type=teacher|section|student&q=Rose
// Returns JSON matches for the "Search & Export" panel's live search boxes.
require_once __DIR__ . '/../../../../config/config.php';

requireAdmin();

header('Content-Type: application/json');

$type = $_GET['type'] ?? '';
$q    = trim($_GET['q'] ?? '');

// Server-side guard (mirrors courses.js): strip special characters per search type.
//   teacher / student: letters + spaces | section: letters, digits, spaces + dash
if ($type === 'teacher' || $type === 'student') {
    $q = preg_replace('/[^\p{L}\p{M} ]/u', '', $q);
} elseif ($type === 'section') {
    $q = preg_replace('/[^\p{L}\p{M}0-9 \-]/u', '', $q);
}
$q = trim(preg_replace('/ {2,}/', ' ', (string) $q));

if (!in_array($type, ['teacher', 'section', 'student'], true)) {
    echo json_encode(['success' => false, 'errors' => ['Invalid search type.']]);
    exit();
}

if ($q === '' || mb_strlen($q) < 1) {
    echo json_encode(['success' => true, 'results' => []]);
    exit();
}

$like = '%' . $q . '%';
$results = [];

if ($type === 'teacher') {
    $stmt = $pdo->prepare("
        SELECT teacher_id, firstname, lastname, department,
               (SELECT COUNT(*) FROM classofferings co WHERE co.teacher_id = teachers.teacher_id AND co.is_archived = 0 AND co.school_year_id IN (SELECT school_year_id FROM schoolyears WHERE is_current = 1)) AS class_count
        FROM teachers
        WHERE CONCAT(firstname, ' ', lastname) LIKE ? OR firstname LIKE ? OR lastname LIKE ?
        ORDER BY lastname, firstname
        LIMIT 10
    ");
    $stmt->execute([$like, $like, $like]);
    foreach ($stmt->fetchAll() as $t) {
        $results[] = [
            'id'       => (int) $t['teacher_id'],
            'label'    => trim($t['firstname'] . ' ' . $t['lastname']),
            'sublabel' => ($t['department'] ? $t['department'] . ' · ' : '') . $t['class_count'] . ' class' . ($t['class_count'] == 1 ? '' : 'es'),
        ];
    }
} elseif ($type === 'section') {
    $stmt = $pdo->prepare("
        SELECT section_id, section_name, grade_level,
               (SELECT COUNT(*) FROM classofferings co WHERE co.section_id = sections.section_id AND co.is_archived = 0 AND co.school_year_id IN (SELECT school_year_id FROM schoolyears WHERE is_current = 1)) AS class_count
        FROM sections
        WHERE section_name LIKE ?
          AND is_archived = 0
          AND school_year_id IN (SELECT school_year_id FROM schoolyears WHERE is_current = 1)
        ORDER BY grade_level, section_name
        LIMIT 10
    ");
    $stmt->execute([$like]);
    foreach ($stmt->fetchAll() as $s) {
        $results[] = [
            'id'       => (int) $s['section_id'],
            'label'    => $s['section_name'],
            'sublabel' => 'Grade ' . $s['grade_level'] . ' · ' . $s['class_count'] . ' class' . ($s['class_count'] == 1 ? '' : 'es'),
        ];
    }
} elseif ($type === 'student') {
    $stmt = $pdo->prepare("
        SELECT student_id, firstname, middlename, lastname, student_lrn,
               (SELECT COUNT(*) FROM enrollments e JOIN classofferings co ON co.offering_id = e.offering_id WHERE e.student_id = students.student_id AND e.status NOT IN ('pending','denied') AND co.is_archived = 0 AND co.school_year_id IN (SELECT school_year_id FROM schoolyears WHERE is_current = 1)) AS class_count
        FROM students
        WHERE CONCAT(firstname, ' ', lastname) LIKE ? OR firstname LIKE ? OR lastname LIKE ? OR student_lrn LIKE ?
        ORDER BY lastname, firstname
        LIMIT 10
    ");
    $stmt->execute([$like, $like, $like, $like]);
    foreach ($stmt->fetchAll() as $s) {
        $results[] = [
            'id'       => (int) $s['student_id'],
            'label'    => trim($s['firstname'] . ' ' . $s['lastname']),
            'sublabel' => 'LRN ' . $s['student_lrn'] . ' · ' . $s['class_count'] . ' class' . ($s['class_count'] == 1 ? '' : 'es'),
        ];
    }
}

echo json_encode(['success' => true, 'results' => $results]);
exit();