<?php
/**
 * Shared password policy for SUA IntelliLearn.
 *
 * Requirements:
 * - Minimum 8 characters
 * - At least one uppercase letter
 * - At least one special character
 */
function validate_password_policy(string $password): array {
    $errors = [];

    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain at least one uppercase letter.';
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = 'Password must contain at least one special character.';
    }

    return $errors;
}

function password_policy_is_valid(string $password): bool {
    return empty(validate_password_policy($password));
}

function password_policy_hint(): string {
    return 'Minimum 8 characters, including at least one uppercase letter and one special character.';
}

/**
 * Verify a submitted password against the stored value.
 *
 * Legacy support: some accounts in `users` still have plaintext passwords
 * from before hashing was added. If the stored value doesn't look like a
 * bcrypt/argon hash, fall back to a plain (constant-time) comparison.
 * change_user_password() re-hashes and saves on a successful match, so
 * accounts upgrade automatically the next time someone changes their
 * password.
 *
 * NOTE: login.php also needs to accept hashed passwords (password_verify)
 * for this to work end-to-end — otherwise anyone who changes their
 * password here won't be able to log back in. See the note in
 * profile_handler.php.
 */
function verify_user_password(string $submitted, string $stored): bool {
    $looksHashed = (bool) preg_match('/^\$(2y|argon2i|argon2id)\$/', $stored);
    if ($looksHashed) {
        return password_verify($submitted, $stored);
    }
    return hash_equals($stored, $submitted);
}

/**
 * Change a user's password. Verifies the current password first (accepting
 * legacy plaintext, see verify_user_password()), then stores the new
 * password hashed with password_hash().
 */
function change_user_password(PDO $pdo, int $userId, string $currentPassword, string $newPassword, string $confirmPassword): array {
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $row = $stmt->fetch();

    if (!$row) {
        return ['success' => false, 'errors' => ['User not found.']];
    }

    if (!verify_user_password($currentPassword, $row['password'])) {
        return ['success' => false, 'errors' => ['Current password is incorrect.']];
    }

    $errors = [];
    $errors = array_merge($errors, array_map(
        fn($error) => 'New ' . strtolower($error),
        validate_password_policy($newPassword)
    ));
    if ($newPassword !== $confirmPassword) {
        $errors[] = 'New password and confirmation do not match.';
    }
    if ($newPassword === $currentPassword) {
        $errors[] = 'New password must be different from your current password.';
    }

    if (!empty($errors)) {
        return ['success' => false, 'errors' => $errors];
    }

    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    $update = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
    $update->execute([$hash, $userId]);

    return ['success' => true];
}
