<?php
/**
 * Dashboard data layer
 * All database queries for the Admin Dashboard live here.
 * dashboard.php just calls these functions and renders HTML —
 * it should not contain any raw SQL.
 */

/**
 * Total number of students.
 */
function get_total_students(mysqli $conn): int {
    $result = $conn->query("SELECT COUNT(*) AS cnt FROM Students");
    return $result ? (int) $result->fetch_assoc()['cnt'] : 0;
}

function get_total_Class(mysqli $conn): int {
    $result = $conn->query("SELECT COUNT(*) AS cnt FROM classofferings");
    return $result ? (int) $result->fetch_assoc()['cnt'] : 0;
}

/**
 * Total number of teachers.
 */
function get_total_teachers(mysqli $conn): int {
    $result = $conn->query("SELECT COUNT(*) AS cnt FROM teachers");
    return $result ? (int) $result->fetch_assoc()['cnt'] : 0;
}

/**
 * Total number of user accounts (all roles).
 */
function get_total_users_count(mysqli $conn): int {
    $result = $conn->query("SELECT COUNT(*) AS cnt FROM Users");
    return $result ? (int) $result->fetch_assoc()['cnt'] : 0;
}

/**
 * Most recently created users, with display name/email resolved
 * from whichever role table (Students / teachers / Admin) they belong to.
 *
 * @return array<int, array{id:int, username:string, Role:string, status:string, created_at:string, full_name:string, email:?string}>
 */
function get_recent_users(mysqli $conn, int $limit = 4): array {
    $sql = "SELECT u.id, u.username, u.Role, u.status, u.created_at,
                COALESCE(CONCAT(s.firstname, ' ', s.lastname), CONCAT(t.firstname, ' ', t.lastname), u.username) AS full_name,
                COALESCE(s.email, t.email, a.email) AS email
             FROM Users u
             LEFT JOIN Students s ON s.user_id = u.id
             LEFT JOIN teachers t ON t.user_id = u.id
             LEFT JOIN Admin a ON a.user_id = u.id
             ORDER BY u.created_at DESC
             LIMIT ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $result = $stmt->get_result();

    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    $stmt->close();

    return $users;
}

/**
 * Build initials from a full name, e.g. "Ana Reyes" -> "AR"
 */
function get_initials(string $name): string {
    $parts = preg_split('/\s+/', trim($name));
    $initials = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        $initials .= strtoupper(substr($part, 0, 1));
    }
    return $initials ?: '?';
}

/**
 * A small deterministic color palette so avatars aren't all one color.
 */
function get_avatar_color(string $seed): string {
    $colors = ['#8b5cf6', 'var(--info)', 'var(--warning)', 'var(--success)', '#ec4899'];
    $index = crc32($seed) % count($colors);
    return $colors[$index];
}

/**
 * Total number of active class offerings (courses).
 */
function get_active_courses_count(mysqli $conn): int {
    $result = $conn->query("SELECT COUNT(*) AS cnt FROM classofferings WHERE status = 'active'");
    return $result ? (int) $result->fetch_assoc()['cnt'] : 0;
}

/**
 * Total number of class offerings, regardless of status.
 */
function get_total_courses_count(mysqli $conn): int {
    $result = $conn->query("SELECT COUNT(*) AS cnt FROM classofferings");
    return $result ? (int) $result->fetch_assoc()['cnt'] : 0;
}

/**
 * Total number of active (currently enrolled) enrollments.
 */
function get_total_enrollees_count(mysqli $conn): int {
    $result = $conn->query("SELECT COUNT(*) AS cnt FROM enrollments WHERE status = 'active'");
    return $result ? (int) $result->fetch_assoc()['cnt'] : 0;
}

/**
 * Count of enrollment_requests still awaiting a decision.
 */
function get_pending_enrollments_count(mysqli $conn): int {
    $result = $conn->query("SELECT COUNT(*) AS cnt FROM enrollment_requests WHERE status = 'pending'");
    return $result ? (int) $result->fetch_assoc()['cnt'] : 0;
}

