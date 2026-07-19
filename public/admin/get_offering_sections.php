<?php
/**
 * Backend endpoint for the "Section" dropdown in the Enroll Student
 * modal on enrollment.php. Returns the open class offerings (active,
 * matching subject + grade level + strand) so the admin picks a
 * specific section up front, instead of resolving ambiguous matches
 * at approval time. Called via fetch() — always returns JSON, never
 * renders a page.
 *
 * Matching rules mirror approve_enrollment.php: subject_id + grade_level
 * always match; strand is only filtered on when provided (junior high
 * requests, which have no strand, match any section for that grade).
 */

require_once __DIR__ . '/../../config/config.php';

requireAdmin();

header('Content-Type: application/json');

$gradeLevel = $_GET['grade_level'] ?? '';
$subjectId  = $_GET['subject_id'] ?? '';
$strand     = trim($_GET['strand'] ?? '');

if (!in_array((string) $gradeLevel, ['7', '8', '9', '10', '11', '12'], true) || !ctype_digit((string) $subjectId)) {
    echo json_encode(['success' => false, 'errors' => ['Grade level and subject are required.']]);
    exit();
}

if ($strand !== '' && !in_array($strand, ['STEM', 'ABM', 'HUMSS', 'TVL'], true)) {
    echo json_encode(['success' => false, 'errors' => ['Invalid strand.']]);
    exit();
}

$gradeLevel = (int) $gradeLevel;
$subjectId  = (int) $subjectId;

$sql = "
    SELECT co.offering_id, co.capacity, sec.section_name, sec.strand,
        (SELECT COUNT(*) FROM enrollments e WHERE e.offering_id = co.offering_id AND e.status = 'active') AS enrolled_count
    FROM classofferings co
    JOIN sections sec ON sec.section_id = co.section_id
    WHERE co.subject_id = ? AND sec.grade_level = ? AND co.status = 'active'
";
$params = [$subjectId, $gradeLevel];

if ($strand !== '') {
    $sql .= " AND sec.strand = ?";
    $params[] = $strand;
}

$sql .= " ORDER BY sec.section_name";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$options = [];
foreach ($rows as $c) {
    $seatsLeft = (int) $c['capacity'] - (int) $c['enrolled_count'];
    if ($seatsLeft <= 0) {
        continue; // full sections aren't offered as a choice
    }
    $options[] = [
        'offering_id' => (int) $c['offering_id'],
        'label' => $c['section_name']
            . ($c['strand'] ? ' (' . $c['strand'] . ')' : '')
            . ' · ' . $seatsLeft . ' seat' . ($seatsLeft === 1 ? '' : 's') . ' left',
    ];
}

echo json_encode(['success' => true, 'options' => $options]);