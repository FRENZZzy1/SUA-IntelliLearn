<?php

// ================= STATS (whole school, unaffected by filters) =================
$pendingCount        = (int) $pdo->query("SELECT COUNT(DISTINCT student_id) FROM enrollment_requests WHERE status = 'pending'")->fetchColumn();
$pendingNewThisWeek   = (int) $pdo->query("SELECT COUNT(*) FROM enrollment_requests WHERE status = 'pending' AND submitted_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)")->fetchColumn();

$totalEnrolled        = (int) $pdo->query("SELECT COUNT(DISTINCT student_id) FROM enrollments WHERE status = 'active'")->fetchColumn();
$enrolledNewThisWeek  = (int) $pdo->query("SELECT COUNT(DISTINCT student_id) FROM enrollments WHERE status = 'active' AND enrolled_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)")->fetchColumn();

$approvedThisWeek     = (int) $pdo->query("SELECT COUNT(*) FROM enrollment_requests WHERE status = 'approved' AND decided_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)")->fetchColumn();

$deniedCount          = (int) $pdo->query("SELECT COUNT(*) FROM enrollment_requests WHERE status = 'denied'")->fetchColumn();
$deniedThisWeek       = (int) $pdo->query("SELECT COUNT(*) FROM enrollment_requests WHERE status = 'denied' AND decided_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)")->fetchColumn();


?>