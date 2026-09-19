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
