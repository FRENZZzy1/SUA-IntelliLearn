<?php
require_once __DIR__ . '/../../../../config/config.php';
requireAdmin();
header('Content-Type: application/json');

function batch_json(bool $success, array $data = []): void {
    echo json_encode(array_merge(['success' => $success], $data));
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') batch_json(false, ['message' => 'Invalid request method.']);
if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) batch_json(false, ['message' => 'Invalid security token.']);
if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) batch_json(false, ['message' => 'Please select a valid Excel file.']);

$autoload = __DIR__ . '/../../../../vendor/autoload.php';
if (!is_file($autoload)) batch_json(false, ['message' => 'PhpSpreadsheet is not installed. Run composer install on the server/local environment.']);
require_once $autoload;

try {
    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($_FILES['excel_file']['tmp_name']);
    $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
    if (count($rows) < 2) batch_json(false, ['message' => 'The Excel file has no student rows.']);

    $header = array_map(fn($v) => strtolower(trim((string)$v)), $rows[1]);
    $map = [];
    foreach ($header as $col => $name) $map[$name] = $col;

    $required = ['firstname','lastname','lrn','birthdate','gender','year_level'];
    foreach ($required as $field) {
        if (!isset($map[$field])) batch_json(false, ['message' => "Missing required column: $field"]);
    }

    $validLevels = ['7','8','9','10','11','12'];
    $created = 0; $skipped = 0; $errors = [];

    $pdo->beginTransaction();
    $checkLrn = $pdo->prepare("SELECT student_id FROM Students WHERE student_lrn = ? LIMIT 1");
    $checkUsername = $pdo->prepare("SELECT id FROM Users WHERE username = ? LIMIT 1");
    $insertUser = $pdo->prepare("INSERT INTO Users (username,password,role,status,created_at,updated_at) VALUES (?,?, 'student', ?, NOW(), NOW())");
    $insertStudent = $pdo->prepare("INSERT INTO Students (user_id,student_lrn,firstname,lastname,middlename,email,gender,birthdate,address,guardian_name,guardian_contact,year_level,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())");

    foreach (array_slice($rows, 1) as $index => $row) {
        $excelRow = $index + 2;
        $get = fn($name) => trim((string)($row[$map[$name]] ?? ''));
        $firstname=$get('firstname'); $lastname=$get('lastname'); $lrn=$get('lrn');
        $middlename=$get('middlename'); $email=$get('email'); $gender=$get('gender');
        $birthdate=$get('birthdate'); $address=$get('address'); $guardian=$get('guardian_name');
        $guardianContact=$get('guardian_contact'); $year=$get('year_level');

        if ($firstname==='' && $lastname==='' && $lrn==='') continue;
        $rowErrors=[];
        if ($firstname==='') $rowErrors[]='first name is required';
        if ($lastname==='') $rowErrors[]='last name is required';
        if (!preg_match('/^\d{12}$/', preg_replace('/\D/','',$lrn))) $rowErrors[]='LRN must be 12 digits';
        else $lrn=preg_replace('/\D/','',$lrn);
        if (!in_array($gender,['Male','Female'],true)) $rowErrors[]='gender must be Male or Female';
        if (!in_array($year,$validLevels,true)) $rowErrors[]='year_level must be 7, 8, 9, 10, 11, or 12';
        $dateObj = DateTime::createFromFormat('Y-m-d',$birthdate);
        if (!$dateObj) {
            $dateObj = DateTime::createFromFormat('m/d/Y',$birthdate);
            if (!$dateObj) $rowErrors[]='birthdate must be YYYY-MM-DD or MM/DD/YYYY';
        }
        if ($email!=='' && !filter_var($email,FILTER_VALIDATE_EMAIL)) $rowErrors[]='invalid email';
        if ($guardianContact!=='') {
            try { $guardianContact=validatePhilippineMobileNumber($guardianContact); }
            catch (InvalidArgumentException $e) { $rowErrors[]=$e->getMessage(); }
        }
        if ($rowErrors) { $errors[]="Row $excelRow: ".implode(', ',$rowErrors); $skipped++; continue; }

        $checkLrn->execute([$lrn]);
        if ($checkLrn->fetch()) { $errors[]="Row $excelRow: LRN already exists ($lrn)"; $skipped++; continue; }

        do { $username=generateStudentUsername($pdo); $checkUsername->execute([$username]); } while ($checkUsername->fetch());

        $passwordPlain=ucfirst(strtolower($lastname)).$dateObj->format('mdy').'!';
        $insertUser->execute([$username,password_hash($passwordPlain,PASSWORD_DEFAULT),'active']);
        $uid=(int)$pdo->lastInsertId();
        $insertStudent->execute([$uid,$lrn,$firstname,$lastname,$middlename?:null,$email?:null,$gender,$dateObj->format('Y-m-d'),$address?:null,$guardian?:null,$guardianContact?:null,$year]);
        $created++;
    }
    $pdo->commit();
    batch_json(true,['message'=>"Import finished. $created student account(s) created; $skipped row(s) skipped.",'created'=>$created,'skipped'=>$skipped,'errors'=>$errors]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    batch_json(false,['message'=>'Import failed: '.$e->getMessage()]);
}