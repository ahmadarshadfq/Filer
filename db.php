<?php
$servername = "localhost";
$username = "root";
$password = "";
$database = "user_system"; // Change to your actual database name

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
