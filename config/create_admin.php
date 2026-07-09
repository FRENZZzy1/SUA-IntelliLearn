<?php

include 'config.php';

$username = "Frenzz";
$password = password_hash("Admin@123", PASSWORD_DEFAULT);

$stmt = $conn->prepare("INSERT INTO users(username,password,role) VALUES(?,?,?)");

$role = "admin";

$stmt->bind_param("sss",$username,$password,$role);

if($stmt->execute()){
    echo "Admin account created";
}else{
    echo "Error: " . $stmt->error;
}
?>