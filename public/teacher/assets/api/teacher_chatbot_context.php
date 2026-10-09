<?php
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
               sec.strand, co.quarter, sy.label AS school_year,
               co.schedule_days, co.start_time, co.end_time,
               (SELECT COUNT(*) FROM enrollments e
                WHERE e.offering_id = co.offering_id AND e.status = 'enrolled') AS student_count
        FROM classofferings co
        JOIN subjects sub ON sub.subject_id = co.subject_id
        JOIN sections sec ON sec.section_id = co.section_id
        LEFT JOIN schoolyears sy ON sy.school_year_id = co.school_year_id
        WHERE co.teacher_id = ? AND co.status = 'active'
        ORDER BY sec.grade_level, sec.section_name, sub.subject_name, co.quarter
    ");
    $stmt->execute([$teacherId]);
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $context = [];
    $context[] = "TEACHER: " . trim($teacher['firstname'] . ' ' . $teacher['lastname']);
    $context[] = "AUTHORIZED SCOPE: only this teacher's active classes and enrolled students.";

    if (!$classes) {
        $context[] = "No active classes.";
        return implode("\n", $context);
    }

    $offeringIds = array_map('intval', array_column($classes, 'offering_id'));
    $ph = implode(',', array_fill(0, count($offeringIds), '?'));

    $context[] = "CLASSES:";
    foreach ($classes as $c) {
        $schedule = $c['schedule_days'] ?: 'TBA';
        if ($c['start_time']) {
            $schedule .= ' ' . date('g:i A', strtotime($c['start_time'])) . '-' . date('g:i A', strtotime($c['end_time']));
        }
        $context[] = "- {$c['subject_name']} | Grade {$c['grade_level']} | Section {$c['section_name']} | {$c['quarter']} | SY " .
            ($c['school_year'] ?: 'Unknown') . " | {$schedule} | {$c['student_count']} students";
    }

    $q = strtolower($question);
    $studentIntent = (bool)preg_match('/\b(student|students|learner|learners|grade|grades|score|scores|attendance|absent|late|missing|risk|performance)\b/i', $q);
    $assignmentIntent = (bool)preg_match('/\b(assignment|assignments|activity|activities|submission|submissions|due|missing)\b/i', $q);
    $quizIntent = (bool)preg_match('/\b(quiz|quizzes|test|tests)\b/i', $q);
    $requestIntent = (bool)preg_match('/\b(pending|request|requests|approval|approvals|join)\b/i', $q);

    if ($studentIntent) {
        $stmt = $pdo->prepare("
            SELECT DISTINCT s.firstname, s.lastname, sub.subject_name,
                   sec.grade_level, sec.section_name, co.quarter
            FROM students s
            JOIN enrollments e ON e.student_id = s.student_id AND e.status = 'enrolled'
            JOIN classofferings co ON co.offering_id = e.offering_id
            JOIN subjects sub ON sub.subject_id = co.subject_id
            JOIN sections sec ON sec.section_id = co.section_id
            WHERE co.teacher_id = ? AND co.status = 'active'
            ORDER BY s.lastname, s.firstname, sub.subject_name
            LIMIT 300
        ");
        $stmt->execute([$teacherId]);
        $context[] = "STUDENT ROSTER (names and class placement only):";
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $context[] = "- {$r['firstname']} {$r['lastname']} | Grade {$r['grade_level']} | {$r['section_name']} | {$r['subject_name']} | {$r['quarter']}";
        }
    }

    if ($assignmentIntent || $studentIntent) {
        $stmt = $pdo->prepare("
            SELECT a.title, a.points, a.type, a.status, a.due_date,
                   sub.subject_name, sec.section_name
            FROM assignments a
            JOIN classofferings co ON co.offering_id = a.offering_id
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
            JOIN classofferings co ON co.offering_id = q.offering_id
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

    if ($studentIntent) {
        $stmt = $pdo->prepare("
            SELECT s.firstname, s.lastname, sub.subject_name, sec.section_name,
                   COUNT(att.attendance_id) AS total_count,
                   SUM(att.status = 'Present') AS present_count,
                   SUM(att.status = 'Late') AS late_count,
                   SUM(att.status = 'Absent') AS absent_count,
                   SUM(att.status = 'Excused') AS excused_count
            FROM students s
            JOIN enrollments e ON e.student_id = s.student_id AND e.status = 'enrolled'
            JOIN classofferings co ON co.offering_id = e.offering_id AND co.teacher_id = ?
            JOIN subjects sub ON sub.subject_id = co.subject_id
            JOIN sections sec ON sec.section_id = co.section_id
            LEFT JOIN attendance att ON att.student_id = s.student_id AND att.offering_id = co.offering_id
            WHERE co.status = 'active'
            GROUP BY s.student_id, s.firstname, s.lastname, sub.subject_name, sec.section_name
            ORDER BY s.lastname, s.firstname
            LIMIT 250
        ");
        $stmt->execute([$teacherId]);
        $context[] = "ATTENDANCE SUMMARY:";
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $total = (int)$r['total_count'];
            $credited = (int)$r['present_count'] + ((int)$r['late_count'] * 0.5) + (int)$r['excused_count'];
            $pct = $total ? round($credited / $total * 100, 1) . '%' : 'No records';
            $context[] = "- {$r['firstname']} {$r['lastname']} | {$r['subject_name']} | {$r['section_name']} | attendance={$pct} | present={$r['present_count']} late={$r['late_count']} absent={$r['absent_count']} excused={$r['excused_count']}";
        }

        $stmt = $pdo->prepare("
            SELECT s.firstname, s.lastname, sub.subject_name, sec.section_name,
                   a.title, a.points, MAX(sm.score) AS best_score
            FROM students s
            JOIN enrollments e ON e.student_id = s.student_id AND e.status = 'enrolled'
            JOIN classofferings co ON co.offering_id = e.offering_id AND co.teacher_id = ?
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
        $context[] = "ASSIGNMENT SCORES:";
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $score = $r['best_score'] === null ? 'N/A' : $r['best_score'];
            $context[] = "- {$r['firstname']} {$r['lastname']} | {$r['subject_name']} | {$r['section_name']} | {$r['title']} | {$score}/{$r['points']}";
        }

        $stmt = $pdo->prepare("
            SELECT s.firstname, s.lastname, sub.subject_name, sec.section_name,
                   q.title, MAX(CASE WHEN qa.max_score > 0 THEN (qa.score / qa.max_score) * 100 ELSE NULL END) AS best_pct
            FROM students s
            JOIN enrollments e ON e.student_id = s.student_id AND e.status = 'enrolled'
            JOIN classofferings co ON co.offering_id = e.offering_id AND co.teacher_id = ?
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
        $context[] = "QUIZ SCORES:";
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $score = $r['best_pct'] === null ? 'N/A' : round((float)$r['best_pct'], 1) . '%';
            $context[] = "- {$r['firstname']} {$r['lastname']} | {$r['subject_name']} | {$r['section_name']} | {$r['title']} | {$score}";
        }
    }

    if ($requestIntent) {
        $stmt = $pdo->prepare("
            SELECT s.firstname, s.lastname, sub.subject_name, sec.section_name,
                   e.status, e.enrolled_at
            FROM enrollments e
            JOIN students s ON s.student_id = e.student_id
            JOIN classofferings co ON co.offering_id = e.offering_id AND co.teacher_id = ?
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
