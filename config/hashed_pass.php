<?php
require_once 'config.php'; // Change this to your database connection file

// Get all users
$sql = "SELECT id, password FROM users";
$result = $conn->query($sql);

if (!$result) {
    die("Error: " . $conn->error);
}

$updated = 0;

while ($row = $result->fetch_assoc()) {
    $id = $row['id'];
    $password = $row['password'];

    // Skip if already hashed
    if (password_get_info($password)['algo'] !== null) {
        continue;
    }

    // Hash the plaintext password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Update the database
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->bind_param("si", $hashedPassword, $id);

    if ($stmt->execute()) {
        $updated++;
    }

    $stmt->close();
}

echo "Done! {$updated} password(s) were hashed.";
?>