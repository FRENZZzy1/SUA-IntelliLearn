<?php
/**
 * Dashboard data layer (PDO version)
 * All database queries for the Admin Dashboard live here.
 * dashboard.php just calls these functions and renders HTML —
 * it should not contain any raw SQL.
 *
 * NOTE: every function now takes PDO $pdo instead of mysqli $conn.
 * Callers (dashboard.php, chatbot.php, etc.) need to pass $pdo,
 * which is already created in your config file.
 */

/**
 * Total number of students.
 */
function get_total_students(PDO $pdo): int {
    $row = $pdo->query("SELECT COUNT(*) AS cnt FROM Students")->fetch();
    return $row ? (int) $row['cnt'] : 0;
}

function get_total_Class(PDO $pdo): int {
    $row = $pdo->query("SELECT COUNT(*) AS cnt FROM classofferings")->fetch();
    return $row ? (int) $row['cnt'] : 0;
}

function get_pending_enrollments(PDO $pdo): int {
    $row = $pdo->query("SELECT COUNT(DISTINCT student_id) AS cnt FROM enrollment_requests WHERE status = 'pending'")->fetch();
    return $row ? (int) $row['cnt'] : 0;
}

/**
 * Total number of teachers.
 */
function get_total_teachers(PDO $pdo): int {
    $row = $pdo->query("SELECT COUNT(*) AS cnt FROM teachers")->fetch();
    return $row ? (int) $row['cnt'] : 0;
}

/**
 * Total number of user accounts (all roles).
 */
function get_total_users_count(PDO $pdo): int {
    $row = $pdo->query("SELECT COUNT(*) AS cnt FROM Users")->fetch();
    return $row ? (int) $row['cnt'] : 0;
}

/**
 * Most recently created users, with display name/email resolved
 * from whichever role table (Students / teachers / Admin) they belong to.
 *
 * @return array<int, array{id:int, username:string, Role:string, status:string, created_at:string, full_name:string, email:?string}>
 */
