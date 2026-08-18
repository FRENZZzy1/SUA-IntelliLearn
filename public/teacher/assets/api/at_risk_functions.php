<?php
/**
 * at_risk_functions.php
 * -----------------------------------------------------------------
 * Student at-risk detection engine.
 *
 * Combines three signals per (student, class offering):
 *   - Attendance rate                (weight 30%)
 *   - Assignment score average       (weight 40%)
 *   - Quiz score average             (weight 30%)
 *
 * Each is converted to a 0-100 "performance" percentage. Missing work
 * (an assignment/quiz that's past due with no submission/attempt) is
 * scored as 0 for that item, so it drags the average down like it
 * should. Work that's submitted but not yet graded is excluded (not
 * the student's fault the teacher hasn't graded it yet).
 *
 * If a class genuinely has no attendance data yet, that 30% weight is
 * redistributed proportionally across whichever signals DO have data,
 * so a brand-new class isn't unfairly penalized for missing history.
 *
 * risk_score = 100 - weighted performance   (0-100, higher = riskier)
 *   >= 60  -> High
 *   >= 35  -> Medium
 *   <  35  -> Low
 *   no data at all in any category -> 'Insufficient Data'
 * -----------------------------------------------------------------
 */

const RISK_WEIGHT_ATTENDANCE  = 0.30;
const RISK_WEIGHT_ASSIGNMENTS = 0.40;
const RISK_WEIGHT_QUIZZES     = 0.30;

const RISK_THRESHOLD_HIGH   = 60.0;
const RISK_THRESHOLD_MEDIUM = 35.0;

/**
 * Main entry point. Returns one row per (student, offering) enrollment.
 *
 * @param PDO   $pdo
 * @param int[] $offeringIds  Offerings to include (e.g. this teacher's active classes)
 * @return array<int, array<string,mixed>>
 */
function get_at_risk_roster(PDO $pdo, array $offeringIds): array
{
    if (empty($offeringIds)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($offeringIds), '?'));

    // ---- Base roster: active enrollments in these offerings ----------
    $stmt = $pdo->prepare("
        SELECT
            e.student_id, e.offering_id,
            s.firstname, s.lastname,
            sub.subject_name, sec.section_name, sec.grade_level
        FROM enrollments e
        JOIN students s        ON s.student_id = e.student_id
        JOIN classofferings co ON co.offering_id = e.offering_id
        JOIN subjects sub      ON sub.subject_id = co.subject_id
        JOIN sections sec      ON sec.section_id = co.section_id
        WHERE e.status = 'active'
          AND e.offering_id IN ($placeholders)
        ORDER BY sec.grade_level, sub.subject_name, s.lastname
    ");
    $stmt->execute($offeringIds);
    $roster = $stmt->fetchAll();

    if (!$roster) {
        return [];
    }

    $attendanceMap = calculate_attendance_metrics($pdo, $offeringIds);
    $assignmentMap = calculate_assignment_metrics($pdo, $offeringIds);
    $quizMap       = calculate_quiz_metrics($pdo, $offeringIds);

    $results = [];
    foreach ($roster as $row) {
        $key = $row['student_id'] . '_' . $row['offering_id'];

        $att   = $attendanceMap[$key] ?? null;
        $asg   = $assignmentMap[$key] ?? null;
        $quiz  = $quizMap[$key] ?? null;

        [$riskScore, $riskLabel] = classify_risk(
            $att['attendance_pct'] ?? null,
            $asg['assignment_pct'] ?? null,
            $quiz['quiz_pct'] ?? null
        );

        $results[] = [
            'student_id'   => (int) $row['student_id'],
            'offering_id'  => (int) $row['offering_id'],
            'name'         => trim($row['firstname'] . ' ' . $row['lastname']),
            'subject'      => $row['subject_name'],
            'section'      => $row['section_name'],
            'grade_level'  => (int) $row['grade_level'],

            'attendance_pct'     => $att['attendance_pct'] ?? null,
            'attendance_present' => $att['present_cnt'] ?? 0,
            'attendance_late'    => $att['late_cnt'] ?? 0,
            'attendance_absent'  => $att['absent_cnt'] ?? 0,
            'attendance_excused' => $att['excused_cnt'] ?? 0,
            'attendance_total'   => $att['total_cnt'] ?? 0,

            'assignment_pct'        => $asg['assignment_pct'] ?? null,
            'assignment_missing'    => $asg['missing_count'] ?? 0,
            'assignment_total_due'  => $asg['total_due'] ?? 0,

            'quiz_pct'        => $quiz['quiz_pct'] ?? null,
            'quiz_missing'    => $quiz['missing_count'] ?? 0,
            'quiz_total_due'  => $quiz['total_due'] ?? 0,

            'risk_score' => $riskScore,
            'risk_label' => $riskLabel,
        ];
    }

    // Sort riskiest first; null scores (insufficient data) sink to the bottom.
    usort($results, function ($a, $b) {
        if ($a['risk_score'] === null && $b['risk_score'] === null) return 0;
        if ($a['risk_score'] === null) return 1;
        if ($b['risk_score'] === null) return -1;
        return $b['risk_score'] <=> $a['risk_score'];
    });

    return $results;
}

