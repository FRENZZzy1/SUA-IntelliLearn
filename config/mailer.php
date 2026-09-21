<?php
/**
 * SMTP mail helper for SUA IntelliLearn.
 *
 * SMTP credentials are read from environment variables so they are never
 * committed to the repository:
 *   SUA_MAIL_HOST
 *   SUA_MAIL_PORT
 *   SUA_MAIL_USERNAME
 *   SUA_MAIL_PASSWORD
 *   SUA_MAIL_ENCRYPTION  (tls or ssl)
 *   SUA_MAIL_FROM_EMAIL
 *   SUA_MAIL_FROM_NAME
 *   SUA_APP_URL
 */

use PHPMailer\PHPMailer\PHPMailer;

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!is_file($autoload)) {
    throw new RuntimeException('PHPMailer is not installed. Run "composer install" in the project root.');
}
require_once $autoload;

function sua_mail_config(): array
{
    $config = [
        'host' => getenv('SUA_MAIL_HOST') ?: '',
        'port' => (int)(getenv('SUA_MAIL_PORT') ?: 587),
        'username' => getenv('SUA_MAIL_USERNAME') ?: '',
        'password' => getenv('SUA_MAIL_PASSWORD') ?: '',
        'encryption' => strtolower(getenv('SUA_MAIL_ENCRYPTION') ?: 'tls'),
        'from_email' => getenv('SUA_MAIL_FROM_EMAIL') ?: '',
        'from_name' => getenv('SUA_MAIL_FROM_NAME') ?: 'SUA IntelliLearn',
        'app_url' => rtrim(getenv('SUA_APP_URL') ?: 'http://localhost/SUA-IntelliLearn/public', '/'),
    ];

    // Optional local-only configuration file. Keep this file out of Git.
    $localConfig = __DIR__ . '/mail_config.php';
    if (is_file($localConfig)) {
        $local = require $localConfig;
        if (is_array($local)) {
            $config = array_merge($config, $local);
        }
    }

    foreach (['host', 'username', 'password', 'from_email'] as $required) {
        if ($config[$required] === '') {
            throw new RuntimeException("Missing SMTP configuration: {$required}");
        }
    }

    return $config;
}

function sendTeacherWelcomeEmail(
    string $recipientEmail,
    string $teacherName,
    string $username,
    string $plainPassword
): void {
    $config = sua_mail_config();

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $config['host'];
    $mail->SMTPAuth = true;
    $mail->Username = $config['username'];
    $mail->Password = $config['password'];
    $mail->Port = $config['port'];

    if ($config['encryption'] === 'ssl') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    } else {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    }

    $mail->CharSet = 'UTF-8';
    $mail->setFrom($config['from_email'], $config['from_name']);
    $mail->addAddress($recipientEmail, $teacherName);
    $mail->isHTML(true);
    $mail->Subject = 'Your SUA IntelliLearn Teacher Account';

    $safeName = htmlspecialchars($teacherName, ENT_QUOTES, 'UTF-8');
    $safeUsername = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
    $safePassword = htmlspecialchars($plainPassword, ENT_QUOTES, 'UTF-8');
    $loginUrl = htmlspecialchars($config['app_url'] . '/login.php', ENT_QUOTES, 'UTF-8');

    $mail->Body = <<<HTML
<!doctype html>
<html lang="en">
<head><meta charset="UTF-8"></head>
<body style="font-family:Arial,sans-serif;background:#f5f7f5;padding:24px;color:#24352b;">
    <div style="max-width:620px;margin:auto;background:#fff;border:1px solid #dfe7e1;border-radius:12px;padding:28px;">
        <h2 style="margin-top:0;">Welcome to SUA IntelliLearn</h2>
        <p>Hello {$safeName},</p>
        <p>Your teacher account has been created by the school administrator.</p>

        <div style="background:#f3f7f4;border-radius:8px;padding:16px;margin:20px 0;">
            <p style="margin:0 0 8px;"><strong>Login username:</strong> {$safeUsername}</p>
            <p style="margin:0;"><strong>Temporary password:</strong> {$safePassword}</p>
        </div>

        <p><a href="{$loginUrl}" style="display:inline-block;background:#1f6f54;color:#fff;text-decoration:none;padding:10px 18px;border-radius:7px;">Open IntelliLearn</a></p>
        <p style="font-size:13px;color:#66756d;">For security, please change your password after signing in and do not share these credentials.</p>
        <p>Regards,<br>SUA IntelliLearn Administration</p>
    </div>
</body>
</html>
HTML;

    $mail->AltBody = "Welcome to SUA IntelliLearn, {$teacherName}.\n\n"
        . "Username: {$username}\n"
        . "Temporary password: {$plainPassword}\n\n"
        . "Login: {$config['app_url']}/login.php\n\n"
        . "Please change your password after signing in.";

    $mail->send();
}