function get_recent_users(PDO $pdo, int $limit = 4): array {
    $sql = "SELECT u.id, u.username, u.Role, u.status, u.created_at,
                COALESCE(CONCAT(s.firstname, ' ', s.lastname), CONCAT(t.firstname, ' ', t.lastname), u.username) AS full_name,
                COALESCE(s.email, t.email, a.email) AS email
             FROM Users u
             LEFT JOIN Students s ON s.user_id = u.id
             LEFT JOIN teachers t ON t.user_id = u.id
             LEFT JOIN Admin a ON a.user_id = u.id
             ORDER BY u.created_at DESC
             LIMIT ?";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
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
function get_active_courses_count(PDO $pdo): int {
    $row = $pdo->query("SELECT COUNT(*) AS cnt FROM classofferings WHERE status = 'active'")->fetch();
    return $row ? (int) $row['cnt'] : 0;
}

/**
 * Total number of class offerings, regardless of status.
 */
function get_total_courses_count(PDO $pdo): int {
    $row = $pdo->query("SELECT COUNT(*) AS cnt FROM classofferings")->fetch();
    return $row ? (int) $row['cnt'] : 0;
}

/**
 * Total number of active (currently enrolled) enrollments.
 */
function get_total_enrollees_count(PDO $pdo): int {
    $row = $pdo->query("SELECT COUNT(*) AS cnt FROM enrollments WHERE status = 'active'")->fetch();
    return $row ? (int) $row['cnt'] : 0;
}

/**
 * Count of enrollment_requests still awaiting a decision.
 */
function get_pending_enrollments_count(PDO $pdo): int {
    $row = $pdo->query("SELECT COUNT(*) AS cnt FROM enrollment_requests WHERE status = 'pending'")->fetch();
    return $row ? (int) $row['cnt'] : 0;
}

/**
 * Pending enrollment requests for the dashboard "Pending Enrollments" widget,
 * grouped by student + matched section (same collapsing rule used on the
 * full Enrollment page) so a student who checked several subjects in one
 * submission shows as a single row with a "View Classes" trigger instead
 * of one row per subject.
 *
 * @return array{groups: array, total_groups: int}
 */
function get_pending_enrollment_groups(PDO $pdo, int $limit = 5): array {
    $sql = "SELECT
                er.request_id,
                er.student_id,
                er.grade_level,
                er.subject_id,
                er.strand,
                er.offering_id,
                er.submitted_at,
                st.firstname,
                st.lastname,
                subj.subject_name,
                sec2.section_id AS matched_section_id
            FROM enrollment_requests er
            JOIN students st    ON st.student_id = er.student_id
            JOIN subjects subj  ON subj.subject_id = er.subject_id
            LEFT JOIN classofferings co2 ON co2.offering_id = er.offering_id
            LEFT JOIN sections sec2      ON sec2.section_id  = co2.section_id
            WHERE er.status = 'pending'
            ORDER BY er.submitted_at DESC";

    $rows = $pdo->query($sql)->fetchAll();

    $groups = [];
    foreach ($rows as $r) {
        $sectionKey = $r['matched_section_id'] ? ('sec' . $r['matched_section_id']) : ('req' . $r['request_id']);
        $groupKey = $r['student_id'] . '_' . $sectionKey;

        if (!isset($groups[$groupKey])) {
            $groups[$groupKey] = [
                'student_name' => trim($r['firstname'] . ' ' . $r['lastname']),
                'grade_level'  => (int) $r['grade_level'],
                'strand'       => $r['strand'],
                'submitted_at' => $r['submitted_at'],
                'subjects'     => [],
                'request_ids'  => [],
            ];
        }

        $groups[$groupKey]['subjects'][]    = $r['strand'] ? $r['subject_name'] . ' (' . $r['strand'] . ')' : $r['subject_name'];
        $groups[$groupKey]['request_ids'][] = (int) $r['request_id'];

        if (strtotime($r['submitted_at']) < strtotime($groups[$groupKey]['submitted_at'])) {
            $groups[$groupKey]['submitted_at'] = $r['submitted_at'];
        }
    }

    $groups = array_values($groups);
    usort($groups, fn($a, $b) => strtotime($b['submitted_at']) <=> strtotime($a['submitted_at']));

    return [
        'groups'       => array_slice($groups, 0, $limit),
        'total_groups' => count($groups),
    ];
}

/**
 * Enrollment fill rate per subject+grade-level, aggregated across every
 * active class offering for that combination (a subject can have several
 * sections/offerings at the same grade level). Powers the dashboard's
 * "Course Enrollment Progress" bars.
 *
 * Capacity/enrolled counts are aggregated in a derived table (one row per
 * offering) before summing, so offerings with several active enrollments
 * don't fan-out and inflate the summed capacity.
 *
 * @return array<int, array{label:string, enrolled:int, capacity:int, percent:int, color:string}>
 */
function get_course_enrollment_progress(PDO $pdo, int $limit = 6): array {
    $sql = "SELECT subject_id, subject_name, grade_level,
                SUM(capacity) AS capacity,
                SUM(enrolled_count) AS enrolled
            FROM (
                SELECT co.offering_id, subj.subject_id, subj.subject_name, sec.grade_level, co.capacity,
                    (SELECT COUNT(*) FROM enrollments e WHERE e.offering_id = co.offering_id AND e.status = 'active') AS enrolled_count
                FROM classofferings co
                JOIN subjects subj ON subj.subject_id = co.subject_id
                JOIN sections sec  ON sec.section_id  = co.section_id
                WHERE co.status = 'active'
            ) x
            GROUP BY subject_id, subject_name, grade_level
            ORDER BY grade_level ASC, subject_name ASC
            LIMIT ?";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();

    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $capacity = (int) $row['capacity'];
        $enrolled = (int) $row['enrolled'];
        $percent  = $capacity > 0 ? (int) min(100, round(($enrolled / $capacity) * 100)) : 0;

        $rows[] = [
            'label'    => trim($row['subject_name'] . ' ' . $row['grade_level']),
            'enrolled' => $enrolled,
            'capacity' => $capacity,
            'percent'  => $percent,
            // Low-fill classes get flagged red regardless of subject; otherwise
            // reuse the same deterministic palette as the user-avatar colors
            // so bars stay visually varied without needing a fixed color map.
            'color'    => $percent < 75 ? 'var(--danger)' : get_avatar_color($row['subject_name'] . $row['grade_level']),
        ];
    }

    return $rows;
}

