<?php
$host = "localhost";
$user = "root"; // change if needed
$password = ""; // change if needed
$dbname = "internship_db";

$conn = new mysqli($host, $user, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
