<?php
require_once '../../config/config.php'; requireAdminModule('settings','write'); header('Content-Type: text/html; charset=utf-8');
if(!validateCSRFToken($_POST['csrf']??'')){http_response_code(419);exit('Session expired.');}
$action=$_POST['action']??'';
try{
 if($action==='toggle'){ $v=(($_POST['enabled']??'0')==='1')?'1':'0'; $s=$pdo->prepare("INSERT INTO system_settings(setting_key,setting_value,updated_by) VALUES('teacher_evaluation_enabled',?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_by=VALUES(updated_by)");$s->execute([$v,$_SESSION['user_id']??null]);}
 elseif($action==='create'){
  $sy=(int)$_POST['school_year_id'];$title=trim($_POST['title']??'');$desc=trim($_POST['description']??'');$start=str_replace('T',' ',trim($_POST['start_date']??''));$end=str_replace('T',' ',trim($_POST['end_date']??''));
  if(!$title||!$start||!$end||strtotime($end)<=strtotime($start)) throw new Exception('Enter a valid title and date range.');
  $pdo->beginTransaction();$s=$pdo->prepare("INSERT INTO teacher_evaluations(school_year_id,title,description,start_date,end_date,anonymous,created_by) VALUES(?,?,?,?,?,?,?)");$s->execute([$sy,$title,$desc,$start,$end,isset($_POST['anonymous'])?1:0,$_SESSION['user_id']??null]);$id=(int)$pdo->lastInsertId();
  $questions=[['The teacher explains lessons clearly.','Teaching Effectiveness'],['The teacher demonstrates knowledge of the subject.','Subject Knowledge'],['The teacher communicates instructions and expectations clearly.','Communication'],['The teacher keeps students engaged in learning activities.','Student Engagement'],['The teacher provides fair and useful feedback.','Assessment & Feedback']];
  $q=$pdo->prepare("INSERT INTO evaluation_questions(evaluation_id,question_text,category,question_type,sort_order,is_required) VALUES(?,?,?,?,?,1)");$i=1;foreach($questions as $x)$q->execute([$id,$x[0],$x[1],'rating',$i++]);
  $q=$pdo->prepare("INSERT INTO evaluation_questions(evaluation_id,question_text,category,question_type,sort_order,is_required) VALUES(?,?,?,?,?,0)");$q->execute([$id,'What can the teacher improve?','General','text',99]);$pdo->commit();
 } elseif(in_array($action,['open','close'],true)){ $id=(int)$_POST['evaluation_id'];$status=$action==='open'?'open':'closed'; if($status==='open')$pdo->exec("UPDATE teacher_evaluations SET status='closed' WHERE status='open'");$s=$pdo->prepare("UPDATE teacher_evaluations SET status=? WHERE evaluation_id=?");$s->execute([$status,$id]);}
 else throw new Exception('Invalid action.');
 header('Location: teacher_evaluation.php');exit;
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();http_response_code(422);echo htmlspecialchars($e->getMessage());}