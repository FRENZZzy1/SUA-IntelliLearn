<?php
require_once __DIR__ . '/../../../../config/config.php';
requireAdmin();
$autoload=__DIR__.'/../../../../vendor/autoload.php';
if (!is_file($autoload)) { http_response_code(500); exit('PhpSpreadsheet is not installed.'); }
require_once $autoload;
$book=new \PhpOffice\PhpSpreadsheet\Spreadsheet(); $sheet=$book->getActiveSheet(); $sheet->setTitle('Students');
$headers=['firstname','lastname','middlename','lrn','email','gender','birthdate','address','guardian_name','guardian_contact','year_level'];
$sheet->fromArray($headers,null,'A1');
$sheet->fromArray(['Maria','Santos','Clara','136090100234','maria@example.com','Female','2010-05-20','','Juan Santos','09123456789','7'],null,'A2');
$sheet->fromArray(['Juan','Dela Cruz','','136090100235','','Male','2010-08-15','','','', '8'],null,'A3');
$sheet->getStyle('A1:K1')->getFont()->setBold(true);
$sheet->freezePane('A2'); $sheet->getAutoFilter()->setRange('A1:K3');
foreach(['A'=>18,'B'=>20,'C'=>18,'D'=>16,'E'=>30,'F'=>12,'G'=>15,'H'=>30,'I'=>25,'J'=>20,'K'=>14] as $col=>$width) $sheet->getColumnDimension($col)->setWidth($width);
$sheet->getComment('K1')->getText()->createTextRun('Required. Use 7, 8, 9, 10, 11, or 12.');
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="SUA_Student_Import_Template.xlsx"');
header('Cache-Control: max-age=0');
(new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save('php://output'); exit;