<?php
/**
 * Creates pending enrollment requests for multiple students and multiple
 * class offerings. All selected students share the selected section/classes.
 */
require_once __DIR__ . '/../../config/config.php';
requireAdmin();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'errors' => ['Invalid request method.']]);
    exit();
}

if (!validateCSRFToken($_POST['csrf'] ?? '')) {
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => ['Your session expired. Please refresh the page and try again.']]);
    exit();
}

$studentIds = $_POST['student_ids'] ?? [];
$offeringIds = $_POST['offering_ids'] ?? [];
$gradeLevel = $_POST['grade_level'] ?? '';
$strand = trim($_POST['strand'] ?? '');

if (!is_array($studentIds)) $studentIds = [$studentIds];
if (!is_array($offeringIds)) $offeringIds = [$offeringIds];
$studentIds = array_values(array_unique(array_filter(array_map('intval', $studentIds), fn($v) => $v > 0)));
$offeringIds = array_values(array_unique(array_filter(array_map('intval', $offeringIds), fn($v) => $v > 0)));

if (!in_array((string)$gradeLevel, ['7','8','9','10','11','12'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => ['Please choose a grade level.']]);
    exit();
}
if ($strand !== '' && !in_array($strand, ['STEM','ABM','HUMSS','TVL'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => ['Invalid strand.']]);
    exit();
}
if (!$studentIds) {
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => ['Select at least one student.']]);
    exit();
}
if (!$offeringIds) {
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => ['Select at least one subject.']]);
    exit();
}

$gradeLevel = (int)$gradeLevel;

