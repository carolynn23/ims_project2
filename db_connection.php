<?php
// db_connection.php

// Database credentials
$host = 'localhost';  // Database host (e.g., 'localhost')
$username = 'root';   // Your database username
$password = '';       // Your database password
$database = 'your_database_name'; // Your database name

// Create a connection
$conn = new mysqli($host, $username, $password, $database);

// Check for connection errors
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// You can also set your connection character set
$conn->set_charset("utf8");
?>
