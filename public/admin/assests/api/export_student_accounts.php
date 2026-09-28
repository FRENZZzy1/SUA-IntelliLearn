<?php
require_once __DIR__ . '/../../../../config/config.php';
requireAdmin();
$autoload=__DIR__.'/../../../../vendor/autoload.php';
if (!is_file($autoload)) { http_response_code(500); exit('PhpSpreadsheet is not installed.'); }
require_once $autoload;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$roleFilter=$_GET['role']??'student';
if ($roleFilter!=='student') $roleFilter='student';
$year=$_GET['year_level']??'all';
$params=[];
$where='';
if ($year!=='all' && in_array($year,['7','8','9','10','11','12'],true)) { $where=' WHERE s.year_level = ?'; $params[]=$year; }

$stmt=$pdo->prepare("SELECT s.firstname,s.middlename,s.lastname,s.year_level,u.username,s.student_lrn,s.email FROM Students s JOIN Users u ON u.id=s.user_id $where ORDER BY CAST(s.year_level AS UNSIGNED), s.lastname, s.firstname");
$stmt->execute($params); $students=$stmt->fetchAll(PDO::FETCH_ASSOC);

$book=new Spreadsheet(); $sheet=$book->getActiveSheet(); $sheet->setTitle('Student Accounts');
$sheet->mergeCells('A1:F1'); $sheet->setCellValue('A1','SAINT URIEL ACADEMY — STUDENT ACCOUNT LIST');
$sheet->mergeCells('A2:F2'); $sheet->setCellValue('A2','Generated '.date('F j, Y g:i A'));
$headers=['No.','Full Name','Username','Year Level','LRN','Email']; $sheet->fromArray($headers,null,'A4');
$r=5; $n=1;
foreach($students as $s){
  $name=trim($s['firstname'].' '.($s['middlename']?:'').' '.$s['lastname']);
  $sheet->fromArray([$n++,$name,$s['username'],'Grade '.$s['year_level'],$s['student_lrn'],$s['email']?:''],null,'A'.$r++);
}
$last=max(4,$r-1);
$sheet->getStyle('A1:F1')->applyFromArray(['font'=>['bold'=>true,'size'=>16],'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER]]);
$sheet->getStyle('A2:F2')->applyFromArray(['font'=>['italic'=>true],'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER]]);
$sheet->getStyle('A4:F4')->applyFromArray(['font'=>['bold'=>true,'color'=>['rgb'=>'FFFFFF']],'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'1A5C3A']],'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER]]);
$sheet->getStyle('A4:F'.$last)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
$sheet->getStyle('A5:A'.$last)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('D5:D'.$last)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
foreach(['A'=>8,'B'=>34,'C'=>24,'D'=>15,'E'=>18,'F'=>32] as $col=>$width) $sheet->getColumnDimension($col)->setWidth($width);
$sheet->freezePane('A5'); $sheet->setAutoFilter('A4:F'.$last);
$sheet->getPageSetup()->setOrientation('landscape');
$sheet->getPageSetup()->setFitToWidth(1); $sheet->getPageSetup()->setFitToHeight(0); $sheet->getPageMargins()->setTop(.5)->setBottom(.5)->setLeft(.35)->setRight(.35);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="SUA_Student_Accounts_'.date('Y-m-d').'.xlsx"');
header('Cache-Control: max-age=0');
$writer=new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book); $writer->save('php://output'); exit;