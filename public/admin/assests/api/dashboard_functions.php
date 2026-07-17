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