/**
 * ================= CHATBOT (OpenRouter-backed assistant) =================
 *
 * Retrieval layer for the floating chat assistant. Builds a compact,
 * privacy-conscious text block describing current school data, scoped to
 * whatever the admin's question seems to be about, that gets embedded in
 * the system prompt sent to the LLM.
 *
 * IMPORTANT: this intentionally never includes sensitive student PII
 * (birthdate, address, guardian contact info, LRN) — only the fields
 * already considered safe to show in the dashboard/search UI (name,
 * email, role, status, course/section/subject names). Keep it that way
 * if you extend this — anything added here gets sent to a third-party
 * LLM API over the network.
 */

/**
 * A minimal English/Filipino-admin-context stopword list used to pull the
 * meaningful keywords out of a free-text question before searching.
 */
function chatbot_stopwords(): array {
    return [
        'a','an','the','is','are','was','were','be','been','being','of','in','on','at','to','for',
        'with','and','or','but','so','if','than','then','that','this','these','those','it','its',
        'as','by','from','into','about','how','many','much','what','who','whom','which','when',
        'where','why','can','could','do','does','did','has','have','had','will','would','should',
        'i','you','we','they','he','she','me','my','our','your','their','there','here',
        'please','show','list','tell','give','find','get','me','all','any','some',
    ];
}

/**
 * Pull up to $max distinct, meaningful keywords out of a question so we
 * can run several targeted searches instead of one long LIKE '%whole
 * sentence%' query that would rarely match anything.
 */
function extract_chat_keywords(string $question, int $max = 4): array {
    $clean = preg_replace('/[^a-zA-Z0-9\s]/', ' ', $question);
    $words = preg_split('/\s+/', strtolower(trim($clean)));
    $stopwords = array_flip(chatbot_stopwords());

    $keywords = [];
    foreach ($words as $word) {
        if ($word === '' || strlen($word) < 3 || isset($stopwords[$word])) {
            continue;
        }
        if (!in_array($word, $keywords, true)) {
            $keywords[] = $word;
        }
        if (count($keywords) >= $max) {
            break;
        }
    }

    return $keywords;
}

/**
 * Build the full text context block handed to the LLM: whole-school
 * stats (always included, they're cheap and give the model orientation)
 * plus search hits for whatever keywords were found in the question.
 */
function get_chatbot_context(mysqli $conn, string $question): string {
    $lines = [];

    $lines[] = "SCHOOL-WIDE STATS:";
    $lines[] = "- Total students: " . get_total_students($conn);
    $lines[] = "- Total teachers: " . get_total_teachers($conn);
    $lines[] = "- Total user accounts: " . get_total_users_count($conn);
    $lines[] = "- Total course offerings: " . get_total_courses_count($conn);
    $lines[] = "- Active course offerings: " . get_active_courses_count($conn);
    $lines[] = "- Currently enrolled (active enrollments): " . get_total_enrollees_count($conn);
    $lines[] = "- Pending enrollment requests: " . get_pending_enrollments_count($conn);

    $keywords = extract_chat_keywords($question);
    $seenUsers = $seenCourses = $seenSubjects = [];
    $userRows = $courseRows = $subjectRows = [];

    foreach ($keywords as $kw) {
        foreach (search_users($conn, $kw, 5) as $row) {
            if (!isset($seenUsers[$row['id']])) {
                $seenUsers[$row['id']] = true;
                $userRows[] = $row;
            }
        }
        foreach (search_courses($conn, $kw, 5) as $row) {
            if (!isset($seenCourses[$row['offering_id']])) {
                $seenCourses[$row['offering_id']] = true;
                $courseRows[] = $row;
            }
        }
        foreach (search_subjects($conn, $kw, 5) as $row) {
            if (!isset($seenSubjects[$row['subject_id']])) {
                $seenSubjects[$row['subject_id']] = true;
                $subjectRows[] = $row;
            }
        }
    }

    if (!empty($userRows)) {
        $lines[] = "\nMATCHING USERS (name · role · status · email):";
        foreach (array_slice($userRows, 0, 8) as $u) {
            $lines[] = "- {$u['full_name']} · {$u['role']} · {$u['status']}" . ($u['email'] ? " · {$u['email']}" : '');
        }
    }

    if (!empty($courseRows)) {
        $lines[] = "\nMATCHING COURSES (subject — section · grade · teacher · quarter · capacity · status):";
        foreach (array_slice($courseRows, 0, 8) as $c) {
            $lines[] = "- {$c['subject_name']} — {$c['section_name']} · Grade {$c['grade_level']} · {$c['teacher_name']} · Q{$c['quarter']} · cap {$c['capacity']} · {$c['status']}";
        }
    }

    if (!empty($subjectRows)) {
        $lines[] = "\nMATCHING SUBJECTS (name · description):";
        foreach (array_slice($subjectRows, 0, 8) as $s) {
            $lines[] = "- {$s['subject_name']}" . ($s['description'] ? " · {$s['description']}" : '');
        }
    }

    if (empty($userRows) && empty($courseRows) && empty($subjectRows)) {
        $lines[] = "\n(No specific student/teacher/course/subject records matched keywords from this question — only school-wide stats above are available.)";
    }

    return implode("\n", $lines);
}