try {
    // Validate that every selected offering belongs to one section/term and
    // matches the selected grade/strand. The selected section itself is
    // represented by the offerings, so no schema change is required.
    $marks = implode(',', array_fill(0, count($offeringIds), '?'));
    $stmt = $pdo->prepare("\n        SELECT co.offering_id, co.subject_id, co.quarter, co.capacity, co.status,\n               co.section_id, sec.school_year_id, sec.grade_level, sec.strand,\n               subj.subject_name,\n               (SELECT COUNT(*) FROM enrollments e WHERE e.offering_id = co.offering_id AND e.status = 'active') AS enrolled_count\n        FROM classofferings co\n        JOIN sections sec ON sec.section_id = co.section_id\n        JOIN subjects subj ON subj.subject_id = co.subject_id\n        WHERE co.offering_id IN ($marks)\n          AND co.status = 'active'\n          AND sec.grade_level = ?\n    ");
    $params = array_merge($offeringIds, [$gradeLevel]);
    if ($strand !== '') {
        $stmt = $pdo->prepare("\n            SELECT co.offering_id, co.subject_id, co.quarter, co.capacity, co.status,\n                   co.section_id, sec.school_year_id, sec.grade_level, sec.strand,\n                   subj.subject_name,\n                   (SELECT COUNT(*) FROM enrollments e WHERE e.offering_id = co.offering_id AND e.status = 'active') AS enrolled_count\n            FROM classofferings co\n            JOIN sections sec ON sec.section_id = co.section_id\n            JOIN subjects subj ON subj.subject_id = co.subject_id\n            WHERE co.offering_id IN ($marks)\n              AND co.status = 'active'\n              AND sec.grade_level = ?\n              AND sec.strand = ?\n        ");
        $params = array_merge($offeringIds, [$gradeLevel, $strand]);
    }
    $stmt->execute($params);
    $offerings = $stmt->fetchAll();

    if (count($offerings) !== count($offeringIds)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'errors' => ['One or more selected subjects no longer match the selected grade/strand or are unavailable. Refresh and try again.']]);
        exit();
    }

    $sectionIds = array_values(array_unique(array_map('intval', array_column($offerings, 'section_id'))));
    $quarters = array_values(array_unique(array_column($offerings, 'quarter')));
    $schoolYears = array_values(array_unique(array_map('intval', array_column($offerings, 'school_year_id'))));
    if (count($sectionIds) !== 1 || count($quarters) !== 1 || count($schoolYears) !== 1) {
        http_response_code(422);
        echo json_encode(['success' => false, 'errors' => ['Selected subjects must belong to the same section, term, and school year.']]);
        exit();
    }

    $offeringById = [];
    foreach ($offerings as $o) $offeringById[(int)$o['offering_id']] = $o;

    // Load names in one query for useful per-student feedback.
    $studentMarks = implode(',', array_fill(0, count($studentIds), '?'));
    $studentStmt = $pdo->prepare("SELECT student_id, firstname, lastname FROM students WHERE student_id IN ($studentMarks)");
    $studentStmt->execute($studentIds);
    $students = [];
    foreach ($studentStmt->fetchAll() as $s) $students[(int)$s['student_id']] = trim($s['firstname'].' '.$s['lastname']);

    $summary = ['success' => 0, 'failed' => 0];
    $failures = [];

    foreach ($studentIds as $studentId) {
        $studentName = $students[$studentId] ?? ('Student #' . $studentId);
        $studentErrors = [];
        $insertedForStudent = 0;

        foreach ($offeringIds as $offeringId) {
            $o = $offeringById[$offeringId];
            $subjectId = (int)$o['subject_id'];
            $quarter = $o['quarter'];
            $schoolYearId = (int)$o['school_year_id'];

            if ((int)$o['enrolled_count'] >= (int)$o['capacity']) {
                $studentErrors[] = $o['subject_name'] . ': section is full.';
                continue;
            }

            $dup = $pdo->prepare("\n                SELECT COUNT(*) FROM enrollment_requests er\n                JOIN classofferings co ON co.offering_id = er.offering_id\n                JOIN sections sec ON sec.section_id = co.section_id\n                WHERE er.student_id = ? AND er.subject_id = ? AND er.grade_level = ?\n                  AND er.status = 'pending' AND co.quarter = ? AND sec.school_year_id = ?\n            ");
            $dup->execute([$studentId, $subjectId, $gradeLevel, $quarter, $schoolYearId]);
            if ((int)$dup->fetchColumn() > 0) {
                $studentErrors[] = $o['subject_name'] . ': already has a pending request.';
                continue;
            }

            $activeSql = "\n                SELECT COUNT(*) FROM enrollments en\n                JOIN classofferings co ON co.offering_id = en.offering_id\n                JOIN sections sec ON sec.section_id = co.section_id\n                WHERE en.student_id = ? AND en.status = 'active'\n                  AND co.subject_id = ? AND sec.grade_level = ?\n                  AND co.quarter = ? AND sec.school_year_id = ?\n            ";
            $activeParams = [$studentId, $subjectId, $gradeLevel, $quarter, $schoolYearId];
            if ($strand !== '') { $activeSql .= ' AND sec.strand = ?'; $activeParams[] = $strand; }
            $active = $pdo->prepare($activeSql);
            $active->execute($activeParams);
            if ((int)$active->fetchColumn() > 0) {
                $studentErrors[] = $o['subject_name'] . ': already actively enrolled.';
                continue;
            }

            $deniedSql = "\n                SELECT COUNT(*) FROM enrollment_requests er\n                JOIN classofferings co ON co.offering_id = er.offering_id\n                JOIN sections sec ON sec.section_id = co.section_id\n                WHERE er.student_id = ? AND er.subject_id = ? AND er.grade_level = ?\n                  AND er.status = 'denied' AND co.quarter = ? AND sec.school_year_id = ?\n            ";
            $deniedParams = [$studentId, $subjectId, $gradeLevel, $quarter, $schoolYearId];
            if ($strand !== '') { $deniedSql .= ' AND er.strand = ?'; $deniedParams[] = $strand; }
            else { $deniedSql .= ' AND er.strand IS NULL'; }
            $denied = $pdo->prepare($deniedSql);
            $denied->execute($deniedParams);
            if ((int)$denied->fetchColumn() > 0) {
                $studentErrors[] = $o['subject_name'] . ': has a denied request; reopen it from Enrollment instead.';
                continue;
            }

            $insert = $pdo->prepare("INSERT INTO enrollment_requests (student_id, grade_level, subject_id, strand, offering_id, status) VALUES (?, ?, ?, ?, ?, 'pending')");
            $insert->execute([$studentId, $gradeLevel, $subjectId, $strand !== '' ? $strand : null, $offeringId]);
            $insertedForStudent++;
        }

        if ($insertedForStudent > 0) $summary['success']++;
        if ($studentErrors) {
            $summary['failed']++;
            $failures[] = ['student' => $studentName, 'errors' => $studentErrors];
        }
    }

    setFlashMessage('success', 'Bulk enrollment requests submitted.');
    echo json_encode(['success' => $summary['success'] > 0, 'summary' => $summary, 'failures' => $failures]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'errors' => ['Database error: ' . $e->getMessage()]]);
}
