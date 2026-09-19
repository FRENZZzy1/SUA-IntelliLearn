<?php
require_once '../../config/config.php';
requireAdmin();

$stmt = $pdo->query("
    SELECT
        t.teacher_id,
        CONCAT(t.firstname, ' ', t.lastname) AS teacher_name,
        COUNT(te.evaluation_id) AS response_count,
        ROUND(AVG(te.rating_teaching_quality),2) AS teaching_quality,
        ROUND(AVG(te.rating_communication),2) AS communication,
        ROUND(AVG(te.rating_preparation),2) AS preparation,
        ROUND(AVG(te.rating_fairness),2) AS fairness,
        ROUND(AVG(te.rating_support),2) AS support,
        ROUND(AVG((te.rating_teaching_quality + te.rating_communication + te.rating_preparation + te.rating_fairness + te.rating_support)/5),2) AS overall_rating
    FROM teachers t
    LEFT JOIN teacher_evaluations te ON te.teacher_id = t.teacher_id
    GROUP BY t.teacher_id, t.firstname, t.lastname
    ORDER BY teacher_name
");
$teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

$selectedTeacher = (int)($_GET['teacher_id'] ?? 0);
$comments = [];
if ($selectedTeacher > 0) {
    $stmt = $pdo->prepare("
        SELECT te.comments, te.created_at, sub.subject_name, co.quarter, sec.section_name
        FROM teacher_evaluations te
        JOIN classofferings co ON co.offering_id = te.offering_id
        JOIN subjects sub ON sub.subject_id = co.subject_id
        JOIN sections sec ON sec.section_id = co.section_id
        WHERE te.teacher_id = ? AND te.comments IS NOT NULL AND te.comments <> ''
        ORDER BY te.created_at DESC
    ");
    $stmt->execute([$selectedTeacher]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Teacher Evaluations · SUA IntelliLearn</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="assests/css/dashboard.css">
<style>
.content{padding:32px}.panel{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:20px;margin-bottom:20px;overflow:auto}table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:12px;border-bottom:1px solid #eee}th{font-size:.85rem}.rating{font-weight:700}.muted{color:#6b7280}.comment{padding:12px;background:#f9fafb;border-radius:10px;margin:10px 0}
</style></head><body>
<?php include '../../includes/admin_sidebar.php'; ?>
<div class="main-content"><?php include '../../includes/admin_header.php'; ?>
<div class="content">
<h1>Teacher Evaluations</h1><p class="muted">Aggregated student feedback. Individual student identities are not displayed.</p>
<div class="panel"><table><thead><tr><th>Teacher</th><th>Responses</th><th>Teaching</th><th>Communication</th><th>Preparation</th><th>Fairness</th><th>Support</th><th>Overall</th><th></th></tr></thead><tbody>
<?php foreach($teachers as $teacher): ?><tr>
<td><?= htmlspecialchars($teacher['teacher_name']) ?></td><td><?= (int)$teacher['response_count'] ?></td>
<td><?= $teacher['teaching_quality'] !== null ? htmlspecialchars($teacher['teaching_quality']) : '—' ?></td>
<td><?= $teacher['communication'] !== null ? htmlspecialchars($teacher['communication']) : '—' ?></td>
<td><?= $teacher['preparation'] !== null ? htmlspecialchars($teacher['preparation']) : '—' ?></td>
<td><?= $teacher['fairness'] !== null ? htmlspecialchars($teacher['fairness']) : '—' ?></td>
<td><?= $teacher['support'] !== null ? htmlspecialchars($teacher['support']) : '—' ?></td>
<td class="rating"><?= $teacher['overall_rating'] !== null ? htmlspecialchars($teacher['overall_rating']) : '—' ?></td>
<td><a href="?teacher_id=<?= (int)$teacher['teacher_id'] ?>">Comments</a></td>
</tr><?php endforeach; ?>
</tbody></table></div>
<?php if($selectedTeacher && $comments): ?><div class="panel"><h2>Anonymous Comments</h2>
<?php foreach($comments as $comment): ?><div class="comment"><strong><?= htmlspecialchars($comment['subject_name']) ?> · <?= htmlspecialchars($comment['quarter']) ?></strong><br><?= nl2br(htmlspecialchars($comment['comments'])) ?><div class="muted"><?= htmlspecialchars($comment['created_at']) ?></div></div><?php endforeach; ?>
</div><?php elseif($selectedTeacher): ?><div class="panel">No written comments for this teacher.</div><?php endif; ?>
</div></div></body></html>
