<?php
$servername = "localhost";
$username = "root";
$password = "";
$database = "user_system"; // Apne database ka naam yahan change karo

$conn = new mysqli($servername, $username, $password, $database);

// Agar connection fail ho jaye to error show karega
if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}
?>