/**
 * Most recently created active class offerings, resolved to their
 * subject/section/teacher, for the dashboard's "Courses & Subjects" widget.
 * offering_id is included so the row's "View Students" action can call the
 * same read-only roster endpoint the full Courses & Subjects page uses.
 *
 * @return array<int, array{offering_id:int, subject_name:string, grade_level:int, strand:?string, teacher_name:?string, status:string}>
 */
function get_recent_course_offerings(PDO $pdo, int $limit = 4): array {
    $sql = "SELECT co.offering_id, co.status,
                co.subject_id, co.section_id, co.teacher_id,
                co.quarter, co.school_year_id, co.capacity,
                co.schedule_days, co.start_time, co.end_time,
                subj.subject_name,
                sec.grade_level, sec.strand,
                t.teacher_id AS t_id, t.firstname AS teacher_firstname, t.lastname AS teacher_lastname
            FROM classofferings co
            JOIN subjects subj ON subj.subject_id = co.subject_id
            JOIN sections sec  ON sec.section_id  = co.section_id
            LEFT JOIN teachers t ON t.teacher_id = co.teacher_id
            ORDER BY co.created_at DESC
            LIMIT ?";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();

    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $rows[] = [
            'offering_id'    => (int) $row['offering_id'],
            'subject_name'   => $row['subject_name'],
            'grade_level'    => (int) $row['grade_level'],
            'strand'         => $row['strand'],
            'teacher_name'   => $row['t_id'] ? trim($row['teacher_firstname'] . ' ' . $row['teacher_lastname']) : null,
            'status'         => $row['status'],
            // Raw fields for the Update Course modal (dashboard's own copy).
            'subject_id'     => (int) $row['subject_id'],
            'section_id'     => (int) $row['section_id'],
            'teacher_id'     => $row['teacher_id'] ? (int) $row['teacher_id'] : '',
            'quarter'        => $row['quarter'],
            'school_year_id' => (int) $row['school_year_id'],
            'capacity'       => (int) $row['capacity'],
            'schedule_days'  => $row['schedule_days'],
            'start_time'     => $row['start_time'],
            'end_time'       => $row['end_time'],
        ];
    }

    return $rows;
}

function get_admin_profile(PDO $pdo, int $userId): ?array {
    $sql = "SELECT u.id AS user_id, u.username, u.role, u.status, u.created_at,
                   a.admin_id, a.email, a.access_level, a.position
            FROM users u
            JOIN admin a ON a.user_id = u.id
            WHERE u.id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}
 
/**
 * Update an admin's editable profile fields (email, position).
 *
 * access_level and username are intentionally NOT editable here.
 * access_level is a permission field — letting an admin grant themselves
 * a higher access level would be a privilege-escalation bug, so that
 * should only ever change through a separate, higher-privileged
 * user-management flow (or directly in the DB).
 */
function update_admin_profile(PDO $pdo, int $adminId, string $email, string $position): array {
    $errors = [];
 
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
 
    $validPositions = ['principal', 'registrar', 'staff'];
    if (!in_array($position, $validPositions, true)) {
        $errors[] = 'Invalid position.';
    }
 
    if (!empty($errors)) {
        return ['success' => false, 'errors' => $errors];
    }
 
    // Make sure the email isn't already used by a different admin.
    $check = $pdo->prepare("SELECT admin_id FROM admin WHERE email = ? AND admin_id != ?");
    $check->execute([$email, $adminId]);
    if ($check->fetch()) {
        return ['success' => false, 'errors' => ['That email is already in use by another admin account.']];
    }
 
    $stmt = $pdo->prepare("UPDATE admin SET email = ?, position = ? WHERE admin_id = ?");
    $stmt->execute([$email, $position, $adminId]);
 
    return ['success' => true];
}
 
/**
 * Verify a submitted password against the stored value.
 *
 * Legacy support: some accounts in `users` still have plaintext passwords
 * from before hashing was added. If the stored value doesn't look like a
 * bcrypt/argon hash, fall back to a plain (constant-time) comparison.
 * change_user_password() re-hashes and saves on a successful match, so
 * accounts upgrade automatically the next time someone changes their
 * password.
 *
 * NOTE: login.php also needs to accept hashed passwords (password_verify)
 * for this to work end-to-end — otherwise anyone who changes their
 * password here won't be able to log back in. See the note in
 * profile_handler.php.
 */
