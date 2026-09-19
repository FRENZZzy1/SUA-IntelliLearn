<?php
require_once '../../config/config.php';

if (($_SESSION['role'] ?? '') !== 'student') {
    http_response_code(403);
    exit('Access denied.');
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$stmt = $pdo->prepare("SELECT student_id, firstname, lastname FROM students WHERE user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) exit('Student profile not found.');

$studentId = (int)$student['student_id'];

$stmt = $pdo->prepare("
    SELECT
        co.offering_id,
        co.teacher_id,
        co.quarter,
        sub.subject_name,
        sec.section_name,
        sy.label AS school_year,
        t.firstname AS teacher_first,
        t.lastname AS teacher_last,
        te.evaluation_id,
        te.rating_teaching_quality,
        te.rating_communication,
        te.rating_preparation,
        te.rating_fairness,
        te.rating_support,
        te.comments
    FROM enrollments e
    JOIN classofferings co ON co.offering_id = e.offering_id
    JOIN subjects sub ON sub.subject_id = co.subject_id
    JOIN sections sec ON sec.section_id = co.section_id
    JOIN schoolyears sy ON sy.school_year_id = co.school_year_id
    JOIN teachers t ON t.teacher_id = co.teacher_id
    LEFT JOIN teacher_evaluations te
        ON te.student_id = e.student_id AND te.offering_id = e.offering_id
    WHERE e.student_id = ?
      AND e.status = 'active'
      AND co.status = 'active'
    ORDER BY sy.start_date DESC, FIELD(co.quarter, 'TRM 3','TRM 2','TRM 1'), sub.subject_name
");
$stmt->execute([$studentId]);
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Teacher Evaluation · SUA IntelliLearn</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/dashboard.css">
<style>
.page{padding:32px}.intro{margin-bottom:24px}.intro h1{margin:0 0 8px}.intro p{color:#6b7280}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:18px}
.card{background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:22px;box-shadow:0 5px 18px rgba(0,0,0,.04)}
.card h3{margin:0 0 6px}.meta{color:#6b7280;font-size:.9rem;margin-bottom:18px}.done{color:#15803d;font-weight:600}
.question{margin:16px 0}.question strong{display:block;margin-bottom:8px}.stars{display:flex;gap:6px;flex-wrap:wrap}.stars label{cursor:pointer}.stars input{display:none}.stars span{display:inline-flex;width:38px;height:34px;align-items:center;justify-content:center;border:1px solid #d1d5db;border-radius:8px}.stars input:checked+span{background:#111827;color:#fff;border-color:#111827}
textarea{width:100%;min-height:90px;padding:10px;border:1px solid #d1d5db;border-radius:8px;box-sizing:border-box;resize:vertical}
button.submit{width:100%;margin-top:14px;padding:11px;border:0;border-radius:9px;background:#111827;color:#fff;cursor:pointer;font-weight:600}
.notice{padding:16px;background:#f3f4f6;border-radius:12px;color:#4b5563}
</style>
</head>
<body>
<?php include '../../includes/student_sidebar.php'; ?>
<main class="main-content">
<?php include '../../includes/student_header.php'; ?>
<div class="page">
<div class="intro"><h1>Teacher Evaluation</h1><p>Your feedback helps the school review teaching and improve learning experiences. Evaluations are tied to your enrolled class.</p></div>
<?php if (!$classes): ?>
<div class="notice">You do not have any active classes to evaluate.</div>
<?php else: ?>
<div class="grid">
<?php foreach ($classes as $class): ?>
<div class="card">
<h3><?= htmlspecialchars($class['subject_name']) ?></h3>
<div class="meta"><?= htmlspecialchars($class['teacher_first'].' '.$class['teacher_last']) ?> · <?= htmlspecialchars($class['section_name']) ?> · <?= htmlspecialchars($class['quarter']) ?></div>
<?php if ($class['evaluation_id']): ?>
<div class="notice"><span class="done"><i class="fas fa-circle-check"></i> Evaluation submitted</span><br><small>You have already submitted feedback for this class.</small></div>
<?php else: ?>
<form method="post" action="assets/api/submit_teacher_evaluation.php">
<input type="hidden" name="offering_id" value="<?= (int)$class['offering_id'] ?>">
<input type="hidden" name="csrf" value="<?= htmlspecialchars(generateCSRFToken()) ?>">
<?php
$questions = [
 ['rating_teaching_quality','Teaching quality and clarity'],
 ['rating_communication','Communication and explanation'],
 ['rating_preparation','Preparation and organization'],
 ['rating_fairness','Fairness in activities and grading'],
 ['rating_support','Availability and support for students']
];
foreach ($questions as [$name,$label]):
?>
<div class="question"><strong><?= htmlspecialchars($label) ?></strong>
<div class="stars">
<?php for($i=1;$i<=5;$i++): ?>
<label><input type="radio" name="<?= $name ?>" value="<?= $i ?>" required><span><?= $i ?></span></label>
<?php endfor; ?>
</div></div>
<?php endforeach; ?>
<div class="question"><strong>Comments (optional)</strong><textarea name="comments" maxlength="2000" placeholder="Share constructive feedback..."></textarea></div>
<button class="submit" type="submit"><i class="fas fa-paper-plane"></i> Submit Evaluation</button>
</form>
<?php endif; ?>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
</div>
</main>
</body>
</html>
