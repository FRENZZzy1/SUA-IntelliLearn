<?php
/**
 * Return class offerings for one section in the current school year/term.
 * Used by the simplified Admin > Classes section browser.
 */
require_once __DIR__ . '/../../config/config.php';

requireAdmin();
header('Content-Type: application/json; charset=utf-8');

try {
    $sectionId = filter_input(INPUT_GET, 'section_id', FILTER_VALIDATE_INT);
    if (!$sectionId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'errors' => ['Invalid section.']]);
        exit();
    }

    $termIntervals = getTermIntervals($pdo);
    $currentTerm = resolveCurrentTerm($termIntervals);

    $sectionStmt = $pdo->prepare("
        SELECT sec.section_id, sec.section_name, sec.grade_level, sec.strand,
               sec.school_year_id, sy.label AS school_year_label
        FROM sections sec
        LEFT JOIN schoolyears sy ON sy.school_year_id = sec.school_year_id
        WHERE sec.section_id = ?
        LIMIT 1
    ");
    $sectionStmt->execute([$sectionId]);
    $section = $sectionStmt->fetch();

    if (!$section) {
        http_response_code(404);
        echo json_encode(['success' => false, 'errors' => ['Section not found.']]);
        exit();
    }

    $sql = "
        SELECT
            co.offering_id,
            co.subject_id,
            co.capacity,
            co.status,
            co.quarter,
            co.school_year_id,
            co.schedule_days,
            co.start_time,
            co.end_time,
            s.subject_name,
            t.firstname AS teacher_firstname,
            t.lastname AS teacher_lastname,
            (
                SELECT COUNT(*)
                FROM enrollments e
                WHERE e.offering_id = co.offering_id
                  AND e.status = 'active'
            ) AS enrolled_count
        FROM classofferings co
        JOIN subjects s ON s.subject_id = co.subject_id
        LEFT JOIN teachers t ON t.teacher_id = co.teacher_id
        WHERE co.section_id = ?
          AND co.school_year_id = ?
    ";

    $params = [$sectionId, (int)$section['school_year_id']];

    if ($currentTerm) {
        $sql .= " AND co.quarter = ?";
        $params[] = $currentTerm;
    }

    $sql .= " ORDER BY s.subject_name ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $classes = [];
    foreach ($stmt->fetchAll() as $row) {
        $teacherName = trim(($row['teacher_firstname'] ?? '') . ' ' . ($row['teacher_lastname'] ?? ''));
        $schedule = '';

        if (!empty($row['schedule_days'])) {
            $schedule = trim($row['schedule_days']);
        }

        if (!empty($row['start_time']) && !empty($row['end_time'])) {
            $time = date('g:i A', strtotime($row['start_time'])) . ' - ' . date('g:i A', strtotime($row['end_time']));
            $schedule = $schedule ? $schedule . ' · ' . $time : $time;
        }

        $classes[] = [
            'offering_id' => (int)$row['offering_id'],
            'subject_id' => (int)$row['subject_id'],
            'subject_name' => $row['subject_name'],
            'teacher_name' => $teacherName,
            'capacity' => (int)$row['capacity'],
            'enrolled_count' => (int)$row['enrolled_count'],
            'status' => $row['status'],
            'quarter' => $row['quarter'],
            'schedule' => $schedule,
            'start_time' => $row['start_time'],
            'end_time' => $row['end_time']
        ];
    }

    echo json_encode([
        'success' => true,
        'section' => $section,
        'current_term' => $currentTerm,
        'classes' => $classes
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'errors' => ['Unable to load section classes.']]);
}
