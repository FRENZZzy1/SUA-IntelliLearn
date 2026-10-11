<?php
/**
 * System Usage Statistics for the dashboard card.
 * Built entirely from existing tables (no extra logging table needed).
 *
 * "Active" = a distinct user who did any of the following in the window:
 *   - logged in or out (login/logout rewrite users.current_session_token,
 *     which bumps users.updated_at)
 *   - a student who started a quiz attempt
 *   - a student who handed in an assignment
 * Note: an admin editing an account also bumps updated_at, so counts are a
 * close approximation rather than an exact login log.
 *
 * Windows use the database clock (CURDATE()/NOW()) so they line up with how
 * the timestamps were stored.
 *
 * @return array{active_today:int, active_week:int, quizzes_week:int, ai_week:int}
 */
function get_system_usage_stats(PDO $pdo): array {
    $stats = ['active_today' => 0, 'active_week' => 0, 'quizzes_week' => 0, 'ai_week' => 0];

    $activeUsers = function (string $since) use ($pdo): int {
        $sql = "SELECT COUNT(*) FROM (
                    SELECT id AS uid FROM users
                    WHERE status = 'active' AND updated_at >= $since
                    UNION
                    SELECT st.user_id FROM quiz_attempts qa
                    JOIN students st ON st.student_id = qa.student_id
                    WHERE qa.started_at >= $since
                    UNION
                    SELECT st.user_id FROM submissions sb
                    JOIN students st ON st.student_id = sb.student_id
                    WHERE sb.submitted_at >= $since
                ) active_users";
        return (int) $pdo->query($sql)->fetchColumn();
    };

    $safe = function (callable $fn): int {
        try {
            return (int) $fn();
        } catch (PDOException $e) {
            return 0; // keep the dashboard rendering if a table is missing
        }
    };

    $stats['active_today'] = $safe(fn() => $activeUsers('CURDATE()'));
    $stats['active_week']  = $safe(fn() => $activeUsers('(NOW() - INTERVAL 7 DAY)'));

    // Quizzes actually handed in by students (not just opened).
    $stats['quizzes_week'] = $safe(fn() => $pdo->query(
        "SELECT COUNT(*) FROM quiz_attempts
         WHERE status IN ('submitted','graded')
           AND COALESCE(submitted_at, started_at) >= (NOW() - INTERVAL 7 DAY)"
    )->fetchColumn());

    // AI runs that produced a result: generated quizzes + at-risk analyses.
    $stats['ai_week'] = $safe(fn() => $pdo->query(
        "SELECT
            (SELECT COUNT(*) FROM quiz_generation_jobs
              WHERE status = 'completed'
                AND COALESCE(completed_at, created_at) >= (NOW() - INTERVAL 7 DAY))
          + (SELECT COUNT(*) FROM at_risk_insights
              WHERE generated_at >= (NOW() - INTERVAL 7 DAY))"
    )->fetchColumn());

    return $stats;
}
