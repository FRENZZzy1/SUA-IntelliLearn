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

function get_pending_enrollments(mysqli $conn): int {
    $result = $conn->query("SELECT COUNT(*) AS cnt FROM enrollment_requests WHERE status = 'pending'");
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
 * Pull grade levels (7-12) mentioned in the question, e.g. "grade 10",
 * "g10", "grade-11". Requires a "grade"/"g" cue so we don't misfire on
 * unrelated numbers (capacities, counts, etc).
 */
function extract_grade_levels(string $question): array {
    $grades = [];
    if (preg_match_all('/\bgrade[\s\-]*?(\d{1,2})\b/i', $question, $m)) {
        foreach ($m[1] as $g) {
            $g = (int) $g;
            if ($g >= 7 && $g <= 12) $grades[] = $g;
        }
    }
    if (preg_match_all('/\bg[\s\-]?(\d{1,2})\b/i', $question, $m)) {
        foreach ($m[1] as $g) {
            $g = (int) $g;
            if ($g >= 7 && $g <= 12) $grades[] = $g;
        }
    }
    return array_values(array_unique($grades));
}

/**
 * Detect known enum-style status words anywhere in the question. We
 * return every match found; callers decide which table(s) it applies to
 * since several tables share the same words (e.g. "active").
 */
function extract_status_keywords(string $question): array {
    $known = ['pending', 'approved', 'denied', 'active', 'inactive', 'suspended', 'dropped', 'completed'];
    $found = [];
    foreach ($known as $status) {
        if (preg_match('/\b' . preg_quote($status, '/') . '\b/i', $question)) {
            $found[] = $status;
        }
    }
    return $found;
}

/**
 * Detect SHS strand mentions (STEM, ABM, HUMSS, TVL, GAS, ICT, etc).
 */
function extract_strands(string $question): array {
    $known = ['STEM', 'ABM', 'HUMSS', 'TVL', 'GAS', 'ICT', 'ARTS'];
    $found = [];
    foreach ($known as $strand) {
        if (preg_match('/\b' . preg_quote($strand, '/') . '\b/i', $question)) {
            $found[] = $strand;
        }
    }
    return $found;
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
 * Current active school year label + date range (cheap, gives the model
 * orientation on "this year" / "current quarter" type questions).
 */
function get_current_schoolyear(mysqli $conn): ?array {
    $result = $conn->query("SELECT label, start_date, end_date FROM schoolyears WHERE is_current = 1 LIMIT 1");
    $row = $result ? $result->fetch_assoc() : null;
    return $row ?: null;
}

/**
 * Search sections by name, strand, or grade level. Includes adviser name
 * and a live count of active class offerings tied to the section, so
 * "how many classes does Rizal have" is answerable without a follow-up.
 */
function search_sections(mysqli $conn, string $term, int $limit = 6): array {
    $like = '%' . $term . '%';

    $sql = "SELECT sec.section_id, sec.section_name, sec.grade_level, sec.strand,
                CONCAT(t.firstname, ' ', t.lastname) AS adviser_name,
                sy.label AS school_year,
                (SELECT COUNT(*) FROM classofferings co WHERE co.section_id = sec.section_id AND co.status = 'active') AS active_offerings
             FROM sections sec
             LEFT JOIN teachers t ON t.teacher_id = sec.adviser_id
             LEFT JOIN schoolyears sy ON sy.school_year_id = sec.school_year_id
             WHERE sec.section_name LIKE ? OR sec.strand LIKE ?
             ORDER BY sec.grade_level ASC, sec.section_name ASC
             LIMIT ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssi', $like, $like, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) $rows[] = $row;
    $stmt->close();
    return $rows;
}

/**
 * Sections filtered by grade level (used when the question names a grade
 * but no specific section, e.g. "sections in grade 11").
 */
function search_sections_by_grade(mysqli $conn, array $grades, int $limit = 8): array {
    if (empty($grades)) return [];
    $placeholders = implode(',', array_fill(0, count($grades), '?'));
    $types = str_repeat('i', count($grades));

    $sql = "SELECT sec.section_id, sec.section_name, sec.grade_level, sec.strand,
                CONCAT(t.firstname, ' ', t.lastname) AS adviser_name
             FROM sections sec
             LEFT JOIN teachers t ON t.teacher_id = sec.adviser_id
             WHERE sec.grade_level IN ($placeholders)
             ORDER BY sec.grade_level ASC, sec.section_name ASC
             LIMIT $limit";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$grades);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) $rows[] = $row;
    $stmt->close();
    return $rows;
}

/**
 * Given a list of offering_ids, return active-enrollment counts so
 * "seats remaining" / "is X full" questions can be answered from real
 * numbers instead of the static capacity field alone.
 */
function get_offering_enrollment_counts(mysqli $conn, array $offeringIds): array {
    if (empty($offeringIds)) return [];
    $placeholders = implode(',', array_fill(0, count($offeringIds), '?'));
    $types = str_repeat('i', count($offeringIds));

    $sql = "SELECT offering_id, COUNT(*) AS enrolled_count
             FROM enrollments
             WHERE status = 'active' AND offering_id IN ($placeholders)
             GROUP BY offering_id";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$offeringIds);
    $stmt->execute();
    $result = $stmt->get_result();

    $counts = [];
    while ($row = $result->fetch_assoc()) {
        $counts[(int) $row['offering_id']] = (int) $row['enrolled_count'];
    }
    $stmt->close();
    return $counts;
}

/**
 * What a teacher actually teaches: subject, section, grade, quarter,
 * status — resolved by matching on first/last name.
 */
function get_teacher_workload(mysqli $conn, string $term, int $limit = 10): array {
    $like = '%' . $term . '%';

    $sql = "SELECT co.offering_id, co.quarter, co.status,
                sub.subject_name, sec.section_name, sec.grade_level,
                CONCAT(t.firstname, ' ', t.lastname) AS teacher_name
             FROM teachers t
             JOIN classofferings co ON co.teacher_id = t.teacher_id
             JOIN subjects sub ON sub.subject_id = co.subject_id
             JOIN sections sec ON sec.section_id = co.section_id
             WHERE t.firstname LIKE ? OR t.lastname LIKE ?
             ORDER BY sec.grade_level ASC, sub.subject_name ASC
             LIMIT ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssi', $like, $like, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) $rows[] = $row;
    $stmt->close();
    return $rows;
}

/**
 * A student's current course schedule (subject, section, quarter,
 * status) — resolved by matching on first/last name. Deliberately
 * excludes birthdate/address/guardian/LRN, same privacy rule as the rest
 * of this file.
 */
function get_student_schedule(mysqli $conn, string $term, int $limit = 10): array {
    $like = '%' . $term . '%';

    $sql = "SELECT e.status AS enrollment_status,
                sub.subject_name, sec.section_name, sec.grade_level, co.quarter,
                CONCAT(s.firstname, ' ', s.lastname) AS student_name
             FROM students s
             JOIN enrollments e ON e.student_id = s.student_id
             JOIN classofferings co ON co.offering_id = e.offering_id
             JOIN subjects sub ON sub.subject_id = co.subject_id
             JOIN sections sec ON sec.section_id = co.section_id
             WHERE s.firstname LIKE ? OR s.lastname LIKE ?
             ORDER BY co.quarter ASC, sub.subject_name ASC
             LIMIT ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssi', $like, $like, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) $rows[] = $row;
    $stmt->close();
    return $rows;
}

/**
 * Enrollment requests, filtered by any combination of grade level,
 * strand, and status (all optional). This is what makes "how many
 * pending requests for grade 11 STEM" answerable.
 */
function search_enrollment_requests(mysqli $conn, array $grades = [], array $strands = [], array $statuses = [], int $limit = 10): array {
    $where = [];
    $params = [];
    $types = '';

    if (!empty($grades)) {
        $where[] = 'er.grade_level IN (' . implode(',', array_fill(0, count($grades), '?')) . ')';
        foreach ($grades as $g) { $params[] = $g; $types .= 'i'; }
    }
    if (!empty($strands)) {
        $where[] = 'er.strand IN (' . implode(',', array_fill(0, count($strands), '?')) . ')';
        foreach ($strands as $s) { $params[] = $s; $types .= 's'; }
    }
    // Only requests use pending/approved/denied; ignore other statuses that don't apply here.
    $validRequestStatuses = array_values(array_intersect($statuses, ['pending', 'approved', 'denied']));
    if (!empty($validRequestStatuses)) {
        $where[] = 'er.status IN (' . implode(',', array_fill(0, count($validRequestStatuses), '?')) . ')';
        foreach ($validRequestStatuses as $s) { $params[] = $s; $types .= 's'; }
    }

    if (empty($where)) return [];

    $sql = "SELECT er.request_id, er.grade_level, er.strand, er.status, er.submitted_at,
                CONCAT(s.firstname, ' ', s.lastname) AS student_name,
                sub.subject_name
             FROM enrollment_requests er
             JOIN students s ON s.student_id = er.student_id
             JOIN subjects sub ON sub.subject_id = er.subject_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY er.submitted_at DESC
             LIMIT ?";

    $params[] = $limit;
    $types .= 'i';

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) $rows[] = $row;
    $stmt->close();
    return $rows;
}

/**
 * Learning materials matching a keyword in title, resolved to the
 * subject/section they belong to. Excludes file_path/external_url
 * (internal storage detail, not useful to a chatbot answer).
 */
function search_learning_materials(mysqli $conn, string $term, int $limit = 6): array {
    $like = '%' . $term . '%';

    $sql = "SELECT lm.title, lm.type, sub.subject_name, sec.section_name
             FROM learning_materials lm
             JOIN classofferings co ON co.offering_id = lm.offering_id
             JOIN subjects sub ON sub.subject_id = co.subject_id
             JOIN sections sec ON sec.section_id = co.section_id
             WHERE lm.title LIKE ?
             ORDER BY lm.created_at DESC
             LIMIT ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('si', $like, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) $rows[] = $row;
    $stmt->close();
    return $rows;
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

    $sy = get_current_schoolyear($conn);
    if ($sy) {
        $lines[] = "- Current school year: {$sy['label']} ({$sy['start_date']} to {$sy['end_date']})";
    }

    // ---- Entity extraction from the free-text question ----
    $keywords = extract_chat_keywords($question);
    $grades   = extract_grade_levels($question);
    $strands  = extract_strands($question);
    $statuses = extract_status_keywords($question);

    $foundAnything = false;

    // ---- Keyword-driven lookups across users / courses / subjects / sections ----
    $seenUsers = $seenCourses = $seenSubjects = $seenSections = [];
    $userRows = $courseRows = $subjectRows = $sectionRows = [];

    foreach ($keywords as $kw) {
        foreach (search_users($conn, $kw, 5) as $row) {
            if (!isset($seenUsers[$row['id']])) { $seenUsers[$row['id']] = true; $userRows[] = $row; }
        }
        foreach (search_courses($conn, $kw, 5) as $row) {
            if (!isset($seenCourses[$row['offering_id']])) { $seenCourses[$row['offering_id']] = true; $courseRows[] = $row; }
        }
        foreach (search_subjects($conn, $kw, 5) as $row) {
            if (!isset($seenSubjects[$row['subject_id']])) { $seenSubjects[$row['subject_id']] = true; $subjectRows[] = $row; }
        }
        foreach (search_sections($conn, $kw, 5) as $row) {
            if (!isset($seenSections[$row['section_id']])) { $seenSections[$row['section_id']] = true; $sectionRows[] = $row; }
        }
    }

    if (!empty($userRows)) {
        $foundAnything = true;
        $lines[] = "\nMATCHING USERS (name · role · status · email):";
        foreach (array_slice($userRows, 0, 8) as $u) {
            $lines[] = "- {$u['full_name']} · {$u['role']} · {$u['status']}" . ($u['email'] ? " · {$u['email']}" : '');
        }

        // If a matched user is a student, pull their real schedule; if a
        // teacher, pull their real teaching load. This is what turns a
        // name match into an actually useful answer.
        foreach (array_slice($userRows, 0, 3) as $u) {
            if ($u['role'] === 'student') {
                $schedule = get_student_schedule($conn, $u['full_name'], 6);
                if (!empty($schedule)) {
                    $lines[] = "\n{$u['full_name']}'S CURRENT SCHEDULE (subject · section · grade · quarter · status):";
                    foreach ($schedule as $s) {
                        $lines[] = "- {$s['subject_name']} · {$s['section_name']} · Grade {$s['grade_level']} · Q{$s['quarter']} · {$s['enrollment_status']}";
                    }
                }
            } elseif ($u['role'] === 'teacher') {
                $workload = get_teacher_workload($conn, $u['full_name'], 8);
                if (!empty($workload)) {
                    $lines[] = "\n{$u['full_name']}'S TEACHING LOAD (subject · section · grade · quarter · status):";
                    foreach ($workload as $w) {
                        $lines[] = "- {$w['subject_name']} · {$w['section_name']} · Grade {$w['grade_level']} · Q{$w['quarter']} · {$w['status']}";
                    }
                }
            }
        }
    }

    if (!empty($courseRows)) {
        $foundAnything = true;
        $counts = get_offering_enrollment_counts($conn, array_column($courseRows, 'offering_id'));
        $lines[] = "\nMATCHING COURSES (subject — section · grade · teacher · quarter · enrolled/capacity · status):";
        foreach (array_slice($courseRows, 0, 8) as $c) {
            $enrolled = $counts[$c['offering_id']] ?? 0;
            $lines[] = "- {$c['subject_name']} — {$c['section_name']} · Grade {$c['grade_level']} · {$c['teacher_name']} · Q{$c['quarter']} · {$enrolled}/{$c['capacity']} enrolled · {$c['status']}";
        }
    }

    if (!empty($subjectRows)) {
        $foundAnything = true;
        $lines[] = "\nMATCHING SUBJECTS (name · description):";
        foreach (array_slice($subjectRows, 0, 8) as $s) {
            $lines[] = "- {$s['subject_name']}" . ($s['description'] ? " · {$s['description']}" : '');
        }
    }

    if (!empty($sectionRows)) {
        $foundAnything = true;
        $lines[] = "\nMATCHING SECTIONS (name · grade · strand · adviser · active offerings):";
        foreach (array_slice($sectionRows, 0, 8) as $s) {
            $lines[] = "- {$s['section_name']} · Grade {$s['grade_level']}" . ($s['strand'] ? " ({$s['strand']})" : '') .
                " · adviser: " . ($s['adviser_name'] ?: 'unassigned') . " · {$s['active_offerings']} active offering(s)";
        }
    }

    // ---- Grade-level lookups (e.g. "sections in grade 11") ----
    if (!empty($grades) && empty($sectionRows)) {
        $gradeSections = search_sections_by_grade($conn, $grades, 8);
        if (!empty($gradeSections)) {
            $foundAnything = true;
            $lines[] = "\nSECTIONS IN GRADE(S) " . implode(', ', $grades) . " (name · strand · adviser):";
            foreach ($gradeSections as $s) {
                $lines[] = "- {$s['section_name']}" . ($s['strand'] ? " ({$s['strand']})" : '') . " · adviser: " . ($s['adviser_name'] ?: 'unassigned');
            }
        }
    }

    // ---- Enrollment requests: triggered by grade/strand/status mentions, e.g. "pending grade 11 STEM requests" ----
    $requestRows = search_enrollment_requests($conn, $grades, $strands, $statuses, 10);
    if (!empty($requestRows)) {
        $foundAnything = true;
        $lines[] = "\nMATCHING ENROLLMENT REQUESTS (student · grade · strand · subject · status · submitted):";
        foreach ($requestRows as $r) {
            $lines[] = "- {$r['student_name']} · Grade {$r['grade_level']}" . ($r['strand'] ? " ({$r['strand']})" : '') .
                " · {$r['subject_name']} · {$r['status']} · " . date('M j, Y', strtotime($r['submitted_at']));
        }
    }

    // ---- Learning materials, only when a keyword plausibly names one ----
    $materialRows = [];
    $seenMaterials = [];
    foreach ($keywords as $kw) {
        foreach (search_learning_materials($conn, $kw, 5) as $row) {
            $key = $row['title'] . '|' . $row['section_name'];
            if (!isset($seenMaterials[$key])) { $seenMaterials[$key] = true; $materialRows[] = $row; }
        }
    }
    if (!empty($materialRows)) {
        $foundAnything = true;
        $lines[] = "\nMATCHING LEARNING MATERIALS (title · type · subject · section):";
        foreach (array_slice($materialRows, 0, 8) as $m) {
            $lines[] = "- {$m['title']} · {$m['type']} · {$m['subject_name']} · {$m['section_name']}";
        }
    }

    if (!$foundAnything) {
        $lines[] = "\n(No specific student/teacher/course/subject/section/request records matched this question — only school-wide stats above are available. If the question needs a record lookup, ask the admin to include a name, grade level, section, or subject to search for.)";
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