/**
 * ================= GLOBAL SEARCH =================
 *
 * Searches across Users (resolved to Students/teachers/Admin), courses
 * (classofferings resolved to subject/teacher/section), and subjects.
 *
 * @return array{users: array, courses: array, subjects: array}
 */
function global_search(mysqli $conn, string $term, int $limitPerGroup = 6): array {
    $term = trim($term);
    if ($term === '') {
        return ['users' => [], 'courses' => [], 'subjects' => []];
    }

    return [
        'users'    => search_users($conn, $term, $limitPerGroup),
        'courses'  => search_courses($conn, $term, $limitPerGroup),
        'subjects' => search_subjects($conn, $term, $limitPerGroup),
    ];
}

/**
 * Search Users, joined to whichever role table (Students / teachers / Admin)
 * they belong to, matching on username, first/last name, or email.
 */
function search_users(mysqli $conn, string $term, int $limit = 6): array {
    $like = '%' . $term . '%';

    $sql = "SELECT u.id, u.username, u.Role AS role, u.status,
                COALESCE(CONCAT(s.firstname, ' ', s.lastname), CONCAT(t.firstname, ' ', t.lastname), a.position, u.username) AS full_name,
                COALESCE(s.email, t.email, a.email) AS email
             FROM Users u
             LEFT JOIN Students s ON s.user_id = u.id
             LEFT JOIN teachers t ON t.user_id = u.id
             LEFT JOIN Admin a ON a.user_id = u.id
             WHERE u.username LIKE ?
                OR s.firstname LIKE ? OR s.lastname LIKE ?
                OR t.firstname LIKE ? OR t.lastname LIKE ?
                OR s.email LIKE ? OR t.email LIKE ? OR a.email LIKE ?
             ORDER BY full_name ASC
             LIMIT ?";

    $stmt = $conn->prepare($sql);
    $params = [$like, $like, $like, $like, $like, $like, $like, $like, $limit];
    $stmt->bind_param('ssssssssi', ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();

    return $rows;
}

/**
 * Search course offerings (classofferings), matching on subject name,
 * section name, or teacher name.
 */
function search_courses(mysqli $conn, string $term, int $limit = 6): array {
    $like = '%' . $term . '%';

    $sql = "SELECT co.offering_id, co.quarter, co.capacity, co.status,
                sub.subject_id, sub.subject_name,
                sec.section_id, sec.section_name, sec.grade_level,
                t.teacher_id, CONCAT(t.firstname, ' ', t.lastname) AS teacher_name
             FROM classofferings co
             JOIN subjects sub ON sub.subject_id = co.subject_id
             JOIN teachers t ON t.teacher_id = co.teacher_id
             JOIN sections sec ON sec.section_id = co.section_id
             WHERE sub.subject_name LIKE ?
                OR sec.section_name LIKE ?
                OR t.firstname LIKE ? OR t.lastname LIKE ?
             ORDER BY sub.subject_name ASC
             LIMIT ?";

    $stmt = $conn->prepare($sql);
    $params = [$like, $like, $like, $like, $limit];
    $stmt->bind_param('ssssi', ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();

    return $rows;
}

/**
 * Search subjects by name or description.
 */
function search_subjects(mysqli $conn, string $term, int $limit = 6): array {
    $like = '%' . $term . '%';

    $sql = "SELECT subject_id, subject_name, description
             FROM subjects
             WHERE subject_name LIKE ? OR description LIKE ?
             ORDER BY subject_name ASC
             LIMIT ?";

    $stmt = $conn->prepare($sql);
    $params = [$like, $like, $limit];
    $stmt->bind_param('ssi', ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();

    return $rows;
}