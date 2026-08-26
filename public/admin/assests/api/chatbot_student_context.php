<?php
/**
 * Student-specific retrieval for the admin chatbot.
 *
 * This file intentionally exposes only dashboard-safe student fields:
 * name, grade, strand, section, enrollment status, subject and teacher.
 * Sensitive fields such as LRN, birthdate, address and guardian details
 * are never selected here.
 */

function chatbot_is_student_roster_request(string $question): bool {
    $q = strtolower($question);

    $studentWords = ['student', 'students', 'learner', 'learners', 'pupil', 'pupils', 'enrollee', 'enrollees'];
    $listWords = ['list', 'names', 'name', 'roster', 'who', 'show', 'give', 'display', 'enumerate'];

    $hasStudentWord = false;
    foreach ($studentWords as $word) {
        if (preg_match('/\\b' . preg_quote($word, '/') . '\\b/i', $q)) {
            $hasStudentWord = true;
            break;
        }
    }

    $hasListWord = false;
    foreach ($listWords as $word) {
        if (preg_match('/\\b' . preg_quote($word, '/') . '\\b/i', $q)) {
            $hasListWord = true;
            break;
        }
    }

    // A filter such as "grade 7 students" is already enough to identify
    // this as a roster request even when the user does not say "list".
    $hasStudentFilter = (bool) preg_match('/\\bgrade[\\s\\-]*?(7|8|9|10|11|12)\\b/i', $q)
        || (bool) preg_match('/\\b(section|strand|class|subject)\\b/i', $q)
        || (bool) preg_match('/\\benrolled\\b/i', $q);

    return ($hasStudentWord && $hasListWord) || ($hasStudentWord && $hasStudentFilter);
}

function chatbot_extract_section_terms(string $question): array {
    $terms = [];
    if (preg_match_all('/\\bsection\\s+([a-z0-9][a-z0-9 _-]{0,30})/i', $question, $matches)) {
        foreach ($matches[1] as $raw) {
            $term = trim($raw);
            $term = preg_replace('/\\s+(students?|learners?|enrolled|names?|roster|list|please|are|is)\\b.*$/i', '', $term);
            $term = trim($term, " \\t\\n\\r\\0\\x0B-:,.");
            if ($term !== '' && strlen($term) <= 30 && !in_array(strtolower($term), $terms, true)) {
                $terms[] = strtolower($term);
            }
        }
    }
    return $terms;
}

function chatbot_extract_student_subjects(PDO $pdo, array $keywords): array {
    $subjects = [];
    $seen = [];

    foreach ($keywords as $keyword) {
        $like = '%' . $keyword . '%';
        $stmt = $pdo->prepare("SELECT subject_id, subject_name FROM subjects WHERE subject_name LIKE ? ORDER BY subject_name ASC LIMIT 5");
        $stmt->execute([$like]);
        foreach ($stmt->fetchAll() as $row) {
            $id = (int) $row['subject_id'];
            if (!isset($seen[$id])) {
                $seen[$id] = true;
                $subjects[] = $row;
            }
        }
    }

    return $subjects;
}

function chatbot_extract_student_teachers(PDO $pdo, array $keywords): array {
    $teachers = [];
    $seen = [];

    foreach ($keywords as $keyword) {
        $like = '%' . $keyword . '%';
        $stmt = $pdo->prepare("SELECT teacher_id, firstname, lastname FROM teachers WHERE firstname LIKE ? OR lastname LIKE ? ORDER BY lastname, firstname LIMIT 5");
        $stmt->execute([$like, $like]);
        foreach ($stmt->fetchAll() as $row) {
            $id = (int) $row['teacher_id'];
            if (!isset($seen[$id])) {
                $seen[$id] = true;
                $teachers[] = $row;
            }
        }
    }

    return $teachers;
}

