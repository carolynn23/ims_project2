<?php
session_start();
require_once 'includes/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employer') {
    header("Location: login.php");
    exit();
}

$internship_id = $_GET['id'] ?? null;

if (!$internship_id) {
    die("Invalid internship ID.");
}

$user_id = $_SESSION['user_id'];

// Get employer ID
$stmt = $conn->prepare("SELECT employer_id FROM employers WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$emp_result = $stmt->get_result();
$employer = $emp_result->fetch_assoc();
$employer_id = $employer['employer_id'];

// Delete the internship
$stmt = $conn->prepare("DELETE FROM internships WHERE internship_id = ? AND employer_id = ?");
$stmt->bind_param("ii", $internship_id, $employer_id);

if ($stmt->execute()) {
    header("Location: employer-dashboard.php");
    exit();
} else {
    echo "Error deleting internship.";
}