/**
 * Compute risk metrics for a single student in a single offering.
 * Used by the AJAX "AI Insights" endpoint so it doesn't have to pull
 * the whole roster just to analyze one row.
 */
function get_at_risk_single(PDO $pdo, int $studentId, int $offeringId): ?array
{
    $roster = get_at_risk_roster($pdo, [$offeringId]);
    foreach ($roster as $row) {
        if ($row['student_id'] === $studentId) {
            return $row;
        }
    }
    return null;
}

function classify_risk(?float $attendancePct, ?float $assignmentPct, ?float $quizPct): array
{
    $components = [];
    if ($attendancePct !== null) {
        $components[] = ['weight' => RISK_WEIGHT_ATTENDANCE, 'value' => $attendancePct];
    }
    if ($assignmentPct !== null) {
        $components[] = ['weight' => RISK_WEIGHT_ASSIGNMENTS, 'value' => $assignmentPct];
    }
    if ($quizPct !== null) {
        $components[] = ['weight' => RISK_WEIGHT_QUIZZES, 'value' => $quizPct];
    }

    if (empty($components)) {
        return [null, 'Insufficient Data'];
    }

    $totalWeight = array_sum(array_column($components, 'weight'));
    $weightedSum = 0.0;
    foreach ($components as $c) {
        $weightedSum += $c['value'] * $c['weight'];
    }
    $performance = $weightedSum / $totalWeight; // 0-100, higher = better
    $riskScore = round(100 - $performance, 1);

    if ($riskScore >= RISK_THRESHOLD_HIGH) {
        $label = 'High';
    } elseif ($riskScore >= RISK_THRESHOLD_MEDIUM) {
        $label = 'Medium';
    } else {
        $label = 'Low';
    }

    return [$riskScore, $label];
}

