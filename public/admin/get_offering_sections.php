<?php
/**
 * Backend endpoint for the "Section" dropdown in the Enroll Student
 * modal on enrollment.php. Returns the sections that have at least one
 * open (active, seats-available) class offering for the given
 * grade level + strand, matched against whichever term is currently
 * active per "Set Term Interval". Called via fetch() — always returns
 * JSON, never renders a page.
 *
 * Unlike subject-first lookups, this is intentionally subject-agnostic:
 * a section can host several class offerings (subjects) for the same
 * term, so the subject list is resolved afterwards via
 * get_section_offerings.php once a section is chosen.
 */

require_once __DIR__ . '/../../config/config.php';

requireAdmin();

header('Content-Type: application/json');

$gradeLevel = $_GET['grade_level'] ?? '';
$strand     = trim($_GET['strand'] ?? '');

if (!in_array((string) $gradeLevel, ['7', '8', '9', '10', '11', '12'], true)) {
    echo json_encode(['success' => false, 'errors' => ['Grade level is required.']]);
    exit();
}

if ($strand !== '' && !in_array($strand, ['STEM', 'ABM', 'HUMSS', 'TVL'], true)) {
    echo json_encode(['success' => false, 'errors' => ['Invalid strand.']]);
    exit();
}

// Term is no longer picked in the modal — every enrollment is matched
// against whichever term is currently active, same as new classes.
$term = resolveCurrentTerm(getTermIntervals($pdo));
if ($term === null) {
    echo json_encode(['success' => false, 'errors' => ['No term is currently active based on today\'s date. Set the term intervals first (Classes & Subjects > "Set Term Interval").']]);
    exit();
}

$gradeLevel = (int) $gradeLevel;

$sql = "
    SELECT DISTINCT sec.section_id, sec.section_name, sec.strand, sy.label AS school_year_label
    FROM classofferings co
    JOIN sections sec ON sec.section_id = co.section_id
    JOIN schoolyears sy ON sy.school_year_id = sec.school_year_id
    WHERE sec.grade_level = ? AND co.quarter = ? AND co.status = 'active'
      AND co.capacity > (
          SELECT COUNT(*) FROM enrollments e WHERE e.offering_id = co.offering_id AND e.status = 'active'
      )
";
$params = [$gradeLevel, $term];

if ($strand !== '') {
    $sql .= " AND sec.strand = ?";
    $params[] = $strand;
}

$sql .= " ORDER BY sec.section_name";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $options = [];
    foreach ($rows as $s) {
        $options[] = [
            'section_id' => (int) $s['section_id'],
            'label' => $s['section_name']
                . ($s['strand'] ? ' (' . $s['strand'] . ')' : '')
                . ' · ' . $s['school_year_label'],
        ];
    }

    echo json_encode(['success' => true, 'options' => $options]);
} catch (PDOException $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => ['Database error: ' . $e->getMessage()]]);
}