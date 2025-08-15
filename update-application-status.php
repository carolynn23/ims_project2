<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employer') {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $application_id = intval($_POST['application_id']);
    $status = $_POST['status'] === 'approved' ? 'approved' : 'rejected';

    $stmt = $conn->prepare("UPDATE applications SET status = ? WHERE application_id = ?");
    $stmt->bind_param("si", $status, $application_id);

    if ($stmt->execute()) {
        $_SESSION['message'] = "Application status updated successfully.";
    } else {
        $_SESSION['message'] = "Failed to update status.";
    }

    header("Location: view-applications.php");
    exit();
}
?>