// =======================================================================
// Attendance
// =======================================================================
function calculate_attendance_metrics(PDO $pdo, array $offeringIds): array
{
    $placeholders = implode(',', array_fill(0, count($offeringIds), '?'));
    $stmt = $pdo->prepare("
        SELECT
            student_id, offering_id,
            SUM(status = 'Present') AS present_cnt,
            SUM(status = 'Late')    AS late_cnt,
            SUM(status = 'Absent')  AS absent_cnt,
            SUM(status = 'Excused') AS excused_cnt,
            COUNT(*)                AS total_cnt
        FROM attendance
        WHERE offering_id IN ($placeholders)
        GROUP BY student_id, offering_id
    ");
    $stmt->execute($offeringIds);

    $map = [];
    foreach ($stmt->fetchAll() as $row) {
        $total = (int) $row['total_cnt'];
        // Present = full credit, Late = half credit, Excused = full credit
        // (an excused absence shouldn't count against the student), Absent = 0.
        $creditedDays = $row['present_cnt'] + ($row['late_cnt'] * 0.5) + $row['excused_cnt'];
        $pct = $total > 0 ? round(($creditedDays / $total) * 100, 1) : null;

        $key = $row['student_id'] . '_' . $row['offering_id'];
        $map[$key] = [
            'attendance_pct' => $pct,
            'present_cnt'    => (int) $row['present_cnt'],
            'late_cnt'       => (int) $row['late_cnt'],
            'absent_cnt'     => (int) $row['absent_cnt'],
            'excused_cnt'    => (int) $row['excused_cnt'],
            'total_cnt'      => $total,
        ];
    }
    return $map;
}

// =======================================================================
// Assignments
// =======================================================================
function calculate_assignment_metrics(PDO $pdo, array $offeringIds): array
{
    $placeholders = implode(',', array_fill(0, count($offeringIds), '?'));

    // Only assignments that are actually due count toward the score —
    // work that isn't due yet can't be "missing".
    $stmt = $pdo->prepare("
        SELECT assignment_id, offering_id, points
        FROM assignments
        WHERE offering_id IN ($placeholders)
          AND status != 'draft'
          AND due_date IS NOT NULL
          AND due_date <= NOW()
    ");
    $stmt->execute($offeringIds);
    $dueAssignments = $stmt->fetchAll();

    if (!$dueAssignments) {
        return [];
    }

    $assignmentsByOffering = [];
    $assignmentIds = [];
    foreach ($dueAssignments as $a) {
        $assignmentsByOffering[$a['offering_id']][] = $a;
        $assignmentIds[] = $a['assignment_id'];
    }

    // Pull every submission for these assignments; we'll take each
    // student's BEST graded attempt per assignment.
    $subPlaceholders = implode(',', array_fill(0, count($assignmentIds), '?'));
    $stmt = $pdo->prepare("
        SELECT assignment_id, student_id, score
        FROM submissions
        WHERE assignment_id IN ($subPlaceholders)
    ");
    $stmt->execute($assignmentIds);

    // best[assignment_id][student_id] = ['has_submission'=>true, 'best_score'=>float|null]
    $best = [];
    foreach ($stmt->fetchAll() as $s) {
        $aId = $s['assignment_id'];
        $sId = $s['student_id'];
        if (!isset($best[$aId][$sId])) {
            $best[$aId][$sId] = ['has_submission' => true, 'best_score' => null];
        }
        if ($s['score'] !== null) {
            $score = (float) $s['score'];
            if ($best[$aId][$sId]['best_score'] === null || $score > $best[$aId][$sId]['best_score']) {
                $best[$aId][$sId]['best_score'] = $score;
            }
        }
    }

    // Which students are enrolled where, so we know who to score against.
    $enrolled = get_enrolled_student_offering_pairs($pdo, $offeringIds);

    $map = [];
    foreach ($enrolled as [$studentId, $offeringId]) {
        $assignments = $assignmentsByOffering[$offeringId] ?? [];
        if (!$assignments) {
            continue;
        }

        $values = [];
        $missing = 0;
        foreach ($assignments as $a) {
            $aId = $a['assignment_id'];
            $points = (float) $a['points'];
            $entry = $best[$aId][$studentId] ?? null;

            if ($entry === null) {
                // Never submitted at all -> counts as zero.
                $values[] = 0.0;
                $missing++;
            } elseif ($entry['best_score'] !== null && $points > 0) {
                $values[] = min(100, ($entry['best_score'] / $points) * 100);
            }
            // else: submitted but not graded yet -> excluded, not counted as missing.
        }

        $key = $studentId . '_' . $offeringId;
        $map[$key] = [
            'assignment_pct' => $values ? round(array_sum($values) / count($values), 1) : null,
            'missing_count'  => $missing,
            'total_due'      => count($assignments),
        ];
    }

    return $map;
}

// =======================================================================
// Quizzes
// =======================================================================
function calculate_quiz_metrics(PDO $pdo, array $offeringIds): array
{
    $placeholders = implode(',', array_fill(0, count($offeringIds), '?'));

    $stmt = $pdo->prepare("
        SELECT quiz_id, offering_id
        FROM quizzes
        WHERE offering_id IN ($placeholders)
          AND status = 'published'
          AND (available_until IS NULL OR available_until <= NOW())
    ");
    $stmt->execute($offeringIds);
    $dueQuizzes = $stmt->fetchAll();

    if (!$dueQuizzes) {
        return [];
    }

    $quizzesByOffering = [];
    $quizIds = [];
    foreach ($dueQuizzes as $q) {
        $quizzesByOffering[$q['offering_id']][] = $q;
        $quizIds[] = $q['quiz_id'];
    }

    $quizPlaceholders = implode(',', array_fill(0, count($quizIds), '?'));
    $stmt = $pdo->prepare("
        SELECT quiz_id, student_id, score, max_score
        FROM quiz_attempts
        WHERE quiz_id IN ($quizPlaceholders)
          AND status IN ('submitted', 'graded')
    ");
    $stmt->execute($quizIds);

    // best[quiz_id][student_id] = best percentage achieved
    $best = [];
    foreach ($stmt->fetchAll() as $r) {
        $qId = $r['quiz_id'];
        $sId = $r['student_id'];
        if ($r['score'] === null || $r['max_score'] === null || (float) $r['max_score'] <= 0) {
            continue;
        }
        $pct = min(100, ((float) $r['score'] / (float) $r['max_score']) * 100);
        if (!isset($best[$qId][$sId]) || $pct > $best[$qId][$sId]) {
            $best[$qId][$sId] = $pct;
        }
    }

    $attempted = []; // [quiz_id][student_id] = true, tracks who has ANY attempt (even ungraded)
    $stmt = $pdo->prepare("
        SELECT DISTINCT quiz_id, student_id
        FROM quiz_attempts
        WHERE quiz_id IN ($quizPlaceholders)
    ");
    $stmt->execute($quizIds);
    foreach ($stmt->fetchAll() as $r) {
        $attempted[$r['quiz_id']][$r['student_id']] = true;
    }

    $enrolled = get_enrolled_student_offering_pairs($pdo, $offeringIds);

    $map = [];
    foreach ($enrolled as [$studentId, $offeringId]) {
        $quizzes = $quizzesByOffering[$offeringId] ?? [];
        if (!$quizzes) {
            continue;
        }

        $values = [];
        $missing = 0;
        foreach ($quizzes as $q) {
            $qId = $q['quiz_id'];
            if (isset($best[$qId][$studentId])) {
                $values[] = $best[$qId][$studentId];
            } elseif (isset($attempted[$qId][$studentId])) {
                // attempted but not gradable yet (e.g. in_progress) -> excluded
                continue;
            } else {
                $values[] = 0.0;
                $missing++;
            }
        }

        $key = $studentId . '_' . $offeringId;
        $map[$key] = [
            'quiz_pct'      => $values ? round(array_sum($values) / count($values), 1) : null,
            'missing_count' => $missing,
            'total_due'     => count($quizzes),
        ];
    }

    return $map;
}

// =======================================================================
// Shared helper
// =======================================================================
function get_enrolled_student_offering_pairs(PDO $pdo, array $offeringIds): array
{
    static $cache = [];
    $cacheKey = implode(',', $offeringIds);
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $placeholders = implode(',', array_fill(0, count($offeringIds), '?'));
    $stmt = $pdo->prepare("
        SELECT student_id, offering_id
        FROM enrollments
        WHERE status = 'active' AND offering_id IN ($placeholders)
    ");
    $stmt->execute($offeringIds);

    $pairs = [];
    foreach ($stmt->fetchAll() as $r) {
        $pairs[] = [(int) $r['student_id'], (int) $r['offering_id']];
    }
    $cache[$cacheKey] = $pairs;
    return $pairs;
}

// =======================================================================
// AI insight caching (avoids re-calling Gemini on every page view)
// =======================================================================
function ensure_at_risk_insights_table(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS at_risk_insights (
            insight_id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            offering_id INT NOT NULL,
            risk_label VARCHAR(20) NOT NULL,
            risk_score DECIMAL(5,2) DEFAULT NULL,
            why TEXT,
            how TEXT,
            recommended_actions TEXT,
            generated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_student_offering (student_id, offering_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function get_cached_insight(PDO $pdo, int $studentId, int $offeringId, int $maxAgeSeconds = 43200): ?array
{
    ensure_at_risk_insights_table($pdo);
    $stmt = $pdo->prepare("
        SELECT risk_label, risk_score, why, how, recommended_actions, generated_at
        FROM at_risk_insights
        WHERE student_id = ? AND offering_id = ?
          AND generated_at >= (NOW() - INTERVAL ? SECOND)
        LIMIT 1
    ");
    $stmt->execute([$studentId, $offeringId, $maxAgeSeconds]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    return [
        'why'                 => $row['why'],
        'how'                 => $row['how'],
        'recommended_actions' => json_decode($row['recommended_actions'], true) ?: [],
        'generated_at'        => $row['generated_at'],
        'cached'              => true,
    ];
}

function save_insight(PDO $pdo, int $studentId, int $offeringId, string $riskLabel, ?float $riskScore, string $why, string $how, array $actions): void
{
    ensure_at_risk_insights_table($pdo);
    $stmt = $pdo->prepare("
        INSERT INTO at_risk_insights (student_id, offering_id, risk_label, risk_score, why, how, recommended_actions, generated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            risk_label = VALUES(risk_label),
            risk_score = VALUES(risk_score),
            why = VALUES(why),
            how = VALUES(how),
            recommended_actions = VALUES(recommended_actions),
            generated_at = NOW()
    ");
    $stmt->execute([$studentId, $offeringId, $riskLabel, $riskScore, $why, $how, json_encode($actions)]);
}
