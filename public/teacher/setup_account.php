<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/teacher_account_setup.php';

$uid = filter_input(INPUT_GET, 'uid', FILTER_VALIDATE_INT);
$token = $_GET['token'] ?? '';
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uid = filter_input(INPUT_POST, 'uid', FILTER_VALIDATE_INT);
    $token = $_POST['token'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!$uid || !teacher_setup_token_is_valid($token)) {
        $error = 'This account setup link is invalid or has expired.';
    } elseif (!password_policy_is_valid($password)) {
        $error = implode(' ', validate_password_policy($password));
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $stmt = $pdo->prepare("SELECT id, username, role, status, password FROM Users WHERE id = ? LIMIT 1");
        $stmt->execute([$uid]);
        $user = $stmt->fetch();

        if (!$user || $user['role'] !== 'teacher' || $user['status'] !== 'active') {
            $error = 'This teacher account is not available for setup.';
        } elseif (strpos($user['password'], '$2') !== 0 || !password_verify('SETUP|' . $token, $user['password'])) {
            $error = 'This setup link is invalid or has already been used.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $update = $pdo->prepare("UPDATE Users SET password = ?, updated_at = NOW(), current_session_token = NULL WHERE id = ? AND role = 'teacher'");
            $update->execute([$hash, $uid]);
            $success = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Set Up Teacher Account | SUA IntelliLearn</title>
<style>
body{font-family:Arial,sans-serif;background:#f4f7f5;margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;color:#1f2937}
.card{width:min(460px,calc(100% - 32px));background:#fff;border-radius:16px;padding:32px;box-shadow:0 12px 35px rgba(0,0,0,.1)}
h1{margin:0 0 8px;color:#124029;font-size:1.6rem}.sub{color:#6b7280;margin-bottom:24px}
label{display:block;font-weight:600;margin:16px 0 7px}input{width:100%;box-sizing:border-box;padding:12px;border:1px solid #d1d5db;border-radius:9px;font-size:1rem}
button{width:100%;margin-top:22px;padding:13px;border:0;border-radius:9px;background:#124029;color:#fff;font-weight:700;font-size:1rem;cursor:pointer}
.alert{padding:12px;border-radius:9px;margin-bottom:16px;background:#fef2f2;color:#991b1b}.ok{background:#ecfdf5;color:#166534}
.hint{font-size:.85rem;color:#6b7280;margin-top:8px;line-height:1.45}
</style>
</head>
<body>
<div class="card">
<h1>Set Up Your Teacher Account</h1>
<p class="sub">Create your personal password for SUA IntelliLearn.</p>
<?php if ($error): ?><div class="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?>
<div class="alert ok">Your password has been set successfully. You can now sign in using your teacher email and new password.</div>
<a href="../login.php" style="display:block;text-align:center;margin-top:18px;color:#124029;font-weight:700;">Go to Login</a>
<?php else: ?>
<form method="post">
<input type="hidden" name="uid" value="<?= htmlspecialchars((string)$uid) ?>">
<input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
<label for="password">New Password</label>
<input id="password" name="password" type="password" minlength="8" required>
<div class="hint">Minimum 8 characters, including at least one uppercase letter and one special character.</div>
<label for="confirm_password">Confirm Password</label>
<input id="confirm_password" name="confirm_password" type="password" minlength="8" required>
<button type="submit">Set Password</button>
</form>
<?php endif; ?>
</div>
</body>
</html>