function verify_user_password(string $submitted, string $stored): bool {
    $looksHashed = (bool) preg_match('/^\$(2y|argon2i|argon2id)\$/', $stored);
    if ($looksHashed) {
        return password_verify($submitted, $stored);
    }
    return hash_equals($stored, $submitted);
}
 
/**
 * Change a user's password. Verifies the current password first (accepting
 * legacy plaintext, see verify_user_password()), then stores the new
 * password hashed with password_hash().
 */
function change_user_password(PDO $pdo, int $userId, string $currentPassword, string $newPassword, string $confirmPassword): array {
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
 
    if (!$row) {
        return ['success' => false, 'errors' => ['User not found.']];
    }
 
    if (!verify_user_password($currentPassword, $row['password'])) {
        return ['success' => false, 'errors' => ['Current password is incorrect.']];
    }
 
    $errors = [];
    if (strlen($newPassword) < 8) {
        $errors[] = 'New password must be at least 8 characters.';
    }
    if ($newPassword !== $confirmPassword) {
        $errors[] = 'New password and confirmation do not match.';
    }
    if ($newPassword === $currentPassword) {
        $errors[] = 'New password must be different from your current password.';
    }
 
    if (!empty($errors)) {
        return ['success' => false, 'errors' => $errors];
    }
 
    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    $update = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
    $update->execute([$hash, $userId]);
 
    return ['success' => true];
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
function get_current_schoolyear(PDO $pdo): ?array {
    $row = $pdo->query("SELECT label, start_date, end_date FROM schoolyears WHERE is_current = 1 LIMIT 1")->fetch();
    return $row ?: null;
}

/**
 * Search sections by name, strand, or grade level. Includes adviser name
 * and a live count of active class offerings tied to the section, so
 * "how many classes does Rizal have" is answerable without a follow-up.
 */
function search_sections(PDO $pdo, string $term, int $limit = 6): array {
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

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(1, $like);
    $stmt->bindValue(2, $like);
    $stmt->bindValue(3, $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

/**
 * Sections filtered by grade level (used when the question names a grade
 * but no specific section, e.g. "sections in grade 11").
 */
function search_sections_by_grade(PDO $pdo, array $grades, int $limit = 8): array {
    if (empty($grades)) return [];
    $placeholders = implode(',', array_fill(0, count($grades), '?'));

    // $limit is interpolated directly (not bindable via a mixed IN(...) +
    // trailing LIMIT ? param list without extra bookkeeping); it's an int
    // cast above the call site is not guaranteed, so force it here too.
    $limit = (int) $limit;

    $sql = "SELECT sec.section_id, sec.section_name, sec.grade_level, sec.strand,
                CONCAT(t.firstname, ' ', t.lastname) AS adviser_name
             FROM sections sec
             LEFT JOIN teachers t ON t.teacher_id = sec.adviser_id
             WHERE sec.grade_level IN ($placeholders)
             ORDER BY sec.grade_level ASC, sec.section_name ASC
             LIMIT $limit";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($grades);

    return $stmt->fetchAll();
}

/**
 * Given a list of offering_ids, return active-enrollment counts so
 * "seats remaining" / "is X full" questions can be answered from real
 * numbers instead of the static capacity field alone.
 */
function get_offering_enrollment_counts(PDO $pdo, array $offeringIds): array {
    if (empty($offeringIds)) return [];
    $placeholders = implode(',', array_fill(0, count($offeringIds), '?'));

    $sql = "SELECT offering_id, COUNT(*) AS enrolled_count
             FROM enrollments
             WHERE status = 'active' AND offering_id IN ($placeholders)
             GROUP BY offering_id";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($offeringIds);

    $counts = [];
    foreach ($stmt->fetchAll() as $row) {
        $counts[(int) $row['offering_id']] = (int) $row['enrolled_count'];
    }
    return $counts;
}

/**
 * What a teacher actually teaches: subject, section, grade, quarter,
 * status — resolved by matching on first/last name.
 */
function get_teacher_workload(PDO $pdo, string $term, int $limit = 10): array {
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

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(1, $like);
    $stmt->bindValue(2, $like);
    $stmt->bindValue(3, $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

/**
 * A student's current course schedule (subject, section, quarter,
 * status) — resolved by matching on first/last name. Deliberately
 * excludes birthdate/address/guardian/LRN, same privacy rule as the rest
 * of this file.
 */
function get_student_schedule(PDO $pdo, string $term, int $limit = 10): array {
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

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(1, $like);
    $stmt->bindValue(2, $like);
    $stmt->bindValue(3, $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

/**
 * Enrollment requests, filtered by any combination of grade level,
 * strand, and status (all optional). This is what makes "how many
 * pending requests for grade 11 STEM" answerable.
 */
function search_enrollment_requests(PDO $pdo, array $grades = [], array $strands = [], array $statuses = [], int $limit = 10): array {
    $where = [];
    $params = [];

    if (!empty($grades)) {
        $where[] = 'er.grade_level IN (' . implode(',', array_fill(0, count($grades), '?')) . ')';
        foreach ($grades as $g) { $params[] = $g; }
    }
    if (!empty($strands)) {
        $where[] = 'er.strand IN (' . implode(',', array_fill(0, count($strands), '?')) . ')';
        foreach ($strands as $s) { $params[] = $s; }
    }
    // Only requests use pending/approved/denied; ignore other statuses that don't apply here.
    $validRequestStatuses = array_values(array_intersect($statuses, ['pending', 'approved', 'denied']));
    if (!empty($validRequestStatuses)) {
        $where[] = 'er.status IN (' . implode(',', array_fill(0, count($validRequestStatuses), '?')) . ')';
        foreach ($validRequestStatuses as $s) { $params[] = $s; }
    }

    if (empty($where)) return [];

    // $limit is bound separately below via bindValue(..., PDO::PARAM_INT)
    // rather than mixed into $params, since the earlier IN(...) params
    // don't have a fixed type string to track in PDO (unlike mysqli).
    $sql = "SELECT er.request_id, er.grade_level, er.strand, er.status, er.submitted_at,
                CONCAT(s.firstname, ' ', s.lastname) AS student_name,
                sub.subject_name
             FROM enrollment_requests er
             JOIN students s ON s.student_id = er.student_id
             JOIN subjects sub ON sub.subject_id = er.subject_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY er.submitted_at DESC
             LIMIT ?";

    $stmt = $pdo->prepare($sql);
    $i = 1;
    foreach ($params as $p) {
        $stmt->bindValue($i++, $p);
    }
    $stmt->bindValue($i, $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

/**
 * Learning materials matching a keyword in title, resolved to the
 * subject/section they belong to. Excludes file_path/external_url
 * (internal storage detail, not useful to a chatbot answer).
 */
function search_learning_materials(PDO $pdo, string $term, int $limit = 6): array {
    $like = '%' . $term . '%';

    $sql = "SELECT lm.title, lm.type, sub.subject_name, sec.section_name
             FROM learning_materials lm
             JOIN classofferings co ON co.offering_id = lm.offering_id
             JOIN subjects sub ON sub.subject_id = co.subject_id
             JOIN sections sec ON sec.section_id = co.section_id
             WHERE lm.title LIKE ?
             ORDER BY lm.created_at DESC
             LIMIT ?";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(1, $like);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

/**
 * Build the full text context block handed to the LLM: whole-school
 * stats (always included, they're cheap and give the model orientation)
 * plus search hits for whatever keywords were found in the question.
 */
function get_chatbot_context(PDO $pdo, string $question): string {
    $lines = [];

    $lines[] = "SCHOOL-WIDE STATS:";
    $lines[] = "- Total students: " . get_total_students($pdo);
    $lines[] = "- Total teachers: " . get_total_teachers($pdo);
    $lines[] = "- Total user accounts: " . get_total_users_count($pdo);
    $lines[] = "- Total course offerings: " . get_total_courses_count($pdo);
    $lines[] = "- Active course offerings: " . get_active_courses_count($pdo);
    $lines[] = "- Currently enrolled (active enrollments): " . get_total_enrollees_count($pdo);
    $lines[] = "- Pending enrollment requests: " . get_pending_enrollments_count($pdo);

    $sy = get_current_schoolyear($pdo);
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
        foreach (search_users($pdo, $kw, 5) as $row) {
            if (!isset($seenUsers[$row['id']])) { $seenUsers[$row['id']] = true; $userRows[] = $row; }
        }
        foreach (search_courses($pdo, $kw, 5) as $row) {
            if (!isset($seenCourses[$row['offering_id']])) { $seenCourses[$row['offering_id']] = true; $courseRows[] = $row; }
        }
        foreach (search_subjects($pdo, $kw, 5) as $row) {
            if (!isset($seenSubjects[$row['subject_id']])) { $seenSubjects[$row['subject_id']] = true; $subjectRows[] = $row; }
        }
        foreach (search_sections($pdo, $kw, 5) as $row) {
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
                $schedule = get_student_schedule($pdo, $u['full_name'], 6);
                if (!empty($schedule)) {
                    $lines[] = "\n{$u['full_name']}'S CURRENT SCHEDULE (subject · section · grade · quarter · status):";
                    foreach ($schedule as $s) {
                        $lines[] = "- {$s['subject_name']} · {$s['section_name']} · Grade {$s['grade_level']} · Q{$s['quarter']} · {$s['enrollment_status']}";
                    }
                }
            } elseif ($u['role'] === 'teacher') {
                $workload = get_teacher_workload($pdo, $u['full_name'], 8);
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
        $counts = get_offering_enrollment_counts($pdo, array_column($courseRows, 'offering_id'));
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
        $gradeSections = search_sections_by_grade($pdo, $grades, 8);
        if (!empty($gradeSections)) {
            $foundAnything = true;
            $lines[] = "\nSECTIONS IN GRADE(S) " . implode(', ', $grades) . " (name · strand · adviser):";
            foreach ($gradeSections as $s) {
                $lines[] = "- {$s['section_name']}" . ($s['strand'] ? " ({$s['strand']})" : '') . " · adviser: " . ($s['adviser_name'] ?: 'unassigned');
            }
        }
    }

    // ---- Enrollment requests: triggered by grade/strand/status mentions, e.g. "pending grade 11 STEM requests" ----
    $requestRows = search_enrollment_requests($pdo, $grades, $strands, $statuses, 10);
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
        foreach (search_learning_materials($pdo, $kw, 5) as $row) {
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
function global_search(PDO $pdo, string $term, int $limitPerGroup = 6): array {
    $term = trim($term);
    if ($term === '') {
        return ['users' => [], 'courses' => [], 'subjects' => []];
    }

    return [
        'users'    => search_users($pdo, $term, $limitPerGroup),
        'courses'  => search_courses($pdo, $term, $limitPerGroup),
        'subjects' => search_subjects($pdo, $term, $limitPerGroup),
    ];
}

/**
 * Search Users, joined to whichever role table (Students / teachers / Admin)
 * they belong to, matching on username, first/last name, or email.
 */
function search_users(PDO $pdo, string $term, int $limit = 6): array {
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

    $stmt = $pdo->prepare($sql);
    for ($i = 1; $i <= 8; $i++) {
        $stmt->bindValue($i, $like);
    }
    $stmt->bindValue(9, $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

/**
 * Search course offerings (classofferings), matching on subject name,
 * section name, or teacher name.
 */
function search_courses(PDO $pdo, string $term, int $limit = 6): array {
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

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(1, $like);
    $stmt->bindValue(2, $like);
    $stmt->bindValue(3, $like);
    $stmt->bindValue(4, $like);
    $stmt->bindValue(5, $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

/**
 * Search subjects by name or description.
 */
function search_subjects(PDO $pdo, string $term, int $limit = 6): array {
    $like = '%' . $term . '%';

    $sql = "SELECT subject_id, subject_name, description
             FROM subjects
             WHERE subject_name LIKE ? OR description LIKE ?
             ORDER BY subject_name ASC
             LIMIT ?";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(1, $like);
    $stmt->bindValue(2, $like);
    $stmt->bindValue(3, $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}