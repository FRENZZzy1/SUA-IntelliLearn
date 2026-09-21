<?php
// Copy this file to config/mail_config.php for local XAMPP testing.
// Do NOT commit mail_config.php. Use an SMTP/app password, not your normal email password.

return [
    'host' => 'smtp.gmail.com',
    'port' => 587,
    'username' => 'your-school-email@example.com',
    'password' => 'your-smtp-or-app-password',
    'encryption' => 'tls',
    'from_email' => 'your-school-email@example.com',
    'from_name' => 'SUA IntelliLearn',
    'app_url' => 'http://localhost/SUA-IntelliLearn/public',
];
