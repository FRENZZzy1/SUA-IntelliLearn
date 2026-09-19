<?php
require_once '../../config/config.php';
if (($_SESSION['role'] ?? '') !== 'student') { http_response_code(403); exit('Access denied.'); }

$userId=(int)($_SESSION['user_id']??0);
$s=$pdo->prepare("SELECT student_id FROM students WHERE user_id=? LIMIT 1"); $s->execute([$userId]);
$studentId=(int)$s->fetchColumn(); if(!$studentId) exit('Student profile not found.');

$evaluationId=(int)($_GET['evaluation_id']??0);
$offeringId=(int)($_GET['offering_id']??0);
$eStmt=$pdo->prepare("SELECT * FROM teacher_evaluations WHERE status='open' AND NOW() BETWEEN start_date AND end_date AND (?=0 OR evaluation_id=?) ORDER BY evaluation_id DESC LIMIT 1");
$eStmt->execute([$evaluationId,$evaluationId]); $evaluation=$eStmt->fetch(PDO::FETCH_ASSOC);
if(!$evaluation){$evaluation=null;}

$classes=[];
if($evaluation){
  $q=$pdo->prepare("SELECT co.offering_id,sub.subject_name,sec.section_name,t.firstname teacher_first,t.lastname teacher_last,
      EXISTS(SELECT 1 FROM evaluation_responses er WHERE er.evaluation_id=? AND er.student_id=? AND er.offering_id=co.offering_id) completed
      FROM enrollments en JOIN classofferings co ON co.offering_id=en.offering_id
      JOIN subjects sub ON sub.subject_id=co.subject_id JOIN sections sec ON sec.section_id=co.section_id
      JOIN teachers t ON t.teacher_id=co.teacher_id
      WHERE en.student_id=? AND en.status='active' AND co.status='active' AND co.school_year_id=?
      ORDER BY sub.subject_name");
  $q->execute([(int)$evaluation['evaluation_id'],$studentId,$studentId,(int)$evaluation['school_year_id']]); $classes=$q->fetchAll(PDO::FETCH_ASSOC);
}
$selected=null;
if($offeringId){foreach($classes as $x){if((int)$x['offering_id']===$offeringId){$selected=$x;break;}}}
$questions=[];
if($evaluation && $selected){
  $q=$pdo->prepare("SELECT * FROM evaluation_questions WHERE evaluation_id=? ORDER BY sort_order,question_id");$q->execute([(int)$evaluation['evaluation_id']]);$questions=$q->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Teacher Evaluation · SUA IntelliLearn</title><link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet"><link rel="stylesheet" href="assets/css/dashboard.css">
<style>.page{padding:28px}.card{background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:22px;margin-bottom:18px}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px}.meta,.muted{color:#6b7280}.card h2,.card h3{margin-top:0}.q{padding:16px 0;border-bottom:1px solid #eee}.scale{display:flex;gap:7px;flex-wrap:wrap;margin-top:10px}.scale label{cursor:pointer}.scale input{display:none}.scale span{display:inline-flex;width:40px;height:36px;align-items:center;justify-content:center;border:1px solid #d1d5db;border-radius:8px}.scale input:checked+span{background:#111827;color:#fff}.btn{display:inline-block;padding:10px 14px;border:0;border-radius:8px;background:#111827;color:#fff;text-decoration:none;cursor:pointer}.notice{padding:14px;background:#f3f4f6;border-radius:10px}.done{color:#15803d;font-weight:600}textarea{width:100%;min-height:100px;padding:10px;border:1px solid #d1d5db;border-radius:8px;box-sizing:border-box}</style>
</head><body><?php include '../../includes/student_sidebar.php';?><main class="main-content"><?php include '../../includes/student_header.php';?><div class="page">
<h1>Teacher Evaluation</h1>
<?php if(!$evaluation):?><div class="card"><h2>No active evaluation</h2><p class="muted">There is currently no teacher evaluation open for students.</p></div>
<?php elseif(!$selected):?>
<div class="card"><h2><?=htmlspecialchars($evaluation['title'])?></h2><p><?=nl2br(htmlspecialchars($evaluation['description']??''))?></p><p class="muted">Open until <?=htmlspecialchars(date('M j, Y g:i A',strtotime($evaluation['end_date'])))?>.</p></div>
<div class="grid"><?php foreach($classes as $x):?><div class="card"><h3><?=htmlspecialchars($x['subject_name'])?></h3><div class="meta"><?=htmlspecialchars($x['teacher_first'].' '.$x['teacher_last'])?> · <?=htmlspecialchars($x['section_name'])?></div><?php if($x['completed']):?><p class="done"><i class="fas fa-circle-check"></i> Completed</p><?php else:?><a class="btn" href="teacher_evaluation.php?evaluation_id=<?=(int)$evaluation['evaluation_id']?>&offering_id=<?=(int)$x['offering_id']?>">Evaluate</a><?php endif;?></div><?php endforeach;?></div>
<?php else:?>
<div class="card"><a href="teacher_evaluation.php?evaluation_id=<?=(int)$evaluation['evaluation_id']?>" class="muted">← Back to evaluations</a><h2><?=htmlspecialchars($selected['subject_name'])?></h2><p class="meta">Teacher: <?=htmlspecialchars($selected['teacher_first'].' '.$selected['teacher_last'])?> · <?=htmlspecialchars($selected['section_name'])?></p><p><?=nl2br(htmlspecialchars($evaluation['description']??''))?></p></div>
<?php if($selected['completed']):?><div class="card"><div class="done"><i class="fas fa-circle-check"></i> Evaluation already submitted.</div></div>
<?php else:?><form class="card" method="post" action="assets/api/submit_teacher_evaluation.php"><input type="hidden" name="csrf" value="<?=htmlspecialchars(generateCSRFToken())?>"><input type="hidden" name="evaluation_id" value="<?=(int)$evaluation['evaluation_id']?>"><input type="hidden" name="offering_id" value="<?=(int)$selected['offering_id']?>">
<?php foreach($questions as $q):?><div class="q"><strong><?=htmlspecialchars($q['question_text'])?><?php if($q['is_required']):?> *<?php endif;?></strong><?php if($q['question_type']==='rating'):?><div class="scale"><?php for($i=1;$i<=5;$i++):?><label><input type="radio" name="q_<?=(int)$q['question_id']?>" value="<?=$i?>" <?=$q['is_required']?'required':''?>><span><?=$i?></span></label><?php endfor;?></div><small class="muted">1 = Strongly Disagree · 5 = Strongly Agree</small><?php else:?><textarea name="q_<?=(int)$q['question_id']?>" maxlength="2000" placeholder="Share constructive feedback..."></textarea><?php endif;?></div><?php endforeach;?><button class="btn" type="submit"><i class="fas fa-paper-plane"></i> Submit Evaluation</button></form><?php endif;?>
<?php endif;?>
</div></main></body></html>