function chatbot_search_student_roster(PDO $pdo, string $question, int $limit = 150): array {
    $grades = extract_grade_levels($question);
    $strands = extract_strands($question);
    $statuses = extract_status_keywords($question);
    $keywords = extract_chat_keywords($question, 8);
    $sectionTerms = chatbot_extract_section_terms($question);
    $subjectRows = chatbot_extract_student_subjects($pdo, $keywords);
    $teacherRows = chatbot_extract_student_teachers($pdo, $keywords);

    $where = [];
    $params = [];
    $joins = [];

    // If the question has enrollment-related filters, use active enrollment
    // records as the source of grade/strand/section/class information.
    $needsEnrollment = !empty($grades) || !empty($strands) || !empty($sectionTerms) || !empty($subjectRows) || !empty($teacherRows)
        || (bool) preg_match('/\\b(enrolled|enrollment|roster|class|course|subject|teacher)\\b/i', $question);

    if ($needsEnrollment) {
        $joins[] = "JOIN enrollments e ON e.student_id = s.student_id";
        $joins[] = "JOIN classofferings co ON co.offering_id = e.offering_id";
        $joins[] = "JOIN sections sec ON sec.section_id = co.section_id";
        $joins[] = "JOIN subjects sub ON sub.subject_id = co.subject_id";
        $joins[] = "LEFT JOIN teachers t ON t.teacher_id = co.teacher_id";

        if (!empty($grades)) {
            $where[] = 'sec.grade_level IN (' . implode(',', array_fill(0, count($grades), '?')) . ')';
            foreach ($grades as $grade) $params[] = $grade;
        }

        if (!empty($strands)) {
            $where[] = 'sec.strand IN (' . implode(',', array_fill(0, count($strands), '?')) . ')';
            foreach ($strands as $strand) $params[] = $strand;
        }

        if (!empty($sectionTerms)) {
            $sectionParts = [];
            foreach ($sectionTerms as $term) {
                $sectionParts[] = 'sec.section_name LIKE ?';
                $params[] = '%' . $term . '%';
            }
            $where[] = '(' . implode(' OR ', $sectionParts) . ')';
        }

        if (!empty($subjectRows)) {
            $ids = array_values(array_unique(array_map(fn($r) => (int) $r['subject_id'], $subjectRows)));
            $where[] = 'sub.subject_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
            foreach ($ids as $id) $params[] = $id;
        }

        if (!empty($teacherRows)) {
            $ids = array_values(array_unique(array_map(fn($r) => (int) $r['teacher_id'], $teacherRows)));
            $where[] = 't.teacher_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
            foreach ($ids as $id) $params[] = $id;
        }

        // "enrolled" without an explicit status means currently active.
        // An explicit status such as dropped/completed overrides this.
        $enrollmentStatuses = array_values(array_intersect($statuses, ['active', 'dropped', 'completed']));
        if (empty($enrollmentStatuses) && preg_match('/\\b(enrolled|currently enrolled|active enrollments?)\\b/i', $question)) {
            $enrollmentStatuses = ['active'];
        }
        if (!empty($enrollmentStatuses)) {
            $where[] = 'e.status IN (' . implode(',', array_fill(0, count($enrollmentStatuses), '?')) . ')';
            foreach ($enrollmentStatuses as $status) $params[] = $status;
        }
    }

    $limit = max(1, min(150, (int) $limit));
    $joinSql = implode("\n", $joins);
    $whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    if ($needsEnrollment) {
        $sql = "SELECT DISTINCT
                    s.student_id,
                    CONCAT(s.firstname, ' ', s.lastname) AS student_name,
                    sec.grade_level,
                    sec.strand,
                    sec.section_name
                FROM students s
                {$joinSql}
                {$whereSql}
                ORDER BY sec.grade_level ASC, sec.section_name ASC, s.lastname ASC, s.firstname ASC
                LIMIT {$limit}";
    } else {
        $sql = "SELECT
                    s.student_id,
                    CONCAT(s.firstname, ' ', s.lastname) AS student_name
                FROM students s
                ORDER BY s.lastname ASC, s.firstname ASC
                LIMIT {$limit}";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // Count the exact filtered roster separately so the model knows whether
    // the displayed list is complete. The count uses the same joins/filters
    // but does not need the LIMIT.
    $count = count($rows);
    if ($needsEnrollment) {
        $countSql = "SELECT COUNT(DISTINCT s.student_id) AS cnt
                     FROM students s
                     {$joinSql}
                     {$whereSql}";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $countRow = $countStmt->fetch();
        $count = $countRow ? (int) $countRow['cnt'] : count($rows);
    } else {
        $countRow = $pdo->query("SELECT COUNT(*) AS cnt FROM students")->fetch();
        $count = $countRow ? (int) $countRow['cnt'] : count($rows);
    }

    return [
        'rows' => $rows,
        'total' => $count,
        'limited' => $count > $limit,
        'filters' => [
            'grades' => $grades,
            'strands' => $strands,
            'sections' => $sectionTerms,
            'subjects' => array_values(array_map(fn($r) => $r['subject_name'], $subjectRows)),
            'teachers' => array_values(array_map(fn($r) => trim($r['firstname'] . ' ' . $r['lastname']), $teacherRows)),
        ],
    ];
}

function get_chatbot_student_context(PDO $pdo, string $question): string {
    if (!chatbot_is_student_roster_request($question)) {
        return '';
    }

    $result = chatbot_search_student_roster($pdo, $question, 150);
    $rows = $result['rows'];

    $lines = [];
    $lines[] = "\nSTUDENT ROSTER LOOKUP (live database):";
    $lines[] = '- Matching students: ' . $result['total'];

    $filters = [];
    if (!empty($result['filters']['grades'])) $filters[] = 'Grade ' . implode(', Grade ', $result['filters']['grades']);
    if (!empty($result['filters']['strands'])) $filters[] = 'strand ' . implode(', ', $result['filters']['strands']);
    if (!empty($result['filters']['sections'])) $filters[] = 'section ' . implode(', ', $result['filters']['sections']);
    if (!empty($result['filters']['subjects'])) $filters[] = 'subject ' . implode(', ', $result['filters']['subjects']);
    if (!empty($result['filters']['teachers'])) $filters[] = 'teacher ' . implode(', ', $result['filters']['teachers']);
    if (!empty($filters)) $lines[] = '- Applied filters: ' . implode('; ', $filters);

    if (empty($rows)) {
        $lines[] = '- No students matched the requested filters.';
        return implode("\n", $lines);
    }

    $lines[] = $result['limited']
        ? '- The list below is the first ' . count($rows) . ' of ' . $result['total'] . ' matching students. Do not claim this is the complete list.'
        : '- The list below contains all matching students.';
    $lines[] = '- Student names:';

    foreach ($rows as $index => $row) {
        $details = '';
        if (isset($row['grade_level'])) {
            $details = ' · Grade ' . $row['grade_level'];
            if (!empty($row['strand'])) $details .= ' (' . $row['strand'] . ')';
            if (!empty($row['section_name'])) $details .= ' · ' . $row['section_name'];
        }
        $lines[] = ($index + 1) . '. ' . $row['student_name'] . $details;
    }

    return implode("\n", $lines);
}
