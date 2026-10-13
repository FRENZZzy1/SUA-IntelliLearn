<?php
require_once __DIR__ . '/../../../../config/config.php';
requireAdmin();
$autoload=__DIR__.'/../../../../vendor/autoload.php';
if (!is_file($autoload)) { http_response_code(500); exit('PhpSpreadsheet is not installed.'); }
require_once $autoload;

$book = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
$sheet = $book->getActiveSheet();
$sheet->setTitle('Students');

$headers = ['firstname','lastname','middlename','lrn','email','gender','birthdate','address','guardian_name','guardian_contact','year_level','strand'];
$sheet->fromArray($headers, null, 'A1');
$sheet->fromArray(['Maria','Santos','Clara','136090100234','maria@example.com','Female','2010-05-20','','Juan Santos','09123456789','7',''], null, 'A2');
$sheet->fromArray(['Juan','Dela Cruz','','136090100235','','Male','2010-08-15','','','', '8',''], null, 'A3');
$sheet->getStyle('A1:L1')->getFont()->setBold(true);
$sheet->freezePane('A2');
$sheet->getAutoFilter()->setRange('A1:L3');

foreach (['A'=>18,'B'=>20,'C'=>18,'D'=>16,'E'=>30,'F'=>12,'G'=>15,'H'=>30,'I'=>25,'J'=>20,'K'=>14,'L'=>12] as $col => $width) {
    $sheet->getColumnDimension($col)->setWidth($width);
}

// Add Excel dropdowns so teachers can select valid values instead of typing them.
$dropdowns = [
    'F' => '"Male,Female"',
    'K' => '"7,8,9,10,11,12"',
    'L' => '"GAS,ABM,HUMSS,TVL"',
];
foreach ($dropdowns as $column => $options) {
    for ($row = 2; $row <= 500; $row++) {
        $validation = $sheet->getCell($column . $row)->getDataValidation();
        $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
        $validation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
        $validation->setAllowBlank($column === 'L');
        $validation->setShowInputMessage(true);
        $validation->setShowErrorMessage(true);
        $validation->setShowDropDown(true);
        $validation->setErrorTitle('Invalid selection');
        $validation->setError('Please select a value from the dropdown list.');
        $validation->setPromptTitle('Choose a value');
        $validation->setPrompt('Select an available option from the dropdown.');
        $validation->setFormula1($options);
    }
}

$sheet->getComment('F1')->getText()->createTextRun('Required. Select Male or Female from the dropdown.');
$sheet->getComment('K1')->getText()->createTextRun('Required. Select Grade 7, 8, 9, 10, 11, or 12 from the dropdown.');
$sheet->getComment('L1')->getText()->createTextRun('Optional. Select GAS, ABM, HUMSS, or TVL. Leave blank if not applicable.');
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="SUA_Student_Import_Template.xlsx"');
header('Cache-Control: max-age=0');
(new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save('php://output');
exit;
