<?php
/**
 * Teacher chatbot context builder.
 *
 * ALWAYS included: today's date, class list, per-class performance summary,
 * risk levels + at-risk students, upcoming deadlines, pending request count.
 * Intent-based: per-student summary, assignment/quiz lists, raw score rows,
 * pending request details.
 *
 * At-risk data comes from get_at_risk_roster() in at_risk_functions.php, i.e.
 * the SAME engine as the At-Risk Students page, so the chatbot and the page
 * always agree (30% attendance / 40% assignments / 30% quizzes; High >= 60,
 * Medium >= 35; at least 3 data points per category).
 */
if (!function_exists('get_at_risk_roster')) {
    require_once __DIR__ . '/at_risk_functions.php';
}

function teacher_chatbot_context(PDO $pdo, string $question): string
{
    $userId = (int)($_SESSION['user_id'] ?? 0);
    $stmt = $pdo->prepare("SELECT teacher_id, firstname, lastname FROM teachers WHERE user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$teacher) return "No teacher record is linked to this account.";

    $teacherId = (int)$teacher['teacher_id'];
    $stmt = $pdo->prepare("
        SELECT co.offering_id, sub.subject_name, sec.section_name, sec.grade_level,
               co.quarter, sy.label AS school_year,
               co.schedule_days, co.start_time, co.end_time,
               (SELECT COUNT(*) FROM enrollments e
                WHERE e.offering_id = co.offering_id AND e.status = 'enrolled') AS student_count
        FROM classofferings co
        JOIN subjects sub ON sub.subject_id = co.subject_id
        JOIN sections sec ON sec.section_id = co.section_id
        LEFT JOIN schoolyears sy ON sy.school_year_id = co.school_year_id
        WHERE co.section_id NOT IN (SELECT section_id FROM sections WHERE is_archived = 1) AND co.teacher_id = ? AND co.status = 'active'
        ORDER BY sec.grade_level, sec.section_name, sub.subject_name, co.quarter
    ");
    $stmt->execute([$teacherId]);
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $context = [];
    $context[] = "TODAY: " . date('l, F j, Y');
    $context[] = "TEACHER: " . trim($teacher['firstname'] . ' ' . $teacher['lastname']);
    $context[] = "AUTHORIZED SCOPE: only this teacher's active classes and enrolled students.";

    if (!$classes) {
        $context[] = "No active classes.";
        return implode("\n", $context);
    }

    $offeringIds = array_map('intval', array_column($classes, 'offering_id'));
    $ph = implode(',', array_fill(0, count($offeringIds), '?'));
    $classLabel = [];

    $context[] = "CLASSES:";
    foreach ($classes as $c) {
        $schedule = $c['schedule_days'] ?: 'TBA';
        if ($c['start_time']) {
            $schedule .= ' ' . date('g:i A', strtotime($c['start_time'])) . '-' . date('g:i A', strtotime($c['end_time']));
        }
        $classLabel[(int)$c['offering_id']] = "{$c['subject_name']} | Section {$c['section_name']}";
        $context[] = "- {$c['subject_name']} | Grade {$c['grade_level']} | Section {$c['section_name']} | {$c['quarter']} | SY " .
            ($c['school_year'] ?: 'Unknown') . " | {$schedule} | {$c['student_count']} students";
    }

    /* ---------- Intent detection (broadened) ---------- */
    $q = strtolower($question);
    $studentIntent = (bool)preg_match('/\b(student|students|learner|learners|grade|grades|score|scores|attendance|absent|absences|late|missing|risk|at-risk|performance|performing|struggling|failing|fail|passing|weak|low|lowest|highest|top|best|rank|average|avg|improve|improvement|intervention|help|who|which|names?|roster|list|class|section)\b/i', $q);
    $assignmentIntent = (bool)preg_match('/\b(assignment|assignments|activity|activities|submission|submissions|submitted|due|missing|homework)\b/i', $q);
    $quizIntent = (bool)preg_match('/\b(quiz|quizzes|test|tests|exam|exams)\b/i', $q);
    $scoreIntent = (bool)preg_match('/\b(score|scores|grade|grades|points|result|results|marks?)\b/i', $q);
    $requestIntent = (bool)preg_match('/\b(pending|request|requests|approval|approvals|approve|join|enroll|enrollment)\b/i', $q);

    /* ---------- Risk engine (same logic as the At-Risk Students page) ---------- */
    $asgOk = "COALESCE(a.status,'') <> 'draft'";

    $perf = get_at_risk_roster($pdo, $offeringIds); // sorted riskiest first

    $metric = function ($pct, $enough, $count, $min) {
        if ($enough && $pct !== null) return round((float)$pct, 1) . '%';
        return $count > 0 ? "building data ({$count}/{$min})" : 'no data';
    };

    $classAgg = [];
    $riskCounts = ['High' => 0, 'Medium' => 0, 'Low' => 0, 'Insufficient Data' => 0];
    $atRisk = [];
    foreach ($perf as $r) {
        $label = $r['risk_label'];
        if (isset($riskCounts[$label])) $riskCounts[$label]++;
        if ($label === 'High' || $label === 'Medium') $atRisk[] = $r;

        $oid = (int)$r['offering_id'];
        $classAgg[$oid] ??= ['n' => 0, 'att' => [], 'asg' => [], 'qz' => [], 'missing' => 0, 'high' => 0, 'medium' => 0];
        $classAgg[$oid]['n']++;
        if ($r['attendance_pct'] !== null) $classAgg[$oid]['att'][] = (float)$r['attendance_pct'];
        if ($r['assignment_pct'] !== null) $classAgg[$oid]['asg'][] = (float)$r['assignment_pct'];
        if ($r['quiz_pct'] !== null)       $classAgg[$oid]['qz'][]  = (float)$r['quiz_pct'];
        $classAgg[$oid]['missing'] += (int)$r['assignment_missing'] + (int)$r['quiz_missing'];
        if ($label === 'High')   $classAgg[$oid]['high']++;
        if ($label === 'Medium') $classAgg[$oid]['medium']++;
    }

    $avg = fn(array $a) => $a ? round(array_sum($a) / count($a), 1) . '%' : 'N/A';

    /* ---------- ALWAYS: class performance summary ---------- */
    $context[] = "CLASS PERFORMANCE SUMMARY (same engine as the At-Risk Students page; averages only include students with enough data):";
    foreach ($classAgg as $oid => $a) {
        $context[] = "- " . ($classLabel[$oid] ?? "Class $oid") .
            " | students={$a['n']} | avg attendance=" . $avg($a['att']) .
            " | avg assignment score=" . $avg($a['asg']) .
            " | avg quiz score=" . $avg($a['qz']) .
            " | missing items (assignments+quizzes)={$a['missing']}" .
            " | High risk={$a['high']} | Medium risk={$a['medium']}";
    }

    /* ---------- ALWAYS: risk levels + at-risk students ---------- */
    $context[] = "RISK RULE (matches the At-Risk Students page): risk score = 100 - weighted performance " .
        "(attendance 30%, assignments 40%, quizzes 30%). High >= 60, Medium >= 35, Low < 35. " .
        "Each category needs at least " . RISK_MIN_DATA_POINTS . " items to count; otherwise it shows 'building data'. " .
        "Missing past-due work counts as 0; submitted-but-ungraded work is excluded. " .
        "Attendance credit: Present=full, Late=half, Excused=full, Absent=0.";
    $context[] = "RISK LEVEL COUNTS (student-class entries): High={$riskCounts['High']}, Medium={$riskCounts['Medium']}, " .
        "Low={$riskCounts['Low']}, Insufficient Data={$riskCounts['Insufficient Data']}";
    $context[] = "AT-RISK STUDENTS (High and Medium, riskiest first" . (count($atRisk) > 40 ? ', showing first 40 of ' . count($atRisk) : '') . "):";
    if (!$atRisk) $context[] = "- None";
    foreach (array_slice($atRisk, 0, 40) as $r) {
        $context[] = "- {$r['name']} | {$r['subject']} | {$r['section']} | {$r['quarter']} | risk={$r['risk_label']} (score {$r['risk_score']})" .
            " | attendance=" . $metric($r['attendance_pct'], $r['attendance_enough'], $r['attendance_total'], $r['min_data_points']) .
            " (present={$r['attendance_present']} late={$r['attendance_late']} absent={$r['attendance_absent']} excused={$r['attendance_excused']})" .
            " | assignments=" . $metric($r['assignment_pct'], $r['assignment_enough'], $r['assignment_total_due'], $r['min_data_points']) .
            " (missing {$r['assignment_missing']} of {$r['assignment_total_due']} due)" .
            " | quizzes=" . $metric($r['quiz_pct'], $r['quiz_enough'], $r['quiz_total_due'], $r['min_data_points']) .
            " (missing {$r['quiz_missing']} of {$r['quiz_total_due']} due)";
    }

    /* ---------- ALWAYS: upcoming deadlines (next 14 days) ---------- */
    $stmt = $pdo->prepare("
        SELECT a.title, a.due_date, sub.subject_name, sec.section_name
        FROM assignments a
        JOIN classofferings co ON co.offering_id = a.offering_id AND co.section_id NOT IN (SELECT section_id FROM sections WHERE is_archived = 1)
        JOIN subjects sub ON sub.subject_id = co.subject_id
        JOIN sections sec ON sec.section_id = co.section_id
        WHERE co.teacher_id = ? AND co.status = 'active' AND $asgOk
          AND a.due_date >= NOW() AND a.due_date <= DATE_ADD(NOW(), INTERVAL 14 DAY)
        ORDER BY a.due_date ASC
        LIMIT 30
    ");
    $stmt->execute([$teacherId]);
    $context[] = "UPCOMING ASSIGNMENT DEADLINES (next 14 days):";
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) $context[] = "- None";
    foreach ($rows as $r) {
        $context[] = "- {$r['title']} | {$r['subject_name']} | {$r['section_name']} | due {$r['due_date']}";
    }

    $stmt = $pdo->prepare("
        SELECT q.title, q.available_from, q.available_until, sub.subject_name, sec.section_name
        FROM quizzes q
        JOIN classofferings co ON co.offering_id = q.offering_id AND co.section_id NOT IN (SELECT section_id FROM sections WHERE is_archived = 1)
        JOIN subjects sub ON sub.subject_id = co.subject_id
        JOIN sections sec ON sec.section_id = co.section_id
        WHERE co.teacher_id = ? AND co.status = 'active'
          AND q.available_until >= NOW() AND q.available_until <= DATE_ADD(NOW(), INTERVAL 14 DAY)
        ORDER BY q.available_until ASC
        LIMIT 30
    ");
    $stmt->execute([$teacherId]);
    $context[] = "QUIZZES CLOSING SOON (next 14 days):";
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) $context[] = "- None";
    foreach ($rows as $r) {
        $context[] = "- {$r['title']} | {$r['subject_name']} | {$r['section_name']} | closes {$r['available_until']}";
    }

    /* ---------- ALWAYS: pending request count ---------- */
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM enrollments e
        JOIN classofferings co ON co.offering_id = e.offering_id AND co.section_id NOT IN (SELECT section_id FROM sections WHERE is_archived = 1) AND co.teacher_id = ?
        WHERE e.status IN ('pending','requested')
    ");
    $stmt->execute([$teacherId]);
    $context[] = "PENDING ENROLLMENT REQUESTS COUNT: " . (int)$stmt->fetchColumn();

    /* ---------- Intent-based: per-student summary ---------- */
    if ($studentIntent || $scoreIntent) {
        $context[] = "STUDENT PERFORMANCE SUMMARY (one line per student per class, riskiest first" . (count($perf) > 300 ? ', showing first 300 of ' . count($perf) : '') . "):";
        foreach (array_slice($perf, 0, 300) as $r) {
            $context[] = "- {$r['name']} | {$r['subject']} | {$r['section']} | {$r['quarter']} | risk={$r['risk_label']}" .
                " | attendance=" . $metric($r['attendance_pct'], $r['attendance_enough'], $r['attendance_total'], $r['min_data_points']) .
                " (present={$r['attendance_present']} late={$r['attendance_late']} absent={$r['attendance_absent']} excused={$r['attendance_excused']})" .
                " | assignments=" . $metric($r['assignment_pct'], $r['assignment_enough'], $r['assignment_total_due'], $r['min_data_points']) .
                " (missing {$r['assignment_missing']})" .
                " | quizzes=" . $metric($r['quiz_pct'], $r['quiz_enough'], $r['quiz_total_due'], $r['min_data_points']) .
                " (missing {$r['quiz_missing']})";
        }
    }

    /* ---------- Intent-based: assignment & quiz lists ---------- */
    if ($assignmentIntent || $studentIntent) {
        $stmt = $pdo->prepare("
            SELECT a.title, a.points, a.type, a.status, a.due_date,
                   sub.subject_name, sec.section_name
            FROM assignments a
            JOIN classofferings co ON co.offering_id = a.offering_id AND co.section_id NOT IN (SELECT section_id FROM sections WHERE is_archived = 1)
            JOIN subjects sub ON sub.subject_id = co.subject_id
            JOIN sections sec ON sec.section_id = co.section_id
            WHERE co.teacher_id = ? AND co.status = 'active'
            ORDER BY a.due_date IS NULL, a.due_date DESC
            LIMIT 150
        ");
        $stmt->execute([$teacherId]);
        $context[] = "ASSIGNMENTS:";
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $context[] = "- {$r['title']} | {$r['subject_name']} | {$r['section_name']} | {$r['points']} points | due " .
                ($r['due_date'] ?: 'No due date') . " | status={$r['status']}";
        }
    }

    if ($quizIntent || $studentIntent) {
        $stmt = $pdo->prepare("
            SELECT q.title, q.status, q.available_from, q.available_until,
                   sub.subject_name, sec.section_name
            FROM quizzes q
            JOIN classofferings co ON co.offering_id = q.offering_id AND co.section_id NOT IN (SELECT section_id FROM sections WHERE is_archived = 1)
            JOIN subjects sub ON sub.subject_id = co.subject_id
            JOIN sections sec ON sec.section_id = co.section_id
            WHERE co.teacher_id = ? AND co.status = 'active'
            ORDER BY q.created_at DESC
            LIMIT 150
        ");
        $stmt->execute([$teacherId]);
        $context[] = "QUIZZES:";
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $context[] = "- {$r['title']} | {$r['subject_name']} | {$r['section_name']} | available " .
                ($r['available_from'] ?: 'Any time') . " to " . ($r['available_until'] ?: 'No closing time') .
                " | status={$r['status']}";
        }
    }

    /* ---------- Intent-based: raw score rows (only when asked about scores/work) ---------- */
    if ($assignmentIntent || $scoreIntent) {
        $stmt = $pdo->prepare("
            SELECT s.firstname, s.lastname, sub.subject_name, sec.section_name,
                   a.title, a.points, MAX(sm.score) AS best_score
            FROM students s
            JOIN enrollments e ON e.student_id = s.student_id AND e.status = 'enrolled'
            JOIN classofferings co ON co.offering_id = e.offering_id AND co.section_id NOT IN (SELECT section_id FROM sections WHERE is_archived = 1) AND co.teacher_id = ?
            JOIN subjects sub ON sub.subject_id = co.subject_id
            JOIN sections sec ON sec.section_id = co.section_id
            JOIN assignments a ON a.offering_id = co.offering_id
            LEFT JOIN submissions sm ON sm.assignment_id = a.assignment_id AND sm.student_id = s.student_id
            WHERE co.status = 'active'
            GROUP BY s.student_id, s.firstname, s.lastname, sub.subject_name,
                     sec.section_name, a.assignment_id, a.title, a.points
            ORDER BY s.lastname, s.firstname
            LIMIT 400
        ");
        $stmt->execute([$teacherId]);
        $context[] = "ASSIGNMENT SCORES (N/A = no submission or not yet graded):";
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $score = $r['best_score'] === null ? 'N/A' : $r['best_score'];
            $context[] = "- {$r['firstname']} {$r['lastname']} | {$r['subject_name']} | {$r['section_name']} | {$r['title']} | {$score}/{$r['points']}";
        }
    }

    if ($quizIntent || $scoreIntent) {
        $stmt = $pdo->prepare("
            SELECT s.firstname, s.lastname, sub.subject_name, sec.section_name,
                   q.title, MAX(CASE WHEN qa.max_score > 0 THEN (qa.score / qa.max_score) * 100 ELSE NULL END) AS best_pct
            FROM students s
            JOIN enrollments e ON e.student_id = s.student_id AND e.status = 'enrolled'
            JOIN classofferings co ON co.offering_id = e.offering_id AND co.section_id NOT IN (SELECT section_id FROM sections WHERE is_archived = 1) AND co.teacher_id = ?
            JOIN subjects sub ON sub.subject_id = co.subject_id
            JOIN sections sec ON sec.section_id = co.section_id
            JOIN quizzes q ON q.offering_id = co.offering_id
            LEFT JOIN quiz_attempts qa ON qa.quiz_id = q.quiz_id AND qa.student_id = s.student_id
            WHERE co.status = 'active'
            GROUP BY s.student_id, s.firstname, s.lastname, sub.subject_name,
                     sec.section_name, q.quiz_id, q.title
            ORDER BY s.lastname, s.firstname
            LIMIT 400
        ");
        $stmt->execute([$teacherId]);
        $context[] = "QUIZ SCORES (N/A = no attempt):";
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $score = $r['best_pct'] === null ? 'N/A' : round((float)$r['best_pct'], 1) . '%';
            $context[] = "- {$r['firstname']} {$r['lastname']} | {$r['subject_name']} | {$r['section_name']} | {$r['title']} | {$score}";
        }
    }

    /* ---------- Intent-based: pending request details ---------- */
    if ($requestIntent) {
        $stmt = $pdo->prepare("
            SELECT s.firstname, s.lastname, sub.subject_name, sec.section_name,
                   e.status, e.enrolled_at
            FROM enrollments e
            JOIN students s ON s.student_id = e.student_id
            JOIN classofferings co ON co.offering_id = e.offering_id AND co.section_id NOT IN (SELECT section_id FROM sections WHERE is_archived = 1) AND co.teacher_id = ?
            JOIN subjects sub ON sub.subject_id = co.subject_id
            JOIN sections sec ON sec.section_id = co.section_id
            WHERE e.status IN ('pending','requested')
            ORDER BY e.enrolled_at DESC
            LIMIT 100
        ");
        $stmt->execute([$teacherId]);
        $context[] = "PENDING ENROLLMENT REQUESTS:";
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $context[] = "- {$r['firstname']} {$r['lastname']} | {$r['subject_name']} | {$r['section_name']} | {$r['status']} | {$r['enrolled_at']}";
        }
    }

    $context[] = "END OF DATABASE CONTEXT.";
    return implode("\n", $context);
}