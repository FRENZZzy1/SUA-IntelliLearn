<?php
// Shared helpers for the Grade Book / student score details / export.
// The grade book no longer computes percentages or final grades. It only
// reports whether a student has submitted each required item.
//
//  Written Works     = every quiz (automatic) + assignments with type 'Written Work'
//  Performance Tasks = assignments with type 'Activity'
//  Exam              = assignments with type 'Exam'

const GB_CATEGORIES = [
    'ww'   => 'Written Works',
    'pt'   => 'Performance Tasks',
    'exam' => 'Exam',
];

function gb_category_key(?string $assignmentType): string
{
    if ($assignmentType === 'Written Work') return 'ww';
    if ($assignmentType === 'Exam') return 'exam';
    return 'pt'; // 'Activity' (Performance Task) and anything legacy
}

/**
 * Loads the published items of an offering grouped by category, plus which
 * student submitted which item.
 *
 * 'scores' holds, per student and item uid, the score of the latest counted
 * attempt: ['score' => ?float, 'max' => ?float].
 *
 * @return array{items: array<string, list<array>>, done: array<int, array<string,bool>>, scores: array<int, array<string,array>>}
 */
function gb_load_items(PDO $pdo, int $offeringId): array
{
    $items = ['ww' => [], 'pt' => [], 'exam' => []];
    $done  = [];
    $scores = [];

    // Assignments: "submitted" = any submission row that isn't flagged 'missing'.
    $stmt = $pdo->prepare("
        SELECT a.assignment_id, a.title, a.type, a.points, a.created_at, s.student_id, s.score
        FROM assignments a
        LEFT JOIN (
            SELECT sb.assignment_id, sb.student_id, sb.score
            FROM submissions sb
            WHERE sb.status <> 'missing'
              AND sb.attempt_number = (
                  SELECT MAX(x.attempt_number) FROM submissions x
                  WHERE x.assignment_id = sb.assignment_id
                    AND x.student_id = sb.student_id
                    AND x.status <> 'missing'
              )
        ) s ON s.assignment_id = a.assignment_id
        WHERE a.offering_id = ? AND a.status <> 'draft'
        ORDER BY a.created_at, a.assignment_id
    ");
    $stmt->execute([$offeringId]);
    $seen = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $id  = (int) $r['assignment_id'];
        $key = gb_category_key($r['type']);
        $uid = 'a' . $id;
        if (!isset($seen[$uid])) {
            $seen[$uid] = true;
            $items[$key][] = ['uid' => $uid, 'title' => $r['title'], 'kind' => 'Assignment', 'type' => $r['type'] ?: 'Activity', 'max' => (float) $r['points'], 'created_at' => $r['created_at']];
        }
        if ($r['student_id'] !== null) {
            $done[(int) $r['student_id']][$uid] = true;
            $scores[(int) $r['student_id']][$uid] = [
                'score' => $r['score'] !== null ? (float) $r['score'] : null,
                'max'   => (float) $r['points'],
            ];
        }
    }

    // Quizzes are always Written Works. "Submitted" = a finished attempt.
    $stmt = $pdo->prepare("
        SELECT q.quiz_id, q.title, q.created_at, x.student_id, x.score, x.max_score,
               (SELECT COALESCE(SUM(qq.points), 0) FROM quiz_questions qq WHERE qq.quiz_id = q.quiz_id) AS total_points
        FROM quizzes q
        LEFT JOIN (
            SELECT qa.quiz_id, qa.student_id, qa.score, qa.max_score
            FROM quiz_attempts qa
            WHERE qa.status IN ('submitted','graded')
              AND qa.attempt_number = (
                  SELECT MAX(y.attempt_number) FROM quiz_attempts y
                  WHERE y.quiz_id = qa.quiz_id
                    AND y.student_id = qa.student_id
                    AND y.status IN ('submitted','graded')
              )
        ) x ON x.quiz_id = q.quiz_id
        WHERE q.offering_id = ? AND q.status <> 'draft'
        ORDER BY q.created_at, q.quiz_id
    ");
    $stmt->execute([$offeringId]);
    $seen = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $id  = (int) $r['quiz_id'];
        $uid = 'q' . $id;
        if (!isset($seen[$uid])) {
            $seen[$uid] = true;
            $items['ww'][] = ['uid' => $uid, 'title' => $r['title'], 'kind' => 'Quiz', 'type' => 'Written Work', 'max' => (float) $r['total_points'], 'created_at' => $r['created_at']];
        }
        if ($r['student_id'] !== null) {
            $done[(int) $r['student_id']][$uid] = true;
            $max = $r['max_score'] !== null ? (float) $r['max_score'] : (float) $r['total_points'];
            $scores[(int) $r['student_id']][$uid] = [
                'score' => $r['score'] !== null ? (float) $r['score'] : null,
                'max'   => $max,
            ];
        }
    }

    return ['items' => $items, 'done' => $done, 'scores' => $scores];
}

/** 'complete' | 'incomplete' | 'none' (no items in that category yet). */
function gb_category_status(array $categoryItems, array $studentDone): string
{
    if (!$categoryItems) return 'none';
    foreach ($categoryItems as $it) {
        if (empty($studentDone[$it['uid']])) return 'incomplete';
    }
    return 'complete';
}

function gb_status_label(string $status): string
{
    return $status === 'complete' ? 'Complete' : ($status === 'incomplete' ? 'Incomplete' : 'N/A');
}