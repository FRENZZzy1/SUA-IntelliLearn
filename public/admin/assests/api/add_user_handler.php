<?php
/* =====================================================================
   ADD USER — SHARED AJAX HANDLER
   ---------------------------------------------------------------------
   Called via fetch() by add_user_modal.php's submit handler. Point every
   module's copy of the modal at THIS one file (set $aum_endpoint before
   including add_user_modal.php) so the create-user logic only lives in
   one place.

   Adjust the require_once path below to wherever config.php actually is
   relative to this file's final location.
===================================================================== */
require_once __DIR__ . '/../../../../config/config.php'; // <-- adjust path as needed
requireAdmin();

header('Content-Type: application/json');

function aum_json($success, $payload = []) {
    echo json_encode(array_merge(['success' => $success], $payload));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    aum_json(false, ['message' => 'Invalid request method.']);
}

if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
    aum_json(false, ['message' => 'Invalid security token. Please refresh the page and try again.']);
}

$role   = $_POST['role'] ?? 'student';
$status = $_POST['status'] ?? 'active';

// ============================================================
// STUDENT CREATION — separate flow (auto username/password)
// ============================================================
if ($role === 'student') {
    $firstname        = trim($_POST['firstname'] ?? '');
    $lastname         = trim($_POST['lastname'] ?? '');
    $middlename       = trim($_POST['middlename'] ?? '');
    $lrn              = trim($_POST['lrn'] ?? '');
    $email            = trim($_POST['email'] ?? '');
    $birthdate        = trim($_POST['birthdate'] ?? '');
    $address          = trim($_POST['address'] ?? '');
    $guardian_name    = trim($_POST['guardian_name'] ?? '');
    $guardian_contact = trim($_POST['guardian_contact'] ?? '');

    $errors = [];
    if (empty($firstname)) $errors[] = "First name is required.";
    if (empty($lastname)) $errors[] = "Last name is required.";
    if (empty($lrn) || !preg_match('/^\d{12}$/', $lrn)) $errors[] = "A valid 12-digit LRN is required.";
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Email address is invalid.";
    $bday_obj = DateTime::createFromFormat('Y-m-d', $birthdate);
    if (empty($birthdate) || !$bday_obj) $errors[] = "A valid birthdate is required.";
    if (!in_array($status, ['active', 'inactive', 'suspended'])) $errors[] = "Invalid status selected.";

    if (!empty($errors)) {
        aum_json(false, ['errors' => $errors]);
    }

    try {
        $pdo->beginTransaction();

        $check = $pdo->prepare("SELECT student_id FROM Students WHERE student_lrn = ?");
        $check->execute([$lrn]);
        if ($check->fetch()) {
            throw new Exception("A student with this LRN already exists.");
        }

        $last4 = substr($lrn, -4);
        $bday_code = $bday_obj->format('mdy');

        $username_base = "STU-{$last4}-{$bday_code}";
        $username = $username_base;
        $counter = 1;
        while (true) {
            $check = $pdo->prepare("SELECT id FROM Users WHERE username = ?");
            $check->execute([$username]);
            if (!$check->fetch()) break;
            $username = $username_base . '-' . $counter;
            $counter++;
        }

        $password_plain = $lastname . $bday_code;
        $password_hash = password_hash($password_plain, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("
            INSERT INTO Users (username, password, role, status, created_at, updated_at)
            VALUES (?, ?, 'student', ?, NOW(), NOW())
        ");
        $stmt->execute([$username, $password_hash, $status]);
        $user_id = $pdo->lastInsertId();

        $stmt = $pdo->prepare("
            INSERT INTO Students
                (user_id, student_lrn, firstname, lastname, middlename, email, birthdate, address, guardian_name, guardian_contact, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([
            $user_id, $lrn, $firstname, $lastname,
            $middlename !== '' ? $middlename : null,
            $email !== '' ? $email : null,
            $birthdate, $address, $guardian_name, $guardian_contact,
        ]);

        $pdo->commit();

        aum_json(true, [
            'message' => "Student '$firstname $lastname' created. Username: $username | Password: $password_plain (please record and share this securely)",
            'user' => [
                'id' => $user_id, 'role' => 'student', 'status' => $status,
                'name' => "$firstname $lastname", 'username' => $username,
            ],
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        aum_json(false, ['message' => "Database error: " . $e->getMessage()]);
    }
}

// ============================================================
// TEACHER / ADMIN CREATION
// ============================================================
$firstname         = trim($_POST['firstname'] ?? '');
$middlename        = trim($_POST['middlename'] ?? '');
$lastname          = trim($_POST['lastname'] ?? '');
$email             = trim($_POST['email'] ?? '');
$department        = trim($_POST['department'] ?? '');
$contact           = trim($_POST['contact'] ?? '');
$specialization    = trim($_POST['specialization'] ?? '');
$employment_status = trim($_POST['employment_status'] ?? '');
$position          = trim($_POST['position'] ?? '');
$access_level      = trim($_POST['access_level'] ?? '');
$password          = $_POST['password'] ?? '';
$send_email        = isset($_POST['send_email']) ? 1 : 0;

$fullname = $role === 'admin'
    ? $email
    : preg_replace('/\s+/', ' ', trim("$firstname $middlename $lastname"));

$errors = [];
if ($role !== 'admin') {
    if (empty($firstname)) $errors[] = "First name is required.";
    if (empty($lastname)) $errors[] = "Last name is required.";
}
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required.";
if (empty($password) || strlen($password) < 6) $errors[] = "Password must be at least 6 characters.";
if (!in_array($role, ['admin', 'teacher'])) $errors[] = "Invalid role selected.";
if (!in_array($status, ['active', 'inactive', 'suspended'])) $errors[] = "Invalid status selected.";
if ($role === 'teacher' && $employment_status !== '' && !in_array($employment_status, ['full-time', 'part-time'])) {
    $errors[] = "Invalid employment status selected.";
}
if ($role === 'admin') {
    if (!in_array($position, ['principal', 'registrar', 'staff'])) $errors[] = "Invalid position selected.";
    if (!in_array($access_level, ['full', 'limited', 'read_only'])) $errors[] = "Invalid access level selected.";
}

if (empty($errors)) {
    $table = $role === 'teacher' ? 'Teachers' : 'Admin';
    $check = $pdo->prepare("SELECT 1 FROM $table WHERE email = ?");
    $check->execute([$email]);
    if ($check->fetch()) {
        $errors[] = "A " . $role . " with this email already exists.";
    }
}

if (empty($errors)) {
    $check = $pdo->prepare("SELECT id FROM Users WHERE username = ?");
    $check->execute([$email]);
    if ($check->fetch()) {
        $errors[] = "This email is already in use as a login username.";
    }
}

if (!empty($errors)) {
    aum_json(false, ['errors' => $errors]);
}

try {
    $pdo->beginTransaction();

    $username = $email;
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("
        INSERT INTO Users (username, password, role, status, created_at, updated_at)
        VALUES (?, ?, ?, ?, NOW(), NOW())
    ");
    $stmt->execute([$username, $password_hash, $role, $status]);
    $user_id = $pdo->lastInsertId();

    if ($role === 'teacher') {
        $stmt = $pdo->prepare("
            INSERT INTO Teachers (user_id, firstname, lastname, middlename, email, employment_status, department, specialization, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([
            $user_id, $firstname, $lastname,
            $middlename !== '' ? $middlename : null,
            $email,
            $employment_status !== '' ? $employment_status : null,
            $department !== '' ? $department : null,
            $specialization !== '' ? $specialization : null,
        ]);
    } elseif ($role === 'admin') {
        $stmt = $pdo->prepare("
            INSERT INTO Admin (user_id, email, access_level, position, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$user_id, $email, $access_level, $position]);
    }

    $pdo->commit();

    // TODO: if ($send_email) { trigger your welcome-email routine here }

    aum_json(true, [
        'message' => "User '$fullname' created successfully with username: $username",
        'user' => [
            'id' => $user_id, 'role' => $role, 'status' => $status,
            'name' => $fullname, 'username' => $username,
        ],
    ]);
} catch (PDOException $e) {
    $pdo->rollBack();
    aum_json(false, ['message' => "Database error: " . $e->getMessage()]);
}