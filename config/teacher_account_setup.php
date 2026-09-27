<?php
/**
 * Teacher account setup helpers.
 *
 * Uses the existing Users.password field for the one-time setup secret.
 * No new database table/column is required.
 */

const TEACHER_SETUP_TTL = 86400;
const TEACHER_SETUP_FROM_EMAIL = 'pallerxdfrenz@gmail.com';

function teacher_env(string $key, string $default = ''): string {
    $value = getenv($key);
    if ($value !== false && $value !== '') return trim($value);
    if (isset($_ENV[$key]) && $_ENV[$key] !== '') return trim((string)$_ENV[$key]);
    if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') return trim((string)$_SERVER[$key]);
    return $default;
}

function teacher_generate_setup_token(): string {
    return time() . '.' . bin2hex(random_bytes(32));
}

function teacher_setup_url(int $userId): string {
    global $teacherSetupToken;
    if (empty($teacherSetupToken)) {
        throw new RuntimeException('Teacher setup token is unavailable.');
    }

    // Email links must be absolute. Build the application URL from the
    // current request so this works on both XAMPP and the production domain.
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? ($_SERVER['PHP_SELF'] ?? '');
    $basePath = preg_replace('#/public/admin/assests/api/[^/]+$#', '', $scriptName);
    $basePath = rtrim($basePath ?: '', '/');

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443);
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? '';

    if ($host === '') {
        throw new RuntimeException('Unable to determine the application host for the teacher setup link.');
    }

    $url = $scheme . '://' . $host . ($basePath ?: '');

    return rtrim($url, '/') . '/public/teacher/setup_account.php?' . http_build_query([
        'uid' => $userId,
        'token' => $teacherSetupToken,
    ]);
}

function teacher_setup_token_is_valid(string $token): bool {
    $parts = explode('.', $token, 2);
    if (count($parts) !== 2 || !ctype_digit($parts[0]) || !preg_match('/^[a-f0-9]{64}$/', $parts[1])) {
        return false;
    }

    $age = time() - (int)$parts[0];
    return $age >= 0 && $age <= TEACHER_SETUP_TTL;
}

/**
 * Load PHPMailer from Composer if installed, or from a local PHPMailer folder.
 * The project does not commit vendor dependencies.
 */
function load_teacher_mailer(): array {
    $autoloadCandidates = [
        __DIR__ . '/../vendor/autoload.php',
        __DIR__ . '/../public/vendor/autoload.php',
    ];

    foreach ($autoloadCandidates as $autoload) {
        if (is_file($autoload)) {
            require_once $autoload;
            if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
                return ['autoloaded' => true];
            }
        }
    }

    $localCandidates = [
        __DIR__ . '/../vendor/phpmailer/phpmailer/src',
        __DIR__ . '/../PHPMailer/src',
        __DIR__ . '/../public/PHPMailer/src',
    ];

    foreach ($localCandidates as $dir) {
        if (is_file($dir . '/PHPMailer.php')) {
            require_once $dir . '/Exception.php';
            require_once $dir . '/PHPMailer.php';
            require_once $dir . '/SMTP.php';
            return ['autoloaded' => true];
        }
    }

    throw new RuntimeException(
        'PHPMailer is not installed. Install phpmailer/phpmailer or place its src folder in the project.'
    );
}

function send_teacher_setup_email(string $to, string $teacherName, string $username, string $setupUrl): void {
    load_teacher_mailer();

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = teacher_env('SUA_SMTP_USERNAME', TEACHER_SETUP_FROM_EMAIL);
    $mail->Password = teacher_env('SUA_SMTP_PASSWORD');
    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    $mail->CharSet = 'UTF-8';

    if ($mail->Password === '') {
        throw new RuntimeException('SMTP password is not configured. Set SUA_SMTP_PASSWORD in the server environment.');
    }

    $mail->setFrom($mail->Username, 'SUA IntelliLearn');
    $mail->addAddress($to, $teacherName);
    $mail->isHTML(true);
    $mail->Subject = 'SUA IntelliLearn Teacher Account Setup';

    $safeName = htmlspecialchars($teacherName, ENT_QUOTES, 'UTF-8');
    $safeUsername = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
    $safeUrl = htmlspecialchars($setupUrl, ENT_QUOTES, 'UTF-8');

    $mail->Body = <<<HTML
<p>Hello {$safeName},</p>
<p>An administrator created your SUA IntelliLearn teacher account.</p>
<p><strong>Username:</strong> {$safeUsername}</p>
<p>Please click the button below to create your password:</p>
<p><a href="{$safeUrl}" style="display:inline-block;padding:12px 18px;background:#124029;color:#fff;text-decoration:none;border-radius:6px;">Set Up My Password</a></p>
<p>This link expires in 24 hours and can only be used once.</p>
<p>If you did not expect this account, please contact your school administrator.</p>
<p>SUA IntelliLearn</p>
HTML;

    $mail->AltBody = "Hello {$teacherName},\n\n"
        . "Your SUA IntelliLearn teacher account was created.\n"
        . "Username: {$username}\n\n"
        . "Set your password here: {$setupUrl}\n\n"
        . "This link expires in 24 hours and can only be used once.";

    $mail->send();
}
