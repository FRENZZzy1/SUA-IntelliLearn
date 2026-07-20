<?php
/**
 * Drop this into the teacher and student dashboard pages to pull the
 * announcements that should be visible to the logged-in user.
 *
 * Assumes:
 *   - session_start() already called
 *   - $_SESSION['role'] is 'teacher' or 'student'
 *   - $pdo is an existing PDO connection (see db_connect.php)
 */

$role = $_SESSION['role'];
$audienceValue = $role . 's';

$stmt = $pdo->prepare("
    SELECT a.*, u.username AS poster_username,
           t.firstname AS t_first, t.lastname AS t_last
    FROM announcements a
    JOIN users u ON u.id = a.posted_by
    LEFT JOIN teachers t ON t.user_id = u.id
    WHERE a.status = 'published'
      AND (a.audience = 'all' OR a.audience = :audience)
    ORDER BY a.is_pinned DESC, a.created_at DESC
");
$stmt->execute([':audience' => $audienceValue]);
$visibleAnnouncements = $stmt->fetchAll(PDO::FETCH_ASSOC);