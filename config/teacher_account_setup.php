<?php
/**
 * Stateless teacher account setup using the existing Users.password column.
 * No new database table or column is required.
 *
 * A setup token is random, stored only as a password hash prefixed with
 * "SETUP|", and carries its creation timestamp for a 24-hour expiry.
 */
const TEACHER_SETUP_TTL = 86400;
const TEACHER_SETUP_FROM_EMAIL = 'pallerxdfrenz@gmail.com';

function teacher_generate_setup_token(): string {
    return time() . '.' . bin2hex(random_bytes(32));
}

function teacher_setup_url(int $userId, string $email): string {
    global $teacherSetupToken;
    if (empty($teacherSetupToken)) {
        throw new RuntimeException('Teacher setup token is unavailable.');
    }

    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $basePath = preg_replace('#/public/admin/assests/api/[^/]+$#', '', $scriptName);
    $basePath = rtrim($basePath ?: '', '/');
    $base = $basePath . '/public/teacher/setup_account.php';
    $query = http_build_query([
        'uid' => $userId,
        'token' => $teacherSetupToken,
    ]);

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . $base . '?' . $query;
}

function teacher_setup_token_is_valid(string $token): bool {
    $parts = explode('.', $token, 2);
    if (count($parts) !== 2 || !ctype_digit($parts[0]) || !preg_match('/^[a-f0-9]{64}$/', $parts[1])) {
        return false;
    }

    $createdAt = (int)$parts[0];
    return $createdAt > 0 && (time() - $createdAt) >= 0 && (time() - $createdAt) <= TEACHER_SETUP_TTL;
}

function teacher_setup_mail(string $to, string $teacherName, string $username, string $setupUrl): bool {
    $subject = 'SUA IntelliLearn Teacher Account Setup';
    $body = "Hello {$teacherName},

"
          . "An administrator created your SUA IntelliLearn teacher account.

"
          . "Username: {$username}

"
          . "Set your password using this secure link:
{$setupUrl}

"
          . "This link expires in 24 hours and can only be used once.

"
          . "If you did not expect this account, please contact your school administrator.

"
          . "SUA IntelliLearn";

    $headers = "From: SUA IntelliLearn <" . TEACHER_SETUP_FROM_EMAIL . ">
"
             . "Reply-To: " . TEACHER_SETUP_FROM_EMAIL . "
"
             . "Content-Type: text/plain; charset=UTF-8
";

    return mail($to, $subject, $body, $headers);
}
