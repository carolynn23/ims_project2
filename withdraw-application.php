<?php
session_start();
require_once 'config.php';

// Security check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') { 
    header('Location: login.php'); 
    exit(); 
}

$app_id = (int)($_POST['application_id'] ?? 0);
if ($app_id <= 0) { 
    header('Location: student-applications.php?error=invalid_application'); 
    exit(); 
}

// Find student_id
$st = $conn->prepare("SELECT student_id FROM students WHERE user_id=?");
$st->bind_param("i", $_SESSION['user_id']);
$st->execute();
$student_result = $st->get_result()->fetch_assoc();
$student_id = (int)($student_result['student_id'] ?? 0);

if ($student_id <= 0) {
    header('Location: student-applications.php?error=invalid_student'); 
    exit(); 
}

// Verify application belongs to student and get current status
$ck = $conn->prepare("SELECT status, internship_id FROM applications WHERE application_id=? AND student_id=?");
$ck->bind_param("ii", $app_id, $student_id);
$ck->execute();
$row = $ck->get_result()->fetch_assoc();

if (!$row) { 
    header('Location: student-applications.php?error=application_not_found'); 
    exit(); 
}

// Only allow withdrawing PENDING applications
if ($row['status'] === 'pending') {
    try {
        // Start transaction for data integrity
        $conn->begin_transaction();
        
        // Update application status to withdrawn
        $upd = $conn->prepare("UPDATE applications SET status='withdrawn', updated_at=NOW() WHERE application_id=? AND student_id=?");
        $upd->bind_param("ii", $app_id, $student_id);
        
        if (!$upd->execute()) {
            throw new Exception("Failed to update application status");
        }
        
        // Optional: Add notification for employer
        $internship_id = $row['internship_id'];
        $get_employer = $conn->prepare("
            SELECT e.user_id, s.full_name, i.title 
            FROM internships i 
            JOIN employers e ON i.employer_id = e.employer_id 
            JOIN students s ON s.student_id = ?
            WHERE i.internship_id = ?
        ");
        $get_employer->bind_param("ii", $student_id, $internship_id);
        $get_employer->execute();
        $emp_data = $get_employer->get_result()->fetch_assoc();
        
        if ($emp_data) {
            $notification_msg = "{$emp_data['full_name']} has withdrawn their application for '{$emp_data['title']}'";
            $notify = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
            $notify->bind_param("is", $emp_data['user_id'], $notification_msg);
            $notify->execute();
        }
        
        $conn->commit();
        header('Location: student-applications.php?success=application_withdrawn');
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Application withdrawal error: " . $e->getMessage());
        header('Location: student-applications.php?error=withdrawal_failed');
    }
} else {
    // Application is not in pending status
    $status = $row['status'];
    header("Location: student-applications.php?error=cannot_withdraw&status=$status");
}

exit();
?>